<?php

namespace Tests\Feature;

use App\Models\LoginActivity;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.turnstile.enabled', true);
    }

    public function test_login_page_exposes_accessible_autofill_controls(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('autocomplete="username"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('autofocus', false)
            ->assertSee('aria-label="Tampilkan kata sandi"', false)
            ->assertSee('Selesaikan verifikasi keamanan untuk mengaktifkan tombol masuk.');
    }

    public function test_required_login_fields_return_helpful_inline_messages(): void
    {
        $this->post(route('login.process'))
            ->assertSessionHasErrors([
                'username' => 'Masukkan username Anda.',
                'password' => 'Masukkan kata sandi Anda.',
                'cf-turnstile-response' => 'Silakan selesaikan verifikasi keamanan terlebih dahulu.',
            ]);
    }

    public function test_login_page_omits_turnstile_when_it_is_disabled(): void
    {
        config()->set('services.turnstile.enabled', false);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('cf-turnstile', false)
            ->assertDontSee('Selesaikan verifikasi keamanan untuk mengaktifkan tombol masuk.')
            ->assertDontSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false);
    }

    public function test_active_user_can_login_without_turnstile_when_it_is_disabled(): void
    {
        config()->set('services.turnstile.enabled', false);
        Http::preventStrayRequests();

        $user = $this->createUser();

        $this->post(route('login.process'), [
            'username' => $user->username,
            'password' => 'kata-sandi-aman',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_still_show_the_credentials_message_when_turnstile_is_disabled(): void
    {
        config()->set('services.turnstile.enabled', false);
        Http::preventStrayRequests();

        $user = $this->createUser();

        $this->from(route('login'))->post(route('login.process'), [
            'username' => $user->username,
            'password' => 'kata-sandi-salah',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'credentials' => 'Username atau kata sandi tidak cocok. Periksa kembali lalu coba lagi.',
            ])
            ->assertSessionHasInput('username', $user->username);

        $this->assertGuest();
    }

    public function test_active_user_can_login_and_activity_is_recorded(): void
    {
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
            ]),
        ]);

        $user = $this->createUser();

        $this->post(route('login.process'), [
            'username' => $user->username,
            'password' => 'kata-sandi-aman',
            'cf-turnstile-response' => 'valid-token',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->last_login);
        $this->assertDatabaseHas('login_activities', [
            'user_id' => $user->id,
            'activity' => 'login ke dalam aplikasi',
        ]);
    }

    public function test_invalid_credentials_return_neutral_message_and_preserve_username(): void
    {
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
            ]),
        ]);

        $user = $this->createUser();

        $this->from(route('login'))->post(route('login.process'), [
            'username' => $user->username,
            'password' => 'kata-sandi-salah',
            'cf-turnstile-response' => 'valid-token',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'credentials' => 'Username atau kata sandi tidak cocok. Periksa kembali lalu coba lagi.',
            ])
            ->assertSessionHasInput('username', $user->username);

        $this->assertGuest();
    }

    public function test_inactive_account_uses_same_neutral_credentials_message(): void
    {
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
            ]),
        ]);

        $user = $this->createUser('nonaktif');

        $this->post(route('login.process'), [
            'username' => $user->username,
            'password' => 'kata-sandi-aman',
            'cf-turnstile-response' => 'valid-token',
        ])->assertSessionHasErrors('credentials');

        $this->assertGuest();
    }

    public function test_failed_turnstile_returns_helpful_message_without_authenticating(): void
    {
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => false,
                'error-codes' => ['timeout-or-duplicate'],
            ]),
        ]);

        $user = $this->createUser();

        $this->post(route('login.process'), [
            'username' => $user->username,
            'password' => 'kata-sandi-aman',
            'cf-turnstile-response' => 'expired-token',
        ])->assertSessionHasErrors([
            'cf-turnstile-response' => 'Verifikasi keamanan gagal atau telah kedaluwarsa. Silakan coba kembali.',
        ]);

        $this->assertGuest();
        $this->assertSame(0, LoginActivity::query()->where('user_id', $user->id)->count());
    }

    public function test_enabled_turnstile_fails_closed_when_the_service_is_unavailable(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Turnstile unavailable');
        });

        $user = $this->createUser();

        $this->post(route('login.process'), [
            'username' => $user->username,
            'password' => 'kata-sandi-aman',
            'cf-turnstile-response' => 'unverified-token',
        ])->assertSessionHasErrors([
            'cf-turnstile-response' => 'Layanan verifikasi keamanan sedang tidak dapat dihubungi. Silakan coba kembali.',
        ]);

        $this->assertGuest();
        $this->assertSame(0, LoginActivity::query()->where('user_id', $user->id)->count());
    }

    public function test_production_rejects_disabled_turnstile_configuration(): void
    {
        $originalEnvironment = $this->app->environment();

        $this->app['env'] = 'production';
        config()->set([
            'app.debug' => false,
            'app.url' => 'https://absensi.example.test',
            'security.force_https' => true,
            'session.secure' => true,
            'session.encrypt' => true,
            'biometrics.liveness_required' => true,
            'services.turnstile.enabled' => false,
        ]);

        try {
            (new AppServiceProvider($this->app))->boot();
            $this->fail('Konfigurasi production tanpa Turnstile seharusnya ditolak.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('TURNSTILE_ENABLED=true', $exception->getMessage());
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    public function test_authenticated_user_cannot_open_or_submit_login(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)
            ->post(route('login.process'))
            ->assertRedirect(route('dashboard'));
    }

    private function createUser(string $status = 'aktif'): User
    {
        $user = User::create([
            'username' => uniqid('login_', true),
            'name' => 'Pengguna Login Test',
            'email' => uniqid('login_', true).'@example.test',
            'password' => Hash::make('kata-sandi-aman'),
        ]);

        $user->forceFill([
            'role' => 'guru',
            'status' => $status,
        ])->save();

        return $user;
    }
}
