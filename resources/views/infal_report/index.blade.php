@extends('layouts.app')

@section('content')
<style>
    .infal-report-actions {
        align-items: stretch;
    }

    .infal-report-action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .infal-report-filter-button {
        min-width: 120px;
    }

    .infal-report-reset {
        width: 100%;
    }

    @media (max-width: 768px) {
        .infal-report-actions {
            display: block !important;
            padding: 12px !important;
            overflow: hidden;
        }

        .infal-report-action-buttons {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            width: 100% !important;
            gap: 10px !important;
            align-items: stretch !important;
        }

        .infal-report-action-buttons > * {
            min-width: 0 !important;
            width: 100% !important;
        }

        .infal-report-action-buttons .btn,
        .infal-report-filter-button {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 8px 6px !important;
            font-size: 12px !important;
            gap: 4px !important;
            overflow: hidden;
        }

        .infal-report-action-buttons .btn span,
        .infal-report-filter-button span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .infal-report-action-buttons .btn i,
        .infal-report-filter-button i {
            margin-right: 0 !important;
            flex: 0 0 auto;
        }

        .infal-report-reset {
            width: 100% !important;
        }
    }
</style>

<div class="container-fluid">

    {{-- Header Halaman --}}
    <div class="ui-page-hero">
        <div>
            <h3>Rekap Guru Infal</h3>
            <p>Data guru utama yang digantikan oleh guru infal/pengganti.</p>
        </div>
    </div>

    <div class="ui-page-action-row infal-report-actions">
        <div class="infal-report-action-buttons">
            <button type="submit" form="infalReportFilterForm" class="btn btn-primary infal-report-filter-button">
                <i class="bi bi-funnel-fill"></i>
                <span>Filter</span>
            </button>

            <a href="{{ route('infal.report.excel', request()->query()) }}" class="btn btn-success">
                <i class="bi bi-file-earmark-excel-fill"></i>
                <span>Excel</span>
            </a>
 
            <a href="{{ route('infal.report.pdf', request()->query()) }}" class="btn btn-danger">
                <i class="bi bi-file-earmark-pdf-fill"></i>
                <span>PDF</span>
            </a>
        </div>
    </div>

    {{-- Filter --}}
    <div class="card border-0 shadow-sm mb-3 ui-filter-card">
        <div class="card-body">
            <form method="GET" id="infalReportFilterForm">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5 ui-field">
                        <label class="form-label fw-semibold">Tanggal Mulai</label>
                        <input
                            type="date"
                            name="tanggal_mulai"
                            class="form-control"
                            value="{{ request('tanggal_mulai') }}"
                        >
                    </div>

                    <div class="col-md-5 ui-field">
                        <label class="form-label fw-semibold">Tanggal Selesai</label>
                        <input
                            type="date"
                            name="tanggal_selesai"
                            class="form-control"
                            value="{{ request('tanggal_selesai') }}"
                        >
                    </div>

                    <div class="col-md-2">
                        <div class="ui-filter-actions">
                            <a href="{{ route('infal.report.index') }}" class="btn btn-secondary infal-report-reset">
                                Reset
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive-mobile">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead>
                        <tr>
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
                        @forelse($items as $item)
                            <tr>
                                <td>
                                    {{ $item->tanggalLabel() }}
                                </td>

                                <td>{{ $item->teacher->nama_lengkap ?? '-' }}</td>

                                <td>{{ $item->infalTeacher->nama_lengkap ?? '-' }}</td>

                                <td>{{ ucfirst($item->jenis_pengajuan) }}</td>

                                <td>{{ $item->alasan }}</td>

                                <td>
                                    <span class="badge bg-success">
                                        Disetujui
                                    </span>
                                </td>

                                <td>
                                    <span class="badge bg-success">
                                        Disetujui
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    Belum ada data guru infal/pengganti.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-footer-row">
                <div class="pagination-wrapper">
                    {{ $items->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
