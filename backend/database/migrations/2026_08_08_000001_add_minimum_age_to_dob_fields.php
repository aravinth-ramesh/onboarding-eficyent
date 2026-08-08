<?php

use App\Support\FieldValidationRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Date-of-birth fields must also enforce a minimum age, not just reject future
 * dates (EOP-32, re-reported).
 *
 * The earlier pass set allow_future=false. A beneficial owner or director has
 * to be an adult, so an under-18 date — and a stray year like 1832 — are
 * equally invalid. Expressed as `min_age` relative to today rather than a
 * fixed max_date, which would silently go stale.
 */
return new class extends Migration
{
    public function up(): void
    {
        FieldValidationRules::apply();

        Log::info('applied minimum-age rule to date-of-birth fields');
    }

    public function down(): void
    {
        // Data-only.
    }
};
