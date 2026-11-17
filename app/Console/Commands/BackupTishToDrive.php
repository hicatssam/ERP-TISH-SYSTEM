<?php

namespace App\Console\Commands;

use App\Services\Backup\TishDriveBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BackupTishToDrive extends Command
{
    protected $signature = 'backup:tish-drive {--dry-run : Validate settings and encrypted Drive remote without writing files}';
    protected $description = 'Back up the TISH ERP database, invoices, uploads and application to encrypted Google Drive';

    public function handle(TishDriveBackupService $backup): int
    {
        // Shared between manual and scheduled invocations, even if the
        // scheduler's separate withoutOverlapping mutex is not in use.
        $lock = Cache::lock('tish-erp:encrypted-drive-backup', 43200);
        if (! $lock->get()) {
            $this->error('Another TISH encrypted backup is already running.');
            return self::FAILURE;
        }

        try {
            $result = $backup->backup((bool) $this->option('dry-run'));
            if ($this->option('dry-run')) {
                $this->info('Backup configuration validated. No backup was created.');
            } else {
                $this->info('Encrypted Drive backup verified: '.$result['file']
                    .' ('.number_format($result['bytes']).' bytes, '.$result['files'].' files).');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            // Do not print full stack traces or environment secrets to console/email.
            Log::error('TISH encrypted backup failed', [
                'error_type' => get_class($e),
                'reason' => $e->getMessage(),
            ]);
            $this->error('TISH Drive backup failed. See secure application logs.');

            $email = (string) config('tish-backup.alert_email', '');
            if ($email !== '') {
                try {
                    Mail::raw(
                        'TISH ERP backup failed at '.now()->toDateTimeString()
                        .'. Check the server logs and backup scheduler immediately.',
                        static fn ($message) => $message->to($email)->subject('[TISH] Weekly backup FAILED')
                    );
                } catch (Throwable $notifyError) {
                    Log::error('TISH backup failure alert could not be sent', [
                        'error_type' => get_class($notifyError),
                    ]);
                }
            }

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
