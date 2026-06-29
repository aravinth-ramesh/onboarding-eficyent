<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // New KYB field types added to the questions.type ENUM (MySQL only).
    private string $withNew = "ENUM('text','radio','date','select','multi_select','textarea','number','phone','file','table','mcc','address','ubo') NOT NULL";

    private string $withoutNew = "ENUM('text','radio','date','select','multi_select','textarea','number','file','table') NOT NULL";

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE questions MODIFY COLUMN type {$this->withNew}");
            DB::statement("ALTER TABLE admin_questions MODIFY COLUMN type {$this->withNew}");

            return;
        }

        // On non-MySQL drivers the enum is a restrictive CHECK constraint;
        // relax it to a plain string so new question types are accepted.
        foreach (['questions', 'admin_questions'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('type')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE questions MODIFY COLUMN type {$this->withoutNew}");
            DB::statement("ALTER TABLE admin_questions MODIFY COLUMN type {$this->withoutNew}");
        }
    }
};
