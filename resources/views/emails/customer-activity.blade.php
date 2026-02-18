{{-- resources/views/emails/customer-activity.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject }}</title>
    <style>
        /* Reset & Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f5f5f5;
            padding: 20px 0;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Container */
        .email-wrapper {
            max-width: 680px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        /* Header */
        .email-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            padding: 30px 40px;
            color: #ffffff;
        }
        
        .email-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #ffffff;
        }
        
        .email-header p {
            font-size: 14px;
            color: #ecf0f1;
            margin: 0;
            opacity: 0.95;
        }
        
        /* Meta Information */
        .email-meta {
            background-color: #f8f9fa;
            padding: 20px 40px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .meta-item {
            display: inline-block;
            margin-right: 25px;
            margin-bottom: 8px;
        }
        
        .meta-label {
            font-size: 12px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 4px;
        }
        
        .meta-value {
            font-size: 14px;
            color: #212529;
            font-weight: 500;
        }
        
        /* Body Content */
        .email-body {
            padding: 40px 40px 30px 40px;
        }
        
        .greeting {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .content {
            font-size: 15px;
            line-height: 1.8;
            color: #495057;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .content p {
            margin-bottom: 12px;
        }
        
        /* Attachments Section */
        .attachments-section {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #3498db;
        }
        
        .attachments-title {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .attachments-title svg {
            width: 18px;
            height: 18px;
            margin-right: 8px;
        }
        
        .attachments-info {
            font-size: 13px;
            color: #6c757d;
        }
        
        /* Info Box */
        .info-box {
            margin-top: 30px;
            padding: 16px 20px;
            background-color: #e7f3ff;
            border-left: 4px solid #0066cc;
            border-radius: 4px;
        }
        
        .info-box p {
            font-size: 13px;
            color: #004085;
            margin: 0;
            line-height: 1.6;
        }
        
        .info-box p + p {
            margin-top: 6px;
        }
        
        .info-icon {
            display: inline-block;
            width: 16px;
            height: 16px;
            background-color: #0066cc;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 16px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 6px;
        }
        
        /* Signature */
        .signature {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .signature-text {
            font-size: 14px;
            color: #495057;
            line-height: 1.6;
        }
        
        .signature-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Footer */
        .email-footer {
            background-color: #2c3e50;
            padding: 25px 40px;
            color: #ecf0f1;
        }
        
        .footer-company {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #ffffff;
        }
        
        .footer-text {
            font-size: 13px;
            line-height: 1.6;
            color: #bdc3c7;
            margin: 0;
        }
        
        .footer-text + .footer-text {
            margin-top: 6px;
        }
        
        .footer-divider {
            height: 1px;
            background-color: #34495e;
            margin: 15px 0;
        }
        
        .copyright {
            font-size: 12px;
            color: #95a5a6;
            text-align: center;
            margin-top: 12px;
        }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-wrapper {
                margin: 0;
                border-radius: 0;
            }
            
            .email-header,
            .email-meta,
            .email-body,
            .email-footer {
                padding-left: 20px;
                padding-right: 20px;
            }
            
            .email-header h1 {
                font-size: 20px;
            }
            
            .meta-item {
                display: block;
                margin-right: 0;
                margin-bottom: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <!-- Header -->
        <div class="email-header">
            <h1>{{ $subject }}</h1>
            <p>Komunikasi Resmi dari Shelter</p>
        </div>
        
        <!-- Meta Information -->
        <div class="email-meta">
            <div class="meta-item">
                <span class="meta-label">Pengirim</span>
                <span class="meta-value">{{ $fromName }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Tanggal</span>
                <span class="meta-value">{{ \Carbon\Carbon::now()->locale('id')->isoFormat('dddd, D MMMM YYYY') }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Waktu</span>
                <span class="meta-value">{{ \Carbon\Carbon::now()->format('H:i') }} WIB</span>
            </div>
        </div>
        
        <!-- Body Content -->
        <div class="email-body">
            <div class="greeting">
                Kepada Yth. Bapak/Ibu,
            </div>
            
            <div class="content">
                {!! nl2br(e($body)) !!}
            </div>
            
            @if(isset($hasAttachments) && $hasAttachments)
            <!-- Attachments Info -->
            <div class="attachments-section">
                <div class="attachments-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                    </svg>
                    Lampiran Dokumen
                </div>
                <p class="attachments-info">
                    Email ini berisi {{ $attachmentsCount }} file lampiran. Mohon periksa file terlampir untuk informasi lebih detail.
                </p>
            </div>
            @endif
            
            <!-- Info Box -->
            <div class="info-box">
                <p><span class="info-icon">i</span> <strong>Catatan Penting:</strong></p>
                <p>Email ini dikirim secara otomatis melalui sistem Cais Shelter. Jika Anda memiliki pertanyaan atau memerlukan klarifikasi lebih lanjut, silakan hubungi kami melalui kontak yang tertera di bawah ini.</p>
            </div>
            
            <!-- Signature -->
            <div class="signature">
                <div class="signature-text">
                    <p>Hormat kami,</p>
                    <p class="signature-name">{{ $fromName }}</p>
                    <p>{{ $fromAddress }}</p>
                    <p>Cais Shelter System</p>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="email-footer">
            <div class="footer-company">Cais Shelter</div>
            <p class="footer-text">
                Sistem Manajemen Hubungan Pelanggan Terpadu
            </p>
            <p class="footer-text">
                Email ini bersifat rahasia dan ditujukan hanya untuk penerima yang dituju. Jika Anda bukan penerima yang dituju, mohon untuk tidak membaca, menyalin, atau mendistribusikan email ini.
            </p>
            
            <div class="footer-divider"></div>
            
            <div class="copyright">
                &copy; {{ date('Y') }} Cais Shelter. All rights reserved.
            </div>
        </div>
    </div>
</body>
</html>