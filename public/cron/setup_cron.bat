
@echo off
REM ============================================================================
REM Setup Windows Task Scheduler for QR Attendance System
REM Run this file as Administrator
REM ============================================================================

echo.
echo ========================================
echo QR Attendance - Cron Setup
echo ========================================
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% NEQ 0 (
    echo ERROR: This script must be run as Administrator
    echo Right-click and select "Run as administrator"
    pause
    exit /b 1
)

echo Setting up all scheduled tasks...
echo.

REM Delete existing tasks if they exist
schtasks /delete /tn "QR_Attendance_Auto_Absent" /f >nul 2>&1
schtasks /delete /tn "QR_Attendance_Morning_Absent" /f >nul 2>&1
schtasks /delete /tn "QR_Attendance_Evening_Absent" /f >nul 2>&1
schtasks /delete /tn "QR_Attendance_Log_Cleanup" /f >nul 2>&1
schtasks /delete /tn "QR_Attendance_Database_Backup" /f >nul 2>&1

echo.

REM Create unified absent marking task (runs at 9:00 PM daily - after all shifts end)
echo Creating unified absent marking task (9:00 PM - after all shifts)...
schtasks /create /tn "QR_Attendance_Auto_Absent" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\qr\public\cron\cron.php absent" /sc daily /st 21:00 /ru SYSTEM /f

if %errorLevel% EQU 0 (
    echo [SUCCESS] Unified absent marking task created
) else (
    echo [ERROR] Failed to create absent marking task
)

REM Create log cleanup task (runs daily at midnight)
echo Creating log cleanup task (12:00 AM)...
schtasks /create /tn "QR_Attendance_Log_Cleanup" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\qr\public\cron\cron.php cleanup" /sc daily /st 00:00 /ru SYSTEM /f

if %errorLevel% EQU 0 (
    echo [SUCCESS] Log cleanup task created
) else (
    echo [ERROR] Failed to create log cleanup task
)

REM Create database backup task (runs weekly on Sunday at 2:00 AM)
echo Creating database backup task (Sunday 2:00 AM)...
schtasks /create /tn "QR_Attendance_Database_Backup" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\qr\public\cron\cron.php backup" /sc weekly /d SUN /st 02:00 /ru SYSTEM /f

if %errorLevel% EQU 0 (
    echo [SUCCESS] Database backup task created
) else (
    echo [ERROR] Failed to create database backup task
)

echo.
echo.


echo.
echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo Scheduled tasks created:
echo 1. Auto Absent Marking (UNIFIED) - Runs daily at 9:00 PM
echo 2. Log Cleanup - Runs daily at 12:00 AM
echo 3. Database Backup - Runs weekly on Sunday at 2:00 AM
echo.
echo UNIFIED ABSENT MARKING FEATURES:
echo - Automatically processes BOTH morning and evening shifts
echo - Phase 1: Marks students absent who didn't check in
echo - Phase 2: Marks students absent who checked in but didn't check out
echo - Single cron job handles everything - no manual shift parameter needed
echo.
echo To view tasks:
echo   schtasks /query /tn QR_Attendance_Auto_Absent
echo   schtasks /query /tn QR_Attendance_Log_Cleanup
echo   schtasks /query /tn QR_Attendance_Database_Backup
echo.
echo To delete all: schtasks /delete /tn QR_Attendance_* /f
echo.
echo IMPORTANT:
echo - Unified task runs ONCE daily after ALL shifts end (9:00 PM)
echo - Automatically detects which shifts need processing
echo - Adjust run time if your shifts end later than 9:00 PM
echo - Backup frequency and log retention are configurable in Settings
echo - Enable/disable auto-absent in Admin Settings
echo.

pause
