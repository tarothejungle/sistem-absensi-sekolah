@extends('layouts.app')

@section('content')

@php
    $modeGantiInfal = $leave->infal_teacher_id
        && $leave->status_pengajuan === 'disetujui'
        && $leave->status_infal === 'ditolak';

    $isSementara = old('is_sementara', $leave->is_sementara ? '1' : '0') == '1';
    $jamMulaiValue = old('jam_mulai', $leave->jam_mulai ? substr($leave->jam_mulai, 0, 5) : '');
    $jamSelesaiValue = old('jam_selesai', $leave->jam_selesai ? substr($leave->jam_selesai, 0, 5) : '');
@endphp

<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>{{ $modeGantiInfal ? 'Ganti Guru Pengganti' : 'Edit Pengajuan' }}</h3>
        <p>Perbarui data pengajuan sesuai alur persetujuan izin/cuti.</p>
    </div>

    <div class="card ui-form-card">
        <div class="card-body">
            <form
                action="{{ route('leave.update', $leave) }}"
                method="POST"
                enctype="multipart/form-data"
                data-leave-duration-form
                data-leave-duration-locked="{{ $modeGantiInfal ? '1' : '0' }}"
            >
                @csrf
                @method('PUT')
                <input type="hidden" name="is_sementara" value="0">

                <div class="ui-form-section">
                    <div class="ui-form-section-head">
                        <span class="ui-form-section-icon"><i class="bi bi-calendar2-week"></i></span>
                        <div>
                            <h5 class="ui-form-section-title">Detail Pengajuan</h5>
                            <p class="ui-form-section-subtitle">Perbarui jenis pengajuan dan durasi sesuai kebutuhan.</p>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="is_sementara"
                            name="is_sementara"
                            value="1"
                            data-leave-temporary-toggle
                            {{ $isSementara ? 'checked' : '' }}
                            {{ $modeGantiInfal ? 'disabled' : '' }}
                        >
                        <label class="form-check-label" for="is_sementara">Izin sementara</label>
                    </div>

                    @error('is_sementara')
                        <small class="text-danger d-block mb-2">{{ $message }}</small>
                    @enderror

                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6 ui-field">
                            <label class="form-label">Jenis</label>
                            <select
                                name="jenis_pengajuan"
                                class="form-select"
                                required
                                {{ $modeGantiInfal ? 'disabled' : '' }}
                            >
                                <option value="sakit" {{ old('jenis_pengajuan', $leave->jenis_pengajuan) == 'sakit' ? 'selected' : '' }}>Sakit</option>
                                <option value="izin" {{ old('jenis_pengajuan', $leave->jenis_pengajuan) == 'izin' ? 'selected' : '' }}>Izin</option>
                                <option value="cuti" {{ old('jenis_pengajuan', $leave->jenis_pengajuan) == 'cuti' ? 'selected' : '' }}>Cuti</option>
                                <option value="tugas_luar" {{ old('jenis_pengajuan', $leave->jenis_pengajuan) == 'tugas_luar' ? 'selected' : '' }}>Tugas Luar</option>
                            </select>

                            @error('jenis_pengajuan')
                                <small class="text-danger d-block">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-lg-3 col-md-6 ui-field">
                            <label class="form-label" data-leave-start-label>{{ $isSementara ? 'Tanggal Izin' : 'Tanggal Mulai' }}</label>
                            <input
                                type="date"
                                name="tanggal_mulai"
                                class="form-control"
                                value="{{ old('tanggal_mulai', $leave->tanggal_mulai->format('Y-m-d')) }}"
                                required
                                {{ $modeGantiInfal ? 'disabled' : '' }}
                            >

                            @error('tanggal_mulai')
                                <small class="text-danger d-block">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-lg-3 col-md-6 ui-field {{ $isSementara ? 'd-none' : '' }}" data-leave-full-day-field>
                            <label class="form-label">Tanggal Selesai</label>
                            <input
                                type="date"
                                name="tanggal_selesai"
                                class="form-control"
                                value="{{ old('tanggal_selesai', $leave->tanggal_selesai->format('Y-m-d')) }}"
                                data-leave-end-date
                                {{ $isSementara || $modeGantiInfal ? 'disabled' : 'required' }}
                            >

                            @error('tanggal_selesai')
                                <small class="text-danger d-block">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-lg-3 col-md-6 ui-field {{ $isSementara ? '' : 'd-none' }}" data-leave-temporary-field>
                            <label class="form-label">Jam Mulai</label>
                            <input
                                type="time"
                                name="jam_mulai"
                                class="form-control"
                                value="{{ $jamMulaiValue }}"
                                data-leave-temporary-input
                                {{ $isSementara && ! $modeGantiInfal ? 'required' : 'disabled' }}
                            >

                            @error('jam_mulai')
                                <small class="text-danger d-block">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-lg-3 col-md-6 ui-field {{ $isSementara ? '' : 'd-none' }}" data-leave-temporary-field>
                            <label class="form-label">Jam Selesai</label>
                            <input
                                type="time"
                                name="jam_selesai"
                                class="form-control"
                                value="{{ $jamSelesaiValue }}"
                                data-leave-temporary-input
                                {{ $isSementara && ! $modeGantiInfal ? 'required' : 'disabled' }}
                            >

                            @error('jam_selesai')
                                <small class="text-danger d-block">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="ui-form-section">
                    <div class="ui-form-section-head">
                        <span class="ui-form-section-icon"><i class="bi bi-person-check"></i></span>
                        <div>
                            <h5 class="ui-form-section-title">Guru Pengganti</h5>
                            <p class="ui-form-section-subtitle">
                                {{ $modeGantiInfal ? 'Guru pengganti sebelumnya menolak. Pilih pengganti baru atau kosongkan.' : 'Kosongkan jika pengajuan tidak membutuhkan guru pengganti.' }}
                            </p>
                        </div>
                    </div>

                    <div class="ui-field">
                        <label class="form-label">Guru Pengganti / Guru Infal</label>

                        <select name="infal_teacher_id" class="form-select">
                            <option value="">Tidak menggunakan guru pengganti</option>

                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}"
                                    {{ old('infal_teacher_id', $leave->infal_teacher_id) == $teacher->id ? 'selected' : '' }}>
                                    {{ $teacher->nama_lengkap }}
                                </option>
                            @endforeach
                        </select>

                        @error('infal_teacher_id')
                            <small class="text-danger d-block">{{ $message }}</small>
                        @enderror
                    </div>
                </div>

                <div class="ui-form-section">
                    <div class="ui-form-section-head">
                        <span class="ui-form-section-icon"><i class="bi bi-card-text"></i></span>
                        <div>
                            <h5 class="ui-form-section-title">Alasan dan Lampiran</h5>
                            <p class="ui-form-section-subtitle">Perbarui alasan atau ganti lampiran jika diperlukan.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-8 ui-field">
                            <label class="form-label">Alasan</label>
                            <textarea
                                name="alasan"
                                class="form-control"
                                rows="4"
                                required
                                {{ $modeGantiInfal ? 'disabled' : '' }}
                            >{{ old('alasan', $leave->alasan) }}</textarea>

                            @error('alasan')
                                <small class="text-danger d-block">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-md-4 ui-field">
                            <label class="form-label">Lampiran</label>
                            <input
                                type="file"
                                name="lampiran"
                                class="form-control"
                                {{ $modeGantiInfal ? 'disabled' : '' }}
                            >

                            <small class="text-muted">
                                Format JPG, JPEG, PNG, PDF. Maksimal 2MB.
                            </small>

                            @if($leave->lampiran)
                                <div class="mt-2">
                                    <a href="{{ route('leave.attachment.show', $leave) }}" target="_blank">
                                        Lihat lampiran saat ini
                                    </a>
                                </div>
                            @endif

                            @error('lampiran')
                                <small class="text-danger d-block">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="ui-form-actions">
                    <a href="{{ route('leave.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i>
                        {{ $modeGantiInfal ? 'Simpan Guru Pengganti' : 'Simpan Perubahan' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-leave-duration-form]').forEach(function (form) {
            const toggle = form.querySelector('[data-leave-temporary-toggle]');
            const startLabel = form.querySelector('[data-leave-start-label]');
            const endDateInput = form.querySelector('[data-leave-end-date]');
            const fullDayFields = form.querySelectorAll('[data-leave-full-day-field]');
            const temporaryFields = form.querySelectorAll('[data-leave-temporary-field]');
            const temporaryInputs = form.querySelectorAll('[data-leave-temporary-input]');
            const isLocked = form.dataset.leaveDurationLocked === '1';

            if (!toggle) {
                return;
            }

            function syncLeaveDurationFields() {
                const isTemporary = toggle.checked;

                fullDayFields.forEach(function (field) {
                    field.classList.toggle('d-none', isTemporary);
                });

                temporaryFields.forEach(function (field) {
                    field.classList.toggle('d-none', !isTemporary);
                });

                if (startLabel) {
                    startLabel.textContent = isTemporary ? 'Tanggal Izin' : 'Tanggal Mulai';
                }

                if (endDateInput) {
                    endDateInput.disabled = isLocked || isTemporary;
                    endDateInput.required = !isLocked && !isTemporary;
                }

                temporaryInputs.forEach(function (input) {
                    input.disabled = isLocked || !isTemporary;
                    input.required = !isLocked && isTemporary;
                });
            }

            syncLeaveDurationFields();
            toggle.addEventListener('change', syncLeaveDurationFields);
        });
    });
</script>
@endpush
