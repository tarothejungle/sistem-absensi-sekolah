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
        @if($period->status === 'draft')
            <form class="payroll-finalize-form" method="POST" action="{{ route('payroll.finalize', $period) }}" data-confirm-action="true" data-confirm-type="warning" data-confirm-title="Finalisasi periode payroll?" data-confirm-message="Setelah final, periode tidak dapat digenerate ulang kecuali dibuka kembali oleh super admin." data-confirm-submit="Finalisasi">
                @csrf
                <button type="submit" class="btn btn-success"><i class="bi bi-lock-fill me-1"></i> Finalisasi</button>
            </form>
        @endif
    </div>

    <div class="alert alert-info mt-3">
        Status periode: <strong>{{ ucfirst($period->status) }}</strong>.
        @if($period->finalized_at)
            Difinalisasi {{ $period->finalized_at->format('d/m/Y H:i') }} oleh {{ $period->finalizer->name ?? '-' }}.
        @endif
    </div>

    @if($period->status === 'final' && auth()->user()->role === 'super_admin')
        <form method="POST" action="{{ route('payroll.reopen', $period) }}" class="row g-2 align-items-end mb-3">
            @csrf
            <div class="col-md-9">
                <label for="payroll-reopen-reason" class="form-label">Alasan membuka kembali</label>
                <input id="payroll-reopen-reason" type="text" name="reason" class="form-control" minlength="5" maxlength="1000" required>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-warning w-100"><i class="bi bi-unlock-fill me-1"></i> Buka Kembali</button>
            </div>
        </form>
    @endif

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
                                    <small class="text-muted">{{ $item->teacher->user->username ?? '-' }}</small>
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
                <div class="pagination-wrapper">{{ $items->links() }}</div>
            </div>
        </div>
    </div>

    @if($period->audits->isNotEmpty())
        <div class="mt-4">
            <h5 class="fw-bold">Riwayat Audit Payroll</h5>
            <div class="table-responsive-mobile">
                <table class="table table-bordered align-middle">
                    <thead><tr><th>Waktu</th><th>Aksi</th><th>Pengguna</th><th>Alasan</th></tr></thead>
                    <tbody>
                        @foreach($period->audits as $audit)
                            <tr>
                                <td>{{ $audit->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ ucfirst($audit->action) }}</td>
                                <td>{{ $audit->actor->name ?? '-' }}</td>
                                <td>{{ $audit->reason ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
