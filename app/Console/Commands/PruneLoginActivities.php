<?php

namespace App\Console\Commands;

use App\Models\LoginActivity;
use Illuminate\Console\Command;

class PruneLoginActivities extends Command
{
    protected $signature = 'login-activities:prune';

    protected $description = 'Delete login activities older than 24 hours';

    public function handle(): int
    {
        $deleted = LoginActivity::query()
            ->where('created_at', '<=', now()->subHours(24))
            ->delete();

        $this->info("{$deleted} aktivitas login kedaluwarsa dibersihkan.");

        return self::SUCCESS;
    }
}
