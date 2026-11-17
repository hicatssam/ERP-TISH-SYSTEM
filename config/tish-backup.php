<?php

return [
    // Default OFF until an operator configures and tests an encrypted Drive remote.
    'enabled' => (bool) env('TISH_BACKUP_ENABLED', false),
    'remote' => env('TISH_BACKUP_RCLONE_REMOTE', 'tish_drive_crypt'),
    // Prefix is a folder INSIDE the crypt remote (not the raw Google Drive remote).
    'folder' => env('TISH_BACKUP_FOLDER', 'tish-erp'),
    'rclone_bin' => env('TISH_BACKUP_RCLONE_BIN', 'rclone'),
    'mysqldump_bin' => env('TISH_BACKUP_MYSQLDUMP_BIN', 'mysqldump'),
    'retention' => max(2, (int) env('TISH_BACKUP_KEEP', 12)),
    'alert_email' => env('TISH_BACKUP_ALERT_EMAIL', ''),
    // Excluded rebuildable runtime directories; not needed to restore business records.
    'exclude_dirs' => [
        '.git', '.idea', '.vscode', 'vendor', 'node_modules',
        'storage/framework', 'storage/logs', 'storage/app/backups',
        'storage/app/private/backups', 'storage/app/private/tish-backups',
        'storage/app/public/tish-backups',
        'public/hot', 'bootstrap/cache',
    ],
];
