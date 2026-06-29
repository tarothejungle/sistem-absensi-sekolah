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
        @foreach($attendances as $attendance)
            <tr>
                <td>{{ \Carbon\Carbon::parse($attendance->tanggal)->format('d/m/Y') }}</td>
                <td>{{ $attendance->teacher->user->nip ?? '-' }}</td>
                <td>{{ $attendance->teacher->nama_lengkap ?? '-' }}</td>
                <td>{{ $attendance->check_in_time ? \Carbon\Carbon::parse($attendance->check_in_time)->timezone('Asia/Jakarta')->format('H:i') : '-' }}</td>
                <td>{{ $attendance->check_out_time ? \Carbon\Carbon::parse($attendance->check_out_time)->timezone('Asia/Jakarta')->format('H:i') : '-' }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $attendance->status_kehadiran)) }}</td>
                <td>{{ $attendance->keterlambatan_menit ?? 0 }} menit</td>
            </tr>
        @endforeach
    </tbody>
</table>