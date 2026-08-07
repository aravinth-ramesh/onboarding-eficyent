<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdminQuestion extends Model
{
    protected $fillable = [
        'user_id',
        'admin_id',
        'question_group_id',
        'label',
        'description',
        'type',
        'options',
        'is_required',
        'placeholder',
        'help_text',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** The section this follow-up relates to, when the admin named one (EOP-95). */
    public function group(): BelongsTo
    {
        return $this->belongsTo(QuestionGroup::class, 'question_group_id');
    }

    public function answer(): HasOne
    {
        return $this->hasOne(AdminQuestionAnswer::class);
    }

    public function notification(): HasOne
    {
        return $this->hasOne(AdminNotification::class);
    }
}
