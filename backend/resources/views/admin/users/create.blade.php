@extends('admin.layouts.app')

@section('title', 'Add Staff')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Add Staff</span>
        <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
    </div>
    <div class="card-body" style="max-width: 560px;">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            @include('admin.users._form')
            <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus"></i> Create staff member</button>
        </form>
    </div>
</div>
@endsection
