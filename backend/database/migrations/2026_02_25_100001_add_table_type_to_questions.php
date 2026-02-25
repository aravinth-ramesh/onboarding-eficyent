<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'table' to questions.type enum
        DB::statement("ALTER TABLE questions MODIFY COLUMN type ENUM('text', 'radio', 'date', 'select', 'multi_select', 'textarea', 'number', 'file', 'table') NOT NULL");

        // Add 'table' to admin_questions.type enum
        DB::statement("ALTER TABLE admin_questions MODIFY COLUMN type ENUM('text', 'radio', 'date', 'select', 'multi_select', 'textarea', 'number', 'file', 'table') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE questions MODIFY COLUMN type ENUM('text', 'radio', 'date', 'select', 'multi_select', 'textarea', 'number', 'file') NOT NULL");

        DB::statement("ALTER TABLE admin_questions MODIFY COLUMN type ENUM('text', 'radio', 'date', 'select', 'multi_select', 'textarea', 'number', 'file') NOT NULL");
    }
};
