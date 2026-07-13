<?php

namespace App\Services\DocumentIntelligence;

use Illuminate\Http\UploadedFile;

interface DocumentIntelligenceContract
{
    /**
     * Analyze an uploaded document: classify its type and extract dates.
     *
     * Returns null when the document cannot be analyzed (unsupported format,
     * API failure, refusal) — callers must treat null as "accept and flag for
     * human review", never as a hard failure.
     */
    public function analyze(UploadedFile $file): ?DocumentAnalysis;
}
