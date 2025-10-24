# Automatic Absent Marking System

This directory contains scripts for automatically marking students as absent after check-in windows close.

## Files

- `mark_absent.php` - Simple cron script for basic absent marking
- `auto_absent.php` - Advanced script that handles both shifts intelligently

## Setup Instructions

### Option 1: Windows Task Scheduler (Recommended for XAMPP)

1. Open **Task Scheduler** (search for it in Start menu)
2. Click **"Create Basic Task"**
3. Name: "QR Attendance - Auto Mark Absent"
4. Trigger: **Daily**
5. Start time: **11:30 AM** (after morning check-in window)
6. Action: **Start a program**
7. Program: `C:\xampp\php\php.exe`
8. Arguments: `C:\xampp\htdocs\qr_attendance\public\cron\auto_absent.php`
9. Click **Finish**

### Option 2: Linux/Mac Cron

Add this line to your crontab (`crontab -e`):

```bash
# Run at 11:30 AM daily (after morning check-in window)
30 11 * * * /usr/bin/php /path/to/qr_attendance/public/cron/auto_absent.php

# Run at 5:30 PM daily (after evening check-in window)
30 17 * * * /usr/bin/php /path/to/qr_attendance/public/cron/auto_absent.php
```

### Option 3: Web-based Trigger

You can also trigger the auto absent marking manually from the admin panel:

1. Go to **Scan Attendance** page
2. Click the **"Auto Check"** button
3. The system will check if any students should be marked absent

## How It Works

### Auto Absent Logic

1. **Checks Current Time**: Compares against configured check-in end times
2. **Identifies Missing Students**: Finds students with no attendance record for today
3. **Marks by Shift**: Only marks students absent if their shift's check-in window has closed
4. **Logs Everything**: All actions are logged to `logs/auto_absent.log`

### Timing Configuration

The system uses your settings from the admin panel:
- **Morning Check-in End**: Default 11:00 AM
- **Evening Check-in End**: Default 5:00 PM
- **Timezone**: Configurable in settings

### Safety Features

- **No Duplicate Marking**: Won't mark students who already have attendance records
- **Shift-Specific**: Only marks absent students whose check-in window has closed
- **Comprehensive Logging**: All actions and errors are logged
- **Error Handling**: Continues processing even if individual students fail

## Testing

To test the system:

1. **Manual Test**: Click "Auto Check" button in admin panel
2. **Cron Test**: Run the script manually:
   ```bash
   php C:\xampp\htdocs\qr_attendance\public\cron\auto_absent.php
   ```

## Logs

Check these files for system activity:
- `logs/auto_absent.log` - Main auto absent logging
- `logs/cron_errors.log` - Cron-specific errors
- `logs/error.log` - General system errors

## Troubleshooting

### Common Issues

1. **"Database connection not available"**
   - Check your `config.env` file
   - Ensure database is running

2. **"Settings not found"**
   - Run the system settings setup in admin panel
   - Check `system_settings` table exists

3. **"No students found"**
   - Check if students exist in the `students` table
   - Verify student records have correct `shift` values

### Manual Override

If you need to mark students absent manually:
1. Go to **Attendance** page
2. Use the **"Mark Absent"** button
3. Or use the **"Auto Check"** button for intelligent marking
