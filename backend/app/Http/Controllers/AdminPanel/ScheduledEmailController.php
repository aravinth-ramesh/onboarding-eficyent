<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Mail\AdminNotificationMail;
use App\Models\ScheduledEmail;
use App\Models\UserOnboarding;
use App\Services\AdminEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ScheduledEmailController extends Controller
{
    public function index(Request $request): View
    {
        $emails = $this->filteredQuery($request)
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderBy('send_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.scheduled-emails.index', [
            'emails' => $emails,
            'status' => $request->input('status'),
        ]);
    }

    private function filteredQuery(Request $request)
    {
        return ScheduledEmail::with('admin')
            ->when(
                in_array($request->input('status'), ['pending', 'sent', 'cancelled'], true),
                fn ($q) => $q->where('status', $request->input('status')),
            );
    }

    /**
     * Render the email exactly as a recipient will see it — the real
     * branded mailable with {{name}}/{{reference}} filled from the first
     * reachable recipient (or generic sample data if none). Returned as raw
     * HTML for display in a preview iframe.
     */
    public function preview(ScheduledEmail $scheduledEmail, AdminEmailService $emailService): \Illuminate\Http\Response
    {
        $recipient = UserOnboarding::with('user')
            ->whereIn('id', $scheduledEmail->onboarding_ids)
            ->get()
            ->first(fn ($o) => $o->user?->email);

        $vars = $recipient
            ? ['name' => $recipient->user->name ?: 'there', 'reference' => $recipient->reference]
            : ['name' => 'Jane Doe', 'reference' => 'ONB-2026-0000'];

        $sampleUser = $recipient?->user ?? new \App\Models\User(['name' => $vars['name'], 'email' => 'sample@example.com']);

        $html = (new AdminNotificationMail(
            $sampleUser,
            $emailService->fillPlaceholders($scheduledEmail->subject, $vars),
            $emailService->fillPlaceholders($scheduledEmail->body, $vars),
            $emailService->actionUrlFor(null),
            'Open Portal',
        ))->render();

        return response($html);
    }

    /**
     * Clone an existing scheduled email (any status) into a new pending one
     * at a fresh future time — reusing the subject, body and recipients.
     */
    public function duplicate(Request $request, ScheduledEmail $scheduledEmail): RedirectResponse
    {
        $validated = $request->validate([
            'send_at' => 'required|date|after:now',
        ]);

        $copy = ScheduledEmail::create([
            'admin_id' => Auth::guard('admin')->id(),
            'subject' => $scheduledEmail->subject,
            'body' => $scheduledEmail->body,
            'onboarding_ids' => $scheduledEmail->onboarding_ids,
            'send_at' => $validated['send_at'],
            'status' => 'pending',
        ]);

        return redirect()->route('admin.scheduled-emails.index')
            ->with('success', "Scheduled email duplicated for {$copy->send_at->format('M d, Y H:i')} to " . count($copy->onboarding_ids) . ' client(s).');
    }

    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'scheduled-emails-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Send At (UTC)', 'Status', 'Subject', 'Recipients', 'Sent Count',
                'Scheduled By', 'Created At (UTC)', 'Processed At (UTC)',
            ]);

            $this->filteredQuery($request)
                ->orderByDesc('send_at')
                ->lazy()
                ->each(function (ScheduledEmail $email) use ($out) {
                    fputcsv($out, [
                        $email->send_at->toDateTimeString(),
                        $email->status,
                        $email->subject,
                        count($email->onboarding_ids),
                        $email->sent_count ?? '',
                        $email->admin->name ?? '',
                        $email->created_at->toDateTimeString(),
                        $email->processed_at?->toDateTimeString() ?? '',
                    ]);
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function cancel(ScheduledEmail $scheduledEmail): RedirectResponse
    {
        if ($scheduledEmail->status !== 'pending') {
            return redirect()->route('admin.scheduled-emails.index')
                ->with('error', 'Only pending emails can be cancelled.');
        }

        $scheduledEmail->update(['status' => 'cancelled']);

        return redirect()->route('admin.scheduled-emails.index')
            ->with('success', 'Scheduled email cancelled.');
    }
}
