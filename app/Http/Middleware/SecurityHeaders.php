<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Menambahkan header keamanan HTTP pada setiap response.
     *
     * Content-Security-Policy disusun agar tetap kompatibel dengan aset yang
     * dipakai aplikasi: Bootstrap/Chart.js (jsDelivr, unpkg), Google Fonts,
     * serta kamera/canvas (data: & blob:) untuk fitur absensi wajah.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        // Content-Security-Policy dapat dimatikan via env bila perlu debugging.
        if (config('security.csp_enabled', true)) {
            $response->headers->set(
                'Content-Security-Policy',
                $this->contentSecurityPolicy($nonce)
            );
        }

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(self), geolocation=(self), microphone=(), payment=(), usb=()'
        );

        // HSTS hanya bermakna (dan aman) di koneksi HTTPS.
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $directives = [
            "default-src 'self'",
            // Script inline yang masih diperlukan template menggunakan nonce.
            "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' https://cdn.jsdelivr.net https://unpkg.com https://challenges.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://unpkg.com",
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:",
            // data:/blob: untuk preview foto wajah dari kamera; host peta untuk
            // tile Leaflet & ikon marker di menu Setting Lokasi Sekolah.
            "img-src 'self' data: blob: https://unpkg.com https://*.basemaps.cartocdn.com https://*.tile.openstreetmap.org",
            "media-src 'self' blob:",
            // Nominatim dipakai fitur "Cari alamat" pada menu Setting Lokasi.
            "connect-src 'self' https://nominatim.openstreetmap.org https://cdn.jsdelivr.net https://unpkg.com https://challenges.cloudflare.com",
            "manifest-src 'self'",
            "worker-src 'self'",
            "frame-src 'self' https://challenges.cloudflare.com",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ];

        return implode('; ', $directives);
    }
}
