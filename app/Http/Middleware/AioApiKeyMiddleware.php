<?php

namespace App\Http\Middleware;

use App\Models\IntegrationApiAuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AioApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $requestId = $this->requestId($request);
        $keyVersion = null;

        if ((bool) config('services.aio.require_https', false) && ! $request->secure()) {
            return $this->respondAndAudit($request, $requestId, null, $startedAt, response()->json([
                'error' => ['code' => 'https_required', 'message' => 'Integrasi AIO wajib menggunakan HTTPS.'],
            ], 426));
        }

        $currentKey = (string) config('services.aio.api_key', '');
        $previousKey = (string) config('services.aio.previous_api_key', '');
        if ($currentKey === '') {
            Log::warning('AIO integration API accessed but current API key is not configured');

            return $this->respondAndAudit($request, $requestId, null, $startedAt, response()->json([
                'error' => ['code' => 'api_key_not_configured', 'message' => 'Integrasi AIO belum dikonfigurasi di server.'],
            ], 503));
        }

        $providedKey = (string) $request->header('X-API-Key', '');
        if ($providedKey !== '' && hash_equals($currentKey, $providedKey)) {
            $keyVersion = 'current';
        } elseif ($providedKey !== '' && $previousKey !== '' && hash_equals($previousKey, $providedKey)) {
            $keyVersion = 'previous';
        } else {
            Log::warning('AIO integration API authentication failed', [
                'request_id' => $requestId,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return $this->respondAndAudit($request, $requestId, null, $startedAt, response()->json([
                'error' => ['code' => 'invalid_api_key', 'message' => 'API key tidak valid.'],
            ], 401));
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->audit($request, $requestId, $keyVersion, 500, $startedAt);
            throw $exception;
        }

        return $this->respondAndAudit($request, $requestId, $keyVersion, $startedAt, $response);
    }

    private function respondAndAudit(
        Request $request,
        string $requestId,
        ?string $keyVersion,
        float $startedAt,
        Response $response
    ): Response {
        $response->headers->set('X-Request-ID', $requestId);
        $this->audit($request, $requestId, $keyVersion, $response->getStatusCode(), $startedAt);

        return $response;
    }

    private function audit(Request $request, string $requestId, ?string $keyVersion, int $status, float $startedAt): void
    {
        try {
            if (! Schema::hasTable('integration_api_audit_logs')) {
                return;
            }
            IntegrationApiAuditLog::create([
                'integration' => 'aio',
                'request_id' => $requestId,
                'method' => $request->method(),
                'path' => $request->path(),
                'ip_address' => $request->ip(),
                'key_version' => $keyVersion,
                'response_status' => $status,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to persist AIO API audit log', [
                'request_id' => $requestId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function requestId(Request $request): string
    {
        $provided = (string) $request->header('X-Request-ID', '');

        return Str::isUuid($provided) ? $provided : (string) Str::uuid();
    }
}
