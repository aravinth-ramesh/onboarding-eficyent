<?php

namespace App\Services\DocumentIntelligence\Rules;

/**
 * Text pulled out of an uploaded document, with provenance: OCR-derived text
 * carries a mean word confidence so downstream verdicts can be capped when
 * the read was shaky.
 */
class ExtractedText
{
    public function __construct(
        public readonly string $text,
        public readonly bool $viaOcr = false,
        public readonly ?float $ocrConfidence = null,
    ) {}
}
