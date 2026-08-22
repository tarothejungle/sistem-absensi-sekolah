<?php

namespace Tests\Feature;

use App\Jobs\SendWebPushNotification;
use App\Models\AttendanceSession;
use App\Models\PushSubscription;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AppNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PwaPushSubscriptionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('pwa.push_enabled', true);
        config()->set('pwa.vapid_public_key', 'test-public-key');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_authenticated_user_can_create_and_remove_push_subscription(): void
    {
        Queue::fake();
        $user = $this->createUser();
        $payload = $this->subscriptionPayload();

        $this->actingAs($user)
            ->postJson(route('pwa.push.subscribe'), $payload)
            ->assertCreated()
            ->assertJson(['ok' => true]);

        Queue::assertPushed(SendWebPushNotification::class, fn ($job) => $job->userId === $user->id && $job->reminderId === null);

        $subscription = PushSubscription::firstOrFail();
        $this->assertSame($user->id, $subscription->user_id);
        $this->assertSame($payload['endpoint'], $subscription->endpoint);

        $this->actingAs($user)
            ->deleteJson(route('pwa.push.unsubscribe'), ['endpoint' => $payload['endpoint']])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_user_cannot_remove_another_users_subscription(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();
        $payload = $this->subscriptionPayload();

        $this->actingAs($owner)->postJson(route('pwa.push.subscribe'), $payload)->assertCreated();
        $this->actingAs($other)
            ->deleteJson(route('pwa.push.unsubscribe'), ['endpoint' => $payload['endpoint']])
            ->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    public function test_user_cannot_take_over_another_users_subscription(): void
    {
        Queue::fake();
        $owner = $this->createUser();
        $other = $this->createUser();
        $payload = $this->subscriptionPayload();

        $this->actingAs($owner)->postJson(route('pwa.push.subscribe'), $payload)->assertCreated();
        $this->actingAs($other)->postJson(route('pwa.push.subscribe'), $payload)->assertConflict();

        $this->assertSame($owner->id, PushSubscription::firstOrFail()->user_id);
    }

    public function test_push_subscription_rejects_untrusted_or_private_endpoints(): void
    {
        $user = $this->createUser();

        foreach (['https://127.0.0.1/push', 'https://internal.example.test/push', 'https://user@updates.push.services.mozilla.com/push'] as $endpoint) {
            $payload = $this->subscriptionPayload();
            $payload['endpoint'] = $endpoint;
            $this->actingAs($user)
                ->postJson(route('pwa.push.subscribe'), $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('endpoint');
        }
    }

    public function test_app_notification_dispatches_web_push_job_when_enabled(): void
    {
        Queue::fake();
        $user = $this->createUser();

        AppNotificationService::send($user->id, 'Judul', 'Pesan', 'info', route('dashboard'));

        Queue::assertPushed(SendWebPushNotification::class, fn ($job) => $job->userId === $user->id);
    }

    public function test_attendance_reminder_command_deduplicates_same_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-18 07:00:00', config('app.timezone')));
        Queue::fake();
        $user = $this->createUser();
        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => $user->name,
            'jabatan' => 'Guru',
        ]);
        $this->actingAs($user)
            ->postJson(route('pwa.push.subscribe'), $this->subscriptionPayload())
            ->assertCreated();
        $session = AttendanceSession::create([
            'nama_sesi' => 'Sesi PWA Test',
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '12:00:00',
            'toleransi_terlambat' => 15,
            'batas_check_in_mulai' => '06:30:00',
            'batas_check_in_selesai' => '07:30:00',
            'batas_check_out_mulai' => '12:00:00',
            'batas_check_out_selesai' => '13:00:00',
            'status' => 'aktif',
        ]);
        $teacher->attendanceSessions()->attach($session->id, ['is_primary' => true]);

        $this->artisan('notifications:send-attendance-reminders')->assertExitCode(0);
        $this->artisan('notifications:send-attendance-reminders')->assertExitCode(0);

        $this->assertDatabaseCount('push_attendance_reminders', 1);
        $reminderJobs = Queue::pushed(SendWebPushNotification::class, fn ($job) => $job->reminderId !== null);
        $this->assertCount(1, $reminderJobs);
    }

    private function createUser(): User
    {
        $user = User::create([
            'username' => uniqid('pwa_', true),
            'name' => 'Pengguna PWA Test',
            'email' => uniqid('pwa_', true).'@example.test',
            'password' => Hash::make('kata-sandi-aman'),
        ]);
        $user->forceFill(['role' => 'guru', 'status' => 'aktif'])->save();

        return $user;
    }

    private function subscriptionPayload(): array
    {
        return [
            'endpoint' => 'https://updates.push.services.mozilla.com/wpush/v2/'.bin2hex(random_bytes(8)),
            'keys' => [
                'p256dh' => str_repeat('a', 87),
                'auth' => str_repeat('b', 22),
            ],
            'content_encoding' => 'aes128gcm',
            'user_agent' => 'PHPUnit Browser',
        ];
    }
}
