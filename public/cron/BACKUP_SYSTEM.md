# Automatic Backup System

## Overview
The automatic backup system creates database backups based on admin-configured settings in the System Settings page.

## Files

### `public/cron/backup.php`
Main backup script that:
- Checks if backup should run based on frequency setting
- Creates compressed database backups
- Cleans up old backups based on retention setting
- Logs backup status

### Settings
Available in System Settings → Advanced Tab:
- **Backup Frequency**: daily, weekly, monthly, or disabled
- **Backup Retention**: Number of days to keep backups (default: 30)

## Setup

### For Local Testing (Windows)
```batch
# Add to task scheduler or run manually:
php C:\xampp\php\php.exe C:\xampp\htdocs\qr\public\cron\backup.php
```

### For Hostinger
Set up in cPanel → Cron Jobs:
```
# Run daily at 2 AM
0 2 * * * /usr/bin/php /home/username/public_html/qr/public/cron/backup.php
```

Or for weekly backups:
```
# Run every Monday at 3 AM
0 3 * * 1 /usr/bin/php /home/username/public_html/qr/public/cron/backup.php
```

## How It Works

1. **Frequency Check**: Script checks if backup should run based on last backup time
2. **Create Backup**: Uses mysqldump to create SQL backup
3. **Compress**: Compresses backup to .gz format to save space
4. **Store**: Saves to `public/backups/database/`
5. **Cleanup**: Deletes backups older than retention period
6. **Update**: Records last backup time in settings

## Backup Files

Location: `public/backups/database/`
Format: `backup_YYYY-MM-DD_HHMMSS.sql.gz`

Example:
```
backup_2025-01-15_143022.sql.gz  (January 15, 2025 at 2:30 PM)
```

## Manual Backup

Admins can trigger manual backup by running:
```bash
php public/cron/backup.php
```

## Configuration

Settings stored in `system_settings` table:
- `backup_frequency` - When to run backups
- `backup_retention_days` - How long to keep backups
- `last_backup_time` - Timestamp of last backup

## Troubleshooting

### Backup Not Running
- Check cron job is configured correctly
- Verify PHP has mysqldump access
- Check file permissions on backup directory

### Backup Failing
- Ensure database credentials are correct
- Check mysqldump is installed and in PATH
- Verify backup directory has write permissions

### Backups Not Deleting
- Check retention_days setting is not 0
- Verify cleanup logic is running
- Check file modification times

## Security Notes

- Backups contain sensitive data - store securely
- Compressed backups reduce disk usage
- Old backups are automatically deleted
- Admin must manually download if needed

