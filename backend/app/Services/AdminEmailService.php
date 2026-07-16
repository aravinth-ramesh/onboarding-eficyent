<?php

namespace App\Services;

use App\Mail\AdminNotificationMail;
use App\Models\Admin;
use App\Models\AdminEmailLog;
use App\Models\AdminNotification;
use App\Models\User;
use App\Models\UserOnboarding;
use Illuminate\Support\Facades\Mail;

class AdminEmailService
{
    /**
     * Send one composed message to the clients of the given onboarding ids,
     * substituting {{name}} / {{reference}} per recipient. Onboardings whose
     * client is unreachable are skipped. Shared by the bulk-now action and
     * the scheduled-send command. Returns the number actually sent.
     */
    public function sendBulk(?Admin $admin, array $onboardingIds, string $subject, string $body, bool $queue = true): int
    {
        $sent = 0;

        foreach (UserOnboarding::with('user')->whereIn('id', $onboardingIds)->get() as $onboarding) {
            $user = $onboarding->user;
            if (! $user?->email) {
                continue;
            }

            $vars = [
                'name' => $user->name ?: 'there',
                'reference' => $onboarding->reference,
            ];

            try {
                $this->sendEmail(
                    $admin,
                    $user,
                    $this->fillPlaceholders($subject, $vars),
                    $this->fillPlaceholders($body, $vars),
                    queue: $queue,
                );
                $sent++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }

    public function fillPlaceholders(string $text, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', function ($m) use ($vars) {
            return array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0];
        }, $text);
    }

    public function sendEmail(
        ?Admin $admin,
        User $user,
        string $subject,
        string $body,
        ?AdminNotification $notification = null,
        bool $queue = false,
    ): AdminEmailLog {
        $mailable = new AdminNotificationMail(
            $user,
            $subject,
            $body,
            $this->actionUrlFor($notification),
            $notification ? 'View Review' : 'Open Portal',
        );

        // Bulk sends queue so a large loop doesn't block the request; the
        // single-send path stays synchronous so its success/failure is
        // reflected immediately in the flash message.
        $queue
            ? Mail::to($user->email)->queue($mailable)
            : Mail::to($user->email)->send($mailable);

        return AdminEmailLog::create([
            'admin_id' => $admin?->id,
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
