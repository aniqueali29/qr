# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

This is a **QR Code-based Attendance Management System** built with PHP, MySQL, and vanilla JavaScript. The system manages student attendance through QR code scanning with separate portals for students and administrators.

## Architecture

### Dual Portal Structure

The application consists of two separate but interconnected portals:

1. **Student Portal** (`/public/`)
   - Entry point: `index.php` → `login.php` → `dashboard.php`
   - Configuration: `public/includes/config.php`
   - Authentication: `public/includes/auth.php`
   - Session prefix: `student_*`

2. **Admin Portal** (`/public/admin/`)
   - Entry point: `admin/index.php` (dashboard)
   - Configuration: `public/admin/includes/config.php`
   - Authentication: `public/admin/includes/auth.php`
   - Session prefix: `admin_*`

**Important**: Both portals share the same database and use a unified session system (`SESSION_NAME=QR_ATTENDANCE_SESSION`) to allow shared authentication context.

### Configuration System

**Environment Variables**: All configuration is loaded from `public/config.env` using a custom environment loader at `config/env.php`:
- Use `env_get($key, $default)` for strings
- Use `env_bool($key, $default)` for booleans
- Use `env_int($key, $default)` for integers
- **Never hard-code credentials** - always use env variables

**Database Connection**: Both portals establish PDO connections in their respective `config.php` files with:
- Exception mode enabled
- Prepared statements (emulate off)
- UTF-8 charset
- Associative fetch mode by default

### API Structure

APIs are located in `public/api/` and follow a modular pattern:
- **Main APIs**: `admin_api.php`, `student_api.php`, `attendance.php`, `checkin_api.php`
- **Specialized APIs**: `program_management_api.php`, `settings_api.php`, `sync_api.php`, `bulk_import_api.php`
- All APIs use JSON responses and require CSRF validation for mutating operations
- Authentication is checked via session variables (`$_SESSION['role']`, `$_SESSION['user_id']`)

### Security Architecture

**Layered Security Implementation**:
1. **Primary Layer** (`includes/` in root): `auth_middleware.php`, `secure_config.php`, `password_manager.php`, `security_config.php`
2. **Extended Layer** (`public/includes_ext/`): `secure_session.php`, `secure_database.php`, `csrf_protection.php`, `secure_upload.php`
3. **Portal-specific** auth systems in each portal's includes folder

**Key Security Features**:
- CSRF protection via tokens (stored in session)
- Rate limiting on login attempts (5 attempts, 15-minute lockout)
- Session validation with IP tracking
- Suspicious activity detection and logging
- Parameterized queries throughout (use `$pdo->prepare()`)

### Database Schema

Core tables:
- `students`: Student records with `student_id` (primary), program, section, shift
- `attendance`: Check-in records with `student_id` FK, `check_in_time`, `status`
- `programs`: Academic programs with unique `code`
- `sections`: Class sections linked to programs, year levels, and shifts
- `users`: Admin/staff user accounts with roles
- `sessions`: Session management for authentication tracking
- `system_settings`: Dynamic configuration (shift timings, check-in windows)

**Important**: Password migration completed - plaintext `password` and `username` columns removed from `students` table per `database/migrations/2025_10_remove_plaintext_passwords.sql`.

### Attendance System Flow

1. **QR Code Generation**: 
   - Generated per-student via `api/qr_generator.php`
   - Contains encoded student_id
   - Stored/displayed on student dashboard

2. **Check-in Process**:
   - QR code scanned via `admin/scan.php`
   - Validated through `api/checkin_api.php`
   - Checks current time against shift-specific check-in windows from `system_settings`
   - Records attendance with timestamp and status (present/late/absent)

3. **Shift Management**:
   - System supports Morning/Evening shifts
   - Each shift has configurable check-in start/end times in database
   - Automatic absent marking via cron: `public/cron/auto_absent.php`

4. **Auto-Absent System** (`public/cron/`):
   - `auto_absent.php`: Intelligent shift-based absent marking
   - `mark_absent.php`: Simple absent marking script
   - Logs to `logs/auto_absent.log`
   - Can be triggered via Windows Task Scheduler or cron

## Development Commands

### Running the Application

**XAMPP on Windows**:
```powershell
# Start XAMPP services
Start-Process "C:\xampp\xampp-control.exe"

# Or via command line
C:\xampp\apache_start.bat
C:\xampp\mysql_start.bat
```

