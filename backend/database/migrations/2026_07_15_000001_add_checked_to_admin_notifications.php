<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            // Acknowledgement that an admin has actually looked at the
            // client's response. `resolved_at` records that the CLIENT
            // answered; this records that the TEAM checked it — the two are
            // deliberately distinct.
            $table->timestamp('checked_at')->nullable()->after('resolved_at');
            $table->foreignId('checked_by')->nullable()->after('checked_at')
                ->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checked_by');
            $table->dropColumn('checked_at');
        });
    }
};
