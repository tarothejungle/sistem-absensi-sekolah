@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        
        @if (in_array(auth()->user()->role, ['guru', 'bendahara']))
        <h3 class="mb-0 fw-bold">Pengajuan Izin/Cuti</h3>
        @endif
        
        @if (in_array(auth()->user()->role, ['kepala_sekolah', 'super_admin']))
        <h3 class="mb-0 fw-bold">Approval Izin/Cuti</h3>
        @endif

    </div>

    @if (in_array(auth()->user()->role, ['guru', 'bendahara']))
        <div class="ui-page-action-row">
            <a href="{{ route('leave.create') }}" class="btn-add-primary">
                <i class="bi bi-plus-lg"></i>
                <span>Ajukan Izin/Cuti</span>
            </a>
        </div>
    @endif

    @php
        $isGuru = auth()->user()->role === 'guru';
        $canApprove = in_array(auth()->user()->role, ['super_admin', 'kepala_sekolah']);
    @endphp

    @php
        $user = auth()->user();

        $isGuruLike = in_array($user->role, ['guru', 'bendahara']);
        $isApprover = in_array($user->role, ['super_admin', 'kepala_sekolah']);
        $loginTeacherId = $user->teacher->id ?? null;
    @endphp

    <div class="table-responsive-mobile">
        <table class="table table-bordered table-striped align-middle">
            <thead>
                <tr>
                    <th>Guru</th>
                    <th>Jenis</th>
                    <th>Tanggal</th>
                    <th>Alasan</th>
                    <th>Guru Pengganti</th>

                    @if(!$isGuruLike)
                        <th>Lampiran</th>
                    @endif

                    <th>Status Izin/Cuti</th>
                    <th>Status Infal</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                @forelse($leaves as $leave)
                    @php
                        $isRequester = $isGuruLike && $leave->teacher_id == $loginTeacherId;
                        $isInfal = $isGuruLike && $leave->infal_teacher_id == $loginTeacherId;
                    @endphp

                    <tr>
                        {{-- Guru --}}
                        <td>
                            {{ $leave->teacher->nama_lengkap ?? '-' }}
                        </td>

                        {{-- Jenis --}}
                        <td>
                            {{ ucfirst(str_replace('_', ' ', $leave->jenis_pengajuan)) }}

                            @if($leave->is_sementara)
                                <div class="mt-1">
                                    <span class="badge bg-info text-dark">Sementara</span>
                                </div>
                            @endif
                        </td>

                        {{-- Tanggal --}}
                        <td>
                            {{ $leave->tanggalLabel() }}
                        </td>

                        {{-- Alasan --}}
                        <td>
                            {{ $leave->alasan }}
                        </td>

                        {{-- Guru Pengganti --}}
                        <td>
                            @if($leave->infal_teacher_id && $leave->infalTeacher)
                                {{ $leave->infalTeacher->nama_lengkap }}
                            @else
                                <span class="badge bg-secondary">Tidak Perlu</span>
                            @endif
                        </td>

                        {{-- Lampiran, hanya untuk admin/kepsek --}}
                        @if(!$isGuruLike)
                            <td>
                                @if($leave->lampiran)
                                    @php
                                        $lampiranUrl = route('leave.attachment.show', $leave);
                                        $extension = strtolower(pathinfo($leave->lampiran, PATHINFO_EXTENSION));
                                    @endphp

                                    @if(in_array($extension, ['jpg', 'jpeg', 'png']))
                                        <button 
                                            type="button" 
                                            class="btn btn-info btn-sm text-white"
                                            data-bs-toggle="modal"
                                            data-bs-target="#lampiranModal{{ $leave->id }}"
                                        >
                                            <i class="bi bi-eye-fill"></i> Lihat
                                        </button>

                                        <div 
                                            class="modal fade" 
                                            id="lampiranModal{{ $leave->id }}" 
                                            tabindex="-1"
                                            aria-hidden="true"
                                        >
                                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            Lampiran Pengajuan - {{ $leave->teacher->nama_lengkap ?? '-' }}
                                                        </h5>

                                                        <button 
                                                            type="button" 
                                                            class="btn-close" 
                                                            data-bs-dismiss="modal"
                                                            aria-label="Close"
                                                        ></button>
                                                    </div>

                                                    <div class="modal-body text-center">
                                                        <img 
                                                            src="{{ $lampiranUrl }}" 
                                                            alt="Lampiran Pengajuan"
                                                            class="img-fluid rounded"
                                                            style="max-height: 70vh;"
                                                        >
                                                    </div>

                                                    <div class="modal-footer">
                                                        <a href="{{ $lampiranUrl }}" target="_blank" class="btn btn-primary">
                                                            Buka di Tab Baru
                                                        </a>

                                                        <button 
                                                            type="button" 
                                                            class="btn btn-secondary" 
                                                            data-bs-dismiss="modal"
                                                        >
                                                            Tutup
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <a 
                                            href="{{ $lampiranUrl }}" 
                                            target="_blank" 
                                            class="btn btn-info btn-sm text-white"
                                        >
                                            <i class="bi bi-file-earmark-text-fill"></i> Lihat File
                                        </a>
                                    @endif
                                @else
                                    <span class="text-muted">Tidak ada</span>
                                @endif
                            </td>
                        @endif

                        {{-- Status Izin/Cuti --}}
                        <td>
                            @if($leave->status_pengajuan === 'pending')
                                <span class="badge bg-warning text-dark">Pending</span>
                            @elseif($leave->status_pengajuan === 'disetujui')
                                <span class="badge bg-success">Disetujui</span>
                            @elseif($leave->status_pengajuan === 'ditolak')
                                <span class="badge bg-danger">Ditolak</span>
                            @else
                                <span class="badge bg-secondary">{{ $leave->status_pengajuan }}</span>
                            @endif

                            @if($leave->approved_at || $leave->approver || $leave->catatan_approval)
                                <div class="small text-muted mt-2">
                                    @if($leave->approver)
                                        Oleh: {{ $leave->approver->name ?? $leave->approver->nip }}
                                    @endif

                                    @if($leave->approved_at)
                                        <br>{{ $leave->approved_at->format('d/m/Y H:i') }}
                                    @endif

                                    @if($leave->catatan_approval)
                                        <br><span class="fw-semibold">Catatan:</span> {{ $leave->catatan_approval }}
                                    @endif
                                </div>
                            @endif
                        </td>

                        {{-- Status Infal --}}
                        <td>
                            @if(!$leave->infal_teacher_id)
                                <span class="badge bg-secondary">Tidak Perlu</span>
                            @elseif($leave->status_infal === 'pending')
                                <span class="badge bg-warning text-dark">Menunggu</span>
                            @elseif($leave->status_infal === 'disetujui')
                                <span class="badge bg-success">Disetujui</span>
                            @elseif($leave->status_infal === 'ditolak')
                                <span class="badge bg-danger">Ditolak</span>
                            @else
                                <span class="badge bg-secondary">-</span>
                            @endif

                            @if($leave->catatan_infal)
                                <div class="small text-muted mt-2">
                                    <span class="fw-semibold">Catatan:</span> {{ $leave->catatan_infal }}
                                </div>
                            @endif
                        </td>

                        <td>
                            {{-- Guru pengaju: boleh edit/hapus selama pengajuan izin masih pending --}}
                            @if($isRequester && $leave->status_pengajuan === 'pending')
                                <div class="d-flex gap-1 flex-wrap">
                                    <a 
                                        href="{{ route('leave.edit', $leave) }}" 
                                        class="btn btn-warning btn-sm"
                                    >
                                        Edit
                                    </a>

                                    <form 
                                        action="{{ route('leave.destroy', $leave) }}" 
                                        method="POST"
                                        data-confirm-action="true"
                                        data-confirm-type="danger"
                                        data-confirm-icon="bi-trash3"
                                        data-confirm-title="Hapus pengajuan?"
                                        data-confirm-message="Pengajuan izin/cuti ini akan dihapus dari daftar Anda."
                                        data-confirm-submit="Hapus Pengajuan"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="btn btn-danger btn-sm">
                                            Hapus
                                        </button>
                                    </form>
                                </div>

                            {{-- Guru pengaju: kalau izin disetujui tapi guru infal menolak, boleh ganti guru pengganti --}}
                            @elseif($isRequester && $leave->infal_teacher_id && $leave->status_pengajuan === 'disetujui' && $leave->status_infal === 'ditolak')
                                <a 
                                    href="{{ route('leave.edit', $leave) }}" 
                                    class="btn btn-warning btn-sm"
                                >
                                    Ganti Guru Pengganti
                                </a>

                            {{-- Guru infal: hanya bisa approve/tolak setelah izin disetujui kepsek/super admin --}}
                            @elseif($isInfal && $leave->status_pengajuan === 'disetujui' && $leave->status_infal === 'pending')
                                <div class="d-flex gap-1 flex-wrap">
                                    <form 
                                        action="{{ route('leave.infal.approve', $leave) }}" 
                                        method="POST"
                                        data-confirm-action="true"
                                        data-confirm-type="success"
                                        data-confirm-icon="bi-check2-circle"
                                        data-confirm-title="Setujui sebagai guru pengganti?"
                                        data-confirm-message="Anda akan tercatat menyetujui tugas sebagai guru pengganti untuk pengajuan ini."
                                        data-confirm-submit="Setujui"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <button type="submit" class="btn btn-success btn-sm">
                                            Setujui
                                        </button>
                                    </form>

                                    <form 
                                        action="{{ route('leave.infal.reject', $leave) }}" 
                                        method="POST"
                                        data-confirm-action="true"
                                        data-confirm-type="danger"
                                        data-confirm-icon="bi-x-circle"
                                        data-confirm-title="Tolak sebagai guru pengganti?"
                                        data-confirm-message="Status Anda akan dicatat menolak tugas sebagai guru pengganti untuk pengajuan ini."
                                        data-confirm-submit="Tolak"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <textarea
                                            name="catatan_infal"
                                            class="form-control form-control-sm mb-1"
                                            rows="2"
                                            maxlength="500"
                                            placeholder="Catatan penolakan (opsional)"
                                        ></textarea>

                                        <button type="submit" class="btn btn-danger btn-sm">
                                            Tolak
                                        </button>
                                    </form>
                                </div>

                            {{-- Guru infal: kalau izin belum disetujui, belum bisa approve --}}
                            @elseif($isInfal && $leave->status_pengajuan === 'pending')
                                <span class="text-muted">Menunggu persetujuan izin</span>

                            {{-- Super admin / kepala sekolah --}}
                            @elseif($isApprover && $leave->status_pengajuan === 'pending')
                                <div class="d-flex flex-column gap-2">
                                    <form action="{{ route('leave.approve', $leave->id) }}" method="POST">
                                        @csrf

                                        <textarea
                                            name="catatan_approval"
                                            class="form-control form-control-sm mb-1"
                                            rows="2"
                                            maxlength="500"
                                            placeholder="Catatan persetujuan (opsional)"
                                        ></textarea>

                                        <button
                                            type="submit"
                                            class="btn btn-success btn-sm"
                                            data-confirm-action="true"
                                            data-confirm-type="success"
                                            data-confirm-icon="bi-check2-circle"
                                            data-confirm-title="Setujui izin/cuti?"
                                            data-confirm-message="Pengajuan ini akan disetujui dan guru terkait akan menerima status terbaru."
                                            data-confirm-submit="Setujui"
                                        >
                                            Setujui
                                        </button>
                                    </form>

                                    <form action="{{ route('leave.reject', $leave->id) }}" method="POST">
                                        @csrf

                                        <textarea
                                            name="catatan_approval"
                                            class="form-control form-control-sm mb-1"
                                            rows="2"
                                            maxlength="500"
                                            placeholder="Catatan penolakan (opsional)"
                                        ></textarea>

                                        <button
                                            type="submit"
                                            class="btn btn-danger btn-sm"
                                            data-confirm-action="true"
                                            data-confirm-type="danger"
                                            data-confirm-icon="bi-x-circle"
                                            data-confirm-title="Tolak izin/cuti?"
                                            data-confirm-message="Pengajuan ini akan ditolak. Pastikan catatan penolakan sudah sesuai jika diperlukan."
                                            data-confirm-submit="Tolak"
                                        >
                                            Tolak
                                        </button>
                                    </form>
                                </div>

                            {{-- Super admin melihat izin sudah disetujui tapi infal ditolak --}}
                            @elseif($isApprover && $leave->status_pengajuan === 'disetujui' && $leave->status_infal === 'ditolak')
                                <span class="text-muted">Menunggu guru memilih pengganti lain</span>

                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isGuruLike ? 8 : 9 }}" class="text-center text-muted">
                            Belum ada data pengajuan izin/cuti.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

        <!-- <div class="table-footer-row">
                <div class="pagination-wrapper">
                    {{ $leaves->links() }}
                </div>
            </div> -->
        </div>
@endsection
