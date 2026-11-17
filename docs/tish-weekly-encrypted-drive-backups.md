# TISH ERP: encrypted weekly Google Drive backup

## Scope
Every snapshot contains MySQL/MariaDB business data (orders, invoice records, payment records, stock, customer data) and application files: app/, bootstrap/, config/, routes/, resources/, database/, public/, storage/, source lockfiles, and .env. Business PDFs and attachments within public/ and storage/ are covered. Dependencies vendor/ and node_modules/, .git, and runtime caches/logs are excluded and must be rebuilt from lockfiles. This is NOT an operating system / complete host image.

The live SQL dump uses --single-transaction; the file tree is collected afterwards, so files and SQL do not form a simultaneous atomic filesystem snapshot. For high-volume uploads consider maintenance mode, filesystem snapshots, or another consistent capture strategy.

## Encryption / permissions
The backup ZIP contains .env and private financial data. The command rejects raw Google Drive targets; it requires an rclone crypt remote with standard filename encryption backed directly by Google Drive. Crypt encrypts both filenames and bytes, but does not prevent someone with Drive deletion rights from deleting backups. Secure the Drive account using MFA. Store rclone configuration and crypt password/salt OUTSIDE the repo/webroot and separately offline; losing them makes restores impossible. Never put Drive credentials or rclone config in Git.

## Production setup
1. Install rclone, compatible mysqldump, PHP ZIP extension, and ensure disk headroom for both SQL and ZIP. Test rclone version, mysqldump --version, and php -m.
2. Run rclone config as the same OS user that runs Laravel scheduler. Create a Google Drive remote named tish_drive, choosing type=drive, with OAuth for your own dedicated backup account or folder.
3. Create another remote tish_drive_crypt, choose type=crypt, pointing to tish_drive:TISH-ERP-Encrypted. Choose filename_encryption=standard. Use independent random crypt password and password2 (salt). Store recovery copies of secrets in a secure offline password manager. Never choose the Drive root as the crypt target.
4. Test rclone lsd tish_drive_crypt: and confirm that Drive directly displays encrypted (scrambled) filenames.
5. Set the real server .env variables (example placeholders, not real credentials):
    TISH_BACKUP_ENABLED=true
    TISH_BACKUP_RCLONE_REMOTE=tish_drive_crypt
    TISH_BACKUP_FOLDER=tish-erp
    TISH_BACKUP_KEEP=12
    TISH_BACKUP_RCLONE_BIN=/usr/bin/rclone
    TISH_BACKUP_MYSQLDUMP_BIN=/usr/bin/mysqldump
    TISH_BACKUP_ALERT_EMAIL=operations@example.com
   Set a monitored email, or leave it blank until outgoing Laravel mail is configured. On Windows use absolute executable paths. Do not edit .env.example with real credentials.
6. Run php artisan optimize:clear; php artisan backup:tish-drive --dry-run, then php artisan backup:tish-drive for a real upload. Success requires a verified ZIP, successful encrypted upload, and rclone cryptcheck integrity verification. Failures exit nonzero and are logged; optional alert email attempted.
7. Add a production cron job to run Laravel Scheduler every minute:
    * * * * * cd /ABSOLUTE/PATH/TO/TISH && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
   Laravel automatically runs the backup each Monday at 03:00 server-local time (with overlap prevention). Confirm php artisan schedule:list and production timezone. A worker or local browser does not run cron. Windows can use Task Scheduler, but production should run on its own host.

## Retention and safety
The service retains the newest 12 backups (minimum 2). After upload verification, it removes older files matching tish-backup-YYYYMMDD-HHMMSS-XXXXXXXX.zip from the dedicated encrypted folder. It never runs rclone sync/purge or deletes arbitrary Drive files. Plaintext SQL and ZIP working files exist temporarily in storage/app/private/tish-backups and are deleted at the end, even on failure. Workspace access must be restricted to the OS account. Do NOT store backup data within public webroot.

## Restore drill: use an isolated test environment
1. Use the crypt remote, not raw Drive: rclone ls tish_drive_crypt:tish-erp.
2. Download a selected ZIP using rclone copyto tish_drive_crypt:tish-erp/CHOSEN.zip /secure-restore/CHOSEN.zip.
3. Run unzip -t /secure-restore/CHOSEN.zip, extract to a PRIVATE test location, inspect manifest.json and database/mysql.sql.
4. Create a new empty TEST database; import mysql.sql there, restore application/ files into a separate test host, reinstall vendor dependencies, adjust .env for non-production connections and rotate any exposed secrets. Test invoice numbers and uploaded receipts, POS payments, inventory stock and admin login.
5. Record restore test date, selected backup, expected record counts, actual recovered counts and restore time. Never restore on top of production before the drill. Remove local plaintext extracted files after testing.

## Further hardening
Weekly backups alone have an up-to-7-day recovery-point gap. For actively used POS, add daily or more frequent database snapshots/binlogs and a distinct second offsite/offline or immutable backup destination (3-2-1). Crypt protects confidentiality but cannot stop an attacker with Drive delete permissions from erasing backup history. Set up alert monitoring and test restores routinely. No backups actually occur until credentials, binaries, ZIP, scheduler cron and a successful manual backup are configured.
