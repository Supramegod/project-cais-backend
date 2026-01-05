{{-- resources/views/emails/customer-activity.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
        .content {
            white-space: pre-line;
            line-height: 1.8;
            font-size: 14px;
        }
        .info-box {
            background-color: #f9f9f9;
            border-left: 4px solid #4CAF50;
            padding: 10px 15px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>{{ $subject }}</h2>
            <p><strong>From:</strong> {{ $fromName }} &lt;{{ $fromAddress }}&gt;</p>
            <p><strong>Date:</strong> {{ now()->format('d M Y H:i') }}</p>
        </div>
        
        <div class="content">
            {!! nl2br(e($body)) !!}
        </div>
        
        <div class="info-box">
            <p><strong>Note:</strong> This email was sent from Shelter CRM System.</p>
            <p>Please do not reply to this email directly if you have any questions.</p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} Shelter CRM. All rights reserved.</p>
            <p>This is an automated message, please do not reply.</p>
        </div>
    </div>
</body>
</html>