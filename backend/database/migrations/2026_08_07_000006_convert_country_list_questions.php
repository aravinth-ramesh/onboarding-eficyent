<?php

use App\Support\CountryListQuestions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Convert the free-text country-list questions to multi-selects over the
 * country catalogue (EOP-20, EOP-21) on existing databases.
 *
 * Previously entered free text is left in place: it no longer matches an
 * option, so the client is asked to pick the countries properly next time
 * they open that step. Nothing is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $converted = CountryListQuestions::apply();

        Log::info('converted free-text country lists to multi-selects', [
            'questions_converted' => $converted,
        ]);
    }

    public function down(): void
    {
        // Data-only; the free-text type carried no rules to restore.
    }
};
