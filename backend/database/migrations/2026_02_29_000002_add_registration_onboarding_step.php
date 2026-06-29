<?php

use App\Models\OnboardingStep;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the "Registration" onboarding step to existing databases (order 2,
 * shifting later steps down). Idempotent — fresh installs get it from the
 * seeder, so this only does anything when it isn't already present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (OnboardingStep::withTrashed()->where('slug', 'registration')->exists()) {
            return;
        }

        // Make room at order 2.
        DB::table('onboarding_steps')
            ->whereNull('deleted_at')
            ->where('order', '>=', 2)
            ->increment('order');

        OnboardingStep::create([
            'name' => 'Registration',
            'slug' => 'registration',
            'description' => 'Country of incorporation and registration details.',
            'component_key' => 'registration',
            'order' => 2,
            'is_active' => true,
        ]);
    }

    public function down(): void
    {
        OnboardingStep::withTrashed()->where('slug', 'registration')->forceDelete();
    }
};
