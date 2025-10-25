# Student Promotion System

## Overview

The QR Attendance System now includes a comprehensive student promotion management system that allows administrators to bulk promote students to the next academic year.

## Features

### ✅ Configurable Program Duration
- Support for 3-year or 4-year programs
- Configurable from Settings page
- Setting: `max_program_years` (default: 4)

### ✅ Bulk Promotion
- Promote all active students with one click
- 1st year → 2nd year
- 2nd year → 3rd year
- 3rd year → 4th year (if 4-year program)
- Final year → Graduated

### ✅ Automatic Graduation
- Final year students (3rd or 4th year) are automatically graduated
- Graduated students are marked as inactive
- Graduated students remain in the system for historical records

### ✅ Promotion Preview
- See exactly what will happen before promoting
- View student distribution by year
- Count of students to be promoted vs graduated

### ✅ Rollback Support
- Undo promotions done today
- Safety feature in case of mistakes
- Only works for same-day promotions

### ✅ Audit Trail
- `last_year_update` field tracks promotion date
- Logs promotion activities
- Admin actions are recorded

## Database Structure

### Students Table Fields
- `current_year` (INT): 1, 2, 3, or 4
- `year_level` (ENUM): '1st', '2nd', '3rd', '4th'
- `is_graduated` (BOOLEAN): 0 = active, 1 = graduated
- `last_year_update` (DATE): When last promoted
- `is_active` (BOOLEAN): Active status (graduates become inactive)

### System Settings
- `max_program_years`: Maximum years in program (3 or 4)

## Usage

### For Administrators

1. **Configure Program Duration**
   - Go to **Settings** → **System Config** tab
   - Set "Program Duration" to 3 or 4 years
   - Click "Save All"

2. **Review Current Distribution**
   - Go to **Settings** → **System Config** tab
   - Click "Manage Promotions" button
   - Or directly visit: `admin/promote_students.php`

3. **Promote Students**
   - Review the promotion preview
   - Click "Promote All Students"
   - Confirm the action
   - Wait for completion

4. **Rollback if Needed**
   - Click "Rollback Today's Promotions"
   - Only available on the same day as promotion
   - Restores all students to previous year

## Workflow

### Annual Promotion Process (After 11 Months)

1. **Preparation**
   - Backup your database
   - Verify all student records are up to date
   - Check that `max_program_years` setting is correct

2. **Execution**
   - Access promotion page
   - Review preview carefully
   - Click "Promote All Students"
   - Wait for confirmation

3. **Verification**
   - Check student records
   - Verify graduated students
   - Review promotion logs

4. **Rollback (if needed)**
   - Must be done on the same day
   - Click "Rollback Promotions"
   - Verify rollback completed

## API Endpoints

### `api/promote_students.php`

**Preview Promotion**
```
GET /api/promote_students.php?action=preview
```
Returns:
- Current student distribution
- Count of students to promote
- Count of students to graduate
- Maximum program years setting

**Execute Promotion**
```
POST /api/promote_students.php
Body: action=promote
```
Returns:
- Success/failure status
- Number of students promoted
- Number of students graduated
- Detailed list of changes

**Rollback Promotion**
```
POST /api/promote_students.php
Body: action=rollback
```
Returns:
- Success/failure status
- Number of students rolled back

## Security

- Requires admin authentication
- All actions are logged
- Database transactions ensure data integrity
- Rollback only works for same-day promotions

## Best Practices

### Before Promotion
1. ✅ Backup database
2. ✅ Verify all student data is correct
3. ✅ Check `max_program_years` setting
4. ✅ Review preview carefully
5. ✅ Inform relevant staff

### After Promotion
1. ✅ Verify promotion completed successfully
2. ✅ Check graduated students list
3. ✅ Update any external systems
4. ✅ Keep backup for at least 30 days

### If Something Goes Wrong
1. ✅ Use rollback feature (same day only)
2. ✅ Restore from backup if needed
3. ✅ Contact system administrator
4. ✅ Check error logs

## Example Scenarios

### Scenario 1: 4-Year Program
- 1st year students (50) → Promoted to 2nd year
- 2nd year students (45) → Promoted to 3rd year
- 3rd year students (40) → Promoted to 4th year
- 4th year students (35) → Graduated
- **Result:** 135 promoted, 35 graduated

### Scenario 2: 3-Year Program
- 1st year students (50) → Promoted to 2nd year
- 2nd year students (45) → Promoted to 3rd year
- 3rd year students (40) → Graduated
- **Result:** 95 promoted, 40 graduated

## Troubleshooting

### Issue: "Promotion failed with errors"
**Solution:** Check error logs, verify database connection, rollback and try again

### Issue: "No promotions found to rollback"
**Solution:** Rollback only works for same-day promotions

### Issue: "Some students not promoted"
**Solution:** Check if students are active (`is_active = 1`) and not already graduated

### Issue: "Wrong year level after promotion"
**Solution:** Verify `max_program_years` setting, use rollback if same day

## Files

- `/admin/promote_students.php` - Promotion management page
- `/admin/api/promote_students.php` - API endpoint
- `/admin/settings.php` - Settings page with promotion link

## Related Settings

- `max_program_years` - Program duration (3 or 4 years)
- `academic_year_start_month` - When academic year starts
- `enable_auto_absent` - Auto-absent feature
- `auto_absent_morning_hour` - Morning absent marking time
- `auto_absent_evening_hour` - Evening absent marking time

---

**Version:** 1.0  
**Last Updated:** 2025-10-24  
**Maintainer:** QR Attendance System
