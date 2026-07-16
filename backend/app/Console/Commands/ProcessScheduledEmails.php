<?php

namespace App\Console\Commands;

use App\Models\ScheduledEmail;
use App\Services\AdminEmailService;
use Illuminate\Console\Command;

class ProcessScheduledEmails extends Command
{
    protected $signature = 'emails:process-scheduled';

    protected $description = 'Send bulk emails whose scheduled time has arrived';

    public function handle(AdminEmailService $emailService): int
    {
        $due = ScheduledEmail::due()->get();

        if ($due->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($due as $email) {
            // Claim it first so an overlapping run can't double-send.
            $email->update(['status' => 'sent', 'processed_at' => now()]);

            $sent = $emailService->sendBulk(
                $email->admin,
                $email->onboarding_ids,
                $email->subject,
                $email->body,
            );

            $email->update(['sent_count' => $sent]);
            $this->info("Scheduled email #{$email->id}: sent to {$sent} client(s).");
        }

        return self::SUCCESS;
    }
}
