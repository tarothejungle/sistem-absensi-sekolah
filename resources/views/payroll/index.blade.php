@extends('layouts.app')

@section('content')
<style>
    .payroll-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        align-items: end;
    }
    .payroll-form-col {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .payroll-form-col .btn {
        white-space: nowrap;
    }
    @media (max-width: 768px) {
        .payroll-form-col:nth-child(1) { order: 1; }
        .payroll-form-col:nth-child(2) { order: 2; }
        .payroll-form-col:nth-child(3) { order: 3; grid-column: 1 / -1; }
        .payroll-form-col:nth-child(4) { order: 4; grid-column: 1 / -1; }
    }
</style>

@php
    $summary = $summaryPeriod?->items;
    $totalGajiPokok = $summary ? $summary->sum('gaji_pokok') : 0;
    $totalPotongan = $summary ? $summary->sum('potongan_absen') : 0;
    $totalTambahan = $summary ? $summary->sum('tambahan_infal') : 0;
    $totalBersih = $summary ? $summary->sum('gaji_bersih') : 0;
@endphp

<div class="container-fluid">
    <div class="ui-page-hero">
        <div>
            <h3>Penggajian</h3>
            <p>Hitung gaji guru otomatis berdasarkan gaji pokok, potongan ketidakhadiran, dan tambahan guru infal.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3 ui-filter-card">
        <div class="card-body">
            <form
                method="POST"
                action="{{ route('payroll.generate') }}"
                data-confirm-action="true"
                data-confirm-type="warning"
                data-confirm-icon="bi-calculator"
                data-confirm-title="Hitung ulang penggajian?"
                data-confirm-message="Periode yang sama akan dibuat ulang berdasarkan data izin/cuti dan pengaturan gaji terbaru."
                data-confirm-submit="Hitung Ulang"
            >
                @csrf
                <div class="payroll-form-grid">
                    <div class="payroll-form-col">
                        <label class="form-label fw-semibold mb-0">Bulan</label>
                        <select name="bulan" class="form-select" required>
                            @foreach($monthNames as $num => $name)
                                <option value="{{ $num }}" {{ (int) $selectedMonth === (int) $num ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="payroll-form-col">
                        <label class="form-label fw-semibold mb-0">Tahun</label>
                        <input type="number" name="tahun" class="form-control" value="{{ $selectedYear }}" min="2020" max="2100" required>
                    </div>
                    <div class="payroll-form-col">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-calculator-fill me-1"></i> Generate Gaji
                        </button>
                    </div>
                    <div class="payroll-form-col">
                        <a href="{{ route('payroll.settings') }}" class="btn btn-primary">
                            <i class="bi bi-gear-fill me-1"></i> Pengaturan Gaji Guru
                        </a>
                    </div>
                </div>
            </form>
            <small class="text-muted d-block mt-2">
                Sistem menghitung potongan dari pengajuan izin/cuti/sakit yang sudah disetujui dan dari status alfa/tidak hadir. Potongan karena guru infal masuk ke tambahan guru pengganti, sedangkan potongan alfa masuk kas sekolah. Tugas luar tidak ikut dipotong.
            </small>
        </div>
    </div>

    @if($summaryPeriod)
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted fw-semibold">Gaji Pokok</div><h4>Rp {{ number_format($totalGajiPokok, 0, ',', '.') }}</h4></div></div></div>
            <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted fw-semibold">Potongan</div><h4 class="text-danger">Rp {{ number_format($totalPotongan, 0, ',', '.') }}</h4></div></div></div>
            <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted fw-semibold">Tambahan Infal</div><h4 class="text-success">Rp {{ number_format($totalTambahan, 0, ',', '.') }}</h4></div></div></div>
            <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted fw-semibold">Total Bersih</div><h4>Rp {{ number_format($totalBersih, 0, ',', '.') }}</h4></div></div></div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h5 class="mb-0 fw-bold">Riwayat Periode Penggajian</h5>
            </div>
            <div class="table-responsive-mobile">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Periode</th>
                            <th>Rentang Tanggal</th>
                            <th>Total Guru</th>
                            <th>Total Gaji Bersih</th>
                            <th>Dibuat Oleh</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($periods as $period)
                            <tr>
                                <td class="fw-bold">{{ $period->nama_periode }}</td>
                                <td>{{ $period->tanggal_mulai->format('d/m/Y') }} - {{ $period->tanggal_selesai->format('d/m/Y') }}</td>
                                <td>{{ $period->items()->count() }}</td>
                                <td>Rp {{ number_format($period->items()->sum('gaji_bersih'), 0, ',', '.') }}</td>
                                <td>{{ $period->generator->name ?? '-' }}</td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <a href="{{ route('payroll.show', $period) }}" class="btn btn-primary btn-sm">Detail</a>
                                        <a href="{{ route('payroll.print', $period) }}" class="btn btn-danger btn-sm" target="_blank">Cetak PDF</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="ui-empty-state">
                                        <i class="bi bi-cash-stack"></i>
                                        <div class="fw-bold">Belum ada periode penggajian.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $periods->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
@endsection
