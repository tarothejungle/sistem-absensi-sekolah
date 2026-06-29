@php
    $currentPerPage = request('per_page', $paginator->perPage());
    $totalData = $paginator->total();
    $perPageOptions = [7, 14, 21, 28, 35, 70];
@endphp

<form method="GET" class="per-page-form-custom">
    @foreach(request()->except('per_page', 'page') as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach

    <span class="per-page-label-custom">Menampilkan</span>

    <select name="per_page" class="per-page-select-custom" onchange="this.form.submit()">
        @foreach($perPageOptions as $option)
            <option value="{{ $option }}" {{ (int) $currentPerPage === (int) $option ? 'selected' : '' }}>
                {{ $option }}
            </option>
        @endforeach
    </select>

    <span class="per-page-label-custom">
        data per halaman dari total {{ $totalData }} data
    </span>
</form>