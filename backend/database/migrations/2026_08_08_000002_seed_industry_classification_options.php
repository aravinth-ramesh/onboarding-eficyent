<?php

use App\Support\IndustryClassificationOptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Give the Industry Classification (MCC) question its option list, so the
 * admin panel shows the industry name instead of the stored code (retest
 * item 38 — "5942" appeared in View Details rather than "Book Stores").
 */
return new class extends Migration
{
    public function up(): void
    {
        $updated = IndustryClassificationOptions::apply();

        Log::info('seeded industry classification options', ['questions_updated' => $updated]);
    }

    public function down(): void
    {
        // Data-only.
    }
};
