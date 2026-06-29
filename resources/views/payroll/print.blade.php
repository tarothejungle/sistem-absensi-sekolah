<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Penggajian {{ $period->nama_periode }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h2, p { text-align: center; margin: 0; }
        .subtitle { margin-top: 6px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #333; padding: 6px; vertical-align: top; }
        th { background: #eeeeee; }
        .right { text-align: right; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <h2>Rekap Penggajian Guru</h2>
    <p class="subtitle">Periode {{ $period->nama_periode }} ({{ $period->tanggal_mulai->format('d/m/Y') }} - {{ $period->tanggal_selesai->format('d/m/Y') }})</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Guru</th>
                <th>Jabatan</th>
                <th>Gaji Pokok</th>
                <th>Potongan</th>
                <th>Tambahan Infal</th>
                <th>Gaji Bersih</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($period->items->sortBy(fn($item) => $item->teacher->nama_lengkap ?? '') as $item)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $item->teacher->nama_lengkap ?? '-' }}</td>
                    <td>{{ $item->teacher->jabatan ?? '-' }}</td>
                    <td class="right">Rp {{ number_format($item->gaji_pokok, 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format($item->potongan_absen, 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format($item->tambahan_infal, 0, ',', '.') }}</td>
                    <td class="right"><strong>Rp {{ number_format($item->gaji_bersih, 0, ',', '.') }}</strong></td>
                    <td>Tidak hadir: {{ $item->jumlah_absen_diganti }}x; Mengganti: {{ $item->jumlah_mengganti }}x</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3" class="right">TOTAL</th>
                <th class="right">Rp {{ number_format($period->items->sum('gaji_pokok'), 0, ',', '.') }}</th>
                <th class="right">Rp {{ number_format($period->items->sum('potongan_absen'), 0, ',', '.') }}</th>
                <th class="right">Rp {{ number_format($period->items->sum('tambahan_infal'), 0, ',', '.') }}</th>
                <th class="right">Rp {{ number_format($period->items->sum('gaji_bersih'), 0, ',', '.') }}</th>
                <th></th>
            </tr>
        </tfoot>
    </table>
</body>
</html>
