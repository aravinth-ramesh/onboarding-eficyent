<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filter_presets', function (Blueprint $table) {
            // The admin's manual ordering within one page. Lower shows first.
            $table->unsignedInteger('position')->default(0)->after('filters');
        });

        // Seed positions from the order presets currently display in (name, per
        // admin+context) so the list looks identical the moment this ships.
        $counters = [];
        DB::table('filter_presets')->orderBy('admin_id')->orderBy('context')->orderBy('name')
            ->get(['id', 'admin_id', 'context'])
            ->each(function ($row) use (&$counters) {
                $key = $row->admin_id . '|' . $row->context;
                $counters[$key] = ($counters[$key] ?? 0) + 1;
                DB::table('filter_presets')->where('id', $row->id)->update(['position' => $counters[$key]]);
            });
    }

    public function down(): void
    {
        Schema::table('filter_presets', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
