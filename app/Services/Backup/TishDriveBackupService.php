<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Portable weekly ERP snapshot (MySQL + application + public/private uploads).
 *
 * No secrets are passed on process command lines. Backups are uploaded ONLY to
 * a validated rclone crypt remote backed by Google Drive. Plaintext work files
 * are deleted in finally, including on upload/verification failures.
 */
class TishDriveBackupService
{
    public static function excluded(string $relativePath, bool $isDirectory = false): bool
    {
        $path = str_replace('\\', '/', ltrim($relativePath, '/'));
        $forbidden = (array) config('tish-backup.exclude_dirs', []);

        foreach ($forbidden as $excluded) {
            $excluded = trim(str_replace('\\', '/', $excluded), '/');
            if ($path === $excluded || str_starts_with($path, $excluded.'/')) {
                return true;
            }
        }

        // Symlinks are skipped by the iterator. A real public/storage directory
        // is NOT excluded, because it may contain user-managed attachments.
        return $path === '' || str_contains($path, "\0");
    }

    private function run(array $args, int $timeoutSeconds = 3600): string
    {
        $process = new Process($args, base_path());
        $process->setTimeout($timeoutSeconds);
        $process->run();

        if (! $process->isSuccessful()) {
            // Never log process command line: it may contain machine paths.
            throw new RuntimeException('Backup subprocess failed (exit code '.$process->getExitCode().'). Review the server backup log.');
        }

        return trim($process->getOutput());
    }

