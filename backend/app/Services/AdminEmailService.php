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
        if (in_array($type, ['change_request', 'new_question'], true)) {
            return app(EmailTemplateService::class)->render($type, [
                'question_label' => $context ?? '',
            ])['subject'];
        }

        return 'Notification from Eficyent';
    }

    public function getDefaultBody(string $type, ?array $context = null): string
    {
        $userName = $context['user_name'] ?? 'there';

        if (in_array($type, ['change_request', 'new_question'], true)) {
            return app(EmailTemplateService::class)->render($type, [
                'client_name' => $userName,
                'question_label' => $context['question_label'] ?? '',
            ])['body'];
        }

        return "Hello {$userName},\n\nYou have a new notification. Please log in to your account to review it.\n\nThank you,\nEficyent Team";
    }
}
