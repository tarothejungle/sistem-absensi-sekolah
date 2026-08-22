<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionExpirationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_heartbeat_keeps_an_active_authenticated_session_alive(): void
    {
        config(['session.idle_timeout' => 10]);
        $user = $this->createUser();
        $previousActivity = time() - 300;

        $response = $this
            ->actingAs($user)
            ->withSession(['auth.last_activity_at' => $previousActivity])
            ->postJson(route('session.heartbeat'));

        $response->assertNoContent();
        $this->assertAuthenticatedAs($user);
        $this->assertGreaterThan($previousActivity, session('auth.last_activity_at'));
    }

    public function test_server_rejects_the_first_request_after_idle_timeout(): void
    {
        config(['session.idle_timeout' => 10]);
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->withSession(['auth.last_activity_at' => time() - 601])
            ->postJson(route('session.heartbeat'));

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Sesi Anda telah berakhir karena tidak ada aktivitas.',
                'redirect' => route('login', ['expired' => 1]),
            ]);

        $this->assertGuest();
    }

    public function test_custom_419_page_has_login_recovery_and_auto_redirect(): void
    {
        Route::middleware('web')
            ->get('/testing/session-expired', fn () => abort(419));

        $user = $this->createUser();
        $recallerCookie = auth()->guard()->getRecallerName();
        $response = $this->actingAs($user)->get('/testing/session-expired');

        $response
            ->assertStatus(419)
            ->assertSee('Sesi Anda telah berakhir')
            ->assertSee('Kembali ke Halaman Login')
            ->assertSee('redirectCountdown')
            ->assertSee(route('login', ['expired' => 1]), false);

        $this->assertGuest();
        $expiredCookies = collect($response->headers->getCookies())
            ->filter(fn ($cookie) => $cookie->getExpiresTime() < time())
            ->map(fn ($cookie) => $cookie->getName())
            ->all();
        $this->assertContains($recallerCookie, $expiredCookies);
    }

    public function test_login_page_explains_that_the_session_expired(): void
    {
        $this->get(route('login', ['expired' => 1]))
            ->assertOk()
            ->assertSee('Sesi Anda telah berakhir karena tidak ada aktivitas. Silakan login kembali.');
    }

    public function test_password_change_invalidates_a_session_holding_the_old_hash(): void
    {
        $user = $this->createUser();
        $oldHash = $user->password;
        $user->forceFill(['password' => Hash::make('PasswordBaru123!')])->save();

        $this->actingAs($user->fresh())
            ->withSession([
                'auth.last_activity_at' => time(),
                'auth.password_user_id' => $user->id,
                'auth.password_hash' => $oldHash,
            ])
            ->postJson(route('session.heartbeat'))
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Sesi Anda telah berakhir karena password berubah. Silakan login kembali.');

        $this->assertGuest();
    }

    private function createUser(): User
    {
        $user = User::create([
            'username' => 'SESS'.strtoupper(substr(uniqid(), -10)),
            'name' => 'Pengguna Session Test',
            'email' => uniqid('session_', true).'@example.test',
            'password' => Hash::make('kata-sandi-aman'),
        ]);

        $user->forceFill([
            'role' => 'guru',
            'status' => 'aktif',
        ])->save();

        return $user;
    }
}
