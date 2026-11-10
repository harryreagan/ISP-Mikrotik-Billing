<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Code - {{ $appName }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #FAFAF8;
            font-family: 'Jost', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #111111;
        }
        .container {
            max-width: 540px;
            margin: 40px auto;
            background: #FFFFFF;
            border: 1px solid #E6E3F5;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(22, 27, 46, 0.06);
        }
        .header {
            background: linear-gradient(135deg, #161B2E 0%, #2B2170 100%);
            padding: 32px 24px;
            text-align: center;
            color: #FFFFFF;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .body {
            padding: 36px 32px;
            text-align: center;
        }
        .body p {
            font-size: 16px;
            line-height: 1.6;
            color: #666666;
            margin: 0 0 24px;
        }
        .code-box {
            display: inline-block;
            background: #F4F3FA;
            border: 2px dashed #6D3FE0;
            border-radius: 16px;
            padding: 16px 36px;
            font-size: 38px;
            font-weight: 800;
            letter-spacing: 10px;
            color: #161B2E;
            margin: 8px 0 24px;
        }
        .expiry-badge {
            display: inline-block;
            font-size: 13px;
            font-weight: 600;
            color: #F47B06;
            background: rgba(244, 123, 6, 0.1);
            padding: 6px 14px;
            border-radius: 999px;
            margin-bottom: 24px;
        }
        .footer {
            padding: 24px 32px;
            background: #FAFAF8;
            border-top: 1px solid #E6E3F5;
            text-align: center;
            font-size: 13px;
            color: #888888;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $appName }}</h1>
        </div>
        <div class="body">
            <p>Enter the 6-digit verification code below to verify your email address and activate your account:</p>
            <div class="code-box">
                {{ $code }}
            </div>
            <div>
                <span class="expiry-badge">⏱ Expires in 10 minutes</span>
            </div>
            <p style="font-size: 14px; color: #888888;">
                If you did not create an account or request this verification code, no further action is required; you can safely ignore this email.
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ $appName }}. All rights reserved.
        </div>
    </div>
</body>
</html>

