<?php

namespace Tests\Feature;

use App\Mail\AdminNotificationMail;
use App\Models\AdminNotification;
use App\Models\User;
use App\Services\AdminEmailService;
use Tests\TestCase;

class AdminNotificationEmailTest extends TestCase
{
    public function test_action_url_deep_links_to_notification(): void
    {
        config(['app.frontend_url' => 'https://portal.example.com/']);

        $notification = new AdminNotification();
        $notification->id = 42;

        $url = app(AdminEmailService::class)->actionUrlFor($notification);

        $this->assertSame('https://portal.example.com/home?notification=42', $url);
    }

    public function test_action_url_falls_back_to_portal_home_without_notification(): void
    {
        config(['app.frontend_url' => 'https://portal.example.com']);

        $url = app(AdminEmailService::class)->actionUrlFor(null);

        $this->assertSame('https://portal.example.com/home', $url);
    }

    public function test_mail_renders_view_review_button(): void
    {
        $user = new User(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $html = (new AdminNotificationMail(
            $user,
            'Action Required',
            'Please review your submission.',
            'https://portal.example.com/home?notification=42',
            'View Review',
        ))->render();

        $this->assertStringContainsString('https://portal.example.com/home?notification=42', $html);
        $this->assertStringContainsString('View Review', $html);
    }

    public function test_mail_renders_without_button_when_no_action_url(): void
    {
        $user = new User(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $html = (new AdminNotificationMail($user, 'Hello', 'Body text'))->render();

        $this->assertStringNotContainsString('View Review', $html);
        $this->assertStringNotContainsString('Open Portal', $html);
    }
}
