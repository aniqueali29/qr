# Automatic Student Promotion - Setup Guide

## Overview

The system can automatically promote students to the next semester based on elapsed time since their admission. The promotion runs on a **monthly schedule** via Windows Task Scheduler.

## What Gets Updated

When the promotion runs, it updates:
- ✅ `current_semester` (1, 2, 3, 4, 5, 6, 7, 8)
- ✅ `year_level` ("Semester 1", "Semester 2", etc.)
- ✅ `current_year` (1, 2, 3, 4)
- ✅ `last_year_update` (today's date)

**Note:** `enrollment_session_id` (e.g., "Fall 2025") **NEVER changes** - it represents when the student enrolled.

## How It Works

1. **Calculation**: System calculates elapsed time since admission year
2. **Semester Duration**: Default is 6 months per semester (configurable in settings)
3. **Promotion**: Advances students by the appropriate number of semesters
4. **Graduation**: Students in their final semester are marked as graduated

### Example Timeline

**Student admitted in Fall 2025 (September 2025):**

| Date | Elapsed Time | Semester | Year Level | Current Semester |
|------|--------------|----------|------------|------------------|
| Sep 2025 | 0 months | Semester 1 | "Semester 1" | 1 |
| Mar 2026 | 6 months | Semester 2 | "Semester 2" | 2 |
| Sep 2026 | 12 months | Semester 3 | "Semester 3" | 3 |
| Mar 2027 | 18 months | Semester 4 | "Semester 4" | 4 |

## Setup Instructions

### Option 1: Automated Setup (Recommended)

1. Navigate to: `C:\xampp\htdocs\qr\public\cron\`
2. **Right-click** `setup_cron.bat`
3. Select **"Run as administrator"**
4. The script will create all scheduled tasks including student promotion

### Option 2: Manual Setup

Create a scheduled task using Windows Task Scheduler:

```cmd
schtasks /create /tn "QR_Attendance_Student_Promotion" ^
  /tr "C:\xampp\php\php.exe C:\xampp\htdocs\qr\public\cron\cron.php promote" ^
  /sc monthly /d 1 /st 03:00 /ru SYSTEM /f
```

**Parameters:**
- `/tn` - Task name
- `/tr` - Command to run
- `/sc monthly` - Schedule (monthly)
- `/d 1` - Run on 1st of month
- `/st 03:00` - Run at 3:00 AM
- `/ru SYSTEM` - Run as system account

## Recommended Scheduling Times

### Monthly Promotion (Recommended)

**Schedule:** 1st of each month at 3:00 AM

**Why:** 
- Runs when students are not active
- Gives time for previous semester to complete
- Allows admin to review results before students see changes
- Consistent monthly rhythm

### Quarterly Promotion

**Schedule:** 1st of January, April, July, October at 3:00 AM

```cmd
schtasks /create /tn "QR_Attendance_Student_Promotion" ^
  /tr "C:\xampp\php\php.exe C:\xampp\htdocs\qr\public\cron\cron.php promote" ^
  /sc monthly /mo 3 /d 1 /st 03:00 /ru SYSTEM /f
```

### Manual Execution

You can also run the promotion manually:

```bash
# From command prompt
php C:\xampp\htdocs\qr\public\cron\cron.php promote
```

Or via the Admin Panel:
- Navigate to: **Settings** → **Student Promotion**
- Click "Promote by Date" button

## What the System Does

### Automatic Promotion Logic

1. **Calculates expected semester** based on:
   - Admission year
   - Current date
   - Semester length (default: 6 months)
   - Max program years (default: 4 years / 8 semesters)

2. **Updates students** to correct semester:
   ```sql
   UPDATE students 
   SET current_year = 2,
       year_level = 'Semester 3',
       current_semester = 3,
       last_year_update = '2026-03-01'
   WHERE student_id = '25-MET-0001'
   ```

3. **Graduates final semester students**:
   ```sql
   UPDATE students 
   SET is_graduated = 1,
       is_active = 0,
       last_year_update = '2027-09-01'
   WHERE current_semester >= 8
   ```

## Verification

### Check if Task Exists

```cmd
schtasks /query /tn QR_Attendance_Student_Promotion
```

### Check Task History

1. Open **Task Scheduler** (search in Windows)
2. Find "QR_Attendance_Student_Promotion"
3. Click on **"History"** tab

### View Logs

The promotion task logs to Apache error log:
```
C:\xampp\apache\logs\error.log
```

Look for lines starting with `[CRON_PROMOTE]`

### Check Last Promotion

```sql
SELECT MAX(last_year_update) as last_promotion_date
FROM students
WHERE last_year_update IS NOT NULL;
```

## Customizing Promotion Schedule

### Change Run Day

To run on 15th of each month instead of 1st:

```cmd
schtasks /change /tn QR_Attendance_Student_Promotion /d 15
```

### Change Run Time

To run at 6:00 AM instead of 3:00 AM:

```cmd
schtasks /change /tn QR_Attendance_Student_Promotion /st 06:00
```

### Change Frequency

To run weekly every Monday:

```cmd
schtasks /delete /tn QR_Attendance_Student_Promotion /f
schtasks /create /tn QR_Attendance_Student_Promotion ^
  /tr "C:\xampp\php\php.exe C:\xampp\htdocs\qr\public\cron\cron.php promote" ^
  /sc weekly /d MON /st 03:00 /ru SYSTEM /f
```

## Troubleshooting

### Task Not Running

1. Check if task exists:
   ```cmd
   schtasks /query /tn QR_Attendance_Student_Promotion
   ```

2. Verify last run time in Task Scheduler history

3. Check if task is disabled (right-click → Enable)

### Promotion Not Working

1. Check logs at `C:\xampp\apache\logs\error.log`
2. Look for errors starting with `[CRON_PROMOTE]`
3. Verify PHP path is correct: `C:\xampp\php\php.exe`
4. Test manual execution:
   ```bash
   php C:\xampp\htdocs\qr\public\cron\cron.php promote
   ```

### Students Not Progressing

1. Verify `admission_year` is set correctly in student records
2. Check if elapsed time is sufficient (default: 6 months per semester)
3. Review `last_year_update` field - promotion only runs if time has elapsed

## Configuration

### Adjust Semester Length

Default is 6 months per semester. To change:

1. Go to **Admin Panel** → **Settings**
2. Look for **Semester Length** setting
3. Update to desired number of months

Or manually in database:

```sql
UPDATE system_settings 
SET setting_value = '4' 
WHERE setting_key = 'semester_length_months';
```

### Adjust Max Program Years

Default is 4 years (8 semesters). To change:

```sql
UPDATE system_settings 
SET setting_value = '3' 
WHERE setting_key = 'max_program_years';
```

## Uninstalling

To remove the promotion task:

```cmd
schtasks /delete /tn QR_Attendance_Student_Promotion /f
```

Or remove all attendance tasks:

```cmd
schtasks /delete /tn QR_Attendance_* /f
```

## Summary

✅ **Automatic**: Runs monthly on 1st at 3:00 AM
✅ **Safe**: Updates `current_semester`, `year_level`, `current_year`
✅ **Smart**: Calculates based on elapsed time
✅ **Graduates**: Automatically graduates final semester students
✅ **Configurable**: Adjust semester length and max years
✅ **Logs**: All actions logged for review

**Last Updated:** 2025-10-26

