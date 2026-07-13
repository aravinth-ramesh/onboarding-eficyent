<?php

namespace Tests\Support;

/**
 * Builds a minimal, valid, single-page PDF with real text content so the
 * rules driver's extraction cascade (pdftotext / pdfparser) has something
 * genuine to parse. No dependencies — raw PDF 1.4 with an uncompressed
 * content stream.
 */
trait MakesPdfs
{
    /**
     * @param  string[]|string  $lines
     */
    protected function makePdf(array|string $lines): string
    {
        $lines = (array) $lines;

        $escape = fn (string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);

        $content = "BT\n/F1 11 Tf\n14 TL\n50 780 Td\n";
        foreach ($lines as $i => $line) {
            $content .= ($i === 0 ? '' : "T*\n") . '(' . $escape($line) . ") Tj\n";
        }
        $content .= "ET";

        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
                . "/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>",
            4 => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream",
            5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }

    /**
     * Rasterize a (text) PDF to a single PNG page via poppler's pdftoppm —
     * simulates a photo/scan of a document. Returns PNG bytes.
     */
    protected function rasterizePdf(string $pdfBytes, string $format = 'png'): string
    {
        $dir = sys_get_temp_dir() . '/fixture_' . bin2hex(random_bytes(6));
        mkdir($dir, 0700);
        file_put_contents("{$dir}/doc.pdf", $pdfBytes);

        $process = new \Symfony\Component\Process\Process([
            $this->popplerBinary('pdftoppm'),
            '-' . $format, '-r', '200', '-f', '1', '-l', '1',
            "{$dir}/doc.pdf", "{$dir}/out",
        ]);
        $process->mustRun();

        $ext = $format === 'jpeg' ? 'jpg' : $format;
        $files = glob("{$dir}/out*.{$ext}");
        $bytes = file_get_contents($files[0]);

        foreach (glob("{$dir}/*") as $f) {
            @unlink($f);
        }
        @rmdir($dir);

        return $bytes;
    }

    /**
     * Build a scanned-style PDF: the page is a single embedded JPEG image
     * with NO text layer, so only the OCR branch can read it.
     */
    protected function makeScannedPdf(array|string $lines): string
    {
        $jpeg = $this->rasterizePdf($this->makePdf($lines), 'jpeg');
        [$width, $height] = getimagesizefromstring($jpeg);

        $content = "q 612 0 0 792 0 0 cm /Im1 Do Q";

        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
                . "/Resources << /XObject << /Im1 5 0 R >> >> /Contents 4 0 R >>",
            4 => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream",
            5 => "<< /Type /XObject /Subtype /Image /Width {$width} /Height {$height} "
                . "/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode "
                . "/Length " . strlen($jpeg) . " >>\nstream\n{$jpeg}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }

    protected function ocrToolchainAvailable(): bool
    {
        return $this->popplerBinary('pdftoppm') !== null
            && $this->popplerBinary('tesseract') !== null;
    }

    private function popplerBinary(string $name): ?string
    {
        foreach (['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin'] as $dir) {
            if (is_executable("{$dir}/{$name}")) {
                return "{$dir}/{$name}";
            }
        }

        return null;
    }
}
