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
                            @if($approved)
                                <h2 style="margin: 0 0 16px; font-size: 18px; color: #1f7a4d;">Your onboarding has been approved</h2>
                                <p style="margin: 0 0 16px; color: #333333; line-height: 1.6;">
                                    Hello {{ $onboarding->user->name ?? 'there' }},
                                </p>
                                <p style="margin: 0 0 16px; color: #333333; line-height: 1.6;">
                                    Good news — your onboarding application
                                    (reference <strong>{{ $onboarding->reference }}</strong>) has been reviewed
                                    and approved. Welcome aboard! Our team will be in touch with the next steps.
                                </p>
                            @else
                                <h2 style="margin: 0 0 16px; font-size: 18px; color: #a3452c;">Your onboarding application was not approved</h2>
                                <p style="margin: 0 0 16px; color: #333333; line-height: 1.6;">
                                    Hello {{ $onboarding->user->name ?? 'there' }},
                                </p>
                                <p style="margin: 0 0 16px; color: #333333; line-height: 1.6;">
                                    We have completed the review of your onboarding application
                                    (reference <strong>{{ $onboarding->reference }}</strong>) and unfortunately
                                    it was not approved at this time.
                                </p>
                            @endif

                            @if($onboarding->decision_comment)
                                <table cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8f9fa; border-left: 4px solid {{ $approved ? '#1f7a4d' : '#a3452c' }}; border-radius: 4px;">
                                    <tr>
                                        <td style="padding: 14px 18px;">
                                            <p style="margin: 0 0 4px; font-size: 12px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px;">Reviewer note</p>
                                            <p style="margin: 0; font-size: 14px; color: #333333; line-height: 1.6;">{{ $onboarding->decision_comment }}</p>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @unless($approved)
                                <p style="margin: 16px 0 0; color: #333333; line-height: 1.6;">
                                    If you believe this is an error or your circumstances change, please
                                    contact our team at
                                    <a href="mailto:support@eficyent.com" style="color: #1a3a5c;">support@eficyent.com</a>,
                                    quoting your reference number.
                                </p>
                            @endunless
                        </td>
                    </tr>
                    <!-- Call to action -->
                    <tr>
                        <td style="padding: 0 32px 32px;" align="center">
                            <table cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background-color: #1a3a5c; border-radius: 6px;">
                                        <a href="{{ $portalUrl }}" target="_blank" style="display: inline-block; padding: 12px 32px; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none;">
                                            View Your Application
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
