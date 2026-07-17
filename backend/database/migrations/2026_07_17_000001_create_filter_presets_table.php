<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A named filter combination an admin saved on a list page. Personal to
        // the admin who saved it; `context` names the page it applies to so the
        // same table can serve other lists later.
        Schema::create('filter_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('context', 60);
            $table->string('name', 60);
            $table->json('filters');
            $table->timestamps();

            // Re-saving a name overwrites that preset rather than duplicating it.
            $table->unique(['admin_id', 'context', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filter_presets');
    }
};
