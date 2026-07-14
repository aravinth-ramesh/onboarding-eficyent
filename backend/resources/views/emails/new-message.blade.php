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
                            <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">Eficyent{{ $toAdmin ? ' — Admin' : '' }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px;">
                            @if($toAdmin)
                                <h2 style="margin: 0 0 16px; font-size: 18px; color: #1a3a5c;">New client message</h2>
                                <p style="margin: 0 0 16px; color: #333333; line-height: 1.6;">
                                    {{ $onboarding->user->name ?? $onboarding->user->email }}
                                    ({{ $onboarding->reference }}) wrote:
                                </p>
                            @else
                                <h2 style="margin: 0 0 16px; font-size: 18px; color: #1a3a5c;">You have a new message</h2>
                                <p style="margin: 0 0 16px; color: #333333; line-height: 1.6;">
                                    Hello {{ $onboarding->user->name ?? 'there' }}, our team has replied
                                    regarding your application {{ $onboarding->reference }}:
                                </p>
                            @endif
                            <table cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8f9fa; border-left: 4px solid #1a3a5c; border-radius: 4px;">
                                <tr>
                                    <td style="padding: 14px 18px; font-size: 14px; color: #333333; line-height: 1.6;">
                                        {!! nl2br(e($threadMessage->body)) !!}
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
                                        <a href="{{ $actionUrl }}" target="_blank" style="display: inline-block; padding: 12px 32px; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none;">
                                            {{ $actionLabel }}
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
