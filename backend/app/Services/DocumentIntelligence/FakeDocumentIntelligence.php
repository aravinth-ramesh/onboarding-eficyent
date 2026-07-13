<?php

namespace App\Services\DocumentIntelligence;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * Deterministic driver for tests and local development. Behavior is keyed off
 * the uploaded filename so scenarios can be exercised without API calls:
 *
 *   "unreadable"          → analysis failure (null)
 *   "expired"             → expiry_date one year in the past
 *   "stale"               → issue_date two years in the past
 *   any type key/fragment → that document type (e.g. "articles-of-association.pdf")
 *   otherwise             → 'other'
 */
class FakeDocumentIntelligence implements DocumentIntelligenceContract
{
    public function analyze(UploadedFile $file): ?DocumentAnalysis
    {
        $name = strtolower($file->getClientOriginalName());

        if (str_contains($name, 'unreadable')) {
            return null;
        }

        $type = 'other';
        foreach (array_keys(config('document_validation.types')) as $key) {
            $needle = str_replace('_', '-', $key);
            if (str_contains(str_replace('_', '-', $name), $needle)) {
                $type = $key;
                break;
            }
        }
        // Common aliases so fixture names read naturally.
        if (str_contains($name, 'incorporation') && ! str_contains($name, 'articles')) {
            $type = 'certificate_of_incorporation';
        } elseif (str_contains($name, 'articles') || str_contains($name, 'moa') || str_contains($name, 'aoa')) {
            $type = 'articles_of_association';
        } elseif (str_contains($name, 'address')) {
            $type = 'proof_of_address';
        } elseif (str_contains($name, 'passport') || str_contains($name, 'id-card')) {
            $type = 'identity_document';
        }

        $today = CarbonImmutable::today();
        $issueDate = str_contains($name, 'stale') ? $today->subYears(2) : $today->subDays(14);
        $expiryDate = null;
        if (str_contains($name, 'expired')) {
            $expiryDate = $today->subYear();
        } elseif ($type === 'identity_document' || $type === 'license') {
            $expiryDate = $today->addYears(3);
        }

        return new DocumentAnalysis(
            documentType: $type,
            issueDate: $issueDate,
            expiryDate: $expiryDate,
            confidence: 'high',
            summary: 'Fake analysis of ' . $file->getClientOriginalName(),
        );
    }
}
