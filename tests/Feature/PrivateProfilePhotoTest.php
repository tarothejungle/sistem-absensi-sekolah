<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateProfilePhotoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_and_super_admin_can_view_private_profile_photo(): void
    {
        Storage::fake('local');
        $owner = $this->createUser('guru', 'Pemilik Foto');
        $admin = $this->createUser('super_admin', 'Admin Foto');
        $path = 'profile_photos/'.$owner->id.'/profile.jpg';
        Storage::disk('local')->put($path, 'private-photo');
        $owner->forceFill(['profile_photo' => $path])->save();

        $ownerResponse = $this->actingAs($owner)->get(route('profile.photo.show', $owner));
        $ownerResponse->assertOk();
        $this->assertStringContainsString('private', (string) $ownerResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $ownerResponse->headers->get('Cache-Control'));

        $this->actingAs($admin)->get(route('profile.photo.show', $owner))->assertOk();
    }

    public function test_other_user_cannot_view_private_profile_photo(): void
    {
        Storage::fake('local');
        $owner = $this->createUser('guru', 'Pemilik Foto Privat');
        $other = $this->createUser('guru', 'Pengguna Lain');
        $path = 'profile_photos/'.$owner->id.'/profile.jpg';
        Storage::disk('local')->put($path, 'private-photo');
        $owner->forceFill(['profile_photo' => $path])->save();

        $this->actingAs($other)->get(route('profile.photo.show', $owner))->assertForbidden();
    }

    private function createUser(string $role, string $name): User
    {
        $user = User::create([
            'username' => uniqid('photo_', true),
            'name' => $name,
            'email' => uniqid('photo_', true).'@example.test',
            'password' => Hash::make('Password123!'),
        ]);
        $user->forceFill(['role' => $role, 'status' => 'aktif'])->save();

        return $user;
    }
}
