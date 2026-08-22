<?php

namespace Tests\Unit;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersTest extends TestCase
{
    public function test_script_csp_uses_a_request_nonce_without_unsafe_inline(): void
    {
        $container = new Container;
        $config = Mockery::mock(Repository::class);
        $config->shouldReceive('get')->with('security.csp_enabled', true)->andReturn(true);
        $container->instance('config', $config);
        Container::setInstance($container);

        $request = Request::create('/');
        $response = (new SecurityHeaders)->handle(
            $request,
            fn () => new Response('ok')
        );

        $nonce = $request->attributes->get('csp_nonce');
        $csp = (string) $response->headers->get('Content-Security-Policy');

        self::assertIsString($nonce);
        self::assertNotSame('', $nonce);
        self::assertStringContainsString("'nonce-{$nonce}'", $csp);
        self::assertStringContainsString("'strict-dynamic'", $csp);
        self::assertMatchesRegularExpression('/script-src[^;]*;(?:[^;]+;)*?/', $csp);
        self::assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Container::setInstance();

        parent::tearDown();
    }
}