**Access URLs**:
- Student Portal: `http://localhost/qr/public/`
- Admin Portal: `http://localhost/qr/public/admin/`
- API Base: `http://localhost/qr/public/api/`

### Database Management

```powershell
# Access MySQL via XAMPP
C:\xampp\mysql\bin\mysql.exe -u root -p qr_attendance

# Run migrations (manual)
C:\xampp\mysql\bin\mysql.exe -u root -p qr_attendance < database/migrations/[migration_file].sql

# Backup database
C:\xampp\mysql\bin\mysqldump.exe -u root -p qr_attendance > backups/qr_attendance_$(Get-Date -Format 'yyyy-MM-dd_HHmmss').sql
```

### Dependency Management

```powershell
# Install PHP dependencies (from public/ directory)
cd public
composer install

# Update dependencies
composer update
```

**Current Dependencies** (from `public/composer.json`):
- `phpmailer/phpmailer: ^7.0` (email functionality)

### Log Management

```powershell
# View error logs
Get-Content logs/error.log -Tail 50 -Wait

# View auto-absent logs
Get-Content logs/auto_absent.log -Tail 50 -Wait

# View security logs
Get-Content logs/security_$(Get-Date -Format 'yyyy-MM-dd').log -Tail 50 -Wait

# View admin action logs
Get-Content logs/admin_$(Get-Date -Format 'yyyy-MM-dd').log -Tail 50 -Wait

# Clear old logs (older than 30 days)
Get-ChildItem logs/*.log | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-30) } | Remove-Item
```

### Testing

**No automated test suite exists**. Manual testing workflow:

1. **Test Student Login Flow**:
   ```
   Navigate to: http://localhost/qr/public/login.php
   Test credentials from database
   Verify dashboard loads
   ```

2. **Test QR Code Generation**:
   ```
   Login as admin → Students page
   Click "Generate QR" for a student
   Verify QR code downloads/displays
   ```

3. **Test Attendance Scanning**:
   ```
   Admin portal → Scan Attendance
   Scan student QR code
   Verify attendance record created
   Check logs for any errors
   ```

4. **Test Auto-Absent System**:
   ```powershell
   # Run manually to test
   C:\xampp\php\php.exe C:\xampp\htdocs\qr\public\cron\auto_absent.php
   # Check logs
   Get-Content logs/auto_absent.log -Tail 20
   ```

### Debugging

**Enable Debug Mode** (in `public/config.env`):
```env
DEBUG_MODE=true
```

**Common Debug Locations**:
- PHP errors: Check `logs/system_[date].log`
- SQL errors: Usually thrown as PDOException with full details when DEBUG_MODE=true
- Session issues: Check `logs/security_[date].log`
- API responses: Check browser Network tab → Response

## File Organization

```
qr/
├── config/
│   └── env.php                    # Environment variable loader (root-level utilities)
├── database/
│   └── migrations/                # SQL migration files
├── includes/                      # Shared security components (root-level)
│   ├── auth_middleware.php
│   ├── password_manager.php
│   ├── secure_config.php
│   └── security_config.php
├── public/                        # Web-accessible root (STUDENT PORTAL)
│   ├── admin/                     # Admin portal (separate entry point)
│   │   ├── includes/
│   │   │   ├── config.php         # Admin configuration
│   │   │   ├── auth.php           # Admin authentication
│   │   │   └── helpers.php
│   │   ├── api/                   # Admin-specific APIs (deprecated, use ../api/)
│   │   ├── [pages].php            # Admin dashboard pages
│   │   └── assets/                # Admin-specific assets
│   ├── api/                       # Main API directory (shared)
│   │   ├── config.php             # API configuration
│   │   ├── admin_api.php          # Core admin operations
│   │   ├── student_api.php        # Student operations
│   │   ├── checkin_api.php        # Attendance check-in
│   │   └── [other_apis].php
│   ├── includes/                  # Student portal includes
│   │   ├── config.php             # Student portal config
│   │   ├── auth.php               # Student authentication
│   │   └── env.php                # Environment loader (symlink/copy from root)
│   ├── includes_ext/              # Extended security modules
│   │   ├── secure_session.php
│   │   ├── csrf_protection.php
│   │   └── secure_upload.php
│   ├── cron/                      # Scheduled tasks
│   │   ├── auto_absent.php        # Intelligent auto-absent marking
│   │   └── mark_absent.php        # Simple absent marking
│   ├── [pages].php                # Student portal pages
│   ├── config.env                 # Main configuration file (NOT in version control)
│   └── composer.json              # PHP dependencies
├── logs/                          # Application logs (created at runtime)
├── uploads/                       # User uploads
├── backups/                       # Database backups
└── temp/                          # Temporary files
```

