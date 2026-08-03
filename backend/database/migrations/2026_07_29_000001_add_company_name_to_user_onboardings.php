<?php

use App\Models\UserOnboarding;
use App\Support\CompanyName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised company/entity name for an application, so the admin lists and
 * search work off the company — not the person who registered — without a
 * per-row answer lookup. Kept fresh by App\Support\CompanyName on save and
 * submission; backfilled here for existing applications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_onboardings', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('user_type_subcategory_id')->index();
        });

        UserOnboarding::query()->chunkById(100, function ($onboardings) {
            foreach ($onboardings as $onboarding) {
                CompanyName::sync($onboarding);
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_onboardings', function (Blueprint $table) {
            $table->dropColumn('company_name');
        });
    }
};
