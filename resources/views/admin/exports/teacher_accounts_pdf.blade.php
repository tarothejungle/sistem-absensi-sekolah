<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Akun Login Guru</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111;
        }

        h2 {
            text-align: center;
            margin-bottom: 4px;
        }

        .subtitle {
            text-align: center;
            margin-bottom: 18px;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #001362;
            color: white;
            padding: 7px;
            border: 1px solid #ddd;
            text-align: left;
        }

        td {
            padding: 6px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        .note {
            margin-top: 14px;
            font-size: 10px;
        }
    </style>
</head>
<body>

    <h2>Data Akun Login Guru</h2>
    <div class="subtitle">
        Sistem Absensi Sekolah MI Lantaburo
    </div>

    <table>
        <thead>
            <tr>
                <th width="13%">Username</th>
                <th width="19%">Nama Guru</th>
                <th width="19%">Email</th>
                <!-- <th width="13%">Jabatan</th>
                <th width="15%">Mapel</th> -->
                <th width="16%">Sesi</th>
                <th width="15%">Keterangan Password</th>
            </tr>
        </thead>
        <tbody>
            @foreach($teachers as $teacher)
                <tr>
                    <td>{{ $teacher->user->nip ?? '-' }}</td>
                    <td>{{ $teacher->nama_lengkap ?? '-' }}</td>
                    <td>{{ $teacher->user->email ?? $teacher->email ?? '-' }}</td>
                    <!-- <td>{{ $teacher->jabatan ?? '-' }}</td>
                    <td>{{ $teacher->mata_pelajaran ?? '-' }}</td> -->
                    <td>
                        @if($teacher->attendanceSessions && $teacher->attendanceSessions->count() > 0)
                            @foreach($teacher->attendanceSessions as $session)
                                {{ $session->nama_sesi }}
                                <br>
                                {{ substr($session->jam_masuk, 0, 5) }} - {{ substr($session->jam_pulang, 0, 5) }}
                                @if(!$loop->last)
                                    <br><br>
                                @endif
                            @endforeach
                        @else
                            Belum diatur
                        @endif
                    </td>
                    <td>
                        Tidak ditampilkan
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="note">
        Catatan: Demi keamanan, password asli tidak dapat ditampilkan karena tersimpan dalam bentuk hash/enkripsi.
        Jika guru lupa password, gunakan fitur lupa password atau ubah password melalui menu profil masing-masing.
    </div>

</body>
</html>
