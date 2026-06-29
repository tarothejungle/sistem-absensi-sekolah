@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>Ubah Profil</h3>
        <p>Perbarui data diri dan foto profil akun.</p>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="mb-4 text-center">
                    @if($user->profile_photo)
                        <img 
                            src="{{ asset($user->profile_photo) }}" 
                            alt="Foto Profil" 
                            style="width: 110px; height: 110px; object-fit: cover; border-radius: 50%; border: 2px solid #001362;"
                        >
                    @else
                        <div style="width: 110px; height: 110px; border-radius: 50%; background: #001362; color: white; display: flex; align-items: center; justify-content: center; font-size: 36px; margin: auto;">
                            {{ strtoupper(substr($user->name ?? $user->nip, 0, 1)) }}
                        </div>
                    @endif
                </div>

                <div class="mb-3">
                    <label class="form-label">Upload Foto Profil</label>
                    <input type="file" name="profile_photo" class="form-control" accept="image/*">
                    <small class="text-muted">Format: JPG, JPEG, PNG. Maksimal 2MB.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="{{ $user->nip }}" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input 
                        type="text" 
                        name="name" 
                        class="form-control" 
                        value="{{ old('name', $user->name) }}" 
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Instansi Mengajar</label>
                    <input 
                        type="text" 
                        name="instansi_mengajar" 
                        class="form-control" 
                        value="{{ old('instansi_mengajar', $user->instansi_mengajar) }}" 
                        placeholder="Contoh: MI XYZ"
                    >
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tempat Lahir</label>
                        <input 
                            type="text" 
                            name="tempat_lahir" 
                            class="form-control" 
                            value="{{ old('tempat_lahir', $user->tempat_lahir) }}" 
                            placeholder="Contoh: Tangerang"
                        >
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tanggal Lahir</label>
                        <input 
                            type="date" 
                            name="tanggal_lahir" 
                            class="form-control" 
                            value="{{ old('tanggal_lahir', $user->tanggal_lahir) }}"
                        >
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Pendidikan Terakhir</label>
                    <select name="pendidikan_terakhir" class="form-control">
                        <option value="">-- Pilih Pendidikan Terakhir --</option>
                        @foreach(['SMA/SMK', 'D1', 'D2', 'D3', 'S1', 'S2', 'S3'] as $pendidikan)
                            <option value="{{ $pendidikan }}" 
                                {{ old('pendidikan_terakhir', $user->pendidikan_terakhir) === $pendidikan ? 'selected' : '' }}>
                                {{ $pendidikan }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn-add-primary w-100">
                    Simpan Profil
                </button>
            </form>
        </div>
    </div>
</div>
@endsection