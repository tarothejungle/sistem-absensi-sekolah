<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Proxy yang dipercaya oleh aplikasi.
     *
     * JANGAN memakai '*' di produksi: header X-Forwarded-For dapat dipalsukan
     * klien sehingga throttle login bisa dilewati dan IP pada login activity
     * menjadi tidak dapat dipercaya. Isi TRUSTED_PROXIES dengan CIDR proxy
     * nyata (dipisah koma) hanya bila aplikasi memang berada di belakang proxy.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * Header proxy yang dipercaya.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    public function __construct()
    {
        $configured = trim((string) config('security.trusted_proxies', ''));

        if ($configured === '') {
            $this->proxies = null;

            return;
        }

        $this->proxies = array_values(array_filter(array_map(
            'trim',
            explode(',', $configured)
        )));
    }
}
