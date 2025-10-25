# Automatic Absent Marking - Cron Job

This directory contains scripts for automatically marking students as absent if they don't check in by the deadline.

## Files

- **cron.php** - Unified cron entrypoint (absent, cleanup, backup)
- **setup_cron.bat** - Batch file to configure Windows Task Scheduler
- **README.md** - This file

## How It Works

The system automatically marks students as **Absent** in two scenarios:

1. **Missing Check-In**: Students who haven't checked in by the check-in deadline
2. **Missing Check-Out**: Students who checked in but didn't check out by the check-out deadline

### Features
- ✅ **Dual-phase absent marking** (check-in + check-out)
- ✅ Respects shift timings (morning/evening) from admin settings
- ✅ Phase 1: Marks students with NO attendance record as absent
- ✅ Phase 2: Marks students who checked in but didn't check out as absent
- ✅ Can be enabled/disabled from admin settings
- ✅ Logs all operations for debugging
- ✅ Can be run manually or scheduled automatically
- ✅ Adds detailed notes to explain why student was marked absent

## Setup Instructions

### Step 1: Enable Auto-Absent in Settings

1. Login to Admin Panel
2. Go to **Settings** page
3. Under **General Settings**, enable **"Enable Auto Absent"**
4. Configure your check-in deadlines for morning and evening shifts
5. Save settings

### Step 2: Schedule the Cron Job (Automatic)

#### Option A: Using the Setup Script (Recommended)

1. Right-click `setup_cron.bat` and select **"Run as administrator"**
2. The script will create two scheduled tasks:
   - Morning shift task (runs at 10:00 AM)
   - Evening shift task (runs at 3:00 PM)

#### Option B: Manual Task Scheduler Setup

1. Open **Task Scheduler** (search in Windows)
2. Click **"Create Basic Task"**
3. Name: `QR_Attendance_Auto_Absent`
4. Trigger: Daily at 9:00 PM (after all shifts end)
5. Action: Start a program
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\qr\public\cron\cron.php absent`
6. Finish

### Step 3: Test the Cron Job (Manual Run)

Test the script manually before scheduling:

```bash
# Auto-absent (both shifts when due)
php C:\xampp\htdocs\qr\public\cron\cron.php absent

# Auto-absent for a specific shift
php C:\xampp\htdocs\qr\public\cron\cron.php absent morning
php C:\xampp\htdocs\qr\public\cron\cron.php absent evening

# Cleanup logs
php C:\xampp\htdocs\qr\public\cron\cron.php cleanup

# Database backup
php C:\xampp\htdocs\qr\public\cron\cron.php backup

# Run all
php C:\xampp\htdocs\qr\public\cron\cron.php all
```

## Configuration

### Adjusting Run Times

Edit `setup_cron.bat` and change these lines:

```batch
REM Morning task - change 10:00 to your desired time
schtasks /create ... /st 10:00 ...

REM Evening task - change 15:00 to your desired time
schtasks /create ... /st 15:00 ...
```

**Important:** The cron job performs TWO checks:
1. **Check-In Deadline**: Marks students who never checked in
2. **Check-Out Deadline**: Marks students who checked in but didn't check out

**Recommended Scheduling:**

Run the cron AFTER the check-out deadline (which is typically after class ends):

**Morning Shift Example:**
- Check-in: 8:00 AM - 9:30 AM
- Class: 9:30 AM - 1:40 PM
- Check-out: 9:30 AM - 1:40 PM
- **Schedule cron at: 2:00 PM** (after check-out deadline)

**Evening Shift Example:**
- Check-in: 3:00 PM - 5:00 PM
- Class: 5:00 PM - 8:00 PM
- Check-out: 5:00 PM - 8:00 PM
- **Schedule cron at: 8:30 PM** (after check-out deadline)

This ensures both phases complete:
- Students who didn't check in are marked absent
- Students who checked in but didn't check out are also marked absent

### Checking Shift Timings

Check your current shift timing settings:

```sql
SELECT setting_key, setting_value 
FROM system_settings 
WHERE setting_key LIKE '%checkin%';
```

## Troubleshooting

### Check if tasks are scheduled

```cmd
schtasks /query /tn QR_Attendance_Auto_Absent
schtasks /query /tn QR_Attendance_Log_Cleanup
schtasks /query /tn QR_Attendance_Database_Backup
```

### View task execution history

1. Open Task Scheduler
2. Find the task under "Task Scheduler Library"
3. Click on "History" tab at the bottom

### Check logs

The script logs to Apache error log:
```
C:\xampp\apache\logs\error.log
```

Look for lines starting with `[CRON_ABSENT]`

### Common Issues

**Problem:** Task doesn't run
- **Solution:** Ensure task is set to run with SYSTEM account
- Check that PHP path is correct: `C:\xampp\php\php.exe`

**Problem:** Students not being marked
- **Solution:** Check if "Enable Auto Absent" is enabled in settings
- Verify the cron is running AFTER the check-in deadline

**Problem:** "Auto-absent marking is DISABLED"
- **Solution:** Enable it in Admin Settings → General Settings

## Manual Execution

You can also run the script manually anytime:

```bash
# Auto-absent
php C:\xampp\htdocs\qr\public\cron\cron.php absent

# Cleanup
php C:\xampp\htdocs\qr\public\cron\cron.php cleanup

# Backup
php C:\xampp\htdocs\qr\public\cron\cron.php backup

# All
php C:\xampp\htdocs\qr\public\cron\cron.php all
```

## Database Impact

The script:
- Reads from: `system_settings`, `students` tables
- Writes to: `attendance` table
- Adds records with `marked_by_system = 1` flag

## Security

- Script can only be run from command line (CLI), not via web browser
- No web-accessible endpoint
- Uses existing database credentials from config

## Uninstalling

To remove scheduled tasks:

```cmd
schtasks /delete /tn QR_Attendance_Auto_Absent /f
schtasks /delete /tn QR_Attendance_Log_Cleanup /f
schtasks /delete /tn QR_Attendance_Database_Backup /f
```

Or run `setup_cron.bat` which removes old tasks before creating new ones.

## Support

For issues or questions, check the logs at:
- Apache Error Log: `C:\xampp\apache\logs\error.log`
- Script output when running manually

---

**Last Updated:** 2025-10-24
