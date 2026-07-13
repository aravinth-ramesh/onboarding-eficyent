<?php

namespace App\Services\DocumentIntelligence;

use Carbon\CarbonImmutable;

/**
 * Result of AI analysis of a single uploaded document.
 */
class DocumentAnalysis
{
    public function __construct(
        public readonly string $documentType,
        public readonly ?CarbonImmutable $issueDate,
        public readonly ?CarbonImmutable $expiryDate,
        public readonly string $confidence, // low | medium | high
        public readonly string $summary,
        // What the analyzer read (truncated) — surfaced to admins so the
        // rules dictionaries can be tuned against real documents.
        public readonly ?string $excerpt = null,
    ) {}

    public static function fromModelOutput(array $data): self
    {
        $parseDate = function (?string $value): ?CarbonImmutable {
            if (! $value) {
                return null;
            }
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        };

        return new self(
            documentType: (string) ($data['document_type'] ?? 'other'),
            issueDate: $parseDate($data['issue_date'] ?? null),
            expiryDate: $parseDate($data['expiry_date'] ?? null),
            confidence: (string) ($data['confidence'] ?? 'low'),
            summary: (string) ($data['summary'] ?? ''),
        );
    }
}
