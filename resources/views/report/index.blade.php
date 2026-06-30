@extends('layouts.app')

@section('content')

<style>
    .report-action-buttons {
        align-items: center;
        flex-wrap: wrap;
    }

    .btn-report-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-width: 92px;
        height: 40px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 12px;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
        transition: all 0.2s ease;
    }

    .btn-report-action i {
        font-size: 14px;
    }

    .btn-report-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 5px 12px rgba(0, 0, 0, 0.13);
    }

    @media (max-width: 768px) {
        .report-action-buttons {
            width: 100%;
            justify-content: flex-start;
            margin-bottom: 14px;
        }

        .btn-report-action {
            min-width: 100px;
            height: 42px;
        }

        #attendanceStatusChart {
            min-height: 250px;
        }
    }
</style>

<form action="{{ route('reports.index') }}" method="GET">
    <div class="ui-page-hero">
        <div>
            <h3>Laporan Absensi</h3>
            <p>Filter dan cetak rekap kehadiran guru sesuai periode.</p>
        </div>
    </div>

    <div class="ui-page-action-row report-action-buttons">
        <button type="submit" class="btn btn-primary btn-report-action">
            <i class="bi bi-funnel-fill"></i>
            <span>Filter</span>
        </button>

        <a 
            href="{{ route('reports.export.excel', request()->query()) }}" 
            class="btn btn-success btn-report-action"
        >
            <i class="bi bi-file-earmark-excel-fill"></i>
            <span>Export Excel</span>
        </a>

        <a 
            href="{{ route('reports.export.pdf', request()->query()) }}" 
            class="btn btn-danger btn-report-action"
            target="_blank"
        >
            <i class="bi bi-file-earmark-pdf-fill"></i>
            <span>Export PDF</span>
        </a>
    </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Mulai</label>
                        <input 
                            type="date" 
                            name="start_date" 
                            class="form-control" 
                            value="{{ request('start_date') }}"
                        >
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Selesai</label>
                        <input 
                            type="date" 
                            name="end_date" 
                            class="form-control" 
                            value="{{ request('end_date') }}"
                        >
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="">Semua</option>
                            <option value="hadir" {{ request('status') === 'hadir' ? 'selected' : '' }}>Hadir</option>
                            <option value="terlambat" {{ request('status') === 'terlambat' ? 'selected' : '' }}>Terlambat</option>
                            <option value="hadir_tidak_lengkap" {{ request('status') === 'hadir_tidak_lengkap' ? 'selected' : '' }}>Hadir Tidak Lengkap</option>
                            <option value="izin" {{ request('status') === 'izin' ? 'selected' : '' }}>Izin</option>
                            <option value="sakit" {{ request('status') === 'sakit' ? 'selected' : '' }}>Sakit</option>
                            <option value="cuti" {{ request('status') === 'cuti' ? 'selected' : '' }}>Cuti</option>
                            <option value="alfa" {{ request('status') === 'alfa' ? 'selected' : '' }}>Alfa</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="card mb-3">
        <div class="card-header">
            Grafik Status Absensi
        </div>

        <div class="card-body">
            @if(array_sum($chartData) > 0)
                <div style="height: 280px;">
                    <canvas id="attendanceStatusChart"></canvas>
                </div>
            @else
                <div class="text-center text-muted py-5">
                    Belum ada data absensi untuk ditampilkan pada grafik.
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            Tabel Laporan Absensi
        </div>

        <div class="card-body">
            <div class="table-responsive-mobile">
            <table class="table table-bordered table-striped">
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
                            <td>
                                {{ \Carbon\Carbon::parse($attendance->tanggal)->format('d/m/Y') }}
                            </td>
                            <td>
                                {{ $attendance->teacher->user->nip ?? '-' }}
                            </td>
                            <td>
                                {{ $attendance->teacher->nama_lengkap ?? '-' }}
                            </td>
                            <td>
                                {{ $attendance->check_in_time ? \Carbon\Carbon::parse($attendance->check_in_time)->timezone('Asia/Jakarta')->format('H:i') : '-' }}
                            </td>
                            <td>
                                {{ $attendance->check_out_time ? \Carbon\Carbon::parse($attendance->check_out_time)->timezone('Asia/Jakarta')->format('H:i') : '-' }}
                            </td>
                            <td>
                                @if($attendance->status_kehadiran === 'hadir')
                                    <span class="badge bg-success">Hadir</span>
                                @elseif($attendance->status_kehadiran === 'terlambat')
                                    <span class="badge bg-warning text-dark">Terlambat</span>
                                @elseif($attendance->status_kehadiran === 'hadir_tidak_lengkap')
                                    <span class="badge bg-secondary">Hadir Tidak Lengkap</span>
                                @elseif($attendance->status_kehadiran === 'izin')
                                    <span class="badge bg-info text-dark">Izin</span>
                                @elseif($attendance->status_kehadiran === 'sakit')
                                    <span class="badge bg-primary">Sakit</span>
                                @elseif($attendance->status_kehadiran === 'cuti')
                                    <span class="badge bg-secondary">Cuti</span>
                                <!-- @elseif($attendance->status_kehadiran === 'tugas_luar')
                                    <span class="badge bg-dark">Tugas Luar</span> -->
                                @elseif($attendance->status_kehadiran === 'alfa')
                                    <span class="badge bg-danger">Alfa</span>
                                @else
                                    <span class="badge bg-secondary">
                                        {{ ucfirst(str_replace('_', ' ', $attendance->status_kehadiran)) }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                {{ $attendance->keterlambatan_menit ?? 0 }} menit
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                Belum ada data laporan absensi.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const chartLabels = @json($chartLabels);
    const chartData = @json($chartData);

    const chartElement = document.getElementById('attendanceStatusChart');

    if (chartElement) {
        new Chart(chartElement, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Jumlah Absensi',
                    data: chartData,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw + ' data';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
</script>
@endsection
