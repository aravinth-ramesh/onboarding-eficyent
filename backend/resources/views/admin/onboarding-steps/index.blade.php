@extends('admin.layouts.app')

@section('title', 'Onboarding Steps')

@section('actions')
    <a href="{{ route('admin.onboarding-steps.create') }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg"></i> Add Step
    </a>
@endsection

@section('content')
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Component Key</th>
                        <th>Status</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($steps as $step)
                        <tr>
                            <td>{{ $step->order }}</td>
                            <td class="fw-semibold">{{ $step->name }}</td>
                            <td><code>{{ $step->slug }}</code></td>
                            <td><code>{{ $step->component_key }}</code></td>
                            <td>
                                <span class="badge {{ $step->is_active ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $step->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="actions-col">
                                <a href="{{ route('admin.onboarding-steps.edit', $step) }}" class="btn btn-sm btn-outline-primary btn-action">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.onboarding-steps.destroy', $step) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Are you sure?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-action">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No onboarding steps found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($steps->hasPages())
        <div class="card-footer">
            {{ $steps->links() }}
        </div>
    @endif
</div>
@endsection
