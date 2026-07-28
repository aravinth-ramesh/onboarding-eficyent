<?php

namespace App\Http\Controllers\AdminPanel;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Staff management: create colleagues and set their role. Gated by the
 * MANAGE_USERS ability (Admin / Super Admin). Two guardrails keep it safe:
 *   - you may only assign / manage roles below your own (a super_admin can do
 *     anything; an admin can manage analysts, managers and compliance, but not
 *     other admins or super admins) — so no one can escalate their own power;
 *   - the last active super admin can't be demoted or deactivated, so the
 *     platform can never lock itself out.
 * Accounts are deactivated, never deleted, to preserve audit and assignment
 * references.
 */
class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'admins' => Admin::orderByDesc('is_active')->orderBy('name')->paginate(25),
            'actor' => Auth::guard('admin')->user(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', ['roles' => Auth::guard('admin')->user()->assignableRoles()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in($this->assignableRoleValues())],
        ]);

        Admin::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'is_active' => true,
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', "Staff member \"{$validated['name']}\" created.");
    }

    public function edit(Admin $admin): View
    {
        abort_unless(Auth::guard('admin')->user()->canManage($admin), 403);

        return view('admin.users.edit', [
            'admin' => $admin,
            'roles' => Auth::guard('admin')->user()->assignableRoles(),
        ]);
    }

    public function update(Request $request, Admin $admin): RedirectResponse
    {
        abort_unless(Auth::guard('admin')->user()->canManage($admin), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in($this->assignableRoleValues())],
        ]);

        // Never let the last active super admin be demoted out of the role.
        if ($this->isLastActiveSuperAdmin($admin) && $validated['role'] !== AdminRole::SuperAdmin->value) {
            return back()->withInput()->with('error', 'You cannot demote the last active super admin.');
        }

        $admin->name = $validated['name'];
        $admin->email = $validated['email'];
        $admin->role = AdminRole::from($validated['role']);
        if (filled($validated['password'])) {
            $admin->password = $validated['password'];
        }
        $admin->save();

        return redirect()->route('admin.users.index')
            ->with('success', "Staff member \"{$admin->name}\" updated.");
    }

    public function toggleStatus(Admin $admin): RedirectResponse
    {
        abort_unless(Auth::guard('admin')->user()->canManage($admin), 403);

        if ($admin->is_active && $this->isLastActiveSuperAdmin($admin)) {
            return back()->with('error', 'You cannot deactivate the last active super admin.');
        }

        $admin->update(['is_active' => ! $admin->is_active]);

        return back()->with('success',
            "Staff member \"{$admin->name}\" " . ($admin->is_active ? 'activated.' : 'deactivated.'));
    }

    private function assignableRoleValues(): array
    {
        return array_map(fn (AdminRole $r) => $r->value, Auth::guard('admin')->user()->assignableRoles());
    }

    private function isLastActiveSuperAdmin(Admin $target): bool
    {
        return $target->isRole(AdminRole::SuperAdmin)
            && Admin::where('role', AdminRole::SuperAdmin->value)->where('is_active', true)->count() <= 1;
    }
}
