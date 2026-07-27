<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create-super';

    protected $description = 'Create the first super admin using secure interactive prompts';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Perintah ini wajib dijalankan secara interaktif.');

            return self::FAILURE;
        }

        $data = [
            'nip' => trim((string) $this->ask('NIP super admin')),
            'name' => trim((string) $this->ask('Nama lengkap')),
            'email' => trim((string) $this->ask('Email (opsional)')),
            'password' => (string) $this->secret('Password'),
            'password_confirmation' => (string) $this->secret('Ulangi password'),
        ];

        $validator = Validator::make($data, [
            'nip' => ['required', 'string', 'max:30', 'unique:users,nip'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($data): void {
            $user = new User([
                'nip' => $data['nip'],
                'name' => $data['name'],
                'email' => $data['email'] !== '' ? $data['email'] : null,
                'password' => $data['password'],
            ]);
            $user->role = 'super_admin';
            $user->status = 'aktif';
            $user->save();

            Teacher::create([
                'user_id' => $user->id,
                'nama_lengkap' => $data['name'],
                'jabatan' => 'Super Admin',
            ]);
        });

        $this->info('Super admin berhasil dibuat.');

        return self::SUCCESS;
    }
}
