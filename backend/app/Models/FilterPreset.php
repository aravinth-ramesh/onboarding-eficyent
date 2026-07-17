<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named filter combination saved by an admin on a list page.
 */
class FilterPreset extends Model
{
    /**
     * The query params that make up a saved view, per page context. Also the
     * allow-list: anything else in the request is never stored in a preset.
     */
    public const CONTEXTS = [
        'scheduled-emails' => ['status', 'search', 'sort', 'from', 'to'],
    ];

    protected $fillable = [
        'admin_id',
        'context',
        'name',
        'filters',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** One admin's presets for one page, alphabetical. */
    public function scopeOwnedBy(Builder $query, ?int $adminId, string $context): Builder
    {
        return $query->where('admin_id', $adminId)
            ->where('context', $context)
            ->orderBy('name');
    }
}
