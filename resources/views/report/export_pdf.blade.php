<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Absensi</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111;
        }

        h2 {
            text-align: center;
            margin-bottom: 4px;
        }

        .subtitle {
            text-align: center;
            margin-bottom: 20px;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #001362;
            color: white;
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }

        td {
            padding: 7px;
            border: 1px solid #ddd;
        }

        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>

    <h2>Laporan Absensi Guru</h2>

    <div class="subtitle">
        Sistem Absensi Guru MI Lantaburo
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Username</th>
                <th>Nama</th>
                <th>Masuk</th>
                <th>Pulang</th>
                <th>Status</th>
                <th>Terlambat</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendances as $attendance)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($attendance->tanggal)->format('d/m/Y') }}</td>
                    <td>{{ $attendance->teacher->user->nip ?? '-' }}</td>
                    <td>{{ $attendance->teacher->nama_lengkap ?? '-' }}</td>
                    <td>{{ $attendance->check_in_time ? \Carbon\Carbon::parse($attendance->check_in_time)->timezone('Asia/Jakarta')->format('H:i') : '-' }}</td>
                    <td>{{ $attendance->check_out_time ? \Carbon\Carbon::parse($attendance->check_out_time)->timezone('Asia/Jakarta')->format('H:i') : '-' }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $attendance->status_kehadiran)) }}</td>
                    <td>{{ $attendance->keterlambatan_menit ?? 0 }} menit</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">Belum ada data absensi.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>