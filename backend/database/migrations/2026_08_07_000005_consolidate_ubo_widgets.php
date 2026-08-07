<?php

use App\Support\UboTableColumns;
use App\Support\UboWidgetConsolidation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Collapse the two overlapping beneficial-owner widgets onto the owner table
 * (EOP-49): complete the table's columns, carry existing `ubo` answers across,
 * then deactivate the duplicate widget.
 *
 * Idempotent and non-destructive — the `ubo` question and its answers are kept
 * (deactivated), so reviewers can still see what an already-decided
 * application was assessed on, and the change can be reversed by reactivating
 * the question.
 */
return new class extends Migration
{
    public function up(): void
    {
        UboTableColumns::apply();
        $result = UboWidgetConsolidation::apply();

        Log::info('consolidated the duplicate UBO widgets onto the owner table', $result);
    }

    public function down(): void
    {
        // Reactivating the widget would resurrect the duplicate capture; the
        // answers were never deleted, so there is nothing to restore.
    }
};
