@extends('layouts.app')

@section('content')
@php
    $teacher = $teacher ?? null;
@endphp

<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>{{ $teacher ? 'Edit Guru' : 'Tambah Guru' }}</h3>
        <p>Atur akun, role, dan identitas pengguna sekolah. Jadwal diatur melalui menu Setting Jadwal Kerja.</p>
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
                    <input type="text" name="username" class="form-control" value="{{ old('username', $teacher?->user?->username) }}" placeholder="Masukkan username" required>
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
                        <option value="Tenaga Kependidikan" @selected(old('jabatan', $teacher?->jabatan) === 'Tenaga Kependidikan')>Tenaga Kependidikan</option>
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

    @if($teacher)
        <div class="card ui-form-card mt-4">
            <div class="card-body">
                <div class="ui-section-title">
                    <i class="bi bi-person-bounding-box"></i>
                    <span>Data Wajah Absensi</span>
                </div>

                <p class="text-muted mb-3">Pemilik akun wajib menyelesaikan active liveness empat langkah sebelum template dibuat. Setelah tersimpan, super admin menjadi satu-satunya role yang dapat mereset data wajah.</p>

                @if($teacher->faceEnrollment?->isCompatible())
                    <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>
                            <i class="bi bi-check-circle me-1"></i>
                            Template InsightFace sudah terdaftar dan dikunci sejak {{ $teacher->faceEnrollment->enrolled_at?->format('d/m/Y H:i') }}.
                        </span>
                        <form method="POST" action="{{ route('admin.teachers.face-enrollment.delete', array_merge(['teacher' => $teacher], request()->query())) }}" onsubmit="return confirm('Hapus data wajah terdaftar? Guru/karyawan tidak dapat absensi sampai didaftarkan lagi.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">Reset Data Wajah</button>
                        </form>
                    </div>
                @elseif($teacher->faceEnrollment)
                    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span><i class="bi bi-exclamation-triangle me-1"></i> Template wajah tidak kompatibel. Reset agar pemilik akun dapat mendaftar ulang.</span>
                        <form method="POST" action="{{ route('admin.teachers.face-enrollment.delete', array_merge(['teacher' => $teacher], request()->query())) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">Reset Data Wajah</button>
                        </form>
                    </div>
                @else
                    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i> Template InsightFace belum terdaftar. Akun ini belum bisa melakukan absensi biometrik.</div>
                @endif

            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script nonce='{{ request()->attributes->get('csp_nonce') }}'>
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
