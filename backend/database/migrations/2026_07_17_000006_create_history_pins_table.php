<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An admin pinning one of their customization-history entries. Kept
        // separate so the append-only admin_activity_logs audit rows are never
        // modified — a pin is a personal marker layered on top.
        Schema::create('history_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignId('admin_activity_log_id')->constrained('admin_activity_logs')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['admin_id', 'admin_activity_log_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('history_pins');
    }
};
