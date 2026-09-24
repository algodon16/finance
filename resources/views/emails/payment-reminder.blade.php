<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Reminder – Upcoming Payment Deadline</title>
</head>
<body style="margin:0;padding:0;background-color:#f0f2f5;font-family:Arial,Helvetica,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f2f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background-color:#1a2332;padding:20px 28px;">
                            <p style="margin:0;color:#ffffff;font-size:20px;font-weight:bold;">SFMS</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <h2 style="margin:0 0 16px;font-size:18px;color:#1a202c;">Payment Reminder – Upcoming Payment Deadline</h2>
                            <p style="margin:0 0 12px;font-size:14px;color:#1a202c;">Dear {{ $studentName }},</p>
                            <p style="margin:0 0 16px;font-size:14px;color:#1a202c;">This is a reminder that you have an outstanding payment of ₱{{ $amountDue }}.</p>
                            <table width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:6px;font-size:14px;color:#1a202c;">
                                <tr>
                                    <td style="color:#718096;">Payment Type:</td>
                                    <td style="font-weight:bold;">{{ $paymentType }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#718096;">Amount Due:</td>
                                    <td style="font-weight:bold;">₱{{ $amountDue }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#718096;">Payment Deadline:</td>
                                    <td style="font-weight:bold;">{{ $deadline }}</td>
                                </tr>
                            </table>
                            <p style="margin:16px 0 0;font-size:14px;color:#1a202c;">Please settle your payment on or before the deadline.</p>
                            <p style="margin:16px 0 0;font-size:14px;color:#1a202c;">Thank you.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
