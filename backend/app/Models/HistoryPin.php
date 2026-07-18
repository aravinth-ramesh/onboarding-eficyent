<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An admin's pin on one of their customization-history entries. A personal
 * marker layered over the append-only admin_activity_logs audit trail.
 */
class HistoryPin extends Model
{
    protected $fillable = [
        'admin_id',
        'admin_activity_log_id',
    ];
}
