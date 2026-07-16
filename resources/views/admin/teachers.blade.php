@extends('layouts.app')

@section('content')
@php
    $dayLabels = [
        'senin' => 'Senin',
        'selasa' => 'Selasa',
        'rabu' => 'Rabu',
        'kamis' => 'Kamis',
        'jumat' => 'Jumat',
        'sabtu' => 'Sabtu',
        'minggu' => 'Minggu',
    ];
@endphp

<style>
    .table-footer-row {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 16px !important;
        padding-top: 16px !important;
        margin-top: 16px !important;
        border-top: 1px solid rgba(15, 23, 42, 0.08) !important;
        flex-wrap: wrap !important;
    }

    .per-page-form-custom {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        width: auto !important;
        margin: 0 !important;
        flex-wrap: nowrap !important;
    }

    .per-page-label-custom {
        font-size: 14px !important;
        color: #475569 !important;
        font-weight: 700 !important;
        white-space: nowrap !important;
    }

    .per-page-select-custom {
        width: 74px !important;
        min-width: 74px !important;
        max-width: 74px !important;
        height: 40px !important;
        border-radius: 14px !important;
        border: 1px solid #d7e3f5 !important;
        background: #fff !important;
        padding: 6px 12px !important;
        font-weight: 800 !important;
        color: #0f172a !important;
        outline: none !important;
    }

    .pagination-wrapper {
        display: flex !important;
        justify-content: flex-end !important;
        align-items: center !important;
        flex: 1 !important;
    }

    .pagination-wrapper nav {
        display: flex !important;
        justify-content: flex-end !important;
        width: 100% !important;
    }

    .pagination-wrapper nav > div:first-child,
    .pagination-wrapper nav p,
    .pagination-wrapper .small,
    .pagination-wrapper .text-muted {
        display: none !important;
    }

    .pagination-wrapper .pagination {
        margin-bottom: 0 !important;
    }

    .teacher-toolbar {
        width: 100%;
    }

    .teacher-search-form {
        display: flex;
        gap: 8px;
    }

    .teacher-search-form .form-control {
        flex: 1 1 auto;
        min-height: 40px !important;
        padding: 6px 12px !important;
        border-radius: 6px !important;
        font-size: 12px;
    }

    .teacher-search-reset {
        flex: 0 0 40px;
        width: 40px;
        padding-left: 0 !important;
        padding-right: 0 !important;
        justify-content: center;
    }

    .teacher-export-btn {
        min-width: 92px;
        min-height: 34px;
        padding: 6px 10px !important;
        border-radius: 6px !important;
        font-size: 12px;
    }

    @media (min-width: 769px) {
        .teacher-toolbar {
            display: grid !important;
            grid-template-columns: minmax(180px, 1fr) minmax(0, 1fr);
            align-items: center !important;
            gap: 12px;
            padding: 20px 28px !important;
        }

        .teacher-search-form {
            width: 50%;
            max-width: none;
        }

        .teacher-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            width: 100%;
            flex-wrap: nowrap;
        }

        .teacher-actions .btn-add-primary,
        .teacher-actions .bulk-action-form,
        .teacher-actions .bulk-action-form .btn {
            flex: 0 0 auto;
        }

        .teacher-actions .btn-add-primary,
        .teacher-actions .bulk-action-form .btn,
        .teacher-actions .teacher-export-btn {
            min-height: 40px;
            padding: 6px 10px !important;
            border-radius: 6px !important;
            font-size: 12px;
        }
    }

    @media (max-width: 768px) {
        .teacher-toolbar {
            display: flex !important;
            flex-direction: column;
            align-items: stretch !important;
            gap: 12px;
        }

        .teacher-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            width: 100%;
            gap: 10px;
        }

        .teacher-actions .btn-add-primary,
        .teacher-actions .bulk-action-form,
        .teacher-actions .bulk-action-form .btn,
        .teacher-actions .teacher-export-btn {
            width: 100%;
        }

        .teacher-search-form {
            width: 100%;
            align-items: stretch;
        }

        .table-footer-row {
            flex-direction: column !important;
            align-items: flex-start !important;
        }

        .pagination-wrapper {
            width: 100% !important;
            justify-content: flex-start !important;
        }

        .pagination-wrapper nav {
            justify-content: flex-start !important;
        }

        .per-page-form-custom {
            flex-wrap: wrap !important;
        }
    }
</style>

