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
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #1a3a5c; padding: 24px 32px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">Eficyent</h1>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding: 32px;">
                            <h2 style="margin: 0 0 16px; font-size: 18px; color: #1a3a5c;">Your onboarding has been submitted</h2>
                            <p style="margin: 0 0 16px; color: #333333; line-height: 1.6;">
                                {!! nl2br(e($bodyText)) !!}
                            </p>
                            <table cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8f9fa; border: 1px solid #e1e5eb; border-radius: 6px; margin: 0 0 8px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <p style="margin: 0 0 6px; font-size: 13px; color: #6c757d;">Reference Number</p>
                                        <p style="margin: 0 0 12px; font-size: 18px; font-weight: 700; color: #1a3a5c;">{{ $onboarding->reference }}</p>
                                        <p style="margin: 0; font-size: 13px; color: #6c757d;">
                                            Submitted {{ $onboarding->completed_at?->format('M d, Y H:i') }} (UTC)
                                            @if($onboarding->userType) · {{ $onboarding->userType->name }} @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Call to action -->
                    <tr>
                        <td style="padding: 0 32px 32px;" align="center">
                            <table cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background-color: #1a3a5c; border-radius: 6px;">
                                        <a href="{{ $portalUrl }}" target="_blank" style="display: inline-block; padding: 12px 32px; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none;">
                                            View Your Submission
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Footer -->
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
