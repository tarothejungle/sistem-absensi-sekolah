<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Preview Laporan Absensi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link 
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" 
        rel="stylesheet"
    >

    <style>
        body {
            margin: 0;
            background: #f5f6f8;
            font-family: Arial, sans-serif;
        }

        .preview-header {
            height: 64px;
            background: #001362;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
        }

        .preview-title {
            font-size: 18px;
            font-weight: 600;
        }

        .preview-content {
            height: calc(100vh - 64px);
        }

        iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
        }
    </style>
</head>
<body>

    <div class="preview-header">
        <div class="preview-title">
            Preview Laporan Absensi
        </div>

        <div class="d-flex gap-2">
            <a href="{{ $downloadUrl }}" class="btn btn-success">
                Unduh PDF
            </a>

            <button onclick="window.close()" class="btn btn-light">
                Tutup
            </button>
        </div>
    </div>

    <div class="preview-content">
        <iframe src="data:application/pdf;base64,{{ $pdfBase64 }}"></iframe>
    </div>

</body>
</html>