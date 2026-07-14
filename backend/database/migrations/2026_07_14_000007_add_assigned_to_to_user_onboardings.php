<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_onboardings', function (Blueprint $table) {
            // Which admin owns the review of this application.
            $table->foreignId('assigned_to')->nullable()->after('decision_comment')
                ->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_onboardings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
        });
    }
};
