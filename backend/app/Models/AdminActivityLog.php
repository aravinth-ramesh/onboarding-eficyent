<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of a state-changing admin-panel request,
 * written automatically by the LogAdminActivity middleware.
 */
class AdminActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'admin_id',
        'action',
        'method',
        'path',
        'subject_type',
        'subject_id',
        'payload',
        'status',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
