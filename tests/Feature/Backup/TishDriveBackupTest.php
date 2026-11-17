<?php

namespace Tests\Feature\Backup;

use App\Services\Backup\TishDriveBackupService;
use Tests\TestCase;

class TishDriveBackupTest extends TestCase
{
    public function test_backup_is_disabled_by_default_until_encrypted_drive_is_configured(): void
    {
        config()->set('tish-backup.enabled', false);

        $this->artisan('backup:tish-drive', ['--dry-run' => true])
            ->assertExitCode(1);
    }

    public function test_disposable_folders_and_symlink_target_are_excluded(): void
    {
        foreach ([
            '.git', '.git/HEAD', 'vendor/autoload.php', 'node_modules/a.js',
            'storage/framework/cache/foo', 'storage/logs/laravel.log',
            'storage/app/private/tish-backups/temporary.zip',
            'bootstrap/cache/config.php', 'public/hot',
        ] as $file) {
            $this->assertTrue(TishDriveBackupService::excluded($file), $file);
        }
    }

    public function test_business_files_and_private_invoices_are_included(): void
    {
        foreach ([
            '.env', 'artisan', 'composer.json',
            'app/Models/Invoice.php', 'resources/views/customer-menu/invoice.blade.php',
            'public/assets/tish-paper/brand/tish-drive-hero.png',
            'public/storage/invoices/2026-01.pdf',
            'public/uploads/invoices/2026-01.pdf',
            'storage/app/private/invoices/2026-01.pdf',
            'storage/app/public/receipts/2026-01.pdf',
            'storage/app/private/customer-documents/document.pdf',
        ] as $file) {
            $this->assertFalse(TishDriveBackupService::excluded($file), $file);
        }
    }

    public function test_backup_runs_weekly_on_monday_through_laravel_scheduler(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString("command('backup:tish-drive')", $bootstrap);
        $this->assertStringContainsString("weeklyOn(1, '03:00')", $bootstrap);
        $this->assertStringContainsString("config('tish-backup.enabled')", $bootstrap);
        $this->assertStringContainsString('withoutOverlapping(360)', $bootstrap);
    }
}
