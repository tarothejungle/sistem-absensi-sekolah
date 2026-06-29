@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <div>
            <h3>Detail Penggajian {{ $period->nama_periode }}</h3>
            <p>Rincian gaji pokok, potongan ketidakhadiran, tambahan guru infal, dan gaji bersih.</p>
        </div>
    </div>

    <div class="ui-page-action-row">
        <a href="{{ route('payroll.index') }}" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        <a href="{{ route('payroll.print', $period) }}" class="btn btn-danger" target="_blank"><i class="bi bi-file-earmark-pdf-fill me-1"></i> Cetak Rekap PDF</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive-mobile">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Guru</th>
                            <th>Jabatan</th>
                            <th>Gaji Pokok</th>
                            <th>Potongan</th>
                            <th>Tambahan Infal</th>
                            <th>Gaji Bersih</th>
                            <th>Ket.</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $item->teacher->nama_lengkap ?? '-' }}</div>
                                    <small class="text-muted">{{ $item->teacher->user->nip ?? '-' }}</small>
                                </td>
                                <td>{{ $item->teacher->jabatan ?? '-' }}</td>
                                <td>Rp {{ number_format($item->gaji_pokok, 0, ',', '.') }}</td>
                                <td class="text-danger fw-bold">Rp {{ number_format($item->potongan_absen, 0, ',', '.') }}</td>
                                <td class="text-success fw-bold">Rp {{ number_format($item->tambahan_infal, 0, ',', '.') }}</td>
                                <td class="fw-bold">Rp {{ number_format($item->gaji_bersih, 0, ',', '.') }}</td>
                                <td>
                                    <small>
                                        Tidak hadir: {{ $item->jumlah_absen_diganti }}x<br>
                                        Mengganti: {{ $item->jumlah_mengganti }}x
                                    </small>
                                </td>
                                <td>
                                    <a href="{{ route('payroll.slip', [$period, $item]) }}" class="btn btn-primary btn-sm" target="_blank">Slip</a>
                                </td>
                            </tr>
                            @if($item->details->count() > 0)
                                <tr>
                                    <td colspan="8" class="bg-light">
                                        <div class="fw-bold mb-2">Rincian transaksi:</div>
                                        <ul class="mb-0">
                                            @foreach($item->details as $detail)
                                                <li>
                                                    {{ $detail->tanggal_event?->format('d/m/Y') ?? '-' }} -
                                                    {{ $detail->tipe === 'potongan_absen' ? 'Potongan' : 'Tambahan' }}
                                                    Rp {{ number_format($detail->nominal, 0, ',', '.') }}:
                                                    {{ $detail->keterangan }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">Belum ada data penggajian.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="table-footer-row">
                @include('partials.per-page-selector', ['paginator' => $items])
                <div class="pagination-wrapper">{{ $items->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