<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>Data Guru</h3>
        <p>Kelola akun guru, kepala sekolah, dan bendahara beserta jadwal absensinya.</p>
    </div>

    <div class="ui-toolbar teacher-toolbar">
        <form action="{{ route('admin.teachers') }}" method="GET" class="teacher-search-form" data-live-search-form>
            <input type="text" name="keyword" class="form-control" placeholder="Cari Guru" value="{{ request('keyword') }}" autocomplete="off" data-live-search-input>
            @if(request('keyword'))
                <a href="{{ route('admin.teachers') }}" class="btn btn-outline-secondary teacher-search-reset"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>

        <div class="ui-actions teacher-actions">
            <a href="{{ route('admin.teachers.create', request()->query()) }}" class="btn-add-primary">
                <i class="bi bi-plus-lg"></i>
                <span>Tambah Guru</span>
            </a>

            <form
                id="teacherBulkDeleteForm"
                action="{{ route('admin.teachers.bulk-delete') }}"
                method="POST"
                class="bulk-action-form"
                data-confirm-action="true"
                data-confirm-type="danger"
                data-confirm-icon="bi-person-x"
                data-confirm-title="Hapus guru terpilih?"
                data-confirm-message="Semua data guru yang dicentang beserta akun terkait akan dihapus dari sistem."
                data-confirm-submit="Hapus Terpilih"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger" data-bulk-delete-button form="teacherBulkDeleteForm" disabled>
                    <i class="bi bi-trash3"></i>
                    <span>Hapus Terpilih</span>
                </button>
            </form>

            <a href="{{ route('admin.teachers.export.excel', request()->query()) }}" class="btn btn-success teacher-export-btn" aria-label="Excel">
                <i class="bi bi-file-earmark-excel-fill"></i>
                <span>Excel</span>
            </a>

            <a href="{{ route('admin.teachers.export.pdf', request()->query()) }}" class="btn btn-danger teacher-export-btn" target="_blank" aria-label="PDF">
                <i class="bi bi-file-earmark-pdf-fill"></i>
                <span>PDF</span>
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div data-bulk-selection-form data-bulk-delete-target="teacherBulkDeleteForm">
                <div class="table-responsive-mobile">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th width="44">
                                    <input type="checkbox" class="form-check-input" data-check-all aria-label="Pilih semua guru">
                                </th>
                                <th>Username</th>
                                <th>Nama</th>
                                <th>Role</th>
                                <th>Jabatan</th>
                                <th>Mapel</th>
                                <th>Sesi Absensi</th>
                                <th>Hari Absensi</th>
                                <th width="140">Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($teachers as $teacher)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="teacher_ids[]" value="{{ $teacher->id }}" class="form-check-input" data-row-check>
                                    </td>
                                    <td>{{ $teacher->user->nip ?? '-' }}</td>
                                    <td class="fw-semibold">{{ $teacher->nama_lengkap ?? '-' }}</td>
                                    <td>
                                        <span class="badge bg-info text-dark">
                                            {{ \App\Models\Teacher::dataGuruRoleLabel($teacher->user->role ?? null) }}
                                        </span>
                                    </td>
                                    <td>{{ $teacher->jabatan ?? '-' }}</td>
                                    <td>{{ $teacher->mata_pelajaran ?? '-' }}</td>
                                    <td>
                                        @if($teacher->attendanceSessions && $teacher->attendanceSessions->count() > 0)
                                            @foreach($teacher->attendanceSessions as $session)
                                                <div class="mb-1">
                                                    <span class="badge bg-primary">{{ $session->nama_sesi }}</span>
                                                    <small class="text-muted d-block">
                                                        {{ substr($session->jam_masuk, 0, 5) }} - {{ substr($session->jam_pulang, 0, 5) }}
                                                    </small>
                                                </div>
                                            @endforeach
                                        @else
                                            <span class="badge bg-secondary">Belum diatur</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $activeDays = $teacher->schedules
                                                ? $teacher->schedules->where('status', 'aktif')->pluck('hari')->toArray()
                                                : [];
                                        @endphp

                                        @forelse($activeDays as $day)
                                            <span class="badge bg-light text-dark border mb-1">{{ $dayLabels[$day] ?? ucfirst($day) }}</span>
                                        @empty
                                            <span class="badge bg-secondary">Belum diatur</span>
                                        @endforelse
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <a href="{{ route('admin.teachers.edit', array_merge(['teacher' => $teacher], request()->query())) }}" class="btn btn-warning btn-sm">Edit</a>

                                            <form
                                                action="{{ route('admin.teachers.delete', $teacher) }}"
                                                method="POST"
                                                data-confirm-action="true"
                                                data-confirm-type="danger"
                                                data-confirm-icon="bi-person-x"
                                                data-confirm-title="Hapus data guru?"
                                                data-confirm-message="Data guru {{ $teacher->nama_lengkap ?? 'ini' }} beserta akun terkait akan dihapus dari sistem."
                                                data-confirm-submit="Hapus Guru"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="ui-empty-state">
                                        <i class="bi bi-person-x"></i>
                                        <div class="fw-bold">Belum ada data guru.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </div>

            <div class="mt-3">
                {{ $teachers->links('pagination::bootstrap-5') }}
            </div>
            </div>
         </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('[data-live-search-form]');
        const input = document.querySelector('[data-live-search-input]');

        if (!form || !input) {
            return;
        }

        let timer = null;

        input.addEventListener('input', function() {
            window.clearTimeout(timer);

            timer = window.setTimeout(function() {
                form.submit();
            }, 450);
        });
    });
</script>
@endpush
