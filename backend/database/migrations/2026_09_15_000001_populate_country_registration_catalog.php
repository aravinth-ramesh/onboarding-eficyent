<?php

use App\Support\CountryRegistrationCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * The Country Registration screen was empty on any installation that had been
 * migrated but never seeded — the catalog was written only by `db:seed`, while
 * the onboarding form carried on working from the config fallback (item 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Log::info('populated country registration catalog', ['rows' => CountryRegistrationCatalog::apply()]);
    }

    public function down(): void
    {
        // Data-only.
    }
};
