<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eskalasi Keterlambatan Approval</title>
    <style>
        body {
            font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #135080 30%, #000862 70%);
            color: #ffffff;
            padding: 28px 20px;
            text-align: center;
            margin: 0;
            font-size: 20px;
            letter-spacing: 0.5px;
        }

        .header-icon {
            font-size: 36px;
            margin-bottom: 8px;
        }


        .header p {
            margin: 6px 0 0;
            font-size: 13px;
            opacity: 0.85;
        }

        .content {
            padding: 30px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .info-table td {
            padding: 8px 0;
            vertical-align: top;
        }

        .label {
            width: 150px;
            color: #7f8c8d;
            font-weight: 600;
        }

        .value {
            color: #2c3e50;
            font-weight: 700;
        }

        .alert-box {
            background-color: #fff9e6;
            border-left: 4px solid #f1c40f;
            padding: 15px;
            margin-bottom: 20px;
            font-style: italic;
        }

        .footer {
            background-color: #f4f7f6;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #95a5a6;
            border-top: 1px solid #eeeeee;
        }

        .button {
            display: inline-block;
            padding: 12px 25px;
            background-color: #3498db;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2 style="margin:0;">Laporan Eskalasi Approval</h2>
        </div>

        <div class="content">
            <p>Kepada Yth,<br>
                <strong>Bapak Direktur Utama<br>
                    Bapak Hari Wahyudi</strong>
            </p>

            <div class="alert-box">
                Terkait dengan batas waktu (lead time) Approval pada sistem CAIS, bersama ini kami melaporkan adanya
                keterlambatan proses pada dokumen berikut:
            </div>

            <table class="info-table">
                <tr>
                    <td class="label">No. Quotation</td>
                    <td class="value">: {{ $data['quotation_number'] }}</td>
                </tr>
                <tr>
                    <td class="label">Pembuat</td>
                    <td class="value">: {{ $data['creator_name'] }}</td>
                </tr>
                <tr>
                    <td class="label">Perusahaan</td>
                    <td class="value">: {{ $data['company_name'] }}</td>
                </tr>
            </table>

            <p style="margin-top: 30px; font-weight: 600; border-bottom: 1px solid #eee; padding-bottom: 5px;">Detail
                Keterlambatan:</p>

            <table class="info-table">
                <tr>
                    <td class="label">Approver</td>
                    <td class="value" style="color: #e74c3c;">: {{ $data['approver_label'] }}</td>
                </tr>
                <tr>
                    <td class="label">Waktu Masuk</td>
                    <td class="value">: {{ $data['entry_date'] }} | {{ $data['entry_time'] }} WIB</td>
                </tr>
                <tr>
                    <td class="label">Durasi Tunggu</td>
                    <td class="value">: lebih dari 24 Jam</td>
                </tr>
            </table>

            <p>Demi kelancaran operasional, dimohon kebijaksanaan Bapak untuk meninjau atau melakukan eskalasi lebih
                lanjut terkait dokumen tersebut.</p>
            <div style="text-align: center; margin-top: 20px;">
                <a href="https://caisshelter.pages.dev/quotation/view/{{ $data['quotation_id'] }}" class="button">Tinjau
                    Dokumen di CAIS</a>
            </div>
            <p style="margin-top: 30px;">Terima kasih.</p>
        </div>

        <div class="footer">
            <p><strong>CAIS System Automated Notification</strong><br>
                Email ini dikirim secara otomatis oleh sistem, mohon tidak membalas email ini.</p>
        </div>
    </div>
</body>

</html>