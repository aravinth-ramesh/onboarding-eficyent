<?php

namespace App\Services\DocumentIntelligence\Rules;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;
use Spatie\PdfToText\Pdf as PdfToText;

/**
 * Extracts a plain-text layer from uploaded documents, entirely locally.
 *
 * PDFs: poppler's pdftotext when available (fast, robust layout handling),
 * falling back to the pure-PHP smalot/pdfparser. PDFs without a usable text
 * layer (scans) and images go through the OCR branch (pdftoppm + Tesseract).
 * DOCX: unzip + XML strip. Anything still unreadable returns null and the
 * document routes to human review.
 */
class TextExtractor
{
    private const PDFTOTEXT_CANDIDATES = [
        '/opt/homebrew/bin/pdftotext',
        '/usr/local/bin/pdftotext',
        '/usr/bin/pdftotext',
    ];

    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        private OcrExtractor $ocr,
    ) {}

    public function extract(UploadedFile $file): ?ExtractedText
    {
        $mime = strtolower((string) $file->getMimeType());
        $path = $file->getRealPath();

        if ($mime === 'application/pdf') {
            $native = $this->normalize($this->fromPdf($path));
            if ($native !== null) {
                return new ExtractedText($native);
            }

            return $this->fromOcr(fn () => $this->ocr->fromPdf($path));
        }

        if (in_array($mime, self::IMAGE_MIMES, true)) {
            return $this->fromOcr(fn () => $this->ocr->fromImage($path));
        }

        if (str_contains($mime, 'officedocument.wordprocessingml')) {
            $text = $this->normalize($this->fromDocx($path));

            return $text === null ? null : new ExtractedText($text);
        }

        return null;
    }

    private function fromOcr(callable $run): ?ExtractedText
    {
        $result = $run();
        if ($result === null) {
            return null;
        }

        $text = $this->normalize($result['text']);
        if ($text === null) {
            return null;
        }

        // A read this shaky is more likely to mislead than to help.
        if ($result['confidence'] < (float) config('document_validation.rules.ocr.min_mean_confidence')) {
            return null;
        }

        return new ExtractedText($text, viaOcr: true, ocrConfidence: $result['confidence']);
    }

    private function normalize(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim(preg_replace('/[ \t]+/', ' ', $text));

        return strlen($text) >= (int) config('document_validation.rules.min_text_length')
            ? $text
            : null;
    }

    private function fromPdf(string $path): ?string
    {
        $binary = $this->pdftotextBinary();
        if ($binary !== null) {
            try {
                $text = PdfToText::getText($path, $binary);
                if (trim($text) !== '') {
                    return $text;
                }
            } catch (\Throwable $e) {
                Log::info('pdftotext failed, falling back to pdfparser', ['error' => $e->getMessage()]);
            }
        }

        try {
            return (new PdfParser())->parseFile($path)->getText();
        } catch (\Throwable $e) {
            Log::info('pdfparser could not extract text', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function fromDocx(string $path): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return null;
        }

        // Paragraph/break tags become newlines so date labels keep proximity.
        $xml = preg_replace('/<w:(p|br|tab)\b[^>]*\/?>/', "\n", $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1);
    }

    private function pdftotextBinary(): ?string
    {
        $configured = config('document_validation.rules.pdftotext_path');
        if ($configured) {
            return is_executable($configured) ? $configured : null;
        }

        foreach (self::PDFTOTEXT_CANDIDATES as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
