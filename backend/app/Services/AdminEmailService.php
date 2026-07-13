<?php

namespace App\Services;

use App\Mail\AdminNotificationMail;
use App\Models\Admin;
use App\Models\AdminEmailLog;
use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class AdminEmailService
{
    public function sendEmail(
        Admin $admin,
        User $user,
        string $subject,
        string $body,
        ?AdminNotification $notification = null,
    ): AdminEmailLog {
        Mail::to($user->email)->send(
            new AdminNotificationMail(
                $user,
                $subject,
                $body,
                $this->actionUrlFor($notification),
                $notification ? 'View Review' : 'Open Portal',
            )
        );

        return AdminEmailLog::create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'admin_notification_id' => $notification?->id,
            'subject' => $subject,
            'body' => $body,
            'sent_at' => now(),
        ]);
    }

    /**
     * Deep link into the SPA: straight to the notification detail when the
     * email relates to one, otherwise the portal home.
     */
    public function actionUrlFor(?AdminNotification $notification): string
    {
        $base = rtrim(config('app.frontend_url'), '/') . '/home';

        return $notification ? "{$base}?notification={$notification->id}" : $base;
    }

    public function getDefaultSubject(string $type, ?string $context = null): string
    {
        return match ($type) {
            'change_request' => 'Action Required: Please Update Your Response' . ($context ? " - {$context}" : ''),
            'new_question' => 'New Question Assigned to You' . ($context ? " - {$context}" : ''),
            default => 'Notification from Eficyent',
        };
    }

    public function getDefaultBody(string $type, ?array $context = null): string
    {
        $userName = $context['user_name'] ?? 'there';

        return match ($type) {
            'change_request' => "Hello {$userName},\n\nWe have reviewed your onboarding submission and require some changes to one of your answers.\n\nQuestion: " . ($context['question_label'] ?? '') . "\n\nPlease log in to your account to review the details and submit your updated response.\n\nThank you,\nEficyent Team",
            'new_question' => "Hello {$userName},\n\nA new question has been assigned to you that requires your response.\n\nQuestion: " . ($context['question_label'] ?? '') . "\n\nPlease log in to your account to provide your answer.\n\nThank you,\nEficyent Team",
            default => "Hello {$userName},\n\nYou have a new notification. Please log in to your account to review it.\n\nThank you,\nEficyent Team",
        };
    }
}
