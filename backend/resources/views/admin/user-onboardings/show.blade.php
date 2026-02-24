@extends('admin.layouts.app')

@section('title', 'Onboarding Details')

@push('styles')
<style>
    .submitted-answers-section-label {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--color-accent, #2e86de);
        margin-bottom: 8px;
        padding-bottom: 6px;
        border-bottom: 2px solid var(--color-accent, #2e86de);
        display: inline-block;
    }
    .submitted-answers-table {
        width: 100%;
        border-collapse: collapse;
    }
    .submitted-answers-table tr {
        border-bottom: 1px solid #f0f2f5;
    }
    .submitted-answers-table tr:last-child {
        border-bottom: none;
    }
    .submitted-answers-table td {
        padding: 10px 12px;
        vertical-align: top;
        font-size: 0.875rem;
    }
    .submitted-answers-label {
        width: 40%;
        color: #6c757d;
        font-weight: 500;
    }
    .submitted-answers-value {
        color: #2c3e50;
        font-weight: 500;
    }
    .submitted-answers-file-link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: var(--color-accent, #2e86de);
        text-decoration: none;
        font-size: 0.85rem;
        padding: 3px 0;
    }
    .submitted-answers-file-link:hover {
        text-decoration: underline;
        color: var(--color-primary-dark, #0f2440);
    }
    .submitted-answers-file-link i {
        font-size: 0.9rem;
    }
</style>
@endpush

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
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>Submitted Answers</span>
        <span class="badge badge-{{ $userOnboarding->status }}">
            {{ ucfirst(str_replace('_', ' ', $userOnboarding->status)) }}
        </span>
    </div>
    <div class="card-body">
        @php
            $grouped = $userOnboarding->answers
                ->filter(fn($a) => $a->question && $a->question->group)
                ->sortBy([
                    fn($a) => $a->question->group->order ?? 0,
                    fn($a) => $a->question->order ?? 0,
                ])
                ->groupBy(fn($a) => $a->question->group->id);
        @endphp

        @forelse($grouped as $groupId => $groupAnswers)
            @php $groupName = $groupAnswers->first()->question->group->name; @endphp
            <div class="{{ !$loop->first ? 'mt-4' : '' }}">
                <div class="submitted-answers-section-label">{{ $groupName }}</div>
                <table class="submitted-answers-table">
                    <tbody>
                        @foreach($groupAnswers as $answer)
                            @php
                                $question = $answer->question;
                                $type = $question->type ?? 'text';
                                $options = $question->options ?? [];
                                $val = $answer->value;
                            @endphp
                            <tr>
                                <td class="submitted-answers-label">{{ $question->label ?? 'N/A' }}</td>
                                <td class="submitted-answers-value">
                                    @if($type === 'file' && $answer->files->count())
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($answer->files as $file)
                                                <a href="{{ $file->url }}" target="_blank" class="submitted-answers-file-link">
                                                    <i class="bi bi-paperclip"></i>
                                                    {{ $file->original_filename }}
                                                    <small class="text-muted ms-1">({{ $file->file_size < 1048576 ? number_format($file->file_size / 1024, 1) . ' KB' : number_format($file->file_size / 1048576, 1) . ' MB' }})</small>
                                                </a>
                                            @endforeach
                                        </div>
                                    @elseif($type === 'file')
                                        <span class="text-muted">&mdash;</span>
                                    @elseif($type === 'multi_select')
                                        @php
                                            $selected = is_string($val) ? json_decode($val, true) : ($val ?? []);
                                            $labels = collect($selected)->map(function ($v) use ($options) {
                                                $opt = collect($options)->firstWhere('value', $v);
                                                return $opt['label'] ?? $v;
                                            });
                                        @endphp
                                        @if(count($labels))
                                            <div class="d-flex flex-wrap gap-1">
                                                @foreach($labels as $label)
                                                    <span class="badge bg-light text-dark border">{{ $label }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    @elseif(in_array($type, ['radio', 'select']))
                                        @php
                                            $opt = collect($options)->firstWhere('value', $val);
                                        @endphp
                                        {{ $opt['label'] ?? $val ?? '—' }}
                                    @else
                                        {{ $val ?: '—' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                No answers submitted yet.
            </div>
        @endforelse
    </div>
</div>
@endsection
