-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 30, 2025 at 06:09 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `qr_attendance`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `year`, `start_date`, `end_date`, `is_current`, `created_at`) VALUES
(1, 2025, '2024-09-01', '2025-08-31', 1, '2025-10-11 11:16:08');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `timestamp` datetime NOT NULL,
  `status` enum('Check-in','Present','Absent') NOT NULL,
  `check_in_time` datetime DEFAULT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `session_duration` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admission_year` int(11) DEFAULT NULL,
  `current_year` int(11) DEFAULT NULL,
  `shift` varchar(20) DEFAULT NULL,
  `program` varchar(10) DEFAULT NULL,
  `is_graduated` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `student_name`, `timestamp`, `status`, `check_in_time`, `check_out_time`, `session_duration`, `created_at`, `updated_at`, `admission_year`, `current_year`, `shift`, `program`, `is_graduated`, `notes`) VALUES
(33, '25-MET-0002', 'Anique Ali', '2025-10-26 20:58:30', 'Absent', '2025-10-26 20:58:30', NULL, NULL, '2025-10-26 15:58:30', '2025-10-26 15:58:51', 2025, 1, 'Evening', 'MET', 0, ' | Auto-marked absent: No checkout by deadline'),
(34, '25-MET-0001', 'SYED ABDUAL BASIT', '2025-10-26 20:00:00', 'Absent', NULL, NULL, NULL, '2025-10-26 15:58:51', '2025-10-26 15:58:51', NULL, NULL, 'Evening', NULL, 0, 'Auto-marked absent: No check-in by deadline'),
(35, '25-MET-0002', 'Anique Ali', '2025-10-26 20:58:30', 'Present', '2025-10-26 20:58:30', '2025-10-29 09:54:36', 3656, '2025-10-29 04:54:36', '2025-10-29 04:54:36', 2025, 1, 'Evening', 'MET', 0, NULL),
(36, '25-MET-0002', 'Anique Ali', '2025-10-29 09:54:58', 'Present', '2025-10-29 09:54:58', '2025-10-29 09:56:27', 1, '2025-10-29 04:54:58', '2025-10-29 04:56:27', 2025, 1, 'Evening', 'MET', 0, '');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  `action` varchar(100) NOT NULL,
  `user_type` enum('admin','student') NOT NULL,
  `user_id` varchar(100) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `details` text DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `timestamp`, `action`, `user_type`, `user_id`, `ip_address`, `user_agent`, `details`, `data`) VALUES
(1, '2025-10-24 20:48:53', 'database_backup', 'admin', 'system', 'unknown', 'unknown', 'Automatic backup created: backup_2025-10-24_204851.sql.gz', 'null'),
(2, '2025-10-25 01:10:04', 'database_backup', 'admin', 'system', 'unknown', 'unknown', 'Automatic backup created: backup_2025-10-25_011003.sql.gz', 'null'),
(3, '2025-10-25 01:11:31', 'database_backup', 'admin', 'system', 'unknown', 'unknown', 'Automatic backup created: backup_2025-10-25_011131.sql.gz', 'null'),
(4, '2025-10-25 01:19:31', 'database_backup', 'admin', 'system', 'unknown', 'unknown', 'Automatic backup created: backup_2025-10-25_011931.sql.gz', 'null'),
(5, '2025-10-25 01:20:24', 'database_backup', 'admin', 'system', 'unknown', 'unknown', 'Automatic backup created: backup_2025-10-25_012024.sql.gz', 'null'),
(6, '2025-10-25 01:24:02', 'database_backup', 'admin', 'system', 'unknown', 'unknown', 'Automatic backup created: backup_2025-10-25_012402.sql.gz', 'null');

-- --------------------------------------------------------

--
-- Table structure for table `auth_sessions`
--

CREATE TABLE `auth_sessions` (
  `id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auth_sessions`
--

INSERT INTO `auth_sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `last_activity`, `created_at`) VALUES
('13fac7294aef9752c54928a08e4f4231cd13f1b9fff129b929d94488c33612b7', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-30 05:08:20', '2025-10-30 04:25:24'),
('19178c03345844b2778942fad68f61b95e8d78f5be2ad56f3295e3967920de6d', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 03:04:40', '2025-10-26 21:59:32'),
('4302cf403f1363f74321e03ebf5b8aec006d2f538b239519eb874df06989e987', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-30 04:17:09', '2025-10-30 04:17:07'),
('6e9d2eb6fbdf16fc9ac9945c45f49a440bf8559e2aff444a68fa431ff62447a9', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 05:41:34', '2025-10-27 05:41:32'),
('85893b49bb4edc1dc6e7297e86b1dbb2440abc3c514ba99c2471cb7757ebc988', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-29 05:42:01', '2025-10-29 04:49:12'),
('be98c6897248860d4d6d76d3b3f1888e2d60072a7017d1c11552d473e7d641cb', 26, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 00:57:52', '2025-10-27 00:27:52'),
('e15a20e1f79e3e04598e9b28123100d6a9334ca670fbefb63d4658dcc2ea18c6', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-29 07:07:12', '2025-10-29 06:12:39'),
('ee6e9ac3c04c88f0a852c7ac44feeda94542ad6d463c623b5553b34144948aeb', 27, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 00:55:20', '2025-10-27 00:54:18'),
('ef759513477ba3fea35994f365da6b8764a6ed4a5bf37595ecea74a959e88943', 26, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 04:59:05', '2025-10-27 04:21:22'),
('ffb402a88dfd1856ab50fc440c995a5bb5f9f8b49492c53466bf3d3887bd6a4b', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-26 21:56:39', '2025-10-26 21:02:38');

-- --------------------------------------------------------

--
-- Table structure for table `check_in_sessions`
--

CREATE TABLE `check_in_sessions` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `check_in_time` datetime NOT NULL,
  `last_activity` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `verification_code` varchar(6) NOT NULL,
  `purpose` enum('password_reset','email_change') NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_verifications`
--

INSERT INTO `email_verifications` (`id`, `student_id`, `email`, `verification_code`, `purpose`, `expires_at`, `is_used`, `created_at`) VALUES
(19, '24-ESWT-01', 'aniqueali000@gmail.com', '587253', 'password_reset', '2025-10-18 13:58:00', 1, '2025-10-18 08:43:00'),
(20, '24-ESWT-01', 'aniqueali000@gmail.com', '589890', 'password_reset', '2025-10-18 13:59:44', 0, '2025-10-18 08:44:44'),
(21, '24-ESWT-01', 'aniqueali000@gmail.com', '563468', 'password_reset', '2025-10-18 14:02:52', 1, '2025-10-18 08:47:52'),
(22, '24-ESWT-01', 'aniqueali000@gmail.com', '664199', 'password_reset', '2025-10-18 16:55:53', 0, '2025-10-18 11:40:53'),
(23, '25-SWT-330', 'student1_1761307066330@college.edu', '330806', 'password_reset', '2025-10-24 17:15:24', 0, '2025-10-24 12:00:24'),
(24, '25-SWT-330', 'aniqueali28@gmail.com', '314397', 'password_reset', '2025-10-24 17:16:33', 1, '2025-10-24 12:01:33'),
(25, '25-SWT-331', 'student2_1761307066330@college.edu', '900906', 'password_reset', '2025-10-25 00:52:35', 0, '2025-10-24 19:37:35');

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_sessions`
--

CREATE TABLE `enrollment_sessions` (
  `id` int(11) NOT NULL,
  `code` varchar(32) NOT NULL,
  `label` varchar(64) NOT NULL,
  `term` enum('Spring','Summer','Fall','Winter') NOT NULL,
  `year` smallint(6) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollment_sessions`
--

INSERT INTO `enrollment_sessions` (`id`, `code`, `label`, `term`, `year`, `start_date`, `end_date`, `is_active`, `created_at`, `updated_at`) VALUES
(5, 'SU2025', 'Summer 2025', 'Summer', 2025, '2025-06-01', '2025-08-15', 1, '2025-10-26 10:16:45', '2025-10-26 10:16:45'),
(6, 'F2025', 'Fall 2025', 'Fall', 2025, '2025-08-16', '2025-12-31', 1, '2025-10-26 10:16:45', '2025-10-26 15:08:29');

-- --------------------------------------------------------

--
-- Table structure for table `import_logs`
--

CREATE TABLE `import_logs` (
  `id` int(11) NOT NULL,
  `import_type` varchar(50) NOT NULL,
  `total_records` int(11) NOT NULL,
  `successful_records` int(11) NOT NULL,
  `failed_records` int(11) NOT NULL,
  `error_details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `import_logs`
--

INSERT INTO `import_logs` (`id`, `import_type`, `total_records`, `successful_records`, `failed_records`, `error_details`, `created_at`) VALUES
(1, 'student_import', 4, 4, 0, '[]', '2025-10-13 06:09:17'),
(2, 'student_import', 4, 4, 0, '[]', '2025-10-13 07:07:44'),
(3, 'student_import', 4, 4, 0, '[]', '2025-10-13 07:08:19'),
(4, 'student_import', 4, 4, 0, '[]', '2025-10-13 07:27:36'),
(5, 'student_import', 4, 4, 0, '[]', '2025-10-15 20:53:30'),
(6, 'student_import', 4, 4, 0, '[]', '2025-10-16 08:28:34'),
(7, 'student_import', 4, 4, 0, '[]', '2025-10-16 08:43:15'),
(8, 'student_import', 4, 4, 0, '[]', '2025-10-24 11:58:15'),
(9, 'student_import', 4, 0, 4, '[{\"success\":false,\"row\":1,\"student_id\":\"25-SWT-330\",\"error\":\"SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'25-SWT-330\' for key \'student_id\'\"},{\"success\":false,\"row\":2,\"student_id\":\"25-SWT-331\",\"error\":\"SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'25-SWT-331\' for key \'student_id\'\"},{\"success\":false,\"row\":3,\"student_id\":\"25-CIT-332\",\"error\":\"SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'25-CIT-332\' for key \'student_id\'\"},{\"success\":false,\"row\":4,\"student_id\":\"25-CIT-333\",\"error\":\"SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'25-CIT-333\' for key \'student_id\'\"}]', '2025-10-24 11:58:21'),
(10, 'student_import', 4, 0, 4, '[{\"success\":false,\"row\":1,\"student_id\":\"25-SWT-330\",\"error\":\"SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'25-SWT-330\' for key \'student_id\'\"},{\"success\":false,\"row\":2,\"student_id\":\"25-SWT-331\",\"error\":\"SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'25-SWT-331\' for key \'student_id\'\"},{\"success\":false,\"row\":3,\"student_id\":\"25-CIT-332\",\"error\":\"SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'25-CIT-332\' for key \'student_id\'\"},{\"success\":false,\"row\":4,\"student_id\":\"25-CIT-333\",\"error\":\"SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'25-CIT-333\' for key \'student_id\'\"}]', '2025-10-24 11:58:27');

-- --------------------------------------------------------

--
-- Table structure for table `laravel_sessions`
--

CREATE TABLE `laravel_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `created_at`, `used_at`) VALUES
(1, 'aniqueali000@gmail.com', '1c510ec757adbd5f0637b9889d71c0afef3e82ec0d7318c74e687714e7424a80', '2025-10-24 17:52:19', '2025-10-24 11:52:19', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `enabled_shifts` varchar(255) DEFAULT 'Morning,Evening',
  `duration_years` int(11) DEFAULT 4,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`id`, `code`, `name`, `description`, `enabled_shifts`, `duration_years`, `is_active`, `created_at`, `updated_at`) VALUES
(26, 'MET', 'Mechanical', 'Mechanical', 'Morning,Evening', 4, 1, '2025-10-25 17:16:42', '2025-10-25 17:16:42'),
(27, 'EET', 'Electrical', 'Electrical', 'Morning,Evening', 4, 1, '2025-10-25 17:17:07', '2025-10-25 17:17:15'),
(31, 'SWT', 'Software Technology', '', 'Evening', 4, 1, '2025-10-26 13:47:48', '2025-10-26 13:47:48');

-- --------------------------------------------------------

--
-- Stand-in structure for view `program_stats`
-- (See below for the actual view)
--
CREATE TABLE `program_stats` (
`id` int(11)
,`code` varchar(10)
,`name` varchar(100)
,`is_active` tinyint(1)
,`total_students` bigint(21)
,`total_sections` bigint(21)
,`total_capacity` decimal(32,0)
,`avg_attendance` decimal(32,6)
);

-- --------------------------------------------------------

--
-- Table structure for table `qr_codes`
--

CREATE TABLE `qr_codes` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `qr_data` text NOT NULL,
  `qr_image_path` varchar(255) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_codes`
--

INSERT INTO `qr_codes` (`id`, `student_id`, `qr_data`, `qr_image_path`, `generated_at`, `is_active`, `created_by`) VALUES
(35, '22-SWT-01', '22-SWT-01', 'C:\\xampp\\htdocs\\qr_attendance\\public/assets/img/qr_codes/qr_22-SWT-01_1760186456.png', '2025-10-11 12:40:56', 1, 10),
(36, '22-SWT-02', '22-SWT-02', 'C:\\xampp\\htdocs\\qr_attendance\\public/assets/img/qr_codes/qr_22-SWT-02_1760210056.png', '2025-10-11 19:14:16', 1, 10),
(37, '22-ESWT-02', '22-ESWT-02', 'C:\\xampp\\htdocs\\qr_attendance\\public/assets/img/qr_codes/qr_22-ESWT-02_1760217877.png', '2025-10-11 21:24:37', 1, 10),
(38, '25-SWT-26', '25-SWT-26', 'C:\\xampp\\htdocs\\qr_attendance\\public/assets/img/qr_codes/qr_25-SWT-26_1760429175.png', '2025-10-14 08:06:15', 1, 10),
(39, '25-SWT-03', '25-SWT-03', 'C:\\xampp\\htdocs\\qr_attendance\\public/assets/img/qr_codes/qr_25-SWT-03_1760429238.png', '2025-10-14 08:07:18', 1, 10),
(40, '24-ESWT-01', '24-ESWT-01', 'C:\\xampp\\htdocs\\qr_attendance\\public/assets/img/qr_codes/qr_24-ESWT-01_1760444369.png', '2025-10-14 12:19:29', 1, 10),
(75, '25-SWT-595', '{\"student_id\":\"25-SWT-595\",\"name\":\"Sample Student 1\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10),
(76, '25-SWT-596', '{\"student_id\":\"25-SWT-596\",\"name\":\"Sample Student 2\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10),
(77, '25-CIT-597', '{\"student_id\":\"25-CIT-597\",\"name\":\"Sample Student 3\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10),
(78, '25-CIT-598', '{\"student_id\":\"25-CIT-598\",\"name\":\"Sample Student 4\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10),
(79, '25-SWT-599', '{\"student_id\":\"25-SWT-599\",\"name\":\"Sample Student 1\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10),
(80, '25-SWT-600', '{\"student_id\":\"25-SWT-600\",\"name\":\"Sample Student 2\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10),
(81, '25-CIT-601', '{\"student_id\":\"25-CIT-601\",\"name\":\"Sample Student 3\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10),
(82, '25-CIT-602', '{\"student_id\":\"25-CIT-602\",\"name\":\"Sample Student 4\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10),
(83, '25-SWT-330', '{\"student_id\":\"25-SWT-330\",\"name\":\"Sample Student 1\",\"timestamp\":1761318293,\"type\":\"attendance\"}', '', '2025-10-24 15:04:53', 1, 10),
(84, '25-SWT-331', '{\"student_id\":\"25-SWT-331\",\"name\":\"Sample Student 2\",\"timestamp\":1761318293,\"type\":\"attendance\"}', '', '2025-10-24 15:04:53', 1, 10),
(85, '25-CIT-332', '{\"student_id\":\"25-CIT-332\",\"name\":\"Sample Student 3\",\"timestamp\":1761318293,\"type\":\"attendance\"}', '', '2025-10-24 15:04:53', 1, 10),
(86, '25-CIT-333', '{\"student_id\":\"25-CIT-333\",\"name\":\"Sample Student 4\",\"timestamp\":1761318293,\"type\":\"attendance\"}', '', '2025-10-24 15:04:53', 1, 10),
(87, '25-MET-0001', '{\"student_id\":\"25-MET-0001\",\"name\":\"SYED ABDUAL BASIT\",\"timestamp\":1761516770,\"type\":\"attendance\"}', '', '2025-10-26 22:12:50', 1, 1),
(88, '25-MET-0002', '{\"student_id\":\"25-MET-0002\",\"name\":\"Anique Ali\",\"timestamp\":1761516770,\"type\":\"attendance\"}', '', '2025-10-26 22:12:50', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `difficulty` enum('Easy','Medium','Hard') DEFAULT 'Medium',
  `time_limit` int(11) NOT NULL DEFAULT 30,
  `total_questions` int(11) NOT NULL DEFAULT 10,
  `ai_generated` tinyint(1) DEFAULT 0,
  `ai_model` varchar(50) DEFAULT NULL,
  `ai_prompt` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `show_results` tinyint(1) DEFAULT 1,
  `allow_retake` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`id`, `title`, `description`, `subject`, `difficulty`, `time_limit`, `total_questions`, `ai_generated`, `ai_model`, `ai_prompt`, `created_by`, `created_at`, `updated_at`, `is_active`, `show_results`, `allow_retake`) VALUES
(1, 'Ai', '', 'python', 'Medium', 30, 10, 1, 'gpt-4o', 'Create a quiz about Python programming basics covering variables, loops, and functions', 1, '2025-10-27 01:56:25', '2025-10-27 01:56:25', 1, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `quiz_assignments`
--

CREATE TABLE `quiz_assignments` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `session_id` int(11) DEFAULT NULL,
  `program` varchar(50) DEFAULT NULL,
  `assigned_by` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_attempts`
--

CREATE TABLE `quiz_attempts` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `started_at` timestamp NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `submitted` tinyint(1) DEFAULT 0,
  `score` decimal(5,2) DEFAULT 0.00,
  `total_points` int(11) DEFAULT 0,
  `earned_points` int(11) DEFAULT 0,
  `time_taken` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','true_false','short_answer','fill_blank') DEFAULT 'multiple_choice',
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `correct_answer` text NOT NULL,
  `explanation` text DEFAULT NULL,
  `points` int(11) DEFAULT 1,
  `order_number` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`id`, `quiz_id`, `question_text`, `question_type`, `options`, `correct_answer`, `explanation`, `points`, `order_number`, `created_at`) VALUES
(1, 1, 'Which of the following is a valid variable name in Python?', 'multiple_choice', '{\"A\":\"3variable\",\"B\":\"variable_name\",\"C\":\"variable-name\",\"D\":\"variable name\"}', '1', NULL, 1, 1, '2025-10-27 01:56:26'),
(2, 1, 'What will the following code output? \n\nx = 5\ny = 10\nprint(x + y)', 'multiple_choice', '{\"A\":\"15\",\"B\":\"510\",\"C\":\"5 + 10\",\"D\":\"An error\"}', '0', NULL, 1, 2, '2025-10-27 01:56:26'),
(3, 1, 'Which keyword is used to create a function in Python?', 'multiple_choice', '{\"A\":\"function\",\"B\":\"func\",\"C\":\"def\",\"D\":\"define\"}', '2', NULL, 1, 3, '2025-10-27 01:56:26'),
(4, 1, 'What is the output of the following code? \n\nfor i in range(3):\n    print(i)', 'multiple_choice', '{\"A\":\"0, 1, 2\",\"B\":\"1, 2, 3\",\"C\":\"3\",\"D\":\"An error\"}', '0', NULL, 1, 4, '2025-10-27 01:56:26'),
(5, 1, 'How do you check the data type of a variable in Python?', 'multiple_choice', '{\"A\":\"typeof(variable)\",\"B\":\"getType(variable)\",\"C\":\"type(variable)\",\"D\":\"checkType(variable)\"}', '2', NULL, 1, 5, '2025-10-27 01:56:26'),
(6, 1, 'What will the following code do? \n\nx = [1, 2, 3]\nfor num in x:\n    print(num * 2)', 'multiple_choice', '{\"A\":\"Prints 1, 2, 3\",\"B\":\"Prints 2, 4, 6\",\"C\":\"Prints [2, 4, 6]\",\"D\":\"Throws an error\"}', '1', NULL, 1, 6, '2025-10-27 01:56:26'),
(7, 1, 'What is the purpose of the \'return\' statement in a function?', 'multiple_choice', '{\"A\":\"To exit the function and print a value\",\"B\":\"To exit the function and return a value\",\"C\":\"To define the output of the function\",\"D\":\"To iterate over a list\"}', '1', NULL, 1, 7, '2025-10-27 01:56:26'),
(8, 1, 'What will the following code output? \n\ndef greet(name):\n    return f\'Hello, {name}\'\n\nprint(greet(\'Alice\'))', 'multiple_choice', '{\"A\":\"Hello, Alice\",\"B\":\"greet(\'Alice\')\",\"C\":\"An error\",\"D\":\"Hello Alice\"}', '0', NULL, 1, 8, '2025-10-27 01:56:26'),
(9, 1, 'Which of the following is NOT a valid loop in Python?', 'multiple_choice', '{\"A\":\"for\",\"B\":\"while\",\"C\":\"foreach\",\"D\":\"None of the above\"}', '2', NULL, 1, 9, '2025-10-27 01:56:26'),
(10, 1, 'What does the \'break\' statement do in a loop?', 'multiple_choice', '{\"A\":\"Skips the current iteration and continues with the next\",\"B\":\"Exits the loop immediately\",\"C\":\"Restarts the loop from the beginning\",\"D\":\"Throws an error\"}', '1', NULL, 1, 10, '2025-10-27 01:56:26');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_responses`
--

CREATE TABLE `quiz_responses` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `points_earned` int(11) DEFAULT 0,
  `answered_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `year_level` enum('1st','2nd','3rd') NOT NULL,
  `section_name` varchar(10) NOT NULL,
  `shift` enum('Morning','Evening') NOT NULL,
  `capacity` int(11) DEFAULT 40,
  `current_students` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `program_id`, `year_level`, `section_name`, `shift`, `capacity`, `current_students`, `is_active`, `created_at`, `updated_at`) VALUES
(26, 26, '', 'A', 'Evening', 80, 0, 1, '2025-10-25 18:53:08', '2025-10-25 18:53:08'),
(46, 31, '', 'A', 'Evening', 100, 0, 1, '2025-10-26 13:47:48', '2025-10-26 13:47:48');

-- --------------------------------------------------------

--
-- Stand-in structure for view `section_stats`
-- (See below for the actual view)
--
CREATE TABLE `section_stats` (
`id` int(11)
,`section_name` varchar(10)
,`program_code` varchar(10)
,`program_name` varchar(100)
,`year_level` enum('1st','2nd','3rd')
,`shift` enum('Morning','Evening')
,`capacity` int(11)
,`current_students` bigint(21)
,`capacity_utilization` decimal(26,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `code` varchar(32) NOT NULL,
  `label` varchar(64) NOT NULL,
  `term` enum('Spring','Summer','Fall','Winter') NOT NULL,
  `year` smallint(6) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `code`, `label`, `term`, `year`, `start_date`, `end_date`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'SP2024', 'Spring 2024', 'Spring', 2024, '2024-01-15', '2024-05-31', 1, '2025-10-26 09:09:31', '2025-10-26 09:09:31'),
(2, 'SU2024', 'Summer 2024', 'Summer', 2024, '2024-06-01', '2024-08-15', 1, '2025-10-26 09:09:31', '2025-10-26 09:09:31'),
(3, 'F2024', 'Fall 2024', 'Fall', 2024, '2024-08-16', '2024-12-31', 1, '2025-10-26 09:09:31', '2025-10-26 09:09:31'),
(4, 'SP2025', 'Spring 2025', 'Spring', 2025, '2025-01-15', '2025-05-31', 1, '2025-10-26 09:09:31', '2025-10-26 09:09:31'),
(5, 'SU2025', 'Summer 2025', 'Summer', 2025, '2025-06-01', '2025-08-15', 1, '2025-10-26 09:09:31', '2025-10-26 09:09:31'),
(6, 'F2025', 'Fall 2025', 'Fall', 2025, '2025-08-16', '2025-12-31', 1, '2025-10-26 09:09:31', '2025-10-26 09:54:30');

-- --------------------------------------------------------

--
-- Table structure for table `shift_timings`
--

CREATE TABLE `shift_timings` (
  `id` int(11) NOT NULL,
  `shift_name` enum('Morning','Evening') NOT NULL,
  `checkin_start` time NOT NULL,
  `checkin_end` time NOT NULL,
  `class_end` time NOT NULL,
  `minimum_duration_minutes` int(11) DEFAULT 120,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shift_timings`
--

INSERT INTO `shift_timings` (`id`, `shift_name`, `checkin_start`, `checkin_end`, `class_end`, `minimum_duration_minutes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Morning', '09:00:00', '11:00:00', '13:40:00', 120, 1, '2025-10-11 11:16:08', '2025-10-11 11:16:08'),
(2, 'Evening', '15:00:00', '16:00:00', '18:00:00', 120, 1, '2025-10-11 11:16:08', '2025-10-11 11:16:08');

-- --------------------------------------------------------

--
-- Table structure for table `staff_permissions`
--

CREATE TABLE `staff_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `page_url` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_permissions`
--

INSERT INTO `staff_permissions` (`id`, `user_id`, `page_url`, `created_at`, `updated_at`) VALUES
(83, 26, 'index.php', '2025-10-27 00:28:58', '2025-10-27 00:28:58'),
(84, 26, 'students.php', '2025-10-27 00:28:58', '2025-10-27 00:28:58'),
(85, 26, 'attendances.php', '2025-10-27 00:28:58', '2025-10-27 00:28:58'),
(86, 26, 'sessions.php', '2025-10-27 00:28:58', '2025-10-27 00:28:58'),
(87, 26, 'scan.php', '2025-10-27 00:28:58', '2025-10-27 00:28:58'),
(88, 26, 'program_sections.php', '2025-10-27 00:28:58', '2025-10-27 00:28:58'),
(89, 26, 'reports.php', '2025-10-27 00:28:58', '2025-10-27 00:28:58'),
(90, 26, 'settings.php', '2025-10-27 00:28:58', '2025-10-27 00:28:58'),
(91, 27, 'index.php', '2025-10-27 00:52:54', '2025-10-27 00:52:54'),
(92, 27, 'scan.php', '2025-10-27 00:52:54', '2025-10-27 00:52:54'),
(93, 27, 'settings.php', '2025-10-27 00:52:54', '2025-10-27 00:52:54');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `roll_number` varchar(20) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admission_year` int(11) DEFAULT NULL,
  `enrollment_session_id` int(11) DEFAULT NULL,
  `current_year` int(11) DEFAULT 1,
  `shift` enum('Morning','Evening') DEFAULT 'Morning',
  `program` varchar(50) DEFAULT NULL,
  `last_year_update` date DEFAULT NULL,
  `is_graduated` tinyint(1) DEFAULT 0,
  `year_level` varchar(50) DEFAULT NULL,
  `current_semester` tinyint(4) DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `roll_prefix` varchar(20) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `attendance_percentage` decimal(5,2) DEFAULT 0.00,
  `username` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `roll_number`, `name`, `email`, `phone`, `password`, `user_id`, `is_active`, `created_at`, `updated_at`, `admission_year`, `enrollment_session_id`, `current_year`, `shift`, `program`, `last_year_update`, `is_graduated`, `year_level`, `current_semester`, `section`, `profile_picture`, `roll_prefix`, `section_id`, `attendance_percentage`, `username`) VALUES
(165, '25-MET-0001', '25-MET-0001', 'SYED ABDUAL BASIT', '', '03143964789', '25-MET-0001', NULL, 1, '2025-10-25 19:35:16', '2025-10-27 04:27:21', 2025, 6, 1, 'Evening', 'MET', NULL, 0, 'Semester 1', 1, 'A', NULL, NULL, NULL, 0.00, '25-MET-0001'),
(166, '25-MET-0002', '25-MET-0002', 'Anique Ali', 'aniquecodes@gmail.com', '+923010020668', '$argon2id$v=19$m=65536,t=4,p=3$SGRUMVE4SWdJbllSVXhKVA$4jSXVl8NqXr7LdIjkR59gZf3+xOln8j8jLJ0nizBoUs', NULL, 1, '2025-10-26 09:52:02', '2025-10-27 04:27:21', 2025, 6, 1, 'Evening', 'MET', NULL, 0, 'Semester 1', 1, 'A', NULL, NULL, NULL, 0.00, '25-MET-0002');

-- --------------------------------------------------------

--
-- Stand-in structure for view `student_stats`
-- (See below for the actual view)
--
CREATE TABLE `student_stats` (
`student_id` varchar(50)
,`name` varchar(100)
,`email` varchar(100)
,`phone` varchar(20)
,`program` varchar(50)
,`shift` enum('Morning','Evening')
,`year_level` varchar(50)
,`section` varchar(10)
,`program_name` varchar(100)
,`capacity` int(11)
,`total_attendance` bigint(21)
,`present_count` decimal(22,0)
,`attendance_percentage` decimal(28,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `sync_logs`
--

CREATE TABLE `sync_logs` (
  `id` int(11) NOT NULL,
  `sync_type` enum('push_to_web','pull_from_web','bidirectional') NOT NULL,
  `status` enum('success','failed','partial') NOT NULL,
  `records_processed` int(11) DEFAULT 0,
  `records_failed` int(11) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `sync_duration` decimal(10,3) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` varchar(50) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `validation_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`validation_rules`)),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `category`, `description`, `validation_rules`, `last_updated`, `updated_by`, `created_at`) VALUES
(1, 'morning_checkin_start', '00:00:00', 'time', 'shift_timings', 'Morning shift check-in start time', '{\"required\":true,\"type\":\"time\"}', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(2, 'morning_checkin_end', '00:39:00', 'time', 'shift_timings', 'Morning shift check-in end time', '{\"required\":true,\"type\":\"time\"}', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(3, 'morning_checkout_start', '00:00:00', 'time', 'shift_timings', 'Morning shift check-out start time', '{\"required\":true,\"type\":\"time\"}', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(4, 'morning_checkout_end', '01:00:00', 'time', 'shift_timings', 'Morning shift check-out end time', '{\"required\":true,\"type\":\"time\"}', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(5, 'morning_class_end', '01:00:00', 'time', 'shift_timings', 'Morning shift class end time', '{\"required\":true,\"type\":\"time\"}', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(6, 'evening_checkin_start', '06:00:00', 'time', 'shift_timings', 'Evening shift check-in start time', '{\"required\":true,\"type\":\"time\"}', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(7, 'evening_checkin_end', '11:00:00', 'time', 'shift_timings', 'Evening shift check-in end time', '{\"required\":true,\"type\":\"time\"}', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(8, 'evening_checkout_start', '06:00:00', 'time', 'shift_timings', 'Evening shift check-out start time', '{\"required\":true,\"type\":\"time\"}', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(9, 'evening_checkout_end', '11:00:00', 'time', 'shift_timings', 'Evening shift check-out end time', '{\"required\":true,\"type\":\"time\"}', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(10, 'evening_class_end', '11:00:00', 'time', 'shift_timings', 'Evening shift class end time', '{\"required\":true,\"type\":\"time\"}', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(11, 'minimum_duration_minutes', '130', 'integer', 'system_config', 'Minimum duration in minutes for attendance', '{\"required\":true,\"min\":30,\"max\":480}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(12, 'sync_interval_seconds', '30', 'integer', 'system_config', 'Automatic sync interval in seconds', '{\"required\":true,\"min\":10,\"max\":300}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(13, 'timezone', 'Asia/Karachi', 'string', 'system_config', 'System timezone', '{\"required\":true,\"options\":[\"Asia\\/Karachi\",\"UTC\",\"America\\/New_York\",\"Europe\\/London\"]}', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(14, 'academic_year_start_month', '9', 'integer', 'system_config', 'Academic year start month (1-12)', '{\"required\":true,\"min\":1,\"max\":12}', '2025-10-25 19:34:23', 'admin', '2025-10-12 00:09:58'),
(15, 'auto_absent_morning_hour', '11', 'integer', 'system_config', 'Hour to mark morning shift absent (24h format)', '{\"required\":true,\"min\":8,\"max\":16}', '2025-10-26 11:27:04', 'admin', '2025-10-12 00:09:58'),
(16, 'auto_absent_evening_hour', '17', 'integer', 'system_config', 'Hour to mark evening shift absent (24h format)', '{\"required\":true,\"min\":14,\"max\":20}', '2025-10-26 11:27:04', 'admin', '2025-10-12 00:09:58'),
(17, 'website_url', 'http://localhost/qr_attendance/public', 'url', 'integration', 'Base URL of the web application', '{\"required\":true,\"type\":\"url\"}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(18, 'api_endpoint_attendance', '/api/api_attendance.php', 'string', 'integration', 'Attendance API endpoint', '[]', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(19, 'api_endpoint_checkin', '/api/checkin_api.php', 'string', 'integration', 'Check-in API endpoint', '[]', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(20, 'api_endpoint_dashboard', '/api/dashboard_api.php', 'string', 'integration', 'Dashboard API endpoint', '[]', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(21, 'api_key', 'attendance_2025_xyz789_secure', 'string', 'integration', 'API authentication key', '{\"required\":true,\"min_length\":10}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(22, 'api_timeout_seconds', '30', 'integer', 'integration', 'API request timeout in seconds', '{\"required\":true,\"min\":5,\"max\":120}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(23, 'debug_mode', 'enabled', 'boolean', 'advanced', 'Enable debug mode for development', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(24, 'log_errors', 'enabled', 'boolean', 'advanced', 'Enable error logging', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(25, 'show_errors', 'enabled', 'boolean', 'advanced', 'Show errors in development mode', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(26, 'session_timeout_seconds', '3600', 'integer', 'advanced', 'Session timeout seconds', '{\"required\":true,\"min\":300,\"max\":86400}', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(27, 'max_login_attempts', '5', 'integer', 'advanced', 'Max login attempts', '{\"required\":true,\"min\":3,\"max\":10}', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(28, 'login_lockout_minutes', '15', 'integer', 'advanced', 'Login lockout minutes', '{\"required\":true,\"min\":5,\"max\":60}', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(29, 'password_min_length', '8', 'integer', 'advanced', 'Password min length', '{\"required\":true,\"min\":6,\"max\":32}', '2025-10-29 04:49:46', 'admin', '2025-10-12 00:09:58'),
(30, 'max_sync_records', '1000', 'integer', 'advanced', 'Max sync records', '{\"required\":true,\"min\":100,\"max\":10000}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(31, 'api_rate_limit', '100', 'integer', 'advanced', 'Api rate limit', '{\"required\":true,\"min\":10,\"max\":1000}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(56, 'qr_code_size', '200', 'integer', 'qr_code', 'Qr code size', '{\"required\":true,\"min\":100,\"max\":500}', '2025-10-13 15:44:27', 'admin', '2025-10-12 00:29:17'),
(57, 'qr_code_margin', '10', 'integer', 'qr_code', 'Qr code margin', '{\"required\":true,\"min\":0,\"max\":50}', '2025-10-13 15:44:27', 'admin', '2025-10-12 00:29:17'),
(58, 'max_file_size_mb', '5', 'integer', 'file_upload', 'Max file size mb', '{\"required\":true,\"min\":1,\"max\":100}', '2025-10-13 15:44:27', 'admin', '2025-10-12 00:29:17'),
(59, 'smtp_host', 'smtp.gmail.com', 'string', 'email', 'Smtp host', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-12 00:29:17'),
(60, 'smtp_port', '587', 'integer', 'email', 'Smtp port', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-12 00:29:17'),
(61, 'smtp_from_email', 'noreply@example.com', 'email', 'email', 'Smtp from email', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-12 00:29:17'),
(62, 'smtp_from_name', 'QR Attendance System', 'string', 'email', 'Smtp from name', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-12 00:29:17'),
(96, 'qr_code_path', 'assets/img/qr_codes/', 'string', 'qr_code', 'Directory to store QR code images', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-13 08:03:02'),
(98, 'allowed_extensions', 'csv,json,xlsx', 'string', 'file_upload', 'Comma-separated list of allowed file extensions', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-13 08:03:02'),
(101, 'smtp_username', '', 'string', 'email', 'SMTP authentication username', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-13 08:03:02'),
(102, 'smtp_password', '', 'string', 'email', 'SMTP authentication password', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-13 08:03:02'),
(1725, 'globalSearchInput', '', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-15 21:16:01'),
(1726, 'searchAll', 'all', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-15 21:16:01'),
(1727, 'searchStudents', 'students', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-15 21:16:01'),
(1728, 'searchPrograms', 'programs', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-15 21:16:01'),
(1729, 'searchAttendance', 'attendance', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-15 21:16:01'),
(1730, 'api_endpoint_students', '/api/students_sync.php', 'string', 'integration', 'Students sync API endpoint', '[]', '2025-10-18 12:49:15', 'admin', '2025-10-18 12:49:15'),
(1731, 'api_endpoint_settings', '/api/settings_sync.php', 'string', 'integration', 'Settings sync API endpoint', '[]', '2025-10-18 12:49:15', 'admin', '2025-10-18 12:49:15'),
(1732, 'api_endpoint_settings_api', '/api/settings_api.php', 'string', 'integration', 'Settings API endpoint', '[]', '2025-10-18 12:49:15', 'admin', '2025-10-18 12:49:15'),
(1733, 'api_endpoint_student_api', '/api/student_api_simple.php', 'string', 'integration', 'Student API endpoint', '[]', '2025-10-18 12:49:15', 'admin', '2025-10-18 12:49:15'),
(1734, 'api_endpoint_admin_attendance', '/admin/api/attendance.php', 'string', 'integration', 'Admin attendance API endpoint', '[]', '2025-10-18 12:49:15', 'admin', '2025-10-18 12:49:15'),
(1735, 'api_endpoint_sync', '/api/sync_api.php', 'string', 'integration', 'Sync API endpoint', '[]', '2025-10-18 12:49:15', 'admin', '2025-10-18 12:49:15'),
(1736, 'enable_auto_absent', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 18:27:14'),
(1737, 'enable_audit_log', 'enabled', 'boolean', 'advanced', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 18:27:14'),
(1738, 'log_retention_days', '30', 'string', 'advanced', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 18:27:14'),
(1739, 'require_password_change', 'enabled', 'boolean', 'advanced', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 18:27:14'),
(1740, 'backup_frequency', 'weekly', 'string', 'advanced', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 18:27:14'),
(1741, 'backup_retention_days', '30', 'string', 'advanced', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 18:27:14'),
(1742, 'morning_checkin_start_period', 'AM', 'string', 'shift_timings', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 19:10:24'),
(1743, 'morning_checkin_end_period', 'AM', 'string', 'shift_timings', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 19:10:24'),
(1744, 'morning_checkout_start_period', 'AM', 'string', 'shift_timings', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 19:10:24'),
(1745, 'morning_checkout_end_period', 'AM', 'string', 'shift_timings', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 19:10:24'),
(1746, 'morning_class_end_period', 'AM', 'string', 'shift_timings', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 19:10:24'),
(1747, 'evening_checkin_start_period', 'AM', 'string', 'shift_timings', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 19:10:24'),
(1748, 'evening_checkin_end_period', 'AM', 'string', 'shift_timings', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 19:10:24'),
(1749, 'evening_checkout_start_period', 'AM', 'string', 'shift_timings', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 19:10:24'),
(1750, 'evening_checkout_end_period', 'AM', 'string', 'shift_timings', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 19:10:24'),
(1751, 'evening_class_end_period', 'AM', 'string', 'shift_timings', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-23 19:10:24'),
(1752, 'max_program_years', '4', 'integer', 'general', 'Maximum number of years in program (3 or 4)', '{\"required\":true,\"min\":1,\"max\":6,\"type\":\"integer\"}', '2025-10-25 19:34:23', 'admin', '2025-10-24 14:53:11'),
(1758, 'academic_structure_mode', 'semester', 'string', 'system', 'Academic structure mode (year-wise or semester-wise)', '{\"required\":true,\"options\":[\"year\",\"semester\"]}', '2025-10-25 19:34:23', 'admin', '2025-10-25 17:53:36'),
(1759, 'semesters_per_year', '2', 'integer', 'system', 'Number of semesters per academic year', '{\"required\":true,\"min\":1,\"max\":4,\"type\":\"integer\"}', '2025-10-25 19:34:23', 'admin', '2025-10-25 17:53:36'),
(1760, 'semester_names', '[\"Semester 1\",\"Semester 2\",\"Semester 3\",\"Semester 4\",\"Semester 5\",\"Semester 6\",\"Semester 7\",\"Semester 8\"]', 'json', 'system', 'JSON array of semester display names', '{\"required\":true,\"type\":\"json\"}', '2025-10-25 19:34:23', 'admin', '2025-10-25 17:53:36'),
(1761, 'semester_start_months', '[9,2]', 'json', 'system', 'JSON array of semester start months (1-12) in order', '{\"required\":true,\"type\":\"json\"}', '2025-10-25 19:34:23', 'admin', '2025-10-25 17:53:36'),
(1762, 'module_attendance', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 20:30:22'),
(1763, 'module_sessions', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 20:30:22'),
(1764, 'module_reports', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 20:30:22'),
(1765, 'module_program_sections', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 20:30:22'),
(1766, 'module_promote_students', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 20:30:22'),
(1767, 'module_scan', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 20:30:22'),
(1768, 'module_students', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 20:30:22'),
(1769, 'module_dashboard', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 20:30:22'),
(1770, 'module_settings', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 20:30:22'),
(1771, 'auto_morning_checkin_end', '', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1772, 'auto_evening_checkin_end', '', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1773, 'staff-user-id', '26', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1774, 'staff-username', 'admin', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1775, 'staff-email', 'aniqueali000@gmail.com', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1776, 'staff-password', 'password', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1777, 'staff-status', '1', 'json', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1778, 'staff-role', 'admin', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1779, 'perm-dashboard', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1780, 'perm-students', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1781, 'perm-attendance', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1782, 'perm-sessions', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1783, 'perm-scan', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1784, 'perm-programs', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1785, 'perm-reports', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1786, 'perm-settings', 'enabled', 'boolean', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-26 22:10:39'),
(1787, 'last_backup_time', '', 'string', 'backup', 'System setting', '[]', '2025-10-26 22:31:29', NULL, '2025-10-26 22:31:29'),
(1790, 'sidebar_logo', 'uploads/logos/logo_1761525465.png', 'string', '', NULL, NULL, '2025-10-27 00:37:45', NULL, '2025-10-26 23:57:52'),
(1791, 'project_name', 'Hasan Amir', 'string', '', NULL, NULL, '2025-10-27 00:45:57', NULL, '2025-10-27 00:30:21'),
(1792, 'project_short_name', 'JIMSET', 'string', '', NULL, NULL, '2025-10-27 00:38:02', NULL, '2025-10-27 00:30:21'),
(1793, 'project_tagline', '', 'string', '', NULL, NULL, '2025-10-27 00:30:21', NULL, '2025-10-27 00:30:21'),
(1794, 'logoUpload', '', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-27 00:30:28'),
(1795, 'projectName', 'Hasan Amir', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-27 00:30:28'),
(1796, 'projectShortName', 'JIMSET', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-27 00:30:28'),
(1797, 'projectTagline', '', 'string', 'general', 'System setting', '[]', '2025-10-29 04:49:46', 'admin', '2025-10-27 00:30:28'),
(1824, 'ai_provider', 'algion', 'string', 'general', 'System setting', '[]', '2025-10-27 01:35:38', 'admin', '2025-10-27 01:35:38'),
(1825, 'ai_model', 'gpt-4o', 'string', 'general', 'System setting', '[]', '2025-10-27 01:35:38', 'admin', '2025-10-27 01:35:38'),
(1826, 'ai_api_key', '123123', 'json', 'general', 'System setting', '[]', '2025-10-27 01:35:38', 'admin', '2025-10-27 01:35:38'),
(1827, 'ai_api_secret', '', 'string', 'general', 'System setting', '[]', '2025-10-27 01:35:38', 'admin', '2025-10-27 01:35:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','student','teacher','staff','superadmin') NOT NULL DEFAULT 'student',
  `student_id` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `profile_picture`, `password_hash`, `role`, `student_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'aniquecodes@gmail.com', 'uploads/profile_pictures/admin_1_1761718513.jpg', '$2y$10$SpdITAYVsAnkxT02I32LS.umORvxZf/Xq6wbXB/bRdtnZ6/jC8/7W', 'superadmin', NULL, 1, '2025-10-30 04:25:24', '2025-10-11 12:02:11', '2025-10-30 04:25:24'),
(26, 'saifullah', 'aniqueali000@gmail.com', 'uploads/profile_pictures/admin_26_1761524892.jpg', '$2y$10$zdSn939nyMiBoWJk042cuupkgiVE5frjl0zWhpS2E3XpR.uGU743W', 'admin', NULL, 1, '2025-10-27 04:21:22', '2025-10-26 21:37:39', '2025-10-27 04:21:22'),
(27, 'test', 'aniqueali29@gmail.com', NULL, '$2y$10$V5/iX2qxCLAfDb4q5YJ8IuXDmRn6ApU2Lu8Q8owCHbrDXy0IJLXoe', 'staff', NULL, 1, '2025-10-27 00:54:19', '2025-10-27 00:52:54', '2025-10-27 00:54:19');

-- --------------------------------------------------------

--
-- Table structure for table `year_progression_log`
--

CREATE TABLE `year_progression_log` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `old_year` int(11) NOT NULL,
  `new_year` int(11) NOT NULL,
  `progression_date` date NOT NULL,
  `progression_type` enum('automatic','manual') DEFAULT 'automatic',
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `program_stats`
--
DROP TABLE IF EXISTS `program_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `program_stats`  AS SELECT `p`.`id` AS `id`, `p`.`code` AS `code`, `p`.`name` AS `name`, `p`.`is_active` AS `is_active`, count(distinct `s`.`student_id`) AS `total_students`, count(distinct `sec`.`id`) AS `total_sections`, sum(`sec`.`capacity`) AS `total_capacity`, avg(`stats`.`attendance_percentage`) AS `avg_attendance` FROM (((`programs` `p` left join `sections` `sec` on(`p`.`id` = `sec`.`program_id` and `sec`.`is_active` = 1)) left join `students` `s` on(`s`.`section_id` = `sec`.`id`)) left join `student_stats` `stats` on(`s`.`student_id` = `stats`.`student_id`)) GROUP BY `p`.`id`, `p`.`code`, `p`.`name`, `p`.`is_active` ;

-- --------------------------------------------------------

--
-- Structure for view `section_stats`
--
DROP TABLE IF EXISTS `section_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `section_stats`  AS SELECT `sec`.`id` AS `id`, `sec`.`section_name` AS `section_name`, `p`.`code` AS `program_code`, `p`.`name` AS `program_name`, `sec`.`year_level` AS `year_level`, `sec`.`shift` AS `shift`, `sec`.`capacity` AS `capacity`, count(`s`.`student_id`) AS `current_students`, round(count(`s`.`student_id`) / `sec`.`capacity` * 100,2) AS `capacity_utilization` FROM ((`sections` `sec` join `programs` `p` on(`sec`.`program_id` = `p`.`id`)) left join `students` `s` on(`s`.`section_id` = `sec`.`id`)) WHERE `sec`.`is_active` = 1 GROUP BY `sec`.`id`, `sec`.`section_name`, `p`.`code`, `p`.`name`, `sec`.`year_level`, `sec`.`shift`, `sec`.`capacity` ;

-- --------------------------------------------------------

--
-- Structure for view `student_stats`
--
DROP TABLE IF EXISTS `student_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `student_stats`  AS SELECT `s`.`student_id` AS `student_id`, `s`.`name` AS `name`, `s`.`email` AS `email`, `s`.`phone` AS `phone`, `s`.`program` AS `program`, `s`.`shift` AS `shift`, `s`.`year_level` AS `year_level`, `s`.`section` AS `section`, `p`.`name` AS `program_name`, `sec`.`capacity` AS `capacity`, count(`a`.`id`) AS `total_attendance`, sum(case when `a`.`status` = 'Present' then 1 else 0 end) AS `present_count`, CASE WHEN count(`a`.`id`) > 0 THEN round(sum(case when `a`.`status` = 'Present' then 1 else 0 end) / count(`a`.`id`) * 100,2) ELSE 0 END AS `attendance_percentage` FROM (((`students` `s` left join `programs` `p` on(`s`.`program` = `p`.`code`)) left join `sections` `sec` on(`s`.`section_id` = `sec`.`id`)) left join `attendance` `a` on(`s`.`student_id` = `a`.`student_id`)) GROUP BY `s`.`student_id`, `s`.`name`, `s`.`email`, `s`.`phone`, `s`.`program`, `s`.`shift`, `s`.`year_level`, `s`.`section`, `p`.`name`, `sec`.`capacity` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_year` (`year`),
  ADD KEY `idx_is_current` (`is_current`),
  ADD KEY `idx_academic_current` (`is_current`,`year`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_attendance_student_timestamp` (`student_id`,`timestamp`),
  ADD KEY `idx_check_in_time` (`check_in_time`),
  ADD KEY `idx_check_out_time` (`check_out_time`),
  ADD KEY `idx_attendance_program_shift` (`program`,`shift`,`timestamp`),
  ADD KEY `idx_attendance_checkin_checkout` (`student_id`,`check_in_time`,`check_out_time`),
  ADD KEY `idx_attendance_years` (`admission_year`,`current_year`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_attendance_timestamp_status` (`timestamp`,`status`),
  ADD KEY `idx_attendance_student_date_status` (`student_id`,`timestamp`,`status`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_user` (`user_type`,`user_id`);

--
-- Indexes for table `auth_sessions`
--
ALTER TABLE `auth_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `check_in_sessions`
--
ALTER TABLE `check_in_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_active_session` (`student_id`,`is_active`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_check_in_time` (`check_in_time`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_checkin_active` (`is_active`,`student_id`,`check_in_time`),
  ADD KEY `idx_checkin_activity` (`last_activity`,`is_active`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_code` (`verification_code`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `enrollment_sessions`
--
ALTER TABLE `enrollment_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_term_year` (`term`,`year`),
  ADD KEY `idx_active_sessions` (`is_active`),
  ADD KEY `idx_code` (`code`);

--
-- Indexes for table `import_logs`
--
ALTER TABLE `import_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_import_type_date` (`import_type`,`created_at`),
  ADD KEY `idx_import_created` (`created_at`);

--
-- Indexes for table `laravel_sessions`
--
ALTER TABLE `laravel_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_last_activity` (`last_activity`),
  ADD KEY `idx_sessions_cleanup` (`last_activity`,`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_programs_active_name` (`is_active`,`name`(50));

--
-- Indexes for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_qr_student_active` (`student_id`,`is_active`,`generated_at`),
  ADD KEY `idx_qr_generated` (`generated_at`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quiz_assignments`
--
ALTER TABLE `quiz_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quiz_id` (`quiz_id`);

--
-- Indexes for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quiz_student` (`quiz_id`,`student_id`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quiz_id` (`quiz_id`);

--
-- Indexes for table `quiz_responses`
--
ALTER TABLE `quiz_responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attempt_question` (`attempt_id`,`question_id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_section` (`program_id`,`year_level`,`section_name`,`shift`),
  ADD KEY `idx_program_year` (`program_id`,`year_level`),
  ADD KEY `idx_shift` (`shift`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_sections_filter` (`program_id`,`year_level`,`shift`,`is_active`),
  ADD KEY `idx_sections_capacity` (`program_id`,`is_active`,`capacity`,`current_students`),
  ADD KEY `idx_sections_year_shift` (`year_level`,`shift`,`is_active`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_term_year` (`term`,`year`),
  ADD KEY `idx_active_sessions` (`is_active`),
  ADD KEY `idx_code` (`code`);

--
-- Indexes for table `shift_timings`
--
ALTER TABLE `shift_timings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_shift` (`shift_name`),
  ADD KEY `idx_shift_name` (`shift_name`);

--
-- Indexes for table `staff_permissions`
--
ALTER TABLE `staff_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_page` (`user_id`,`page_url`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_page_url` (`page_url`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_students_admission_year` (`admission_year`),
  ADD KEY `idx_students_current_year` (`current_year`),
  ADD KEY `idx_students_shift` (`shift`),
  ADD KEY `idx_students_program` (`program`),
  ADD KEY `idx_students_is_graduated` (`is_graduated`),
  ADD KEY `idx_program` (`program`),
  ADD KEY `idx_shift` (`shift`),
  ADD KEY `idx_year_level` (`year_level`),
  ADD KEY `idx_section` (`section`),
  ADD KEY `idx_admission_year` (`admission_year`),
  ADD KEY `idx_section_id` (`section_id`),
  ADD KEY `idx_students_program_shift_year` (`program`,`shift`,`year_level`),
  ADD KEY `idx_last_year_update` (`last_year_update`),
  ADD KEY `idx_roll_prefix` (`roll_prefix`),
  ADD KEY `idx_students_section_active` (`section_id`,`is_active`,`year_level`),
  ADD KEY `idx_students_name` (`name`(50)),
  ADD KEY `idx_students_email` (`email`),
  ADD KEY `idx_students_roll` (`roll_number`,`is_active`),
  ADD KEY `idx_students_current_semester` (`current_semester`),
  ADD KEY `idx_students_enrollment_session` (`enrollment_session_id`),
  ADD KEY `idx_students_semester_session` (`current_semester`,`enrollment_session_id`);

--
-- Indexes for table `sync_logs`
--
ALTER TABLE `sync_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sync_type` (`sync_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_sync_type_status` (`sync_type`,`status`,`created_at`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_settings_category_key` (`category`,`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_users_profile_picture` (`profile_picture`);

--
-- Indexes for table `year_progression_log`
--
ALTER TABLE `year_progression_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_progression_date` (`progression_date`),
  ADD KEY `idx_progression_student_date` (`student_id`,`progression_date`,`old_year`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `check_in_sessions`
--
ALTER TABLE `check_in_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `enrollment_sessions`
--
ALTER TABLE `enrollment_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `import_logs`
--
ALTER TABLE `import_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `qr_codes`
--
ALTER TABLE `qr_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quiz_assignments`
--
ALTER TABLE `quiz_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `quiz_responses`
--
ALTER TABLE `quiz_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `shift_timings`
--
ALTER TABLE `shift_timings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff_permissions`
--
ALTER TABLE `staff_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=167;

--
-- AUTO_INCREMENT for table `sync_logs`
--
ALTER TABLE `sync_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1828;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `year_progression_log`
--
ALTER TABLE `year_progression_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `auth_sessions`
--
ALTER TABLE `auth_sessions`
  ADD CONSTRAINT `fk_auth_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `laravel_sessions`
--
ALTER TABLE `laravel_sessions`
  ADD CONSTRAINT `laravel_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
