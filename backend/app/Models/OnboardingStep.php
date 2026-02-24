<?php

namespace App\Models;

use App\Models\Concerns\HasAutoOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnboardingStep extends Model
{
    use SoftDeletes, HasAutoOrder;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'component_key',
        'order',
        'is_active',
        'config',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config' => 'array',
        ];
    }

    public function userSteps(): HasMany
    {
        return $this->hasMany(UserOnboardingStep::class);
    }
}