## Common Development Patterns

### Database Queries

**Always use prepared statements**:
```php
// CORRECT
$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

// WRONG - SQL injection risk
$result = $pdo->query("SELECT * FROM students WHERE student_id = '$studentId'");
```

### API Response Format

```php
// Success response
echo json_encode([
    'success' => true,
    'data' => $result,
    'message' => 'Operation completed successfully'
]);

// Error response
http_response_code(400);
echo json_encode([
    'success' => false,
    'error' => 'Error type',
    'message' => 'Human-readable error message'
]);
```

### Authentication Checking

```php
// In API files
require_once 'config.php';
requireAuth(); // Function defined in admin_api.php

// In page files (admin)
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireAdminAuth();

// In page files (student)
require_once 'includes/config.php';
require_once 'includes/auth.php';
if (!isStudentLoggedIn()) {
    header('Location: login.php');
    exit();
}
```

### CSRF Protection

```php
// In forms (HTML)
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

// In JavaScript/AJAX
headers: {
    'X-CSRF-Token': csrfToken
}

// In API validation (already implemented in config.php)
requireCsrfForMethods(['POST', 'PUT', 'PATCH', 'DELETE']);
```

### Logging

```php
// System logs
logMessage("Description of event", 'INFO'); // or 'ERROR', 'WARNING'

// Admin action logs
logAdminAction('ACTION_TYPE', 'Details of action');

// Student action logs
logStudentAction('ACTION_TYPE', 'Details of action');

// Security events
AuthMiddleware::logSecurityEvent('EVENT_TYPE', 'Details');
```

## Key System Settings

**Dynamic Settings** (stored in `system_settings` table, managed via admin settings page):
- Morning check-in start/end times
- Evening check-in start/end times
- Grace period for late arrivals
- Academic year start date
- System timezone

**Static Settings** (in `public/config.env`):
- Database credentials
- SMTP configuration
- API keys and secrets
- Session configuration
- File upload limits

## Cron Job Setup (Auto-Absent System)

**Windows Task Scheduler**:
```
Task Name: QR Attendance Auto Absent
Trigger: Daily at 11:30 AM and 5:30 PM
Action: Start a program
  Program: C:\xampp\php\php.exe
  Arguments: C:\xampp\htdocs\qr\public\cron\auto_absent.php
```

**Linux Cron**:
```bash
# Add to crontab
30 11 * * * /usr/bin/php /path/to/qr/public/cron/auto_absent.php
30 17 * * * /usr/bin/php /path/to/qr/public/cron/auto_absent.php
```

## Important Notes for Development

1. **Session Management**: Both portals share the same session name (`QR_ATTENDANCE_SESSION`) to enable unified authentication. Be careful when modifying session handling.

2. **Shift-Specific Logic**: Many operations are shift-aware (Morning/Evening). Always filter by shift when querying students or attendance for shift-specific operations.

3. **Timezone Handling**: System uses `Asia/Karachi` by default (configurable via `TIMEZONE` env var). All database timestamps should be in system timezone.

4. **QR Code Storage**: QR codes can be generated on-demand or pre-generated. Current implementation generates on-demand via API.

5. **Error Handling**: When `DEBUG_MODE=false`, generic error messages are shown to users. Check logs for actual error details.

6. **API CORS**: API responses include CORS headers based on `FRONTEND_URL`/`FRONTEND_ORIGIN` env variables. Adjust these for production deployments.

7. **File Paths**: The system uses both absolute and relative paths. When working with includes, be aware of the current working directory context.

8. **Password Security**: All password operations should use `password_hash()` and `password_verify()`. Never store plaintext passwords.

9. **Rate Limiting**: Login attempts are rate-limited per IP. This is session-based (not database-backed), so restarting the server resets counters.

10. **Bulk Operations**: Use `bulk_import_api.php` for importing multiple students. Supports CSV parsing and validation.
