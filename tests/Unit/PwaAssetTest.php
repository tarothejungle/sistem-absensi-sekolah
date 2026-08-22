<?php

namespace Tests\Unit;

use Tests\TestCase;

class PwaAssetTest extends TestCase
{
    public function test_manifest_declares_installable_app_metadata(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('/login', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertContains(['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'], $manifest['icons']);
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertGreaterThan(0, filesize(public_path('favicon.ico')));

        foreach (['auth/forgot-password.blade.php', 'auth/reset-password.blade.php', 'errors/419.blade.php', 'report/preview_pdf.blade.php'] as $view) {
            $this->assertStringContainsString("@include('partials.pwa-head')", (string) file_get_contents(resource_path('views/'.$view)));
        }
    }

    public function test_service_worker_does_not_cache_mutating_requests(): void
    {
        $serviceWorker = (string) file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("request.method !== 'GET'", $serviceWorker);
        $this->assertStringContainsString("request.mode === 'navigate'", $serviceWorker);
        $this->assertStringContainsString("self.addEventListener('push'", $serviceWorker);
        $this->assertStringContainsString('target.origin !== self.location.origin', $serviceWorker);
        $this->assertStringContainsString("const VERSION = 'pwa-v3'", $serviceWorker);

        $pwaScript = (string) file_get_contents(public_path('js/pwa.js'));
        $this->assertStringContainsString('syncSubscription', $pwaScript);
        $this->assertStringContainsString("window.addEventListener('pageshow'", $pwaScript);
        $this->assertStringContainsString('display-mode: standalone', $pwaScript);
        $this->assertStringContainsString('Tambahkan ke layar utama', $pwaScript);
    }
}
