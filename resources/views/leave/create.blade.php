@extends('layouts.app')

@section('content')
@php
    $isSementara = old('is_sementara', '0') == '1';
@endphp

<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>Form Pengajuan Izin/Cuti</h3>
        <p>Ajukan izin atau cuti beserta guru pengganti/infal.</p>
    </div>

    <form
        method="POST"
        action="{{ route('leave.store') }}"
        enctype="multipart/form-data"
        class="card card-body ui-form-card"
        data-leave-duration-form
    >
        @csrf
        <input type="hidden" name="is_sementara" value="0">

        <div class="ui-form-section">
            <div class="ui-form-section-head">
                <span class="ui-form-section-icon"><i class="bi bi-calendar2-week"></i></span>
                <div>
                    <h5 class="ui-form-section-title">Detail Pengajuan</h5>
                    <p class="ui-form-section-subtitle">Pilih jenis pengajuan dan durasi izin/cuti.</p>
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
                >
                <label class="form-check-label" for="is_sementara">Izin sementara</label>
            </div>

            @error('is_sementara')
                <small class="text-danger d-block mb-2">{{ $message }}</small>
            @enderror

            <div class="row g-3">
                <div class="col-lg-3 col-md-6 ui-field">
                    <label>Jenis</label>
                    <select name="jenis_pengajuan" class="form-select" required>
                        <option value="sakit" {{ old('jenis_pengajuan', 'sakit') == 'sakit' ? 'selected' : '' }}>Sakit</option>
                        <option value="izin" {{ old('jenis_pengajuan', 'sakit') == 'izin' ? 'selected' : '' }}>Izin</option>
                        <option value="cuti" {{ old('jenis_pengajuan', 'sakit') == 'cuti' ? 'selected' : '' }}>Cuti</option>
                        <option value="tugas_luar" {{ old('jenis_pengajuan', 'sakit') == 'tugas_luar' ? 'selected' : '' }}>Tugas Luar</option>
                    </select>

                    @error('jenis_pengajuan')
                        <small class="text-danger d-block">{{ $message }}</small>
                    @enderror
                </div>

                <div class="col-lg-3 col-md-6 ui-field">
                    <label data-leave-start-label>{{ $isSementara ? 'Tanggal Izin' : 'Tanggal Mulai' }}</label>
                    <input
                        type="date"
                        name="tanggal_mulai"
                        class="form-control"
                        value="{{ old('tanggal_mulai') }}"
                        required
                    >

                    @error('tanggal_mulai')
                        <small class="text-danger d-block">{{ $message }}</small>
                    @enderror
                </div>

                <div class="col-lg-3 col-md-6 ui-field {{ $isSementara ? 'd-none' : '' }}" data-leave-full-day-field>
                    <label>Tanggal Selesai</label>
                    <input
                        type="date"
                        name="tanggal_selesai"
                        class="form-control"
                        value="{{ old('tanggal_selesai') }}"
                        data-leave-end-date
                        {{ $isSementara ? 'disabled' : 'required' }}
                    >

                    @error('tanggal_selesai')
                        <small class="text-danger d-block">{{ $message }}</small>
                    @enderror
                </div>

                <div class="col-lg-3 col-md-6 ui-field {{ $isSementara ? '' : 'd-none' }}" data-leave-temporary-field>
                    <label>Jam Mulai</label>
                    <input
                        type="time"
                        name="jam_mulai"
                        class="form-control"
                        value="{{ old('jam_mulai') }}"
                        data-leave-temporary-input
                        {{ $isSementara ? 'required' : 'disabled' }}
                    >

                    @error('jam_mulai')
                        <small class="text-danger d-block">{{ $message }}</small>
                    @enderror
                </div>

                <div class="col-lg-3 col-md-6 ui-field {{ $isSementara ? '' : 'd-none' }}" data-leave-temporary-field>
                    <label>Jam Selesai</label>
                    <input
                        type="time"
                        name="jam_selesai"
                        class="form-control"
                        value="{{ old('jam_selesai') }}"
                        data-leave-temporary-input
                        {{ $isSementara ? 'required' : 'disabled' }}
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
                    <p class="ui-form-section-subtitle">Kosongkan jika pengajuan tidak membutuhkan guru pengganti/infal.</p>
                </div>
            </div>

            <div class="ui-field">
                <label class="form-label">Guru Pengganti / Guru Infal</label>

                <select name="infal_teacher_id" class="form-select">
                    <option value="">Tidak menggunakan guru pengganti</option>

                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" {{ old('infal_teacher_id') == $teacher->id ? 'selected' : '' }}>
                            {{ $teacher->nama_lengkap }}
                            @if($teacher->mapel)
                                - {{ $teacher->mapel }}
                            @endif
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
                    <p class="ui-form-section-subtitle">Tulis alasan singkat dan lampirkan bukti jika diperlukan.</p>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-8 ui-field">
                    <label>Alasan</label>
                    <textarea
                        name="alasan"
                        class="form-control"
                        rows="4"
                        placeholder="Tuliskan alasan pengajuan"
                        required
                    >{{ old('alasan') }}</textarea>

                    @error('alasan')
                        <small class="text-danger d-block">{{ $message }}</small>
                    @enderror
                </div>

                <div class="col-md-4 ui-field">
                    <label>Lampiran</label>
                    <input
                        type="file"
                        name="lampiran"
                        class="form-control"
                        accept=".jpg,.jpeg,.png,.pdf"
                    >
                    <small class="text-muted">Format JPG, PNG, atau PDF.</small>

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
                <i class="bi bi-send"></i>
                Kirim Pengajuan
            </button>
        </div>
    </form>
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
                    endDateInput.disabled = isTemporary;
                    endDateInput.required = !isTemporary;
                }

                temporaryInputs.forEach(function (input) {
                    input.disabled = !isTemporary;
                    input.required = isTemporary;
                });
            }

            syncLeaveDurationFields();
            toggle.addEventListener('change', syncLeaveDurationFields);
        });
    });
</script>
@endpush
