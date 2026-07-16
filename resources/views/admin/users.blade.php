@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <div>
            <h3>Data Admin</h3>
            <p>Kelola akun super admin dan status akses sistem.</p>
        </div>

    </div>

    <div class="ui-page-action-row">
        <a href="{{ route('admin.users.create') }}" class="btn-add-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Tambah Admin</span>
        </a>

        <form
            id="userBulkDeleteForm"
            action="{{ route('admin.users.bulk-delete') }}"
            method="POST"
            class="bulk-action-form"
            data-confirm-action="true"
            data-confirm-type="danger"
            data-confirm-icon="bi-trash3"
            data-confirm-title="Hapus admin terpilih?"
            data-confirm-message="Semua akun admin yang dicentang akan dihapus dari sistem, kecuali akun yang sedang dipakai."
            data-confirm-submit="Hapus Terpilih"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger" data-bulk-delete-button form="userBulkDeleteForm" disabled>
                <i class="bi bi-trash3"></i>
                <span>Hapus Terpilih</span>
            </button>
        </form>
    </div>

    <div class="card">
        <div class="card-body">
            <div data-bulk-selection-form data-bulk-delete-target="userBulkDeleteForm">
                <div class="table-responsive-mobile">
                    <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th width="44">
                                <input type="checkbox" class="form-check-input" data-check-all aria-label="Pilih semua admin">
                            </th>
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
                                <td>
                                    <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" class="form-check-input" data-row-check @disabled(auth()->id() === $user->id)>
                                </td>
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
                                    <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST" class="m-0" data-no-loading="true">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="status-switch {{ $user->status === 'aktif' ? 'is-active' : '' }}" aria-label="Ubah status admin" @disabled(auth()->id() === $user->id)>
                                            <span></span>
                                        </button>
                                    </form>
                                    <small class="d-block mt-1 {{ $user->status === 'aktif' ? 'text-success' : 'text-muted' }}">
                                        {{ $user->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}
                                    </small>
                                </td>
                                <td>
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-warning btn-sm">
                                        Edit
                                    </a>

                                    <form
                                        action="{{ route('admin.users.delete', $user) }}"
                                        method="POST"
                                        class="d-inline"
                                        data-confirm-action="true"
                                        data-confirm-type="danger"
                                        data-confirm-icon="bi-trash3"
                                    data-confirm-title="Hapus admin?"
                                        data-confirm-message="Akun {{ $user->name }} akan dihapus dari sistem. Tindakan ini tidak bisa dibatalkan."
                                            data-confirm-submit="Hapus Admin"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="btn btn-danger btn-sm">
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">
                                    Belum ada data admin.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <div class="mt-3">
                {{ $users->links('pagination::bootstrap-5') }}
            </div>
         </div>
    </div>
</div>
@endsection
