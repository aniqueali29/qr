-- Migration: Remove plaintext credentials from students table and add helpful indexes

-- 1) Drop insecure columns if they exist
ALTER TABLE `students`
  DROP COLUMN IF EXISTS `password`,
  DROP COLUMN IF EXISTS `username`;

-- 2) Add indexes for performance (ignore errors if existing)
ALTER TABLE `students`
  ADD INDEX `idx_students_student_id` (`student_id`);

ALTER TABLE `attendance`
  ADD INDEX `idx_attendance_student_timestamp` (`student_id`, `timestamp`);

ALTER TABLE `sections`
  ADD INDEX `idx_sections_program_year_shift` (`program_id`, `year_level`, `shift`);


