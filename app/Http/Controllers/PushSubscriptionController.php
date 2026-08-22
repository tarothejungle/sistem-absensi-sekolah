<?php

namespace App\Http\Controllers;

use App\Jobs\SendWebPushNotification;
use App\Models\PushSubscription;
use App\Support\PushEndpointValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PushSubscriptionController extends Controller
{
    public function publicKey(): JsonResponse
    {
        abort_unless(config('pwa.push_enabled'), 404);

        return response()->json([
            'public_key' => config('pwa.vapid_public_key'),
        ]);
    }

    public function store(Request $request, PushEndpointValidator $endpointValidator): JsonResponse
    {
        abort_unless(config('pwa.push_enabled'), 503, 'Push notification belum diaktifkan.');

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:4096', 'url', 'starts_with:https://'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:512'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', Rule::in(['aes128gcm', 'aesgcm'])],
            'user_agent' => ['nullable', 'string', 'max:500'],
        ]);

        $endpoint = $endpointValidator->validate($data['endpoint']);
        $existing = PushSubscription::where('endpoint_hash', PushSubscription::endpointHash($endpoint))->first();
        if ($existing && (int) $existing->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Endpoint push sudah terdaftar pada akun lain.',
            ], 409);
        }

        $shouldSendConfirmation = ! $existing;
        $subscription = PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::endpointHash($endpoint)],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $endpoint,
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
                'user_agent' => $data['user_agent'] ?? $request->userAgent(),
                'last_used_at' => now(),
            ]
        );

        if ($shouldSendConfirmation) {
            SendWebPushNotification::dispatch($request->user()->id, [
                'title' => 'Notifikasi perangkat aktif',
                'body' => 'Notifikasi absensi akan dikirim ke perangkat ini.',
                'type' => 'system',
                'url' => route('dashboard', [], false),
                'icon' => '/icons/icon-192.png',
                'badge' => '/icons/icon-192.png',
                'tag' => 'push-subscription-confirmed',
            ]);
        }

        return response()->json([
            'ok' => true,
            'subscription_id' => $subscription->id,
        ], $subscription->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:4096'],
        ]);

        PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', PushSubscription::endpointHash($data['endpoint']))
            ->delete();

        return response()->json(['ok' => true]);
    }
}
