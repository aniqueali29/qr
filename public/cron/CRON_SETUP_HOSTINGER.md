# Hostinger Cron Job Setup Guide

## Quick Commands to Copy-Paste

### 1. Auto-Absent Marking (Runs daily at 9:00 PM)
```
0 21 * * * php /home/yourusername/public_html/qr/public/cron/absent.php
```

### 2. Student Promotion (Runs monthly on 1st at 3:00 AM)
```
0 3 1 * * php /home/yourusername/public_html/qr/public/cron/promote.php
```

## How to Setup on Hostinger

### Step 1: Access Cron Jobs
1. Login to Hostinger cPanel
2. Go to "Advanced" → "Cron Jobs"
3. Select "Standard (cPanel)" tab

### Step 2: Add Each Job

#### Option A: Manual Setup (Recommended)
Add each cron job individually with these settings:

**Auto-Absent:**
- **Minute:** 0
- **Hour:** 21 (9:00 PM)
- **Day:** *
- **Month:** *
- **Weekday:** *
- **Command:** `php /home/yourusername/public_html/qr/public/cron/absent.php`

**Student Promotion:**
- **Minute:** 0
- **Hour:** 3
- **Day:** 1
- **Month:** *
- **Weekday:** *
- **Command:** `php /home/yourusername/public_html/qr/public/cron/promote.php`


### Step 3: Important Notes

#### Find Your Username
- Your username is displayed in cPanel dashboard
- Replace `yourusername` in the command with your actual username
- Example: If your username is `abc123`, the path would be `/home/abc123/public_html/qr/`

#### PHP Path
- Most Hostinger servers use: `php` or `/usr/bin/php`
- If `php` doesn't work, try `/usr/bin/php`
- Full command example: `/usr/bin/php /home/yourusername/public_html/qr/public/cron/cron.php absent`

#### Permissions
Make sure the cron file is executable:
```
chmod +x /home/yourusername/public_html/qr/public/cron/cron.php
```

### Step 4: Verify It's Working

#### Check Cron Execution
After setup, wait for the scheduled time, then check logs:

1. **Auto-Absent Logs:**
   - Location: `public/logs/auto_absent.log`
   - Open in cPanel File Manager or via FTP
   - Look for entries like: `[YYYY-MM-DD HH:MM:SS] Auto Absent Marking - START`

2. **Database:**
   - Check `attendance` table for "Auto-marked absent by system" notes
   - Check `students` table for updated `current_semester` values

#### Test Manually First
1. Login to cPanel
2. Go to "Advanced" → "Terminal" (or use SSH)
3. Run: `php /home/yourusername/public_html/qr/public/cron/cron.php absent`
4. Check if any errors appear

## Troubleshooting

### Issue: Cron not running
**Solution:**
1. Verify the path is correct (check your username)
2. Use absolute paths for `php` (try `/usr/bin/php`)
3. Check cPanel → Logs → Cron Logs for errors

### Issue: Permission denied
**Solution:**
```bash
chmod 755 /home/yourusername/public_html/qr/public/cron/absent.php
chmod 755 /home/yourusername/public_html/qr/public/cron/promote.php
```

### Issue: PHP path not found
**Solution:**
1. Try `php -v` in terminal to find PHP path
2. Use that path in the cron command

### Issue: Database connection errors
**Solution:**
- Check `public/includes/config.php` has correct database credentials
- Ensure database credentials are configured for production environment

## Recommended Setup

For production, I recommend:

**Daily Auto-Absent** (most important):
```
0 21 * * * php /home/yourusername/public_html/qr/public/cron/absent.php
```

**Monthly Student Promotion** (recommended):
```
0 3 1 * * php /home/yourusername/public_html/qr/public/cron/promote.php
```

## What Each Job Does

### Auto-Absent (`absent.php`)
- Runs at 9:00 PM daily
- Phase 1: Marks students absent if they didn't check in by deadlines
- Phase 2: Marks students absent if they checked in but didn't check out
- **Depends on:** `morning_checkin_end` and `evening_checkin_end` settings
- **Settings:** `enable_auto_absent` (enable/disable)

### Student Promotion (`promote.php`)
- Runs monthly (1st of month at 3:00 AM)
- Calculates expected semester based on admission date and elapsed time
- Promotes students to next semester: `current_semester`, `year_level`
- Graduates students in final semester: sets `is_graduated = 1`
- **Uses:** Semester-based progression (8 semesters total)

## Example Configuration

Your cPanel Cron Jobs page should look like this:

```
Active Cron Jobs:

1. Command: php /home/yourusername/public_html/qr/public/cron/absent.php
   Schedule: 0 21 * * * (Every day at 9:00 PM)

2. Command: php /home/yourusername/public_html/qr/public/cron/promote.php
   Schedule: 0 3 1 * * (1st of month at 3:00 AM)
```

## How Student Promotion Works

### Automatic Calculation
1. **System checks each student's admission date**
2. **Calculates elapsed time** (months since admission)
3. **Determines expected semester** based on:
   - Months elapsed since admission
   - Semesters per year (usually 2)
   - Total program duration (usually 8 semesters)

### Example
- Student admitted: September 2024
- Current date: February 2025
- Months elapsed: 5 months
- Expected semester: 2 (5 months ÷ 6 months per semester = Semester 2)
- If student is in Semester 1, system promotes to Semester 2

### What Gets Updated
- `current_semester`: 1 → 2, 2 → 3, etc.
- `year_level`: Updated based on semester
- `is_graduated`: Set to 1 if in final semester (Semester 8)

## Testing on Hostinger

### Test Auto-Absent
1. SSH into your Hostinger account
2. Run: `php /home/yourusername/public_html/qr/public/cron/absent.php`
3. Check `public/logs/auto_absent.log` for results

### Test Student Promotion
1. SSH into your Hostinger account  
2. Run: `php /home/yourusername/public_html/qr/public/cron/promote.php`
3. Check `students` table for updated `current_semester` values
4. Check logs for promotion summary

### Verify After 24 Hours
1. Check `public/logs/auto_absent.log` for daily execution
2. Check attendance table for "Auto-marked absent" records
3. Check `students` table monthly for promoted semesters

