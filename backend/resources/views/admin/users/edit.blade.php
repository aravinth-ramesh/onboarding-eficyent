@extends('admin.layouts.app')

@section('title', 'Edit Staff')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Edit Staff — {{ $admin->name }}</span>
        <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
    </div>
    <div class="card-body" style="max-width: 560px;">
        <form method="POST" action="{{ route('admin.users.update', $admin) }}">
            @csrf
            @method('PUT')
            @include('admin.users._form')
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save changes</button>
        </form>
    </div>
</div>
@endsection
