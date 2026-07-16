<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekap Guru Infal</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111;
        }

        h2, p {
            text-align: center;
            margin: 0;
        }

        .subtitle {
            margin-top: 6px;
            margin-bottom: 20px;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            border: 1px solid #333;
            padding: 7px;
            vertical-align: top;
        }

        th {
            background: #eeeeee;
            font-weight: bold;
        }

        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>

    <h2>Rekap Guru Infal / Pengganti</h2>

    <p class="subtitle">
        @if($tanggal_mulai && $tanggal_selesai)
            Periode {{ \Carbon\Carbon::parse($tanggal_mulai)->format('d/m/Y') }}
            s/d
            {{ \Carbon\Carbon::parse($tanggal_selesai)->format('d/m/Y') }}
        @else
            Semua Periode
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Guru Utama</th>
                <th>Guru Infal/Pengganti</th>
                <th>Jenis</th>
                <th>Alasan</th>
                <th>Status Izin</th>
                <th>Status Infal</th>
            </tr>
        </thead>

        <tbody>
            @forelse($items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        {{ $item->tanggalLabel() }}
                    </td>
                    <td>{{ $item->teacher->nama_lengkap ?? '-' }}</td>
                    <td>{{ $item->infalTeacher->nama_lengkap ?? '-' }}</td>
                    <td>{{ ucfirst($item->jenis_pengajuan) }}</td>
                    <td>{{ $item->alasan }}</td>
                    <td>Disetujui</td>
                    <td>Disetujui</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">
                        Belum ada data guru infal/pengganti.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
