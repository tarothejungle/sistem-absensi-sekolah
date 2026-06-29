@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>{{ $user ? 'Edit Pengguna' : 'Tambah Pengguna' }}</h3>
        <p>Kelola akun bendahara, kepala sekolah, dan super admin.</p>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form 
                action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}" 
                method="POST"
            >
                @csrf

                @if($user)
                    @method('PUT')
                @endif

                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input 
                        type="text" 
                        name="nip" 
                        class="form-control" 
                        value="{{ old('nip', $user->nip ?? '') }}" 
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input 
                        type="text" 
                        name="name" 
                        class="form-control" 
                        value="{{ old('name', $user->name ?? '') }}" 
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input 
                        type="email" 
                        name="email" 
                        class="form-control" 
                        value="{{ old('email', $user->email ?? '') }}" 
                        placeholder="contoh@email.com"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="">-- Pilih Role --</option>
                        <option value="bendahara" {{ old('role', $user->role ?? '') === 'bendahara' ? 'selected' : '' }}>
                            Bendahara
                        </option>
                        <option value="kepala_sekolah" {{ old('role', $user->role ?? '') === 'kepala_sekolah' ? 'selected' : '' }}>
                            Kepala Sekolah
                        </option>
                        <option value="super_admin" {{ old('role', $user->role ?? '') === 'super_admin' ? 'selected' : '' }}>
                            Super Admin
                        </option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Password {{ $user ? '(Kosongkan jika tidak ingin mengubah)' : '' }}
                    </label>

                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="{{ $user ? 'Kosongkan jika tidak ingin mengubah password' : 'Masukkan password' }}"
                            {{ $user ? '' : 'required' }}
                        >

                        <button 
                            type="button" 
                            class="password-toggle" 
                            onclick="togglePasswordField(this)"
                        >
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="aktif" {{ old('status', $user->status ?? 'aktif') === 'aktif' ? 'selected' : '' }}>
                            Aktif
                        </option>
                        <option value="nonaktif" {{ old('status', $user->status ?? '') === 'nonaktif' ? 'selected' : '' }}>
                            Nonaktif
                        </option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Simpan
                </button>
            </form>
        </div>
    </div>
</div>
@endsection