<?php

use App\Support\UboTableColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Apply the UBO column alignment to existing databases (EOP-49): Nationality
 * becomes a country dropdown and an ID Type dropdown is added on the legacy
 * owner/director tables, matching the controls the `ubo` widget already shows.
 *
 * Additive and idempotent; existing rows keep their values and simply have no
 * value for the new column.
 */
return new class extends Migration
{
    public function up(): void
    {
        $changed = UboTableColumns::apply();

        Log::info('aligned UBO table columns with the ubo widget', [
            'table_questions_changed' => $changed,
        ]);
    }

    public function down(): void
    {
        // Data-only; the previous free-text column carried no rules to restore.
    }
};
