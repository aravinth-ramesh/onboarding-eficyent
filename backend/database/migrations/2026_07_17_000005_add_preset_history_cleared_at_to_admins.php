<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            // When the admin last cleared their preset customization history.
            // The history view hides entries on or before this point; the
            // underlying admin_activity_logs audit trail is never deleted.
            $table->timestamp('preset_history_cleared_at')->nullable()->after('pin_shortcut');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('preset_history_cleared_at');
        });
    }
};
