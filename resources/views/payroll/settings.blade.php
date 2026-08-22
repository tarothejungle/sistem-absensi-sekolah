@extends('layouts.app')

@section('content')
<div class="container-fluid">

    <div class="ui-page-hero">
        <div>
            <h3>Pengaturan Gaji Guru</h3>
            <p>Atur gaji pokok dan potongan ketidakhadiran guru.</p>
        </div>
    </div>

    <form action="{{ route('payroll.settings.bulk-update') }}" method="POST">
        @csrf

        <div class="card ui-form-card">
            <div class="card-body">

                <div class="alert alert-info">
                    Nilai potongan disimpan per guru dan dipakai saat generate payroll. Nilai awal baru mengikuti tier gaji, lalu dapat disesuaikan bendahara. Potongan alfa/tidak hadir dicatat sebagai kas sekolah.
                </div>

                <div class="d-flex gap-2 justify-content-end mb-4 flex-wrap">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Simpan Perubahan
                    </button>

                    <a href="{{ route('payroll.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Kembali ke Penggajian
                    </a>
                </div>

                @if ($errors->any())
                    <div class="ui-error-summary">
                        <strong><i class="bi bi-exclamation-triangle"></i> Pengaturan gaji belum bisa disimpan</strong>
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="table-responsive-mobile">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Guru</th>
                                <th>Jabatan</th>
                                <th>Gaji Pokok</th>
                                <th>Potongan / Tidak Hadir</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($teachers as $teacher)
                                @php
                                    $salary = $teacher->salary ?? null;

                                    $gajiPokok = old(
                                        'salaries.' . $teacher->id . '.gaji_pokok',
                                        $salary->gaji_pokok ?? 0
                                    );

                                    $potonganOtomatis = old(
                                        'salaries.' . $teacher->id . '.potongan_per_absen',
                                        $salary->potongan_per_absen ?? ((float) $gajiPokok >= 1000000 ? 30000 : 20000)
                                    );

                                    $keterangan = old(
                                        'salaries.' . $teacher->id . '.keterangan',
                                        $salary->keterangan ?? 'Otomatis berdasarkan gaji pokok.'
                                    );
                                @endphp

                                <tr>
                                    <td>
                                        <strong>{{ $teacher->nama_lengkap ?? '-' }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $teacher->user->username ?? '-' }}</small>
                                    </td>

                                    <td>{{ $teacher->jabatan ?? '-' }}</td>

                                    <td>
                                        <input
                                            type="number"
                                            name="salaries[{{ $teacher->id }}][gaji_pokok]"
                                            class="form-control gaji-pokok-input"
                                            value="{{ $gajiPokok }}"
                                            min="0"
                                            required
                                        >
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            name="salaries[{{ $teacher->id }}][potongan_per_absen]"
                                            class="form-control potongan-input"
                                            value="{{ $potonganOtomatis }}"
                                            min="0"
                                            step="0.01"
                                            required
                                        >

                                        <small class="text-muted">
                                            Dapat disesuaikan untuk guru ini.
                                        </small>
                                    </td>

                                    <td>
                                        <input
                                            type="text"
                                            name="salaries[{{ $teacher->id }}][keterangan]"
                                            class="form-control"
                                            value="{{ $keterangan }}"
                                            placeholder="Opsional"
                                        >
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="ui-empty-state">
                                            <i class="bi bi-cash-stack"></i>
                                            <div class="fw-bold">Belum ada data guru.</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $teachers->links('pagination::bootstrap-5') }}
                </div>

            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script nonce='{{ request()->attributes->get('csp_nonce') }}'>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('tr').forEach(function (row) {
            const gajiInput = row.querySelector('.gaji-pokok-input');
            const potonganInput = row.querySelector('.potongan-input');

            if (!gajiInput || !potonganInput) {
                return;
            }

            gajiInput.addEventListener('change', function () {
                if (potonganInput.value === '') {
                    potonganInput.value = parseFloat(gajiInput.value || 0) >= 1000000 ? 30000 : 20000;
                }
            });
        });
    });
</script>
@endpush
