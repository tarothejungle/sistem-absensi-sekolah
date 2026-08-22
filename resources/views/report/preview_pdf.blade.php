<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Preview Laporan Absensi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.pwa-head')
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f5f6f8; color: #172033; font-family: Arial, sans-serif; }
        .preview-header { min-height: 64px; background: #001362; color: #fff; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 24px; }
        .preview-title { font-size: 18px; font-weight: 700; }
        .preview-actions { display: flex; gap: 8px; }
        .preview-button { display: inline-flex; min-height: 40px; align-items: center; justify-content: center; border: 1px solid transparent; padding: 8px 14px; color: #fff; background: #198754; text-decoration: none; font-weight: 600; }
        .preview-button.secondary { background: #fff; color: #172033; border-color: #d9deea; }
        .preview-content { height: calc(100vh - 64px); }
        iframe { width: 100%; height: 100%; border: 0; background: #fff; }
        @media (max-width: 600px) { .preview-header { align-items: stretch; flex-direction: column; } .preview-actions { width: 100%; } .preview-button { flex: 1; } .preview-content { height: calc(100vh - 120px); } }
    </style>
</head>
<body>
    <header class="preview-header">
        <div class="preview-title">Preview Laporan Absensi</div>
        <div class="preview-actions">
            <a href="{{ $downloadUrl }}" class="preview-button">Unduh PDF</a>
            <button id="close-preview" type="button" class="preview-button secondary">Tutup</button>
        </div>
    </header>
    <main class="preview-content">
        <iframe src="{{ $streamUrl }}" title="Preview laporan absensi"></iframe>
    </main>
    @vite('resources/js/report-preview.js')
</body>
</html>
