@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>Pengaturan Jam Absensi</h3>
        <p>Pengaturan jam absensi umum untuk sistem.</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.attendance.setting.update') }}" method="POST">
                @csrf

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jam Masuk</label>
                        <input type="time" 
                               name="jam_masuk" 
                               class="form-control" 
                               value="{{ old('jam_masuk', substr($setting->jam_masuk, 0, 5)) }}" 
                               required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jam Pulang</label>
                        <input type="time" 
                               name="jam_pulang" 
                               class="form-control" 
                               value="{{ old('jam_pulang', substr($setting->jam_pulang, 0, 5)) }}" 
                               required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Toleransi Terlambat Menit</label>
                        <input type="number" 
                               name="toleransi_terlambat" 
                               class="form-control" 
                               value="{{ old('toleransi_terlambat', $setting->toleransi_terlambat) }}" 
                               min="0" 
                               required>
                    </div>
                </div>

                <hr>

                <h5>Batas Waktu Tombol Check-in</h5>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Check-in Mulai</label>
                        <input type="time" 
                               name="batas_check_in_mulai" 
                               class="form-control" 
                               value="{{ old('batas_check_in_mulai', substr($setting->batas_check_in_mulai, 0, 5)) }}" 
                               required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Check-in Selesai</label>
                        <input type="time" 
                               name="batas_check_in_selesai" 
                               class="form-control" 
                               value="{{ old('batas_check_in_selesai', substr($setting->batas_check_in_selesai, 0, 5)) }}" 
                               required>
                    </div>
                </div>

                <h5>Batas Waktu Tombol Check-out</h5>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Check-out Mulai</label>
                        <input type="time" 
                               name="batas_check_out_mulai" 
                               class="form-control" 
                               value="{{ old('batas_check_out_mulai', substr($setting->batas_check_out_mulai, 0, 5)) }}" 
                               required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Check-out Selesai</label>
                        <input type="time" 
                               name="batas_check_out_selesai" 
                               class="form-control" 
                               value="{{ old('batas_check_out_selesai', substr($setting->batas_check_out_selesai, 0, 5)) }}" 
                               required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Simpan Pengaturan
                </button>
            </form>
        </div>
    </div>
</div>
@endsection