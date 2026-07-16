<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use Notifiable;

    // Catatan keamanan: 'role' dan 'status' sengaja TIDAK di-fillable agar tidak
    // pernah bisa diubah lewat mass-assignment (mis. $user->update($request->all())).
    // Keduanya hanya boleh diset eksplisit via forceFill() di endpoint admin.
    protected $fillable = ['nip', 'name', 'email', 'profile_photo', 'instansi_mengajar', 'tempat_lahir', 'tanggal_lahir', 'pendidikan_terakhir', 'password', 'last_login'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['last_login' => 'datetime', 'password' => 'hashed'];

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }
}
