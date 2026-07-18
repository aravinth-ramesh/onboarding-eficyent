<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filter_presets', function (Blueprint $table) {
            // A pinned preset floats to the top of its list, above the manual
            // ordering. Several may be pinned; they keep their order among
            // themselves.
            $table->boolean('pinned')->default(false)->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('filter_presets', function (Blueprint $table) {
            $table->dropColumn('pinned');
        });
    }
};
