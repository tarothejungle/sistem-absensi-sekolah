<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Throwable;

class GenerateVapidKeys extends Command
{
    protected $signature = 'pwa:generate-vapid';

    protected $description = 'Generate VAPID keys for Web Push notifications';

    public function handle(): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (Throwable $exception) {
            $this->error('Gagal membuat VAPID key melalui OpenSSL PHP: '.$exception->getMessage());
            $this->line('Pastikan ekstensi OpenSSL aktif dan konfigurasi OpenSSL PHP dapat membuat kurva prime256v1.');
            $this->line('Sebagai alternatif, jalankan perintah ini di Linux/WSL atau gunakan generator VAPID tepercaya lalu isi .env.');

            return self::FAILURE;
        }

        $this->line('PUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('PUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->warn('Simpan private key hanya di .env/server secret manager dan jangan commit ke repository.');

        return self::SUCCESS;
    }
}
