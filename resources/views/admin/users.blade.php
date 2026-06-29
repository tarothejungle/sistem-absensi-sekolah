@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <div>
            <h3>Data Pengguna</h3>
            <p>Kelola akun pengguna, role, dan status akses sistem.</p>
        </div>

    </div>

    <div class="ui-page-action-row">
        <a href="{{ route('admin.users.create') }}" class="btn-add-primary">
            <i class="bi bi-plus-circle-fill"></i>
            <span>Tambah Pengguna</span>
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive-mobile">
                <table class="table table-bordered table-striped"> 
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Nama</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->nip }}</td>
                            <td>{{ $user->name }}</td>
                            <td>
                                @if($user->role === 'bendahara')
                                    <span class="badge bg-primary">Bendahara</span>
                                @elseif($user->role === 'kepala_sekolah')
                                    <span class="badge bg-success">Kepala Sekolah</span>
                                @elseif($user->role === 'super_admin')
                                    <span class="badge bg-dark">Super Admin</span>
                                @else
                                    <span class="badge bg-secondary">{{ $user->role }}</span>
                                @endif
                            </td>
                            <td>
                                @if($user->status === 'aktif')
                                    <span class="badge bg-success">Aktif</span>
                                @else
                                    <span class="badge bg-secondary">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-warning btn-sm">
                                    Edit
                                </a>

                                <form action="{{ route('admin.users.delete', $user) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')

                                    <button 
                                        type="submit" 
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Yakin ingin menghapus pengguna ini?')"
                                    >
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">
                                Belum ada data pengguna.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            <div class="mt-3">
                {{ $users->links('pagination::bootstrap-5') }}
            </div>
         </div>
    </div>
</div>
@endsection