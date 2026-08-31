<?php

use App\Support\BankAccountFormat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * The combined "Account Number / IBAN" field required an IBAN, so every valid
 * domestic account number was rejected (report item 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Log::info('widened bank account number format', ['questions_changed' => BankAccountFormat::apply()]);
    }

    public function down(): void
    {
        // Data-only.
    }
};
