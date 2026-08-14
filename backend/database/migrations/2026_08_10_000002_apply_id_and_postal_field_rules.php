<?php

use App\Support\FieldValidationRules;
use App\Support\UboTableColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Postal codes accepted a single character, and ID/passport numbers rejected the
 * - and / that real document numbers carry (retest items 21 and 31).
 *
 * Both live in question configuration rather than code, so existing databases
 * need the rules applied the same way fresh installs get them from the seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columns = UboTableColumns::apply();
        FieldValidationRules::apply();

        Log::info('applied id and postal field rules', ['ubo_tables_changed' => $columns]);
    }

    public function down(): void
    {
        // Data-only.
    }
};
