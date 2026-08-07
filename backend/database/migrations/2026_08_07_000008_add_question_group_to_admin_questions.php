<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which section an ad-hoc follow-up question relates to (EOP-95).
 *
 * The new-question form offered no way to say, so neither the client nor the
 * reviewer could tell what part of the application it was about. Nullable:
 * existing questions have none, and a general question needn't pick one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_questions', function (Blueprint $table) {
            $table->foreignId('question_group_id')
                ->nullable()
                ->after('admin_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admin_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('question_group_id');
        });
    }
};
