<?php

namespace App\Models;

use App\Models\Concerns\HasAutoOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserTypeSubcategory extends Model
{
    use SoftDeletes, HasAutoOrder;

    public function orderScopeColumns(): array
    {
        return ['user_type_id'];
    }

    protected $fillable = [
        'user_type_id',
        'name',
        'slug',
        'description',
        'is_active',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class);
    }

    public function questionMappings(): HasMany
    {
        return $this->hasMany(QuestionTypeMapping::class);
    }
}
