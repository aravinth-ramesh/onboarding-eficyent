<?php

namespace App\Services;

use App\Models\Question;
use App\Services\DocumentIntelligence\DocumentIntelligenceContract;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * Applies the per-question document policy (expected type, staleness, expiry)
 * to uploaded files using AI analysis.
 *
 * Outcomes per file:
 *   passed        — analysis matched the policy
 *   type_mismatch — wrong document type (blocks unless justified)
 *   expired       — printed expiry date has passed (blocks unless justified)
 *   stale         — issued longer ago than max_age_months (blocks unless justified)
 *   needs_review  — analysis unavailable/uncertain; accepted, flagged for admin
 *   skipped       — validation disabled or no policy on the question
 *
 * Analysis failure is never a hard block: an AI outage must not stop onboarding.
 */
class DocumentValidationService
{
    public function __construct(
        private DocumentIntelligenceContract $intelligence,
    ) {}

    /**
     * @param  UploadedFile[]  $files
     * @return array{blocked: bool, failures: array, file_meta: array}
     */
    public function validate(Question $question, array $files, ?string $justification = null): array
    {
        $rules = $question->validation_rules ?? [];
        $expected = $rules['expected_document'] ?? null;

        if (! config('document_validation.enabled') || ! $expected) {
            return [
                'blocked' => false,
                'failures' => [],
                'file_meta' => array_fill(0, count($files), ['validation_status' => 'skipped']),
            ];
        }

        $maxAgeMonths = isset($rules['max_age_months']) ? (int) $rules['max_age_months'] : null;
        $checkExpiry = (bool) ($rules['check_expiry'] ?? true);
        $justification = trim((string) $justification) ?: null;

        $failures = [];
        $fileMeta = [];

        foreach ($files as $index => $file) {
            $analysis = $this->intelligence->analyze($file);

            $meta = [
                'detected_type' => $analysis?->documentType,
                'issue_date' => $analysis?->issueDate?->toDateString(),
                'expiry_date' => $analysis?->expiryDate?->toDateString(),
                'validation_summary' => $analysis?->summary,
                'justification' => null,
            ];

            [$status, $message] = $this->verdict($analysis, $expected, $maxAgeMonths, $checkExpiry);

            if (in_array($status, ['type_mismatch', 'expired', 'stale'], true)) {
                if ($justification !== null) {
                    // Accepted with the user's justification on record for review.
                    $meta['justification'] = $justification;
                } else {
                    $failures[] = [
                        'file_index' => $index,
                        'filename' => $file->getClientOriginalName(),
                        'reason' => $status,
                        'message' => $message,
                        'can_justify' => true,
                    ];
                }
            }

            $meta['validation_status'] = $status;
            $fileMeta[$index] = $meta;
        }

        return [
            'blocked' => $failures !== [],
            'failures' => $failures,
            'file_meta' => $fileMeta,
        ];
    }

    /**
     * @param  string|string[]  $expected  one acceptable type, or alternatives
     *                                     (e.g. proof of address may be a
     *                                     utility bill or a bank statement)
     * @return array{0: string, 1: string} [status, user-facing message]
     */
    private function verdict($analysis, string|array $expected, ?int $maxAgeMonths, bool $checkExpiry): array
    {
        $expected = (array) $expected;
        $expectedLabel = implode(' or ', array_map(
            fn ($type) => config("document_validation.types.{$type}.label", $type),
            $expected,
        ));

        if ($analysis === null) {
            return ['needs_review', ''];
        }

        // Low-confidence or unclassifiable reads go to a human instead of
        // hard-rejecting a document the analyzer could not make out.
        if ($analysis->documentType === 'other' || $analysis->confidence === 'low') {
            return ['needs_review', ''];
        }

        if (! in_array($analysis->documentType, $expected, true)) {
            $detectedLabel = config("document_validation.types.{$analysis->documentType}.label", $analysis->documentType);

            return ['type_mismatch', "This looks like a {$detectedLabel}, but a {$expectedLabel} is required."];
        }

        $today = CarbonImmutable::today();

        if ($checkExpiry && $analysis->expiryDate !== null && $analysis->expiryDate->lessThan($today)) {
            return ['expired', "This document expired on {$analysis->expiryDate->toFormattedDateString()}. Please upload the latest version, or provide a justification for submitting it."];
        }

        if ($maxAgeMonths !== null && $analysis->issueDate !== null
            && $analysis->issueDate->lessThan($today->subMonths($maxAgeMonths))) {
            return ['stale', "This document is dated {$analysis->issueDate->toFormattedDateString()} — older than the required {$maxAgeMonths} months. Please upload a recent version, or provide a justification."];
        }

        return ['passed', ''];
    }
}
