@extends('admin.layouts.app')

@section('title', 'Staff')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            Staff
            <div class="text-muted" style="font-size: 0.8rem; font-weight: normal;">
                People with access to the admin panel, and their roles.
            </div>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary">
            <i class="bi bi-person-plus"></i> Add staff
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $roleTone = [
                            'analyst' => 'bg-secondary-subtle text-secondary',
                            'compliance' => 'bg-info-subtle text-info-emphasis',
                            'manager' => 'bg-primary-subtle text-primary',
                            'admin' => 'bg-warning-subtle text-warning-emphasis',
                            'super_admin' => 'bg-danger-subtle text-danger',
                        ];
                    @endphp
                    @forelse($admins as $row)
                        <tr class="{{ $row->is_active ? '' : 'text-muted' }}">
                            <td>
                                {{ $row->name }}
                                @if($row->id === $actor->id)
                                    <span class="badge bg-light text-muted border ms-1">you</span>
                                @endif
                            </td>
                            <td>{{ $row->email }}</td>
                            <td>
                                <span class="badge {{ $roleTone[$row->role->value] ?? 'bg-secondary-subtle' }} border">
                                    {{ $row->role->label() }}
                                </span>
                            </td>
                            <td>
                                @if($row->is_active)
                                    <span class="badge bg-success-subtle text-success border">Active</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border">Deactivated</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($actor->canManage($row))
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="{{ route('admin.users.edit', $row) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <form method="POST" action="{{ route('admin.users.toggle', $row) }}"
                                              onsubmit="return confirm('{{ $row->is_active ? 'Deactivate' : 'Activate' }} {{ $row->name }}?')">
                                            @csrf
                                            <button class="btn btn-sm {{ $row->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                {{ $row->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No staff yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($admins->hasPages())
        <div class="card-footer">{{ $admins->links() }}</div>
    @endif
</div>
@endsection
