<?php

namespace App\Models;

use App\Enums\Ability;
use App\Enums\AdminRole;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'role',
    ];

    /** Whether this admin's role grants the given ability (see App\Enums\Ability). */
    public function hasAbility(string $ability): bool
    {
        return $this->role instanceof AdminRole && $this->role->hasAbility($ability);
    }

    public function isRole(AdminRole $role): bool
    {
        return $this->role === $role;
    }

    /** Whether an onboarding may be assigned to this admin for review. */
    public function canReceiveAssignments(): bool
    {
        return $this->is_active
            && $this->role instanceof AdminRole
            && $this->role->canReceiveAssignments();
    }

    /** Active staff who can be assigned companies to review (analysts + managers). */
    public function scopeReviewers($query)
    {
        // Whoever can actually review is assignable. This was a fixed list of
        // analyst and manager, which left compliance officers holding
        // REVIEW/APPROVE/REJECT yet absent from every Assign To dropdown — able
        // to do the work but never to be given it (retest item 35). Deriving
        // the list from the ability keeps the two in step as roles change.
        $roles = collect(AdminRole::cases())
            ->filter(fn (AdminRole $role) => in_array(Ability::REVIEW_ONBOARDING, $role->abilities(), true))
            ->map(fn (AdminRole $role) => $role->value)
            ->all();

        return $query->where('is_active', true)
            ->whereIn('role', $roles)
            ->orderBy('name');
    }

    /**
     * Roles this admin is allowed to grant others: a super admin can grant any
     * role; everyone else only roles strictly below their own level. Prevents
     * privilege escalation via user management.
     *
     * @return array<int, AdminRole>
     */
    public function assignableRoles(): array
    {
        return collect(AdminRole::cases())
            ->filter(fn (AdminRole $r) => $this->isRole(AdminRole::SuperAdmin) || $r->level() < $this->role->level())
            ->values()
            ->all();
    }

    /** Whether this admin may edit/deactivate the given target (never themselves). */
    public function canManage(self $target): bool
    {
        if ($this->id === $target->id) {
            return false;
        }

        return $this->isRole(AdminRole::SuperAdmin) || $target->role->level() < $this->role->level();
    }

    /** Analysts are scoped to their own assignments; everyone else sees more. */
    public function seesOnlyAssignedOnboardings(): bool
    {
        return $this->hasAbility(\App\Enums\Ability::VIEW_ASSIGNED_ONBOARDINGS)
            && ! $this->hasAbility(\App\Enums\Ability::VIEW_ALL_ONBOARDINGS);
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
            'role' => AdminRole::class,
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
