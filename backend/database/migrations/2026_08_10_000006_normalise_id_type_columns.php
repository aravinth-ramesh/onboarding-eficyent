<?php

use App\Support\UboTableColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * The directors table carried ID Type as free text while the beneficial owners
 * table offered a dropdown, so the same field behaved differently in two
 * sections of the same step (EOP-49).
 */
return new class extends Migration
{
    public function up(): void
    {
        Log::info('normalised id type columns', ['questions_changed' => UboTableColumns::apply()]);
    }

    public function down(): void
    {
        // Data-only.
    }
};
