<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class PushEndpointValidator
{
    public function validate(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        $parts = parse_url($endpoint);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? null) !== 'https'
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
            || filter_var($host, FILTER_VALIDATE_IP)
            || ! $this->isAllowedHost($host)
            || ! $this->resolvesOnlyToPublicAddresses($host)) {
            throw ValidationException::withMessages([
                'endpoint' => 'Endpoint Web Push tidak didukung atau tidak aman.',
            ]);
        }

        return $endpoint;
    }

    private function isAllowedHost(string $host): bool
    {
        foreach (config('pwa.allowed_push_hosts', []) as $allowedHost) {
            $allowedHost = strtolower(trim((string) $allowedHost));
            if ($allowedHost === $host) {
                return true;
            }
            if (str_starts_with($allowedHost, '*.')
                && str_ends_with($host, substr($allowedHost, 1))
                && $host !== substr($allowedHost, 2)) {
                return true;
            }
        }

        return false;
    }

    private function resolvesOnlyToPublicAddresses(string $host): bool
    {
        $addresses = gethostbynamel($host) ?: [];
        if (function_exists('dns_get_record')) {
            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return $addresses !== [] && collect($addresses)->every(
            fn (string $address): bool => filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false
        );
    }
}
