@extends('layouts.app')

@section('content')
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

    <div class="ui-page-action-row">
        <a href="{{ route('payroll.settings') }}" class="btn btn-primary">
            <i class="bi bi-gear-fill me-1"></i> Pengaturan Gaji Guru
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('payroll.generate') }}">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Bulan</label>
                        <select name="bulan" class="form-select" required>
                            @foreach($monthNames as $num => $name)
                                <option value="{{ $num }}" {{ (int) $selectedMonth === (int) $num ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Tahun</label>
                        <input type="number" name="tahun" class="form-control" value="{{ $selectedYear }}" min="2020" max="2100" required>
                    </div>
                    <div class="col-md-3 d-flex justify-content-md-end gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-calculator-fill me-1"></i> Generate / Hitung Ulang
                        </button>
                    </div>
                </div>
            </form>
            <small class="text-muted d-block mt-2">
                Sistem menghitung potongan dari pengajuan izin/cuti/sakit yang sudah disetujui kepala sekolah dan guru infal. Tugas luar tidak ikut dipotong.
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
