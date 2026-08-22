<?php

namespace App\Jobs;

use App\Models\PushSubscription;
use App\Models\PushAttendanceReminder;
use App\Services\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class SendWebPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly int $userId,
        public readonly array $payload,
        public readonly ?int $reminderId = null,
    ) {
        $this->afterCommit();
    }

    public function handle(WebPushService $webPush): void
    {
        $attempted = 0;
        $delivered = 0;

        PushSubscription::query()
            ->where('user_id', $this->userId)
            ->orderBy('id')
            ->eachById(function (PushSubscription $subscription) use ($webPush, &$attempted, &$delivered): void {
                $attempted++;
                if ($webPush->send($subscription, $this->payload)) {
                    $delivered++;
                }
            });

        if ($this->reminderId && $delivered > 0) {
            PushAttendanceReminder::query()
                ->whereKey($this->reminderId)
                ->whereNull('sent_at')
                ->update(['sent_at' => now()]);
        }

        if ($this->reminderId && $attempted > 0 && $delivered === 0) {
            throw new RuntimeException('Tidak ada subscription push yang berhasil menerima notifikasi.');
        }
    }
}
