<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            // The staff member's role in the review operation. New rows default
            // to the least-privileged role; see App\Enums\AdminRole.
            $table->string('role', 20)->default('analyst')->after('is_active')->index();
        });

        // Existing admins predate roles and must not lose access — promote them
        // to super_admin so whoever ran the platform keeps full control.
        DB::table('admins')->update(['role' => 'super_admin']);
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
