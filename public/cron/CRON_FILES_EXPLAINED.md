# Cron Files Explained - Hostinger Setup

## ✅ New Separate Files Created

### 1. `public/cron/absent.php` - Auto-Absent Marking
**Purpose:** Automatically marks students as absent

**What it does:**
- Runs daily at 9:00 PM
- Phase 1: Marks absent if no check-in by deadline
- Phase 2: Marks absent if checked-in but no check-out

**How it works:**
1. Checks `enable_auto_absent` setting
2. Reads `morning_checkin_end` and `evening_checkin_end` times
3. Finds students without attendance records
4. Inserts "Absent" records with note: "Auto-marked absent"

**Hostinger Setup:**
```
0 21 * * * php /home/yourusername/public_html/qr/public/cron/absent.php
```

**Settings Used:**
- `enable_auto_absent` - Enable/disable feature
- `morning_checkin_end` - Morning deadline (e.g., "11:00:00")
- `evening_checkin_end` - Evening deadline (e.g., "17:00:00")

---

### 2. `public/cron/promote.php` - Student Promotion
**Purpose:** Automatically promotes students to next semester

**What it does:**
- Runs monthly on the 1st at 3:00 AM
- Promotes students based on elapsed time since admission
- Graduates students in final semester

**How it works:**
1. Gets all active, non-graduated students
2. Calculates expected semester based on admission date:
   - Months elapsed since admission
   - Semesters per year (usually 2)
   - Expected semester = floor(months_elapsed / 6) + 1
3. Compares current vs expected semester
4. If behind: promotes to expected semester
5. If in final semester (8): graduates student

**Example:**
- Student admitted: September 2024 (2024-09-01)
- Today: February 2025 (2025-02-01)
- Months elapsed: 5 months
- Expected semester: floor(5 / 6) + 1 = 1 + 1 = **Semester 2**
- If student is in Semester 1 → Promotes to Semester 2
- If student is in Semester 2 or higher → No change

**Hostinger Setup:**
```
0 3 1 * * php /home/yourusername/public_html/qr/public/cron/promote.php
```

**What Gets Updated:**
- `current_semester`: 1→2, 2→3, etc.
- `year_level`: Updated based on semester
- `is_graduated`: Set to 1 for final semester students

**Settings Used:**
- `academic_structure_mode` (semester-based)
- `semesters_per_year` (usually 2)
- `max_program_years` (usually 4)
- `semester_names` (JSON array)

---

## 🎯 How to Use on Hostinger

### Step 1: Access cPanel
1. Login to Hostinger
2. Go to **Cron Jobs** (under Advanced)
3. Select **Standard (cPanel)** tab

### Step 2: Add Auto-Absent Cron
1. Click **Add New Cron Job**
2. Settings:
   - **Minute:** `0`
   - **Hour:** `21` (9:00 PM)
   - **Day:** `*`
   - **Month:** `*`
   - **Weekday:** `*`
   - **Command:** `php /home/YOUR_USERNAME/public_html/qr/public/cron/absent.php`
3. Click **Add Cron Job**

### Step 3: Add Promotion Cron
1. Click **Add New Cron Job**
2. Settings:
   - **Minute:** `0`
   - **Hour:** `3`
   - **Day:** `1` (1st of month)
   - **Month:** `*`
   - **Weekday:** `*`
   - **Command:** `php /home/YOUR_USERNAME/public_html/qr/public/cron/promote.php`
3. Click **Add Cron Job**

### Step 4: Configure Settings
Go to **Admin → Settings** and set:

**For Auto-Absent:**
- `enable_auto_absent` = Enabled
- `morning_checkin_end` = "11:00:00"
- `evening_checkin_end` = "17:00:00"

**For Promotion:**
- Already configured by enrollment session system
- No additional settings needed

---

## 📊 Verification

### Check Auto-Absent is Working:
1. Wait for next day after 9:00 PM
2. Check `public/logs/auto_absent.log`
3. Look for: "Marked X students as absent"
4. Check `attendance` table for records with "Auto-marked absent"

### Check Promotion is Working:
1. Wait for 1st of next month
2. Check `students` table
3. Look for updated `current_semester` values
4. Students should progress: 1→2, 2→3, etc.

---

## ⚠️ Important Notes

### Why Semester-Based Promotion Works:

**Before (Year-based):**
- Year 1 students → Promote to Year 2 after 12 months
- Year 2 students → Promote to Year 3 after 24 months

**Now (Semester-based):**
- Semester 1 students → Promote to Semester 2 after 6 months
- Semester 2 students → Promote to Semester 3 after 12 months
- Semester 3 students → Promote to Semester 4 after 18 months
- ...and so on until Semester 8 (graduate)

### Time-Based Logic:
The system calculates expected semester based on **real-time** progression:
- Each semester is approximately 6 months
- System checks: "Has it been 6 months since admission?"
- If yes, promote to Semester 2
- If yes for 12 months, promote to Semester 3
- etc.

### Enrollment Sessions vs Promotion:
- **Enrollment Session:** Which batch the student belongs to (Fall 2024, Spring 2025, etc.) - NEVER changes
- **Current Semester:** Academic progress (Semester 1, 2, 3, etc.) - Updates automatically

These work independently!

