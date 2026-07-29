<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reviewer's own decision on an uploaded document, distinct from the
 * automated validation_status. `reviewed_at` / `reviewed_by` already record
 * who cleared a file in the document queue; this adds the explicit verdict
 * (verified / rejected / resubmit requested) and an optional note so the
 * decision is legible on the application review page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answer_files', function (Blueprint $table) {
            $table->string('review_decision')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('review_decision');
        });
    }

    public function down(): void
    {
        Schema::table('answer_files', function (Blueprint $table) {
            $table->dropColumn(['review_decision', 'review_note']);
        });
    }
};
