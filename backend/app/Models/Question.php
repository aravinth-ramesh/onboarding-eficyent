<?php

namespace App\Models;

use App\Models\Concerns\HasAutoOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasAutoOrder, SoftDeletes;

    public function orderScopeColumns(): array
    {
        return ['question_group_id'];
    }

    protected $fillable = [
        'question_group_id',
        'label',
        'description',
        'type',
        'options',
        'is_required',
        'order',
        'validation_rules',
        'placeholder',
        'help_text',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'validation_rules' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(QuestionGroup::class, 'question_group_id');
    }

    public function typeMappings(): HasMany
    {
        return $this->hasMany(QuestionTypeMapping::class);
    }

    /**
     * Deleting a question only soft-deletes it, so the database-level cascade
     * on conditional_rules never fires and its rules outlive it — leaving rules
     * that point at nothing and render blank in the admin list (report item 1).
     * Clear them out with the question.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $question) {
            $question->conditionalRules()->delete();
            $question->dependentRules()->delete();
        });
    }

    public function conditionalRules(): HasMany
    {
        return $this->hasMany(ConditionalRule::class);
    }

    public function dependentRules(): HasMany
    {
        return $this->hasMany(ConditionalRule::class, 'parent_question_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(UserAnswer::class);
    }
}
