<?php

namespace App\Models\Concerns;

/**
 * Automatically assigns the next sequential order value on creation.
 *
 * Usage:
 *   use HasAutoOrder;
 *
 *   // Override for scoped ordering (e.g. questions within a group):
 *   public function orderScopeColumns(): array
 *   {
 *       return ['question_group_id'];
 *   }
 *
 * IMPORTANT: The controller MUST wrap Model::create() inside DB::transaction()
 * so that lockForUpdate() holds the row lock until the insert commits.
 */
trait HasAutoOrder
{
    public static function bootHasAutoOrder(): void
    {
        static::creating(function ($model) {
            // Only auto-assign if order was not explicitly provided (null or 0)
            if ($model->order === null || $model->order === 0) {
                $query = static::query();

                foreach ($model->orderScopeColumns() as $column) {
                    $query->where($column, $model->{$column});
                }

                // lockForUpdate() acquires a row-level lock (SELECT ... FOR UPDATE).
                // Combined with InnoDB gap locks, this prevents concurrent inserts
                // from reading the same max(order) value.
                $model->order = ($query->lockForUpdate()->max('order') ?? 0) + 1;
            }
        });
    }

    /**
     * Columns that define the ordering scope.
     * Override in the model for scoped ordering.
     *
     * Example: Questions are ordered within their group,
     *          so return ['question_group_id'].
     */
    public function orderScopeColumns(): array
    {
        return [];
    }
}
