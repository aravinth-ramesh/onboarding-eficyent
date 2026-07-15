<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f7;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f7; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="background-color: #1a3a5c; padding: 24px 32px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">Eficyent — Admin</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px;">
                            <h2 style="margin: 0 0 16px; font-size: 18px; color: #1a3a5c;">
                                {{ $notification->type === 'change_request' ? 'Client updated their answer' : 'Client answered your question' }}
                            </h2>
                            <p style="margin: 0 0 16px; color: #333333; line-height: 1.6;">
                                {{ $notification->user->name ?? $notification->user->email }}
                                has responded to your request. It is ready for you to check.
                            </p>
                            <table cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8f9fa; border: 1px solid #e1e5eb; border-radius: 6px;">
                                <tr>
                                    <td style="padding: 16px 20px; font-size: 14px; color: #333333; line-height: 1.9;">
                                        @if($onboarding)
                                            <strong>Reference:</strong> {{ $onboarding->reference }}<br>
                                        @endif
                                        <strong>Question:</strong> {{ $notification->userAnswer->question->label ?? $notification->adminQuestion->label ?? '—' }}<br>
                                        <strong>Their response:</strong> {{ $summary }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 32px 32px;" align="center">
                            <table cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background-color: #1a3a5c; border-radius: 6px;">
                                        <a href="{{ $reviewUrl }}" target="_blank" style="display: inline-block; padding: 12px 32px; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none;">
                                            Review Response
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 24px 32px; background-color: #f8f9fa; border-top: 1px solid #e1e5eb; font-size: 12px; color: #6c757d; text-align: center;">
                            &copy; {{ date('Y') }} Eficyent. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
