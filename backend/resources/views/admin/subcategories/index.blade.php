@extends('admin.layouts.app')

@section('title', 'Subcategories: ' . $userType->name)

@section('actions')
    <a href="{{ route('admin.user-types.subcategories.create', $userType) }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg"></i> Add Subcategory
    </a>
    <a href="{{ route('admin.user-types.index') }}" class="btn btn-sm btn-outline-secondary ms-1">
        <i class="bi bi-arrow-left"></i> Back
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
                        <th>Status</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subcategories as $sub)
                        <tr>
                            <td>{{ $sub->order }}</td>
                            <td class="fw-semibold">{{ $sub->name }}</td>
                            <td><code>{{ $sub->slug }}</code></td>
                            <td>
                                <span class="badge {{ $sub->is_active ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $sub->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="actions-col">
                                <a href="{{ route('admin.user-types.subcategories.edit', [$userType, $sub]) }}" class="btn btn-sm btn-outline-primary btn-action">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.user-types.subcategories.destroy', [$userType, $sub]) }}" method="POST" class="d-inline"
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
                            <td colspan="5" class="text-center text-muted py-4">No subcategories found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($subcategories->hasPages())
        <div class="card-footer">
            {{ $subcategories->links() }}
        </div>
    @endif
</div>
@endsection
