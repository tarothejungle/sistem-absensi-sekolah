@extends('layouts.app')

@section('content')
<style>
    @media (max-width: 768px) {
    .card .btn {
        width: 80%;
    }
}
</style>

<div class="container-fluid">

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header Halaman --}}
    <div class="ui-page-hero">
        <div>
            <h3>Rekap Guru Infal</h3>
            <p>Data guru utama yang digantikan oleh guru infal/pengganti.</p>
        </div>
    </div>

    <div class="ui-page-action-row">
        <a href="{{ route('infal.report.excel', request()->query()) }}" class="btn btn-success">
            <i class="bi bi-file-earmark-excel-fill"></i>
            Export Excel
        </a>

        <a href="{{ route('infal.report.pdf', request()->query()) }}" class="btn btn-danger">
            <i class="bi bi-file-earmark-pdf-fill"></i>
            Export PDF
        </a>
    </div>

    {{-- Filter --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Tanggal Mulai</label>
                        <input
                            type="date"
                            name="tanggal_mulai"
                            class="form-control"
                            value="{{ request('tanggal_mulai') }}"
                        >
                    </div>

                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Tanggal Selesai</label>
                        <input
                            type="date"
                            name="tanggal_selesai"
                            class="form-control"
                            value="{{ request('tanggal_selesai') }}"
                        >
                    </div>

                    <div class="col-md-2">
                        <div class="d-flex gap-2 justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                Filter
                            </button>

                            <a href="{{ route('infal.report.index') }}" class="btn btn-secondary">
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
                                    {{ $item->tanggal_mulai->format('d/m/Y') }}
                                    -
                                    {{ $item->tanggal_selesai->format('d/m/Y') }}
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
                @include('partials.per-page-selector', ['paginator' => $items])
                <div class="pagination-wrapper">
                    {{ $items->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
