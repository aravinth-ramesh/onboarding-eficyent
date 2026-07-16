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
        // Explicit send-date sort is purely chronological; without it the
        // default keeps pending emails on top (the ones that still matter).
        $sort = in_array($request->input('sort'), ['asc', 'desc'], true) ? $request->input('sort') : null;

        $query = $this->filteredQuery($request);
        $sort
            ? $query->orderBy('send_at', $sort)
            : $query->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")->orderBy('send_at');

        $emails = $query->paginate(20)->withQueryString();

        return view('admin.scheduled-emails.index', [
            'emails' => $emails,
            'status' => $request->input('status'),
            'search' => $request->input('search'),
            'sort' => $sort,
        ]);
    }

    private function filteredQuery(Request $request)
    {
        return ScheduledEmail::with('admin')
            ->when(
                in_array($request->input('status'), ['pending', 'sent', 'cancelled'], true),
                fn ($q) => $q->where('status', $request->input('status')),
            )
            ->when(
                filled($request->input('search')),
                fn ($q) => $q->where('subject', 'like', '%' . trim($request->input('search')) . '%'),
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

    public function bulkCancel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:scheduled_emails,id',
        ]);

        // Only pending ones can be cancelled; sent/cancelled are left alone.
        $cancelled = ScheduledEmail::whereIn('id', $validated['ids'])
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        $skipped = count($validated['ids']) - $cancelled;
        $message = "{$cancelled} scheduled email(s) cancelled."
            . ($skipped > 0 ? " {$skipped} skipped (already sent or cancelled)." : '');

        return redirect()->route('admin.scheduled-emails.index', $request->except(['ids', '_token']))
            ->with($cancelled > 0 ? 'success' : 'error', $message);
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

    /**
     * Un-cancel a scheduled email back to pending. Only allowed while its
     * send time is still in the future — a past-due restore would fire
     * instantly on the next run, so those are steered to Duplicate instead.
     */
    public function restore(ScheduledEmail $scheduledEmail): RedirectResponse
    {
        if ($scheduledEmail->status !== 'cancelled') {
            return redirect()->route('admin.scheduled-emails.index')
                ->with('error', 'Only cancelled emails can be restored.');
        }

        if ($scheduledEmail->send_at->isPast()) {
            return redirect()->route('admin.scheduled-emails.index')
                ->with('error', 'This email\'s send time has passed — duplicate it with a new time instead.');
        }

        $scheduledEmail->update(['status' => 'pending']);

        return redirect()->route('admin.scheduled-emails.index')
            ->with('success', "Scheduled email restored — it will send on {$scheduledEmail->send_at->format('M d, Y H:i')}.");
    }
}
