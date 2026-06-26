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

    public function answer(): HasOne
    {
        return $this->hasOne(AdminQuestionAnswer::class);
    }

    public function notification(): HasOne
    {
        return $this->hasOne(AdminNotification::class);
    }
}
