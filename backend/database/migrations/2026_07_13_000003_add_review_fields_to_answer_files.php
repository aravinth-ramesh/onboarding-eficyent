<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answer_files', function (Blueprint $table) {
            // What the analyzer actually read — lets admins tune the phrase
            // dictionaries from real uploads (Phase 3 tuning loop).
            $table->text('extracted_excerpt')->nullable()->after('validation_summary');

            // Human sign-off on flagged documents.
            $table->timestamp('reviewed_at')->nullable()->after('justification');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                ->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('answer_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['extracted_excerpt', 'reviewed_at']);
        });
    }
};
