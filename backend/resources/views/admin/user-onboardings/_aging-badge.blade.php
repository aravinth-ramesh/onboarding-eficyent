{{-- SLA / aging chip. Expects $aging = $onboarding->reviewAging() (may be null). --}}
@if(!empty($aging))
    <span class="badge {{ $aging['overdue'] ? 'bg-danger-subtle text-danger border' : 'bg-light text-muted border' }}"
          title="{{ $aging['stage'] === 'approval' ? 'Awaiting approval' : 'Awaiting review' }} · SLA {{ $aging['threshold'] }} day{{ $aging['threshold'] === 1 ? '' : 's' }}">
        <i class="bi bi-clock{{ $aging['overdue'] ? '-fill' : '' }}"></i>
        {{ $aging['days'] }}d{{ $aging['overdue'] ? ' · overdue' : '' }}
    </span>
@endif
