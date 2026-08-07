<?php

use App\Support\DuplicateRegistrationQuestions;
use App\Support\FieldValidationRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Apply the financial-module field rules and retire the duplicated
 * registration identifiers on existing databases.
 *
 *  - EOP-18 the monthly transaction count becomes a whole number
 *  - EOP-19 Source of Funds becomes a free-text explanation
 *  - EOP-23 / EOP-24 the bank account columns gain real rules, including
 *    IBAN (mod-97) and SWIFT/BIC formats
 *  - EOP-29 the regulatory-action explanations need actual prose
 *  - EOP-10 the unvalidated registration-number and tax-id duplicates are
 *    deactivated; the Registration step captures them per country with the
 *    right pattern, check digit and uniqueness
 *
 * Idempotent, and existing answers are left in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        FieldValidationRules::apply();
        $retired = DuplicateRegistrationQuestions::apply();

        Log::info('tightened financial fields and retired duplicate registration questions', [
            'duplicate_registration_questions_retired' => $retired,
        ]);
    }

    public function down(): void
    {
        // Data-only; the untyped free-text fields carried no rules to restore.
    }
};
