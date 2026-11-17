<?php

namespace App\Console\Commands;

use App\Services\Backup\TishDriveBackupService;
use Illuminate\Console\Command;

class CleanupTishBackupStaging extends Command
{
    protected $signature = 'backup:tish-cleanup';
    protected $description = 'Remove abandoned plaintext backup workspaces older than 48 hours';

    public function handle(TishDriveBackupService $service): int
    {
        $removed = $service->cleanupStaleWorkspaces();
        $this->info('Removed abandoned backup workspaces: '.$removed);

        return self::SUCCESS;
    }
}
