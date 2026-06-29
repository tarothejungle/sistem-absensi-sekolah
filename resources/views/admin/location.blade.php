@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="ui-page-hero">
        <h3>Pengaturan Lokasi Sekolah</h3>
        <p>Tentukan titik sekolah dan radius validasi GPS untuk absensi guru.</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.location.update') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Nama Lokasi</label>
                    <input type="text" 
                           name="nama_lokasi" 
                           class="form-control" 
                           value="{{ old('nama_lokasi', $location->nama_lokasi ?? 'Sekolah Utama') }}" 
                           required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Cari Alamat Sekolah</label>
                    <div class="input-group">
                        <input type="text" id="searchBox" class="form-control" placeholder="Contoh: MI XYZ Tangerang">
                        <button type="button" class="btn btn-secondary" onclick="searchLocation()">
                            Cari
                        </button>
                    </div>
                    <small class="text-muted">
                        Gunakan pencarian alamat, lalu klik titik lokasi sekolah pada peta.
                    </small>
                </div>

                <div id="map" style="height: 430px; border-radius: 18px; border: 1px solid #dbe3ef; overflow:hidden;"></div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <label class="form-label">Latitude</label>
                        <input type="text" 
                               id="latitude" 
                               name="latitude" 
                               class="form-control" 
                               value="{{ old('latitude', $location->latitude ?? '-6.2000000') }}" 
                               required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Longitude</label>
                        <input type="text" 
                               id="longitude" 
                               name="longitude" 
                               class="form-control" 
                               value="{{ old('longitude', $location->longitude ?? '106.8166660') }}" 
                               required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Radius Meter</label>
                        <input type="number" 
                               id="radius_meter" 
                               name="radius_meter" 
                               class="form-control" 
                               value="{{ old('radius_meter', $location->radius_meter ?? 150) }}" 
                               min="10" 
                               required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-3">
                    Simpan Lokasi
                </button>
            </form>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
    let defaultLat = parseFloat(document.getElementById('latitude').value) || -6.2000000;
    let defaultLng = parseFloat(document.getElementById('longitude').value) || 106.8166660;
    let defaultRadius = parseInt(document.getElementById('radius_meter').value) || 150;

    let map = L.map('map').setView([defaultLat, defaultLng], 17);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png', {
        maxZoom: 20,
        subdomains: 'abcd',
        attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
    }).addTo(map);

    let marker = L.marker([defaultLat, defaultLng], {
        draggable: true
    }).addTo(map);

    let circle = L.circle([defaultLat, defaultLng], {
        radius: defaultRadius
    }).addTo(map);

    function updateForm(lat, lng) {
        document.getElementById('latitude').value = lat.toFixed(7);
        document.getElementById('longitude').value = lng.toFixed(7);

        marker.setLatLng([lat, lng]);
        circle.setLatLng([lat, lng]);
    }

    marker.on('dragend', function(e) {
        let position = marker.getLatLng();
        updateForm(position.lat, position.lng);
    });

    map.on('click', function(e) {
        updateForm(e.latlng.lat, e.latlng.lng);
    });

    document.getElementById('radius_meter').addEventListener('input', function() {
        let radius = parseInt(this.value) || 150;
        circle.setRadius(radius);
    });

    function searchLocation() {
        let query = document.getElementById('searchBox').value;

        if (!query) {
            alert('Masukkan alamat atau nama sekolah terlebih dahulu.');
            return;
        }

        let url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.length === 0) {
                    alert('Lokasi tidak ditemukan. Coba gunakan kata kunci lain.');
                    return;
                }

                let lat = parseFloat(data[0].lat);
                let lng = parseFloat(data[0].lon);

                map.setView([lat, lng], 18);
                updateForm(lat, lng);
            })
            .catch(() => {
                alert('Gagal mencari lokasi. Periksa koneksi internet.');
            });
    }
</script>
@endsection