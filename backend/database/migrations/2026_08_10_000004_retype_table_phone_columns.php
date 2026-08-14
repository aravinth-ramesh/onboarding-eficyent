<?php

use App\Support\PhoneColumnTypes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Give phone columns inside tables the country dial-code dropdown the
 * standalone phone question already had, instead of a plain text box the
 * client typed "+65-9856545" into by hand (retest items 28 and 31).
 */
return new class extends Migration
{
    public function up(): void
    {
        $changed = PhoneColumnTypes::apply();

        Log::info('retyped table phone columns', ['questions_changed' => $changed]);
    }

    public function down(): void
    {
        // Data-only.
    }
};
