{{-- Shared staff form. Expects $roles (array of AdminRole) and optional $admin. --}}
@php $editing = isset($admin); @endphp

<div class="mb-3">
    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
    <input type="text" id="name" name="name" maxlength="255" required
           class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $editing ? $admin->name : '') }}">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
    <input type="email" id="email" name="email" maxlength="255" required
           class="form-control @error('email') is-invalid @enderror"
           value="{{ old('email', $editing ? $admin->email : '') }}">
    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
    <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
        @foreach($roles as $role)
            <option value="{{ $role->value }}"
                @selected(old('role', $editing ? $admin->role->value : '') === $role->value)>
                {{ $role->label() }}
            </option>
        @endforeach
    </select>
    <div class="form-text">You can only assign roles below your own.</div>
    @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="password" class="form-label">
        Password @unless($editing)<span class="text-danger">*</span>@endunless
    </label>
    <input type="password" id="password" name="password" autocomplete="new-password"
           class="form-control @error('password') is-invalid @enderror"
           @unless($editing) required @endunless minlength="8">
    <div class="form-text">
        At least 8 characters.@if($editing) Leave blank to keep the current password.@endif
    </div>
    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
