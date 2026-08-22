<div class="pwa-offline-banner" role="status" aria-live="polite">Koneksi terputus. Absensi dan perubahan data membutuhkan koneksi internet.</div>
@php
    $pwaClientConfig = [
        'enabled' => (bool) config('pwa.enabled', true),
        'serviceWorkerUrl' => asset('sw.js'),
        'pushEnabled' => (bool) config('pwa.push_enabled', false),
        'vapidPublicKey' => config('pwa.vapid_public_key'),
        'subscribeUrl' => route('pwa.push.subscribe'),
        'unsubscribeUrl' => route('pwa.push.unsubscribe'),
    ];
@endphp
<script nonce='{{ request()->attributes->get('csp_nonce') }}'>
    window.pwaConfig = @json($pwaClientConfig);
</script>
<script nonce='{{ request()->attributes->get('csp_nonce') }}' src="{{ asset('js/pwa.js') }}?v=3" defer></script>
