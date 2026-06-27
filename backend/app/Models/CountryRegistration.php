<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CountryRegistration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'country_code',
        'field_key',
        'label',
        'required',
        'applies_to',
        'pattern',
        'pattern_message',
        'checksum',
        'placeholder',
        'help',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Map the stored row to the field shape consumed by the API / frontend.
     */
    public function toField(): array
    {
        return [
            'key' => $this->field_key,
            'label' => $this->label,
            'required' => (bool) $this->required,
            'pattern' => $this->pattern,
            'pattern_message' => $this->pattern_message,
            'checksum' => $this->checksum,
            'placeholder' => $this->placeholder,
            'help' => $this->help,
        ];
    }

    public function appliesToCategory(string $category): bool
    {
        return $this->applies_to === 'both' || $this->applies_to === $category;
    }
}
