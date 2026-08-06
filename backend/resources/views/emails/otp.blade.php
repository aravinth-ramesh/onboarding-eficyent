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
                            <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">Eficyent</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px;">
                            <h2 style="margin: 0 0 16px; font-size: 18px; color: #1a3a5c;">Your login verification code</h2>
                            <p style="margin: 0 0 24px; color: #333333; line-height: 1.6;">
                                Use the code below to complete your login. It expires in <strong>10 minutes</strong>.
                            </p>
                            <table cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="padding: 20px; background-color: #f0f4ff; border: 1px solid #dbe4ff; border-radius: 6px;">
                                        <span style="font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #1a3a5c;">{{ $otpCode }}</span>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 24px 0 0; color: #6c757d; line-height: 1.6; font-size: 14px;">
                                If you did not request this code, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 24px 32px; background-color: #f8f9fa; border-top: 1px solid #e1e5eb; font-size: 12px; color: #6c757d; text-align: center;">
                            This is an automated message — please do not reply.<br>
                            &copy; {{ date('Y') }} Eficyent. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
