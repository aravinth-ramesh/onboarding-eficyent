<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionTypeMapping extends Model
{
    protected $fillable = [
        'question_id',
        'user_type_id',
        'user_type_subcategory_id',
        'order',
        'is_required',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(UserTypeSubcategory::class, 'user_type_subcategory_id');
    }
}
