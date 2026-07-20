<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jadwal Visit PKS</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #333;">Jadwal Visit PKS</h2>

    <p>Halo <strong>{{ $pic->full_name ?? 'PIC' }}</strong>,</p>

    <p>Berikut jadwal visit untuk PKS:</p>

    <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
        <tr style="background: #f5f5f5;">
            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">No. PKS</th>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ $pks->nomor }}</td>
        </tr>
        <tr>
            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Customer</th>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ $pks->leads->nama_perusahaan ?? 'N/A' }}</td>
        </tr>
        <tr style="background: #f5f5f5;">
            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Periode Kontrak</th>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ $pks->kontrak_awal }} s/d {{ $pks->kontrak_akhir }}</td>
        </tr>
        <tr>
            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Total Jadwal</th>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ $schedules->count() }} jadwal</td>
        </tr>
    </table>

    <h3 style="color: #555;">Daftar Jadwal:</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 8px; border: 1px solid #ddd;">No</th>
                <th style="padding: 8px; border: 1px solid #ddd;">Tanggal</th>
                <th style="padding: 8px; border: 1px solid #ddd;">Site</th>
                <th style="padding: 8px; border: 1px solid #ddd;">Role</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($schedules as $index => $schedule)
            <tr>
                <td style="padding: 8px; border: 1px solid #ddd;">{{ $index + 1 }}</td>
                <td style="padding: 8px; border: 1px solid #ddd;">{{ \Carbon\Carbon::parse($schedule->tgl_jadwal)->format('d M Y') }}</td>
                <td style="padding: 8px; border: 1px solid #ddd;">{{ $schedule->site->nama_site ?? 'N/A' }}</td>
                <td style="padding: 8px; border: 1px solid #ddd;">{{ ucfirst($schedule->role) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top: 20px; color: #777; font-size: 12px;">
        Email ini dikirim otomatis oleh sistem CAIS CRM. Mohon tidak membalas email ini.
    </p>
</body>
</html>
