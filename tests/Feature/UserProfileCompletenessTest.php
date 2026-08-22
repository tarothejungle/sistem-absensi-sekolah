<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use App\Services\UserProfileCompletenessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserProfileCompletenessTest extends TestCase
{
    use DatabaseTransactions;

    private UserProfileCompletenessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UserProfileCompletenessService::class);
    }

    public function test_profile_with_all_fields_is_complete(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();

        $result = $this->service->check($user);

        $this->assertTrue($result['is_complete']);
        $this->assertEmpty($result['missing']);
        $this->assertSame(100, $result['percentage']);
    }

    public function test_profile_missing_photo_is_incomplete(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();
        $user->update(['profile_photo' => null]);

        $result = $this->service->check($user);

        $this->assertFalse($result['is_complete']);
        $this->assertArrayHasKey('profile_photo', $result['missing']);
        $this->assertSame('Foto Profil', $result['missing']['profile_photo']['label']);
    }

    public function test_profile_missing_tempat_lahir_is_incomplete(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();
        $user->update(['tempat_lahir' => null]);

        $result = $this->service->check($user);

        $this->assertFalse($result['is_complete']);
        $this->assertArrayHasKey('tempat_lahir', $result['missing']);
    }

    public function test_profile_missing_tanggal_lahir_is_incomplete(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();
        $user->update(['tanggal_lahir' => null]);

        $result = $this->service->check($user);

        $this->assertFalse($result['is_complete']);
        $this->assertArrayHasKey('tanggal_lahir', $result['missing']);
    }

    public function test_profile_missing_pendidikan_is_incomplete(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();
        $user->update(['pendidikan_terakhir' => null]);

        $result = $this->service->check($user);

        $this->assertFalse($result['is_complete']);
        $this->assertArrayHasKey('pendidikan_terakhir', $result['missing']);
    }

    public function test_profile_missing_jenis_kelamin_is_incomplete(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();
        $teacher->update(['jenis_kelamin' => null]);

        $result = $this->service->check($user);

        $this->assertFalse($result['is_complete']);
        $this->assertArrayHasKey('jenis_kelamin', $result['missing']);
    }

    public function test_profile_missing_no_hp_is_incomplete(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();
        $teacher->update(['no_hp' => null]);

        $result = $this->service->check($user);

        $this->assertFalse($result['is_complete']);
        $this->assertArrayHasKey('no_hp', $result['missing']);
    }

    public function test_profile_missing_email_is_incomplete(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();
        $user->update(['email' => null]);

        $result = $this->service->check($user);

        $this->assertFalse($result['is_complete']);
        $this->assertArrayHasKey('email', $result['missing']);
    }

    public function test_profile_missing_jabatan_is_incomplete(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();
        $teacher->update(['jabatan' => null]);

        $result = $this->service->check($user);

        $this->assertFalse($result['is_complete']);
        $this->assertArrayHasKey('jabatan', $result['missing']);
    }

    public function test_guru_requires_mata_pelajaran(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();
        $teacher->update(['mata_pelajaran' => null]);

        $result = $this->service->check($user);

        $this->assertFalse($result['is_complete']);
        $this->assertArrayHasKey('mata_pelajaran', $result['missing']);
    }

    public function test_tenaga_kependidikan_does_not_require_mata_pelajaran(): void
    {
        [$teacher, $user] = $this->createTendikWithFullProfile();
        $teacher->update(['mata_pelajaran' => null]);

        $result = $this->service->check($user);

        $this->assertTrue($result['is_complete']);
        $this->assertArrayNotHasKey('mata_pelajaran', $result['missing']);
    }

    public function test_bendahara_does_not_require_mata_pelajaran(): void
    {
        [$teacher, $user] = $this->createBendaharaWithFullProfile();
        $teacher->update(['mata_pelajaran' => null]);

        $result = $this->service->check($user);

        $this->assertTrue($result['is_complete']);
    }

    public function test_kepala_sekolah_does_not_require_mata_pelajaran(): void
    {
        [$teacher, $user] = $this->createKepalaSekolahWithFullProfile();
        $teacher->update(['mata_pelajaran' => null]);

        $result = $this->service->check($user);

        $this->assertTrue($result['is_complete']);
    }

    public function test_whitespace_only_name_is_incomplete(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();
        $teacher->update(['nama_lengkap' => '   ']);

        $result = $this->service->check($user);

        $this->assertFalse($result['is_complete']);
        $this->assertArrayHasKey('nama_lengkap', $result['missing']);
    }

    public function test_whitespace_only_instansi_is_incomplete(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();
        $user->update(['instansi_mengajar' => '   ']);

        $result = $this->service->check($user);

        $this->assertFalse($result['is_complete']);
        $this->assertArrayHasKey('instansi_mengajar', $result['missing']);
    }

    public function test_is_complete_method_returns_true_when_complete(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();

        $this->assertTrue($this->service->isComplete($user));
    }

    public function test_is_complete_method_returns_false_when_incomplete(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();
        $user->update(['profile_photo' => null]);

        $this->assertFalse($this->service->isComplete($user));
    }

    public function test_missing_fields_returns_labels(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();
        $user->update([
            'profile_photo' => null,
            'tempat_lahir' => null,
            'tanggal_lahir' => null,
            'pendidikan_terakhir' => null,
        ]);

        $missing = $this->service->missingFields($user);

        $this->assertCount(4, $missing);
        $this->assertArrayHasKey('profile_photo', $missing);
        $this->assertArrayHasKey('tempat_lahir', $missing);
        $this->assertArrayHasKey('tanggal_lahir', $missing);
        $this->assertArrayHasKey('pendidikan_terakhir', $missing);
    }

    public function test_filled_count_and_percentage_are_correct(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();
        $user->update([
            'profile_photo' => null,
            'tempat_lahir' => null,
        ]);

        $result = $this->service->check($user);

        // Guru has 11 required fields (10 base + mata_pelajaran)
        $this->assertSame(11, $result['total_count']);
        $this->assertSame(9, $result['filled_count']);
        $this->assertSame(82, $result['percentage']);
    }

    public function test_instansi_fallback_does_not_fake_completeness(): void
    {
        [$teacher, $user] = $this->createGuruWithFullProfile();
        $user->update(['instansi_mengajar' => null]);

        $result = $this->service->check($user);

        $this->assertFalse($result['is_complete']);
        $this->assertArrayHasKey('instansi_mengajar', $result['missing']);
    }

    private function createGuruWithFullProfile(): array
    {
        $user = User::create([
            'username' => uniqid('user_', true),
            'name' => 'Guru Lengkap',
            'email' => uniqid('guru_', true).'@example.test',
            'password' => Hash::make('password'),
            'profile_photo' => 'profile_photo/test.jpg',
            'instansi_mengajar' => 'MI Lantaburo',
            'tempat_lahir' => 'Tangerang',
            'tanggal_lahir' => '1990-01-15',
            'pendidikan_terakhir' => 'S1',
        ]);

        $user->forceFill(['role' => 'guru', 'status' => 'aktif'])->save();

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => 'Guru Lengkap',
            'jenis_kelamin' => 'L',
            'no_hp' => '081234567890',
            'jabatan' => 'Guru Kelas',
            'mata_pelajaran' => 'Matematika',
        ]);

        return [$teacher, $user];
    }

    private function createTendikWithFullProfile(): array
    {
        $user = User::create([
            'username' => uniqid('user_', true),
            'name' => 'Tendik Lengkap',
            'email' => uniqid('tendik_', true).'@example.test',
            'password' => Hash::make('password'),
            'profile_photo' => 'profile_photo/test.jpg',
            'instansi_mengajar' => 'MI Lantaburo',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1992-05-20',
            'pendidikan_terakhir' => 'S1',
        ]);

        $user->forceFill(['role' => 'tenaga_kependidikan', 'status' => 'aktif'])->save();

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => 'Tendik Lengkap',
            'jenis_kelamin' => 'P',
            'no_hp' => '081234567891',
            'jabatan' => 'Operator',
        ]);

        return [$teacher, $user];
    }

    private function createBendaharaWithFullProfile(): array
    {
        $user = User::create([
            'username' => uniqid('user_', true),
            'name' => 'Bendahara Lengkap',
            'email' => uniqid('bend_', true).'@example.test',
            'password' => Hash::make('password'),
            'profile_photo' => 'profile_photo/test.jpg',
            'instansi_mengajar' => 'MI Lantaburo',
            'tempat_lahir' => 'Bogor',
            'tanggal_lahir' => '1988-03-10',
            'pendidikan_terakhir' => 'S2',
        ]);

        $user->forceFill(['role' => 'bendahara', 'status' => 'aktif'])->save();

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => 'Bendahara Lengkap',
            'jenis_kelamin' => 'L',
            'no_hp' => '081234567892',
            'jabatan' => 'Bendahara',
        ]);

        return [$teacher, $user];
    }

    private function createKepalaSekolahWithFullProfile(): array
    {
        $user = User::create([
            'username' => uniqid('user_', true),
            'name' => 'Kepsek Lengkap',
            'email' => uniqid('ks_', true).'@example.test',
            'password' => Hash::make('password'),
            'profile_photo' => 'profile_photo/test.jpg',
            'instansi_mengajar' => 'MI Lantaburo',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1985-07-01',
            'pendidikan_terakhir' => 'S2',
        ]);

        $user->forceFill(['role' => 'kepala_sekolah', 'status' => 'aktif'])->save();

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => 'Kepsek Lengkap',
            'jenis_kelamin' => 'L',
            'no_hp' => '081234567893',
            'jabatan' => 'Kepala Sekolah',
        ]);

        return [$teacher, $user];
    }
}
