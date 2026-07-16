@extends('layouts.app')

@section('content')
@php
    $teacher = $teacher ?? null;
    $dayLabels = [
        'senin' => 'Senin',
        'selasa' => 'Selasa',
        'rabu' => 'Rabu',
        'kamis' => 'Kamis',
        'jumat' => 'Jumat',
        'sabtu' => 'Sabtu',
        'minggu' => 'Minggu',
    ];

    $selectedDays = old('attendance_days');

    if (!$selectedDays) {
        if ($teacher && $teacher->schedules && $teacher->schedules->count() > 0) {
            $selectedDays = $teacher->schedules
                ->where('status', 'aktif')
                ->pluck('hari')
                ->toArray();
        } else {
            $selectedDays = ['senin', 'selasa', 'rabu', 'kamis', 'jumat'];
        }
    }
@endphp

<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>{{ $teacher ? 'Edit Guru' : 'Tambah Guru' }}</h3>
        <p>Atur akun, role, identitas, sesi absensi, dan hari absensi pengguna sekolah.</p>
    </div>

    @if ($errors->any())
        <div class="ui-error-summary">
            <strong><i class="bi bi-exclamation-triangle"></i> Data guru belum bisa disimpan</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $teacher ? route('admin.teachers.update', array_merge(['teacher' => $teacher], request()->query())) : route('admin.teachers.store', request()->query()) }}" class="card ui-form-card">
        @csrf

        @if ($teacher)
            @method('PUT')
        @endif


        <div class="card-body">
            <div class="ui-section-title">
                <i class="bi bi-person-badge"></i>
                <span>Data Akun dan Identitas</span>
            </div>

            <div class="ui-form-section">
            <div class="row g-3">
                <div class="col-md-6 ui-field">
                    <label class="form-label">Username</label>
                    <input type="text" name="nip" class="form-control" value="{{ old('nip', $teacher?->user?->nip) }}" placeholder="Masukkan username" required>
                </div>

                @if(!$teacher)
                    <div class="col-md-6 ui-field">
                        <label class="form-label">Password Awal</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" class="form-control password-input" placeholder="Masukkan password awal" data-strong-password required>
                            <button type="button" class="password-toggle" onclick="togglePasswordField(this)" aria-label="Lihat password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted d-block mt-2">Minimal 8 karakter, huruf besar-kecil, angka, dan simbol.</small>
                    </div>
                @endif

                <div class="col-md-6 ui-field">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" value="{{ old('nama_lengkap', $teacher?->nama_lengkap) }}" placeholder="Masukkan nama lengkap" required>
                </div>

                <div class="col-md-6 ui-field">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="">Pilih role</option>
                        @foreach(\App\Models\Teacher::DATA_GURU_ROLE_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $teacher?->user?->role) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6 ui-field">
                    <label class="form-label">Jenis Kelamin</label>
                    <select name="jenis_kelamin" class="form-select" required>
                        <option value="">Pilih jenis kelamin</option>
                        <option value="L" {{ old('jenis_kelamin', $teacher?->jenis_kelamin) === 'L' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="P" {{ old('jenis_kelamin', $teacher?->jenis_kelamin) === 'P' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                </div>

                <div class="col-md-6 ui-field">
                    <label class="form-label">No HP</label>
                    <input type="text" name="no_hp" class="form-control" value="{{ old('no_hp', $teacher?->no_hp) }}" placeholder="Contoh: 0812-3456-7890">
                </div>

                <div class="col-md-6 ui-field">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $teacher?->user?->email) }}" placeholder="email@gmail.com" required>
                </div>

                <div class="col-md-6 ui-field">
                    <label class="form-label">Jabatan</label>
                    <select name="jabatan" class="form-select" required>
                        <option value="">Pilih jabatan</option>
                        <option value="Kepala Sekolah" @selected(old('jabatan', $teacher?->jabatan) === 'Kepala Sekolah')>Kepala Sekolah</option>
                        <option value="Bendahara" @selected(old('jabatan', $teacher?->jabatan) === 'Bendahara')>Bendahara</option>
                        <option value="Operator" @selected(old('jabatan', $teacher?->jabatan) === 'Operator')>Operator</option>
                        <option value="Guru Kelas" @selected(old('jabatan', $teacher?->jabatan) === 'Guru Kelas')>Guru Kelas</option>
                        <option value="Guru Bidang" @selected(old('jabatan', $teacher?->jabatan) === 'Guru Bidang')>Guru Bidang</option>
                    </select>
                </div>

                <div class="col-md-6 ui-field">
                    <label class="form-label">Mata Pelajaran</label>
                    <input type="text" name="mata_pelajaran" class="form-control" value="{{ old('mata_pelajaran', $teacher?->mata_pelajaran) }}" placeholder="Contoh: Tahfidz, Matematika, Semua Mapel">
                </div>
            </div>
            </div>

            <hr class="my-4">

            <div class="ui-section-title">
                <i class="bi bi-clock-history"></i>
                <span>Sesi Absensi</span>
            </div>

            <div class="row g-3 mb-4">
                @foreach($sessions as $session)
                    <div class="col-md-6">
                        <label class="ui-option-card d-block h-100" style="cursor:pointer;">
                            <div class="d-flex align-items-start gap-2">
                                <input type="checkbox" name="attendance_session_ids[]" value="{{ $session->id }}" class="form-check-input mt-1"
                                    @if(in_array($session->id, old('attendance_session_ids', $teacher ? $teacher->attendanceSessions->pluck('id')->toArray() : []))) checked @endif>
                                <div>
                                    <div class="fw-bold">{{ $session->nama_sesi }}</div>
                                    <small class="text-muted">
                                        {{ substr($session->jam_masuk, 0, 5) }} - {{ substr($session->jam_pulang, 0, 5) }}
                                    </small>
                                </div>
                            </div>
                        </label>
                    </div>
                @endforeach
            </div>

            <div class="ui-section-title">
                <i class="bi bi-calendar-week"></i>
                <span>Hari Absensi</span>
            </div>

            <div class="row g-2 mb-4">
                @foreach($dayLabels as $value => $label)
                    <div class="col-6 col-md-3 col-lg">
                        <label class="ui-option-card d-flex align-items-center gap-2 justify-content-center" style="cursor:pointer;">
                            <input type="checkbox" name="attendance_days[]" value="{{ $value }}" class="form-check-input" {{ in_array($value, $selectedDays) ? 'checked' : '' }}>
                            <span class="fw-bold">{{ $label }}</span>
                        </label>
                    </div>
                @endforeach
            </div>

            <div class="ui-form-actions">
                <a href="{{ route('admin.teachers', request()->query()) }}" class="btn btn-secondary px-4">
                    <i class="bi bi-arrow-left"></i> Kembali
                </a>

                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save me-1"></i> Simpan
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-strong-password]').forEach(function (input) {
        input.addEventListener('input', function () {
            const valid = input.value.length >= 8
                && /[a-z]/.test(input.value)
                && /[A-Z]/.test(input.value)
                && /[0-9]/.test(input.value)
                && /[^A-Za-z0-9]/.test(input.value);
            input.setCustomValidity(valid ? '' : 'Password harus minimal 8 karakter dan berisi huruf besar, huruf kecil, angka, serta simbol.');
        });
    });
</script>
@endpush
