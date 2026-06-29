<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a conditional rule depend on a virtual field (e.g. the country of
 * incorporation) instead of a parent question's answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conditional_rules', function (Blueprint $table) {
            $table->string('parent_field', 64)->nullable()->after('parent_question_id');
        });

        // parent_question_id becomes optional (a rule may key off parent_field).
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE conditional_rules DROP FOREIGN KEY conditional_rules_parent_question_id_foreign');
            DB::statement('ALTER TABLE conditional_rules MODIFY parent_question_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE conditional_rules ADD CONSTRAINT conditional_rules_parent_question_id_foreign FOREIGN KEY (parent_question_id) REFERENCES questions(id) ON DELETE CASCADE');
        } else {
            Schema::table('conditional_rules', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_question_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('conditional_rules', function (Blueprint $table) {
            $table->dropColumn('parent_field');
        });
    }
};
