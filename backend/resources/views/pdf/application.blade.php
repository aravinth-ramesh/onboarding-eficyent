<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2933; margin: 0; }
        .header { background-color: #1a3a5c; color: #ffffff; padding: 18px 24px; }
        .header h1 { margin: 0 0 2px; font-size: 18px; }
        .header .sub { font-size: 10px; color: #c9d6e4; }
        .meta { width: 100%; border-collapse: collapse; margin: 14px 0 4px; }
        .meta td { padding: 3px 24px; font-size: 11px; }
        .meta .label { color: #6c757d; width: 160px; }
        .status { display: inline-block; padding: 2px 10px; border-radius: 10px; font-weight: bold; font-size: 10px; }
        .status-completed { background: #cce5ff; color: #004085; }
        .status-approved { background: #d1e7dd; color: #0f5132; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .decision { margin: 8px 24px; padding: 8px 12px; background: #f8f9fa; border-left: 3px solid #1a3a5c; font-size: 10px; }
        .section { margin: 16px 24px 0; }
        .section h2 { font-size: 13px; color: #1a3a5c; border-bottom: 1px solid #d8dee6; padding-bottom: 4px; margin: 0 0 8px; }
        .qa { margin-bottom: 8px; page-break-inside: avoid; }
        .qa .q { font-weight: bold; margin-bottom: 1px; }
        .qa .a { color: #37424e; }
        .qa .a div { margin-bottom: 1px; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9px; color: #9aa5b1; padding: 6px; border-top: 1px solid #e4e9ef; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Client Onboarding Application</h1>
        <div class="sub">Eficyent &middot; Generated {{ now()->format('M d, Y H:i') }} UTC</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Reference</td>
            <td><strong>{{ $onboarding->reference }}</strong></td>
            <td class="label">Status</td>
            <td>
                <span class="status status-{{ $onboarding->status }}">
                    {{ $onboarding->status === 'completed' ? 'Submitted' : ucfirst($onboarding->status) }}
                </span>
            </td>
        </tr>
        <tr>
            <td class="label">Applicant</td>
            <td>{{ $onboarding->user->name ?? '—' }} ({{ $onboarding->user->email ?? '—' }})</td>
            <td class="label">Organization Type</td>
            <td>{{ $onboarding->userType->name ?? '—' }}@if($onboarding->subcategory) — {{ $onboarding->subcategory->name }}@endif</td>
        </tr>
        <tr>
            <td class="label">Submitted</td>
            <td>{{ $onboarding->completed_at?->format('M d, Y H:i') ?? '—' }} UTC</td>
            <td class="label">Country of Incorporation</td>
            <td>{{ $onboarding->country_code ?? '—' }}</td>
        </tr>
    </table>

    @if($onboarding->decided_at)
        <div class="decision">
            <strong>{{ ucfirst($onboarding->status) }}</strong> on {{ $onboarding->decided_at->format('M d, Y H:i') }} UTC
            @if($onboarding->decision_comment)
                — "{{ $onboarding->decision_comment }}"
            @endif
        </div>
    @endif

    @if(!empty($onboarding->registration_details))
        <div class="section">
            <h2>Company Registration</h2>
            @foreach($onboarding->registration_details as $key => $detail)
                <div class="qa">
                    <div class="q">{{ $detail['label'] ?? $key }}</div>
                    <div class="a">{{ $detail['value'] ?? '—' }}</div>
                </div>
            @endforeach
        </div>
    @endif

    @foreach($sections as $groupName => $answers)
        <div class="section">
            <h2>{{ $groupName }}</h2>
            @foreach($answers as $answer)
                <div class="qa">
                    <div class="q">{{ $answer->question->label }}</div>
                    <div class="a">
                        @foreach($formatted($answer) as $line)
                            <div>{{ $line }}</div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="footer">
        {{ $onboarding->reference }} &middot; This document reflects the application as submitted to Eficyent.
    </div>
</body>
</html>
