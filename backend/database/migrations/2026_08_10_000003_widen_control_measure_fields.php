<?php

use App\Support\ControlMeasureFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * The AML/CFT "Describe Control Measures" fields capped answers at 200
 * characters, too little to describe the controls applied to crypto custody,
 * gambling, sanctioned jurisdictions and the rest (retest item 30).
 */
return new class extends Migration
{
    public function up(): void
    {
        $changed = ControlMeasureFields::apply();

        Log::info('widened control measure fields', [
            'questions_changed' => $changed,
            'max_length' => ControlMeasureFields::MAX_LENGTH,
        ]);
    }

    public function down(): void
    {
        // Data-only.
    }
};
