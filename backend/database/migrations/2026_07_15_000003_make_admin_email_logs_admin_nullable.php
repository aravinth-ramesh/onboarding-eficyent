<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_email_logs', function (Blueprint $table) {
            // An email-sent record is an audit trail; it must survive the
            // deleting of the admin who sent it (and a scheduled send may
            // fire after its author is gone). Null the FK instead of
            // cascading the log away.
            $table->dropForeign(['admin_id']);
            $table->foreignId('admin_id')->nullable()->change();
            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admin_email_logs', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->foreignId('admin_id')->nullable(false)->change();
            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
        });
    }
};
