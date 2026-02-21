<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnswerAuditLog extends Model
{
    protected $fillable = [
        'user_answer_id',
        'question_id',
        'user_id',
        'edited_by',
        'old_value',
        'new_value',
        'edit_reason',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(UserAnswer::class, 'user_answer_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
