<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_onboardings', function (Blueprint $table) {
            // Set when a rejected application is reopened for editing; a
            // non-null value marks the next submission as a resubmission.
            $table->timestamp('reopened_at')->nullable()->after('decision_comment');
        });
    }

    public function down(): void
    {
        Schema::table('user_onboardings', function (Blueprint $table) {
            $table->dropColumn('reopened_at');
        });
    }
};
