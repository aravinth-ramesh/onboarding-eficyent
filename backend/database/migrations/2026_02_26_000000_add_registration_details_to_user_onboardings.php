<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_onboardings', function (Blueprint $table) {
            // ISO 3166-1 alpha-2 country of incorporation.
            $table->string('country_code', 2)->nullable()->after('user_type_subcategory_id');
            // Captured registration identifiers: { field_key: { label, value } }.
            $table->json('registration_details')->nullable()->after('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('user_onboardings', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'registration_details']);
        });
    }
};
