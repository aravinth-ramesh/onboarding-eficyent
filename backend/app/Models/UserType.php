<?php

namespace App\Models;

use App\Models\Concerns\HasAutoOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserType extends Model
{
    use SoftDeletes, HasAutoOrder;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'has_subcategories',
        'is_active',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'has_subcategories' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(UserTypeSubcategory::class)->orderBy('order');
    }

    public function questionMappings(): HasMany
    {
        return $this->hasMany(QuestionTypeMapping::class);
    }
}
