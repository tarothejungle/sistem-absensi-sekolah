<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji {{ $item->teacher->nama_lengkap ?? '' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .box { border: 1px solid #333; padding: 18px; }
        h2, h3, p { text-align: center; margin: 0; }
        .subtitle { margin-top: 6px; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #333; padding: 8px; vertical-align: top; }
        th { background: #eeeeee; }
        .right { text-align: right; }
        .total { font-size: 14px; font-weight: bold; }
        .no-border td { border: none; padding: 4px; }
    </style>
</head>
<body>
<div class="box">
    <h2>Slip Gaji Guru</h2>
    <p class="subtitle">MI Lantaburo - Periode {{ $period->nama_periode }}</p>

    <table class="no-border">
        <tr><td width="150">Nama</td><td>: {{ $item->teacher->nama_lengkap ?? '-' }}</td></tr>
        <tr><td>Username/NIP</td><td>: {{ $item->teacher->user->nip ?? '-' }}</td></tr>
        <tr><td>Jabatan</td><td>: {{ $item->teacher->jabatan ?? '-' }}</td></tr>
    </table>

    <table>
        <tr><th>Komponen</th><th class="right">Nominal</th></tr>
        <tr><td>Gaji Pokok</td><td class="right">Rp {{ number_format($item->gaji_pokok, 0, ',', '.') }}</td></tr>
        <tr><td>Potongan Ketidakhadiran</td><td class="right">- Rp {{ number_format($item->potongan_absen, 0, ',', '.') }}</td></tr>
        <tr><td>Tambahan Guru Infal/Pengganti</td><td class="right">+ Rp {{ number_format($item->tambahan_infal, 0, ',', '.') }}</td></tr>
        <tr><td class="total">Gaji Bersih</td><td class="right total">Rp {{ number_format($item->gaji_bersih, 0, ',', '.') }}</td></tr>
    </table>

    @if($item->details->count() > 0)
        <h3 style="margin-top:18px; text-align:left;">Rincian</h3>
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jenis</th>
                    <th>Keterangan</th>
                    <th class="right">Nominal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($item->details as $detail)
                    <tr>
                        <td>{{ $detail->tanggal_event?->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ $detail->tipe === 'potongan_absen' ? 'Potongan' : 'Tambahan' }}</td>
                        <td>{{ $detail->keterangan }}</td>
                        <td class="right">Rp {{ number_format($detail->nominal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
</body>
</html>
