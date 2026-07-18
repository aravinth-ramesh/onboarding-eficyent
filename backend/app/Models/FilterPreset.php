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
        'user-onboardings' => [
            'search', 'status', 'user_type_id', 'assigned',
            'resubmitted', 'archived', 'from', 'to', 'date_field',
        ],
    ];

    protected $fillable = [
        'admin_id',
        'context',
        'name',
        'filters',
        'position',
        'pinned',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'position' => 'integer',
            'pinned' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // A new preset lands at the end of its admin's list for that page,
        // unless a position was set explicitly (e.g. an import preserving one).
        static::creating(function (self $preset) {
            if ($preset->position === null) {
                $preset->position = 1 + (int) static::query()
                    ->where('admin_id', $preset->admin_id)
                    ->where('context', $preset->context)
                    ->max('position');
            }
        });
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Drop params that mean nothing on their own, so two views that filter
     * identically compare equal. `date_field` only picks which date a from/to
     * range applies to — without a range it is inert, and the onboardings
     * filter bar submits it on every search whether or not a range is set.
     */
    public static function normalize(string $context, array $filters): array
    {
        if ($context === 'user-onboardings'
            && ! isset($filters['from'])
            && ! isset($filters['to'])) {
            unset($filters['date_field']);
        }

        return $filters;
    }

    /**
     * One admin's presets for one page: pinned first, then the admin's manual
     * order, with name as a stable tiebreak.
     */
    public function scopeOwnedBy(Builder $query, ?int $adminId, string $context): Builder
    {
        return $query->where('admin_id', $adminId)
            ->where('context', $context)
            ->orderByDesc('pinned')
            ->orderBy('position')
            ->orderBy('name');
    }
}
