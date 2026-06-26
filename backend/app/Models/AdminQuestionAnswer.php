<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminQuestionAnswer extends Model
{
    protected $fillable = [
        'admin_question_id',
        'user_id',
        'value',
    ];

    public function adminQuestion(): BelongsTo
    {
        return $this->belongsTo(AdminQuestion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(AdminQuestionAnswerFile::class);
    }
}