    private function remoteName(): string
    {
        $remote = (string) config('tish-backup.remote');
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,60}$/D', $remote)) {
            throw new RuntimeException('Backup remote name is invalid.');
        }

        return $remote;
    }

    private function destination(): string
    {
        $folder = trim((string) config('tish-backup.folder'), '/');
        if (! preg_match('/^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*$/D', $folder)) {
            throw new RuntimeException('Backup folder is invalid; use a dedicated folder name.');
        }

        return $this->remoteName().':'.$folder;
    }

    private function verifyLocalTools(): void
    {
        if (! extension_loaded('zip')) {
            throw new RuntimeException('PHP Zip extension is required for backups.');
        }
        $connection = config('database.connections.'.config('database.default'));
        if (! is_array($connection) || ($connection['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('Backups require a MySQL/MariaDB database connection.');
        }
        $this->run([(string) config('tish-backup.mysqldump_bin'), '--version'], 30);
    }

    /** Fail closed: a raw Drive remote or unencrypted filename setup is rejected. */
    public function verifyEncryptedDriveRemote(): void
    {
        $bin = (string) config('tish-backup.rclone_bin');
        $crypt = $this->run([$bin, 'config', 'show', $this->remoteName()], 30);

        if (! preg_match('/^type\s*=\s*crypt\s*$/mi', $crypt)) {
            throw new RuntimeException('Only an rclone crypt remote is allowed.');
        }
        if (preg_match('/^filename_encryption\s*=\s*(?:off|obfuscate)\s*$/mi', $crypt)) {
            throw new RuntimeException('Standard encrypted filenames are required.');
        }
        if (! preg_match('/^remote\s*=\s*([A-Za-z][A-Za-z0-9_-]*):[^\r\n]+$/mi', $crypt, $matches)) {
            throw new RuntimeException('Crypt remote must point to a dedicated Google Drive folder.');
        }

        $baseName = $matches[1];
        if ($baseName === $this->remoteName()) {
            throw new RuntimeException('Crypt remote cannot target itself.');
        }
        $drive = $this->run([$bin, 'config', 'show', $baseName], 30);
        if (! preg_match('/^type\s*=\s*drive\s*$/mi', $drive)) {
            throw new RuntimeException('Underlying remote must be Google Drive.');
        }

        $this->destination(); // Validate against traversal / unintended root writes.
    }

    private function dumpDatabase(string $destination, string $optionsFile): void
    {
        $db = config('database.connections.'.config('database.default'));
        if (! is_array($db) || ($db['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('Weekly backup currently supports MySQL/MariaDB only.');
        }

        $name = (string) ($db['database'] ?? '');
        if ($name === '' || preg_match('/[\r\n\0]/', $name)) {
            throw new RuntimeException('Database name missing or invalid.');
        }

        $options = [
            'host' => (string) ($db['host'] ?? '127.0.0.1'),
            'user' => (string) ($db['username'] ?? ''),
            'password' => (string) ($db['password'] ?? ''),
            'port' => (string) ($db['port'] ?? 3306),
        ];
        if (! empty($db['unix_socket'])) {
            $options['socket'] = (string) $db['unix_socket'];
        }

        $escaped = static fn (string $value): string =>
            '"'.str_replace(["\\", '"', "\n", "\r"], ["\\\\", '\\"', '\\n', '\\r'], $value).'"';
        $cnf = "[client]\n";
        foreach ($options as $key => $value) {
            $cnf .= $key.'='.$escaped($value)."\n";
        }
        if (file_put_contents($optionsFile, $cnf, LOCK_EX) === false) {
            throw new RuntimeException('Cannot write temporary database options.');
        }
        @chmod($optionsFile, 0600);

        // The defaults-extra-file option MUST be first. The password never
        // appears in stdout, logs, or the process argument list.
        $this->run([
            (string) config('tish-backup.mysqldump_bin'),
            '--defaults-extra-file='.$optionsFile,
            '--single-transaction', '--quick', '--routines', '--events', '--triggers',
            '--hex-blob', '--no-tablespaces', '--default-character-set=utf8mb4',
            '--result-file='.$destination,
            $name,
        ], 7200);

        if (! is_file($destination) || filesize($destination) < 128) {
            throw new RuntimeException('Database dump was empty or incomplete.');
        }
    }

    private function zipSnapshot(string $archive, string $dump): array
    {
        if (! extension_loaded('zip')) {
            throw new RuntimeException('PHP Zip extension is required for backups.');
        }

        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup archive.');
        }

        $included = 0;
        try {
            $base = realpath(base_path());
            if (! $base) {
                throw new RuntimeException('Application path is unavailable.');
            }

            $iterator = new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $file) use ($base) {
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
                    return ! $file->isLink() && ! self::excluded($relative, $file->isDir());
                }
            );

            foreach (new \RecursiveIteratorIterator($iterator) as $file) {
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
                if (! $zip->addFile($file->getPathname(), 'application/'.$relative)) {
                    throw new RuntimeException('Could not archive required project file.');
                }
                $included++;
            }

            if ($included < 10 || ! $zip->addFile($dump, 'database/mysql.sql')) {
                throw new RuntimeException('Backup missing required application files or database dump.');
            }

            $manifest = json_encode([
                'format' => 'tish-erp-backup-v1',
                'created_at_utc' => gmdate('c'),
                'php' => PHP_VERSION,
                'application_files' => $included,
                'includes' => ['application', 'database/mysql.sql'],
                'excludes' => config('tish-backup.exclude_dirs'),
                'instructions' => 'Restore on an isolated server. Never overwrite production before verifying database and file integrity.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if (! $manifest || ! $zip->addFromString('manifest.json', $manifest)) {
                throw new RuntimeException('Could not write backup manifest.');
            }
        } finally {
            if (! $zip->close()) {
                throw new RuntimeException('Could not finalize backup ZIP.');
            }
        }

        $verify = new ZipArchive();
        if ($verify->open($archive, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('Generated ZIP archive failed integrity check.');
        }
        $exists = $verify->locateName('database/mysql.sql') !== false;
        $verify->close();
        if (! $exists || filesize($archive) < 128) {
            throw new RuntimeException('Archive did not contain the database snapshot.');
        }

        return ['files' => $included, 'bytes' => filesize($archive), 'sha256' => hash_file('sha256', $archive)];
    }

    private function pruneVerifiedArchives(string $destination, string $latest): void
    {
        $bin = (string) config('tish-backup.rclone_bin');
        $json = $this->run([$bin, 'lsjson', '--files-only', $destination], 180);
        $files = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($files)) {
            throw new RuntimeException('Could not enumerate backup retention set.');
        }

        $names = [];
        foreach ($files as $item) {
            $name = $item['Name'] ?? null;
            if (is_string($name) && preg_match('/^tish-backup-\d{8}-\d{6}-[a-f0-9]{8}\.zip$/D', $name)) {
                $names[] = $name;
            }
        }
        rsort($names, SORT_STRING);
        if (! in_array($latest, $names, true)) {
            throw new RuntimeException('Verified uploaded archive not found in Drive listing.');
        }
        $keep = (int) config('tish-backup.retention', 12);
        foreach (array_slice($names, $keep) as $old) {
            $this->run([$bin, 'deletefile', $destination.'/'.$old], 180);
        }
    }

    /**
     * @return array{file:string,bytes:int,files:int,sha256:string}
     */
    public function backup(bool $dryRun = false): array
    {
        if (! config('tish-backup.enabled')) {
            throw new RuntimeException('Backups are disabled; configure and test them before enabling.');
        }

        $this->verifyEncryptedDriveRemote();
        $this->verifyLocalTools();

        if ($dryRun) {
            return ['file' => 'DRY-RUN', 'bytes' => 0, 'files' => 0, 'sha256' => ''];
        }

        $base = storage_path('app/private/tish-backups');
        if (! is_dir($base) && ! mkdir($base, 0700, true) && ! is_dir($base)) {
            throw new RuntimeException('Unable to create private backup workspace.');
        }
        $workspace = $base.'/'.bin2hex(random_bytes(12));
        if (! mkdir($workspace, 0700)) {
            throw new RuntimeException('Unable to create isolated backup workspace.');
        }

        $filename = 'tish-backup-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.zip';
        $archive = $workspace.'/'.$filename;
        $db = $workspace.'/mysql.sql';
        $credentials = $workspace.'/mysql-client.cnf';
        $upload = $workspace.'/upload';

        try {
            $this->dumpDatabase($db, $credentials);
            @unlink($credentials);
            $info = $this->zipSnapshot($archive, $db);
            @unlink($db);

            if (! mkdir($upload, 0700) || ! rename($archive, $upload.'/'.$filename)) {
                throw new RuntimeException('Could not prepare backup for upload.');
            }

            $destination = $this->destination();
            $bin = (string) config('tish-backup.rclone_bin');
            $this->run([
                $bin, 'copyto', $upload.'/'.$filename,
                $destination.'/'.$filename, '--retries', '3',
            ], 10800);

            // cryptcheck validates ciphertext integrity against the plaintext ZIP.
            $this->run([$bin, 'cryptcheck', $upload, $destination, '--one-way'], 10800);

            // Pruning happens ONLY after the upload has passed integrity verification.
            // A pruning failure still makes the job fail so monitoring sees it.
            $this->pruneVerifiedArchives($destination, $filename);

            Log::info('TISH backup verified on encrypted Google Drive', [
                'filename' => $filename, 'bytes' => $info['bytes'],
                'files' => $info['files'], 'sha256' => $info['sha256'],
            ]);

            return ['file' => $filename] + $info;
        } finally {
            foreach ([$credentials, $db, $archive, $upload.'/'.$filename] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            if (is_dir($upload)) {
                @rmdir($upload);
            }
            @rmdir($workspace);
        }
    }
}
