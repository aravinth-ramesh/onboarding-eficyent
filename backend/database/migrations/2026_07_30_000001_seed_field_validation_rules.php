<?php

use App\Support\FieldValidationRules;
use Illuminate\Database\Migrations\Migration;

/**
 * Apply field-validation rules to the standard onboarding template so the
 * client-side engine actually enforces them.
 *
 * The rule set lives in App\Support\FieldValidationRules so the seeder can
 * apply the same rules after it (re)creates the questions — on a fresh
 * `migrate --seed` this migration runs before any question exists, and
 * OnboardingDataSeeder::clean() deletes them all on a re-seed.
 */
return new class extends Migration
{
    public function up(): void
    {
        FieldValidationRules::apply();
    }

    public function down(): void
    {
        // Data-only, best-effort seed; nothing to reverse deterministically.
    }
};
