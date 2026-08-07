<?php

use App\Support\CountryOfIncorporationField;
use App\Support\FieldValidationRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Bring existing databases up to the current template rules without a reseed.
 *
 * Both changes ship in the seeder, which only runs on a fresh install:
 *  - EOP-46: "Country of Incorporation" becomes a single-country dropdown
 *    instead of free text, so several countries can't be entered.
 *  - EOP-32 (and EOP-37/39/42): date-of-birth columns are retyped from text to
 *    date with allow_future=false — a text column can never reach the date
 *    validator — plus the min_length / requires_letter / contact rules.
 *
 * The earlier rules migration already ran on deployed databases, so it will
 * never re-fire; this one re-applies the (idempotent) rule set.
 *
 * Existing answers are left untouched. A stored free-text country that doesn't
 * match a catalog name will simply show as unselected next time the client
 * opens that step, and a stored date of birth keeps its value.
 */
return new class extends Migration
{
    public function up(): void
    {
        $converted = CountryOfIncorporationField::apply();

        FieldValidationRules::apply();

        Log::info('applied country + field validation rules to existing template', [
            'country_of_incorporation_questions_converted' => $converted,
        ]);
    }

    public function down(): void
    {
        // Data-only and idempotent; there is no deterministic previous state to
        // restore (the free-text type carried no rules to put back).
    }
};
