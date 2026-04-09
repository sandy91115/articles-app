<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification OTP</title>
</head>
<body style="margin:0;padding:24px;background:#0c1724;color:#eef3f7;font-family:Segoe UI,Arial,sans-serif;">
    <div style="max-width:560px;margin:0 auto;background:#112033;border:1px solid rgba(255,255,255,0.08);border-radius:24px;padding:32px;">
        <p style="margin:0 0 10px;color:#f6c453;font-size:12px;font-weight:700;letter-spacing:0.22em;text-transform:uppercase;">Content Monetization Portal</p>
        <h1 style="margin:0 0 16px;font-size:30px;line-height:1.1;font-family:Georgia,Times New Roman,serif;">Verify your author account</h1>
        <p style="margin:0 0 18px;color:#c0d0df;line-height:1.7;">
            Hi {{ $userName ?: 'there' }}, use the OTP below to verify your email before signing in to the author dashboard.
        </p>
        <div style="margin:0 0 18px;padding:18px 22px;border-radius:18px;background:#0b1320;border:1px solid rgba(246,196,83,0.28);display:inline-block;">
            <span style="display:block;color:#9db2c7;font-size:12px;text-transform:uppercase;letter-spacing:0.16em;margin-bottom:6px;">Your OTP</span>
            <strong style="font-size:34px;letter-spacing:0.24em;color:#f6c453;">{{ $code }}</strong>
        </div>
        <p style="margin:0 0 10px;color:#c0d0df;line-height:1.7;">
            This code expires in {{ $expiresInMinutes }} minutes.
        </p>
        <p style="margin:0;color:#8ea4ba;line-height:1.7;">
            If you did not create this account, you can safely ignore this email.
        </p>
    </div>
</body>
</html>
