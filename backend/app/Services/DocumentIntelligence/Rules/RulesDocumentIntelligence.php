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
        $text = $this->extractor->extract($file);
        if ($text === null) {
            return null; // no text layer (image/scan) → human review
        }

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

        $summary = $classified['matches'] === []
            ? 'No known document markers found in the text.'
            : sprintf(
                'Matched %s (score %d).',
                implode(', ', array_map(fn ($m) => "\"{$m}\"", array_slice($classified['matches'], 0, 4))),
                $classified['score'],
            );

        return new DocumentAnalysis(
            documentType: $classified['type'],
            issueDate: $issueDate,
            expiryDate: $expiryDate,
            confidence: $confidence,
            summary: $summary,
        );
    }
}
