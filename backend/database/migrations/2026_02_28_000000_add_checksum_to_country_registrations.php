<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('country_registrations', function (Blueprint $table) {
            // Named checksum algorithm (e.g. gstin, abn, cnpj). Null = none.
            $table->string('checksum', 32)->nullable()->after('pattern_message');
        });
    }

    public function down(): void
    {
        Schema::table('country_registrations', function (Blueprint $table) {
            $table->dropColumn('checksum');
        });
    }
};
