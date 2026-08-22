<?php

return [
    'csp_enabled' => env('SECURITY_CSP_ENABLED', true),

    /*
    | Daftar CIDR proxy yang dipercaya, dipisah koma. Biarkan kosong bila
    | aplikasi tidak berada di belakang reverse proxy. Jangan pakai '*'.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    /*
    | Dibaca lewat config (bukan env() langsung) agar tetap berfungsi
    | setelah `php artisan config:cache`.
    */
    'force_https' => env('FORCE_HTTPS', false),
];
