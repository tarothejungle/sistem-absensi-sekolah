<table>
    <thead>
        <tr>
            <th>Username</th>
            <th>Nama Guru</th>
            <th>Email</th>
            <th>Jabatan</th>
            <th>Mata Pelajaran</th>
            <th>Sesi Absensi</th>
            <th>Keterangan Password</th>
        </tr>
    </thead>
    <tbody>
        @foreach($teachers as $teacher)
            <tr>
                <td>{{ $teacher->user->nip ?? '-' }}</td>
                <td>{{ $teacher->nama_lengkap ?? '-' }}</td>
                <td>{{ $teacher->user->email ?? $teacher->email ?? '-' }}</td>
                <td>{{ $teacher->jabatan ?? '-' }}</td>
                <td>{{ $teacher->mata_pelajaran ?? '-' }}</td>
                <td>
                    @if($teacher->attendanceSessions && $teacher->attendanceSessions->count() > 0)
                        @foreach($teacher->attendanceSessions as $session)
                            {{ $session->nama_sesi }} 
                            ({{ substr($session->jam_masuk, 0, 5) }} - {{ substr($session->jam_pulang, 0, 5) }})
                            @if(!$loop->last), @endif
                        @endforeach
                    @else
                        Belum diatur
                    @endif
                </td>
                <td>Password tidak ditampilkan. Gunakan fitur lupa password atau ubah password lewat admin/profil.</td>
            </tr>
        @endforeach
    </tbody>
</table>
