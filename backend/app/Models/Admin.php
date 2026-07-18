<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    /** Default pin shortcut when the admin has not chosen one. */
    public const DEFAULT_PIN_SHORTCUT = 'shift+p';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'pin_shortcut',
    ];

    /** The admin's pin shortcut (normalised, e.g. "shift+p"), or the default. */
    public function pinShortcut(): string
    {
        return $this->pin_shortcut ?: self::DEFAULT_PIN_SHORTCUT;
    }

    /** Human-readable form of a normalised combo, e.g. "shift+p" → "Shift+P". */
    public static function displayShortcut(string $combo): string
    {
        $labels = ['ctrl' => 'Ctrl', 'alt' => 'Alt', 'shift' => 'Shift', 'meta' => 'Cmd'];

        return collect(explode('+', $combo))
            ->map(fn ($part) => $labels[$part] ?? strtoupper($part))
            ->implode('+');
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AdminNotification::class);
    }

    public function adminQuestions(): HasMany
    {
        return $this->hasMany(AdminQuestion::class);
    }

    public function assignedOnboardings(): HasMany
    {
        return $this->hasMany(UserOnboarding::class, 'assigned_to');
    }
}
