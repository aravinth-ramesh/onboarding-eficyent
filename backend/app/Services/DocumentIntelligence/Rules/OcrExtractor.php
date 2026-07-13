<?php

namespace App\Services\DocumentIntelligence\Rules;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * OCR branch of the rules driver: rasterizes scanned PDFs (poppler pdftoppm)
 * and reads them — and uploaded images — with Tesseract.
 *
 * Tesseract's TSV output provides per-word confidences; the mean is carried
 * on the result so callers can distinguish a crisp 300-DPI scan from a blurry
 * phone photo. Everything runs locally; failures return null (→ human review).
 */
class OcrExtractor
{
    private const BIN_DIRS = ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin'];

    /**
     * @return array{text: string, confidence: float}|null
     */
    public function fromPdf(string $path): ?array
    {
        $pdftoppm = $this->binary('pdftoppm', 'document_validation.rules.ocr.pdftoppm_path');
        if (! $this->enabled() || $pdftoppm === null) {
            return null;
        }

        $workDir = sys_get_temp_dir() . '/ocr_' . bin2hex(random_bytes(8));
        if (! mkdir($workDir, 0700)) {
            return null;
        }

        try {
            $ocr = config('document_validation.rules.ocr');
            $render = new Process([
                $pdftoppm,
                '-png',
                '-gray',
                '-r', (string) $ocr['dpi'],
                '-f', '1',
                '-l', (string) $ocr['max_pages'],
                $path,
                $workDir . '/page',
            ]);
            $render->setTimeout((float) $ocr['timeout_seconds']);
            $render->run();

            $pages = glob($workDir . '/page*.png') ?: [];
            if ($pages === []) {
                return null;
            }
            sort($pages);

            $texts = [];
            $confidences = [];
            foreach ($pages as $page) {
                $result = $this->ocrImage($page);
                if ($result !== null) {
                    $texts[] = $result['text'];
                    $confidences[] = $result['confidence'];
                }
            }

            if ($texts === []) {
                return null;
            }

            return [
                'text' => implode("\n", $texts),
                'confidence' => array_sum($confidences) / count($confidences),
            ];
        } catch (\Throwable $e) {
            Log::info('PDF OCR failed', ['error' => $e->getMessage()]);

            return null;
        } finally {
            foreach (glob($workDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($workDir);
        }
    }

    /**
     * @return array{text: string, confidence: float}|null
     */
    public function fromImage(string $path): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $prepared = $this->preprocess($path);

        try {
            return $this->ocrImage($prepared ?? $path);
        } finally {
            if ($prepared !== null) {
                @unlink($prepared);
            }
        }
    }

    /**
     * Run Tesseract in TSV mode: reconstruct the text and compute the mean
     * per-word confidence.
     *
     * @return array{text: string, confidence: float}|null
     */
    private function ocrImage(string $path): ?array
    {
        $tesseract = $this->binary('tesseract', 'document_validation.rules.ocr.tesseract_path');
        if ($tesseract === null) {
            return null;
        }

        try {
            $ocr = config('document_validation.rules.ocr');
            $process = new Process([
                $tesseract,
                $path,
                'stdout',
                '-l', (string) $ocr['languages'],
                '--psm', '3',
                'tsv',
            ]);
            $process->setTimeout((float) $ocr['timeout_seconds']);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::info('tesseract failed', ['stderr' => substr($process->getErrorOutput(), 0, 500)]);

                return null;
            }

            return $this->parseTsv($process->getOutput());
        } catch (\Throwable $e) {
            Log::info('tesseract error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{text: string, confidence: float}|null
     */
    private function parseTsv(string $tsv): ?array
    {
        $lines = [];
        $confidences = [];
        $currentKey = null;
        $current = [];

        foreach (preg_split('/\R/', trim($tsv)) as $i => $row) {
            if ($i === 0) {
                continue; // header
            }
            $cols = explode("\t", $row);
            if (count($cols) < 12 || (int) $cols[0] !== 5) {
                continue; // only word-level rows
            }

            $word = trim($cols[11]);
            $conf = (float) $cols[10];
            if ($word === '' || $conf < 0) {
                continue;
            }

            // block/paragraph/line numbers identify the visual line.
            $key = "{$cols[1]}-{$cols[2]}-{$cols[3]}-{$cols[4]}";
            if ($key !== $currentKey && $current !== []) {
                $lines[] = implode(' ', $current);
                $current = [];
            }
            $currentKey = $key;
            $current[] = $word;
            $confidences[] = $conf;
        }
        if ($current !== []) {
            $lines[] = implode(' ', $current);
        }

        if ($confidences === []) {
            return null;
        }

        return [
            'text' => implode("\n", $lines),
            'confidence' => array_sum($confidences) / count($confidences),
        ];
    }

    /**
     * Preprocess photos/screenshots before OCR. ImageMagick (when installed)
     * does the full treatment — grayscale, deskew for tilted phone photos,
     * contrast stretch, upscale of small images. Without it, GD covers
     * everything except deskew. Returns a temp PNG path, or null to OCR the
     * original as-is.
     */
    private function preprocess(string $path): ?string
    {
        return $this->preprocessWithImageMagick($path) ?? $this->preprocessWithGd($path);
    }

    private function preprocessWithImageMagick(string $path): ?string
    {
        $magick = $this->imagemagickBinary();
        if ($magick === null) {
            return null;
        }

        $out = tempnam(sys_get_temp_dir(), 'ocr_pre_') . '.png';

        try {
            $process = new Process(array_merge($magick, [
                $path,
                '-colorspace', 'Gray',
                '-deskew', '40%',
                '+repage',
                '-contrast-stretch', '1%x1%',
                '-resize', '1200x<', // enlarge only when narrower than 1200px
                $out,
            ]));
            $process->setTimeout((float) config('document_validation.rules.ocr.timeout_seconds'));
            $process->run();

            if ($process->isSuccessful() && is_file($out) && filesize($out) > 0) {
                return $out;
            }
        } catch (\Throwable $e) {
            Log::info('imagemagick preprocess failed', ['error' => $e->getMessage()]);
        }

        @unlink($out);

        return null;
    }

    private function preprocessWithGd(string $path): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        try {
            $data = file_get_contents($path);
            $img = @imagecreatefromstring($data);
            if ($img === false) {
                return null;
            }

            imagefilter($img, IMG_FILTER_GRAYSCALE);
            imagefilter($img, IMG_FILTER_CONTRAST, -15);

            if (imagesx($img) < 1200) {
                $img = imagescale($img, imagesx($img) * 2, -1, IMG_BICUBIC);
            }

            $out = tempnam(sys_get_temp_dir(), 'ocr_pre_') . '.png';
            imagepng($img, $out);
            imagedestroy($img);

            return $out;
        } catch (\Throwable) {
            return null;
        }
    }

    private function enabled(): bool
    {
        return (bool) config('document_validation.rules.ocr.enabled');
    }

    /**
     * ImageMagick command prefix: v7 `magick`, or legacy v6 `convert`.
     *
     * @return string[]|null
     */
    private function imagemagickBinary(): ?array
    {
        foreach (self::BIN_DIRS as $dir) {
            if (is_executable("{$dir}/magick")) {
                return ["{$dir}/magick"];
            }
        }
        foreach (self::BIN_DIRS as $dir) {
            if (is_executable("{$dir}/convert")) {
                return ["{$dir}/convert"];
            }
        }

        return null;
    }

    private function binary(string $name, string $configKey): ?string
    {
        $configured = config($configKey);
        if ($configured) {
            return is_executable($configured) ? $configured : null;
        }

        foreach (self::BIN_DIRS as $dir) {
            if (is_executable("{$dir}/{$name}")) {
                return "{$dir}/{$name}";
            }
        }

        return null;
    }
}
