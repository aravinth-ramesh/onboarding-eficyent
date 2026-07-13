<?php

namespace App\Services\DocumentIntelligence\Rules;

use App\Services\DocumentIntelligence\DocumentAnalysis;
use App\Services\DocumentIntelligence\DocumentIntelligenceContract;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * Rules-based document analysis — no AI, nothing leaves the server.
 *
 * Pipeline: extract text locally → detect MRZ (deterministic identity-document
 * path) → classify via anchor-phrase scoring → extract label-anchored dates.
 * Anything unreadable (scans, images, gibberish) returns null, which the
 * policy engine converts to needs_review — never a hard reject.
 */
class RulesDocumentIntelligence implements DocumentIntelligenceContract
{
    public function __construct(
        private TextExtractor $extractor,
        private DocumentClassifier $classifier,
        private DateExtractor $dates,
        private MrzReader $mrz,
    ) {}

    public function analyze(UploadedFile $file): ?DocumentAnalysis
    {
        $extracted = $this->extractor->extract($file);
        if ($extracted === null) {
            return null; // unreadable (failed OCR / gibberish) → human review
        }
        $text = $extracted->text;

        // Identity documents: the MRZ carries a check-digit-verified expiry.
        $mrz = $this->mrz->read($text);
        if ($mrz !== null) {
            return new DocumentAnalysis(
                documentType: 'identity_document',
                issueDate: null,
                expiryDate: CarbonImmutable::parse($mrz->dateOfExpiry)->startOfDay(),
                confidence: $mrz->valid ? 'high' : 'medium',
                summary: sprintf(
                    '%s MRZ parsed (%s, expires %s, check digits %s).',
                    ucfirst(strtolower($mrz->type)),
                    $mrz->issuerCode ?? 'unknown issuer',
                    $mrz->dateOfExpiry,
                    $mrz->valid ? 'valid' : 'NOT verified',
                ),
            );
        }

        $classified = $this->classifier->classify($text);

        $issueDate = $this->dates->labeled($text, 'issue');
        $expiryDate = $this->dates->labeled($text, 'expiry');

        $confidence = 'low';
        if ($classified['type'] !== 'other') {
            $confidence = $this->classifier->isHighConfidence($classified['score']) ? 'high' : 'medium';
        }

        // OCR text below the high-confidence bar can misread the very words
        // and dates we score on — cap the verdict so it still counts, but a
        // borderline call lands with a human instead of hard-certainty.
        $ocrHighBar = (float) config('document_validation.rules.ocr.high_confidence');
        if ($confidence === 'high' && $extracted->viaOcr && $extracted->ocrConfidence < $ocrHighBar) {
            $confidence = 'medium';
        }

        $summary = $classified['matches'] === []
            ? 'No known document markers found in the text.'
            : sprintf(
                'Matched %s (score %d).',
                implode(', ', array_map(fn ($m) => "\"{$m}\"", array_slice($classified['matches'], 0, 4))),
                $classified['score'],
            );
        if ($extracted->viaOcr) {
            $summary .= sprintf(' Read via OCR (%d%% mean word confidence).', (int) $extracted->ocrConfidence);
        }

        return new DocumentAnalysis(
            documentType: $classified['type'],
            issueDate: $issueDate,
            expiryDate: $expiryDate,
            confidence: $confidence,
            summary: $summary,
        );
    }
}
