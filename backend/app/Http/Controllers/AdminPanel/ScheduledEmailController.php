<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\ScheduledEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ScheduledEmailController extends Controller
{
    public function index(): View
    {
        $emails = ScheduledEmail::with('admin')
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderBy('send_at')
            ->paginate(20);

        return view('admin.scheduled-emails.index', compact('emails'));
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
