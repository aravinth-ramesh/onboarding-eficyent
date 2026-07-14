<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 'approved' joins the lifecycle: pending → in_progress → completed
    // (submitted) → approved | rejected.
    private string $withApproved = "ENUM('pending','in_progress','completed','approved','rejected') NOT NULL DEFAULT 'pending'";

    private string $withoutApproved = "ENUM('pending','in_progress','completed','rejected') NOT NULL DEFAULT 'pending'";

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE user_onboardings MODIFY COLUMN status {$this->withApproved}");
        } else {
            // Non-MySQL: the enum is a CHECK constraint; relax to a string.
            Schema::table('user_onboardings', function (Blueprint $t) {
                $t->string('status')->default('pending')->change();
            });
        }

        Schema::table('user_onboardings', function (Blueprint $table) {
            $table->timestamp('decided_at')->nullable()->after('completed_at');
            $table->foreignId('decided_by')->nullable()->after('decided_at')
                ->constrained('admins')->nullOnDelete();
            $table->text('decision_comment')->nullable()->after('decided_by');
        });
    }

    public function down(): void
    {
        Schema::table('user_onboardings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['decided_at', 'decision_comment']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE user_onboardings MODIFY COLUMN status {$this->withoutApproved}");
        }
    }
};
