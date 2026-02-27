{{-- resources/views/emails/quotation-approval.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Persetujuan Quotation</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; background:#eef2f7; color:#333; padding:32px 16px; }
        .wrapper { max-width:640px; margin:0 auto; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,.10); }
        .header { background:linear-gradient(135deg,#1a2e4a 0%,#2563eb 100%); padding:36px 40px 28px; }
        .header-logo { font-size:12px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#93c5fd; margin-bottom:14px; }
        .header h1 { font-size:22px; font-weight:700; color:#fff; margin:0 0 8px; }
        .header-sub { font-size:13px; color:#bfdbfe; }
        .badge-warning { background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
        .badge-info { background:#dbeafe; color:#1e40af; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
        .alert { background:#fffbeb; border-left:4px solid #f59e0b; padding:14px 40px; font-size:13px; color:#78350f; }
        .body { padding:32px 40px; }
        .greeting { font-size:15px; color:#1e293b; margin-bottom:16px; }
        .intro { font-size:14px; color:#475569; line-height:1.7; margin-bottom:28px; }
        .detail-card { width:100%; border:1px solid #e2e8f0; border-radius:8px; border-collapse:collapse; margin-bottom:28px; }
        .detail-card thead th { background:#f1f5f9; padding:12px 20px; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:#64748b; text-align:left; border-bottom:1px solid #e2e8f0; }
        .detail-card td { padding:12px 20px; border-bottom:1px solid #e2e8f0; }
        .detail-card tr:last-child td { border-bottom:none; }
        .label { font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; width:160px; }
        .value { font-size:14px; color:#1e293b; font-weight:500; }
        .cta { text-align:center; margin-bottom:28px; }
        .cta a { display:inline-block; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; text-decoration:none; font-size:15px; font-weight:700; padding:14px 36px; border-radius:8px; box-shadow:0 4px 12px rgba(37,99,235,.35); }
        .info-box { background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:14px 18px; font-size:13px; color:#1e40af; line-height:1.7; }
        .footer { background:#1e293b; padding:28px 40px; }
        .footer-brand { font-size:15px; font-weight:700; color:#f8fafc; margin-bottom:4px; }
        .footer-tag { font-size:12px; color:#64748b; margin-bottom:16px; }
        .footer-contacts { display:flex;justify-content: space-between; margin-bottom:16px; }
        .contact-label { display:block; font-size:10px; text-transform:uppercase; letter-spacing:.5px; color:#475569; margin-bottom:2px; }
        .contact-val { font-size:12px; color:#94a3b8; }
        .footer-hr { height:1px; background:#334155; margin:14px 0; }
        .footer-legal { font-size:11px; color:#475569; line-height:1.6; }
        .copyright { font-size:11px; color:#334155; text-align:center; margin-top:12px; }
        @media(max-width:600px) {
            .wrapper { border-radius:0; }
            .header, .alert, .body, .footer { padding-left:20px; padding-right:20px; }
            .footer-contacts { flex-direction:column; gap:20px; }
        }
    </style>
</head>
<body>
<div class="wrapper">

    <div class="header">
        <div class="header-logo">Cais Shelter System</div>
        <h1>Persetujuan Quotation Diperlukan</h1>
        <div class="header-sub">
            {{ $sentAt->locale('id')->isoFormat('D MMMM YYYY, HH:mm') }} WIB
            &nbsp;·&nbsp;
            <span class="badge-warning">{{ $approvalStage }}</span>
        </div>
    </div>

    <div class="alert">
        <strong>Tindakan diperlukan:</strong> Quotation ini menunggu persetujuan Anda.
    </div>

    <div class="body">
        <p class="greeting">
            Yth. <strong>{{ $recipientName }}</strong>
            &nbsp;<span class="badge-info">{{ $recipientRole }}</span>
        </p>
        <p class="intro">
            Terdapat quotation yang telah selesai dibuat dan memerlukan persetujuan dari Anda sebelum dapat diproses lebih lanjut.
        </p>

        <table class="detail-card">
            <thead>
                <tr><th colspan="2">Detail Quotation</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td class="label">Nomor Quotation</td>
                    <td class="value"><strong>{{ $quotationNumber }}</strong></td>
                </tr>
                <tr>
                    <td class="label">Dibuat Oleh</td>
                    <td class="value">{{ $creatorName }}</td>
                </tr>
                <tr>
                    <td class="label">Tahap</td>
                    <td class="value"><span class="badge-warning">{{ $approvalStage }}</span></td>
                </tr>
                @if($top)
                <tr>
                    <td class="label">Terms of Payment</td>
                    <td class="value">{{ $top }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label">Tanggal</td>
                    <td class="value">{{ $sentAt->locale('id')->isoFormat('dddd, D MMMM YYYY') }}</td>
                </tr>
            </tbody>
        </table>

        <div class="cta">
            <a href="{{ $approvalUrl }}"> &nbsp; Tinjau &amp; Setujui Quotation</a>
        </div>

        <div class="info-box">
             &nbsp;Email ini dikirim otomatis oleh <strong>Cais Shelter System</strong>. Harap tidak membalas email ini secara langsung.
        </div>
    </div>

    <div class="footer">
        <div class="footer-brand">Cais Shelter</div>
        <div class="footer-tag">Sistem Manajemen Hubungan Pelanggan Terpadu</div>
        <div class="footer-contacts">
            <div>
                <span class="contact-label">Direktur Sales</span>
                <span class="contact-label">MUHAMMAD NINO MAYVI DIAN</span>
                <span class="contact-val">nino@shelterindonesia.id</span>
            </div>
            
            <div>
                <span class="contact-label">Direktur Keuangan</span>
                <span class="contact-label">ALIVIAN PRANATYAS HENING LAZUARDI</span>
                <span class="contact-val">alivian@shelterindonesia.id</span>
            </div>
        </div>
        <div class="footer-hr"></div>
        <div class="footer-legal">Email ini bersifat rahasia dan ditujukan hanya untuk penerima yang disebutkan di atas.</div>
        <div class="copyright">&copy; {{ date('Y') }} Cais Shelter. All rights reserved.</div>
    </div>

</div>
</body>
</html>