@extends('admin.layouts.app')

@section('title', 'Onboarding Details')

@section('actions')
    <a href="{{ route('admin.user-onboardings.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
@endsection

@section('content')
<div class="row g-3 mb-4">
    {{-- User Info --}}
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">User Information</div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt class="text-muted" style="font-size: 0.8rem;">Name</dt>
                    <dd>{{ $userOnboarding->user->name ?? 'N/A' }}</dd>

                    <dt class="text-muted" style="font-size: 0.8rem;">Email</dt>
                    <dd>{{ $userOnboarding->user->email ?? 'N/A' }}</dd>

                    <dt class="text-muted" style="font-size: 0.8rem;">User Type</dt>
                    <dd>{{ $userOnboarding->userType->name ?? 'N/A' }}</dd>

                    <dt class="text-muted" style="font-size: 0.8rem;">Subcategory</dt>
                    <dd>{{ $userOnboarding->subcategory->name ?? '-' }}</dd>

                    <dt class="text-muted" style="font-size: 0.8rem;">Status</dt>
                    <dd>
                        <span class="badge badge-{{ $userOnboarding->status }}">
                            {{ ucfirst(str_replace('_', ' ', $userOnboarding->status)) }}
                        </span>
                    </dd>

                    <dt class="text-muted" style="font-size: 0.8rem;">Template Version</dt>
                    <dd>{{ $userOnboarding->template_version ?? '-' }}</dd>

                    <dt class="text-muted" style="font-size: 0.8rem;">Started</dt>
                    <dd>{{ $userOnboarding->started_at?->format('M d, Y H:i') ?? '-' }}</dd>

                    <dt class="text-muted" style="font-size: 0.8rem;">Completed</dt>
                    <dd class="mb-0">{{ $userOnboarding->completed_at?->format('M d, Y H:i') ?? '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Steps --}}
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">Onboarding Steps</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Step</th>
                                <th>Component</th>
                                <th>Status</th>
                                <th>Started</th>
                                <th>Completed</th>
                                <th style="width: 80px;">Toggle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($userOnboarding->steps->sortBy('order') as $step)
                                <tr class="{{ $step->status === 'skipped' ? 'table-warning opacity-75' : ($step->id === $userOnboarding->current_step_id ? 'table-primary' : '') }}">
                                    <td>{{ $step->order }}</td>
                                    <td class="fw-semibold">
                                        {{ $step->name }}
                                        @if($step->id === $userOnboarding->current_step_id)
                                            <span class="badge bg-primary ms-1">Current</span>
                                        @endif
                                    </td>
                                    <td><code>{{ $step->component_key }}</code></td>
                                    <td>
                                        <span class="badge badge-{{ $step->status }}">
                                            {{ ucfirst(str_replace('_', ' ', $step->status)) }}
                                        </span>
                                    </td>
                                    <td>{{ $step->started_at?->format('M d, H:i') ?? '-' }}</td>
                                    <td>{{ $step->completed_at?->format('M d, H:i') ?? '-' }}</td>
                                    <td>
                                        @if($step->status !== 'completed')
                                            <form action="{{ route('admin.user-onboardings.steps.toggle', [$userOnboarding, $step]) }}" method="POST"
                                                onsubmit="return confirm('{{ $step->status === 'skipped' ? 'Enable this step?' : 'Disable (skip) this step?' }}')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm {{ $step->status === 'skipped' ? 'btn-outline-success' : 'btn-outline-warning' }} btn-action"
                                                    title="{{ $step->status === 'skipped' ? 'Enable step' : 'Disable step' }}">
                                                    <i class="bi {{ $step->status === 'skipped' ? 'bi-toggle-off' : 'bi-toggle-on' }}"></i>
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted" title="Completed steps cannot be toggled"><i class="bi bi-lock"></i></span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">No steps found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Answers --}}
<div class="card">
    <div class="card-header">User Answers</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Group</th>
                        <th>Question</th>
                        <th>Type</th>
                        <th>Answer</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($userOnboarding->answers->sortBy('question.group.order') as $answer)
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark">{{ $answer->question->group->name ?? 'N/A' }}</span>
                            </td>
                            <td class="fw-semibold">{{ Str::limit($answer->question->label ?? 'N/A', 60) }}</td>
                            <td><code>{{ $answer->question->type ?? '-' }}</code></td>
                            <td>
                                @if(($answer->question->type ?? '') === 'file' && $answer->files->count())
                                    @foreach($answer->files as $file)
                                        <div class="mb-1">
                                            <a href="{{ $file->url }}" target="_blank" class="text-decoration-none">
                                                <i class="bi bi-file-earmark"></i> {{ $file->original_filename }}
                                            </a>
                                            <small class="text-muted">({{ number_format($file->file_size / 1024, 1) }} KB)</small>
                                        </div>
                                    @endforeach
                                @else
                                    {{ Str::limit($answer->value ?? '-', 100) }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No answers submitted yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
