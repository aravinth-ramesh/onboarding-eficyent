<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answer_files', function (Blueprint $table) {
            // AI document validation verdict — see config/document_validation.php
            $table->string('validation_status')->default('skipped')->after('disk');
            $table->string('detected_type')->nullable()->after('validation_status');
            $table->date('issue_date')->nullable()->after('detected_type');
            $table->date('expiry_date')->nullable()->after('issue_date');
            $table->text('validation_summary')->nullable()->after('expiry_date');
            $table->text('justification')->nullable()->after('validation_summary');
        });
    }

    public function down(): void
    {
        Schema::table('answer_files', function (Blueprint $table) {
            $table->dropColumn([
                'validation_status',
                'detected_type',
                'issue_date',
                'expiry_date',
                'validation_summary',
                'justification',
            ]);
        });
    }
};
