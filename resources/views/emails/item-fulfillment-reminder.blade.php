{{-- resources/views/emails/item-fulfillment-reminder.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengingat Pemenuhan Barang PKS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f5f5f5;
            padding: 20px 0;
        }
        .email-wrapper {
            max-width: 680px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .email-header {
            background: linear-gradient(135deg, #c0392b 0%, #e67e22 100%);
            padding: 30px 40px;
            color: #ffffff;
        }
        .email-header h1 { font-size: 22px; font-weight: 600; margin-bottom: 6px; color: #ffffff; }
        .email-header p { font-size: 14px; color: #f8ecec; margin: 0; }
        .email-body { padding: 30px 40px; }
        .meta { margin-bottom: 24px; }
        .meta-row { font-size: 14px; color: #495057; margin-bottom: 6px; }
        .meta-row strong { color: #2c3e50; }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }
        table.items th, table.items td {
            border: 1px solid #e0e0e0;
            padding: 10px 12px;
            text-align: left;
        }
        table.items th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        table.items td.num { text-align: center; }
        .warn-box {
            margin-top: 24px;
            padding: 16px 20px;
            background-color: #fff3cd;
            border-left: 4px solid #e67e22;
            border-radius: 4px;
            font-size: 14px;
            color: #7a4a00;
        }
        .email-footer {
            background-color: #2c3e50;
            padding: 20px 40px;
            color: #bdc3c7;
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <h1>Pengingat Pemenuhan Barang PKS</h1>
            <p>Masih terdapat barang yang belum terpenuhi pada kontrak yang sedang berjalan</p>
        </div>

        <div class="email-body">
            <div class="meta">
                <div class="meta-row"><strong>Perusahaan:</strong> {{ $pks->nama_perusahaan ?? optional($pks->leads)->nama_perusahaan ?? '-' }}</div>
                <div class="meta-row"><strong>Nomor PKS:</strong> {{ $pks->nomor ?? '-' }}</div>
                <div class="meta-row">
                    <strong>Periode Kontrak:</strong>
                    {{ optional($pks->kontrak_awal)->format('d-m-Y') ?? '-' }}
                    s/d
                    {{ optional($pks->kontrak_akhir)->format('d-m-Y') ?? '-' }}
                </div>
            </div>

            <p style="font-size: 14px; color: #495057; margin-bottom: 8px;">
                Berikut daftar barang yang masih belum terpenuhi:
            </p>

            <table class="items">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Barang</th>
                        <th>Site</th>
                        <th class="num">Diminta</th>
                        <th class="num">Terpenuhi</th>
                        <th class="num">Sisa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingItems as $i => $item)
                        <tr>
                            <td class="num">{{ $i + 1 }}</td>
                            <td>{{ $item['nama'] ?? '-' }}</td>
                            <td>{{ $item['site'] ?? '-' }}</td>
                            <td class="num">{{ $item['qty_diminta'] ?? 0 }}</td>
                            <td class="num">{{ $item['qty_terpenuhi'] ?? 0 }}</td>
                            <td class="num">{{ $item['remaining'] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="num">Tidak ada data.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="warn-box">
                Mohon segera lakukan pemenuhan barang di atas sebelum kontrak berakhir agar layanan kepada klien tetap sesuai perjanjian.
            </div>
        </div>

        <div class="email-footer">
            Email ini dikirim otomatis oleh sistem Cais Shelter. Mohon tidak membalas email ini.
            <br>&copy; {{ date('Y') }} Cais Shelter.
        </div>
    </div>
</body>
</html>
