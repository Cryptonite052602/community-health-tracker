-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 27, 2026 at 04:01 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `healthpatient`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_linking_history`
--

CREATE TABLE `account_linking_history` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `performed_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `full_name`, `created_at`) VALUES
(1, 'admin', '$2y$10$ZnTO45cy54Fi30sQ.04qPehw./Z7YAxrirQWR.qE3b/RLcpivKaTm', 'System Administrator', '2025-05-01 21:20:54');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_targets`
--

CREATE TABLE `announcement_targets` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','staff','user') NOT NULL,
  `action` varchar(255) NOT NULL,
  `table_affected` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `user_type`, `action`, `table_affected`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 2, 'staff', 'create patient', 'sitio1_patients', 10, NULL, NULL, '::1', NULL, '2025-05-04 01:40:46');

-- --------------------------------------------------------

--
-- Table structure for table `consultation_notes`
--

CREATE TABLE `consultation_notes` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `consultation_date` date NOT NULL,
  `next_consultation_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deleted_patients`
--

CREATE TABLE `deleted_patients` (
  `id` int(11) NOT NULL,
  `original_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `last_checkup` date DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deleted_patients`
--

INSERT INTO `deleted_patients` (`id`, `original_id`, `full_name`, `date_of_birth`, `age`, `gender`, `address`, `contact`, `last_checkup`, `added_by`, `user_id`, `deleted_at`, `deleted_by`) VALUES
(96, 202, 'Russel Evan Loquinario  bbossssss', '1999-05-26', 26, 'Male', 'Labangon Cebu City Philippines', '09816497664', '2026-01-27', 1, 179, '2026-01-27 01:42:14', 1),
(97, 204, 'Jerecho Latosa', '1999-12-12', 26, 'Male', 'Barangay Luz, Cebu City Philippines free facebook', '09816497664', '2026-01-01', 1, 181, '2026-01-27 01:43:03', 1),
(98, 203, 'Archiel R. Cabanag', '1999-12-05', 26, 'Male', 'Labangon Cebu City', '09816497664', '2026-01-15', 1, 180, '2026-01-27 01:43:06', 1),
(99, 201, 'Warren Miguel Miras', '1999-05-12', 23, 'Male', 'Barangay Luz, Cebu City Philippines free facebook', '09816497664', '2026-01-21', 1, 178, '2026-01-27 01:43:08', 1),
(100, 207, 'Warren Miguel Miras', '2002-05-25', 0, 'Male', 'Barangay Luz, Cebu City Philippines', '09816497664', '2026-01-05', 1, 178, '2026-01-27 14:33:06', 1),
(101, 208, 'Archiel R. Cabanag', '2002-05-29', 0, 'Male', 'Barangay Luz, Cebu City Philippine', '09816497664', '2026-01-13', 1, 180, '2026-01-27 14:33:09', 1);

-- --------------------------------------------------------

--
-- Table structure for table `existing_info_patients`
--

CREATE TABLE `existing_info_patients` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `blood_type` varchar(3) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `current_medications` text DEFAULT NULL,
  `family_history` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `temperature` decimal(4,2) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `immunization_record` text DEFAULT NULL,
  `chronic_conditions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_consultation_notes`
--

CREATE TABLE `patient_consultation_notes` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `consultation_date` datetime DEFAULT current_timestamp(),
  `note_type` enum('General','Follow-up','Medication','Diagnosis','Referral','Other') DEFAULT 'General',
  `notes` text NOT NULL,
  `next_checkup_date` date DEFAULT NULL,
  `status` enum('Active','Resolved','Pending','Cancelled') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_visits`
--

CREATE TABLE `patient_visits` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `visit_date` datetime NOT NULL,
  `visit_type` varchar(100) NOT NULL,
  `symptoms` text DEFAULT NULL,
  `vital_signs` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `referral_info` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `next_visit_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sitio1_account_linking_history`
--

CREATE TABLE `sitio1_account_linking_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `patient_record_id` int(11) DEFAULT NULL,
  `linked_by` int(11) NOT NULL,
  `action` varchar(50) NOT NULL COMMENT 'link, unlink, create_and_link',
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sitio1_announcements`
--

CREATE TABLE `sitio1_announcements` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('normal','medium','high') DEFAULT 'normal',
  `expiry_date` date DEFAULT NULL,
  `post_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','archived','expired') DEFAULT 'active',
  `audience_type` enum('public','specific','landing_page') NOT NULL DEFAULT 'public',
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sitio1_announcements`
--

INSERT INTO `sitio1_announcements` (`id`, `staff_id`, `title`, `message`, `priority`, `expiry_date`, `post_date`, `updated_at`, `status`, `audience_type`, `image_path`) VALUES
(55, 1, 'Hello', 'asdasfdasdasdasd', 'normal', '2026-01-24', '2026-01-23 11:36:08', NULL, 'archived', 'specific', '/community-health-tracker/uploads/announcements/69735d28f204f.jpg'),
(56, 1, 'Hello', 'asdasfdasdasdasd', 'normal', '2026-01-24', '2026-01-23 11:42:00', NULL, 'archived', 'specific', '/community-health-tracker/uploads/announcements/69735e88db18e.jpg'),
(57, 1, 'Hello', 'asdasfdasdasdasd', 'normal', '2026-01-24', '2026-01-23 12:33:32', NULL, 'archived', 'specific', '/community-health-tracker/uploads/announcements/69736a9c05915.jpg'),
(58, 1, 'Hello sir Russel Evan Loki', 'Hello sir', 'normal', '2026-01-26', '2026-01-25 09:31:03', NULL, 'active', 'specific', NULL),
(59, 1, 'asdasdasds', 'adasdasd', 'medium', NULL, '2026-01-27 13:24:07', NULL, 'active', 'specific', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sitio1_appointments`
--

CREATE TABLE `sitio1_appointments` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `max_slots` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sitio1_consultations`
--

CREATE TABLE `sitio1_consultations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `response` text DEFAULT NULL,
  `responded_by` int(11) DEFAULT NULL,
  `is_custom` tinyint(1) DEFAULT 0,
  `status` enum('pending','responded') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sitio1_patients`
--

CREATE TABLE `sitio1_patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `phic_no` varchar(50) DEFAULT NULL,
  `bhw_assigned` varchar(100) DEFAULT NULL,
  `family_no` varchar(50) DEFAULT NULL,
  `fourps_member` enum('Yes','No') DEFAULT 'No',
  `full_name` varchar(100) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `sitio` varchar(255) DEFAULT NULL,
  `disease` varchar(255) DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `last_checkup` date DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `restored_at` timestamp NULL DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `consultation_type` varchar(20) DEFAULT 'onsite',
  `civil_status` varchar(20) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `consent_given` tinyint(1) DEFAULT 0,
  `consent_date` datetime DEFAULT NULL,
  `patient_record_uid` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sitio1_staff`
--

CREATE TABLE `sitio1_staff` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `is_active` tinyint(1) DEFAULT 1,
  `work_days` varchar(20) DEFAULT '1111100' COMMENT '7-digit string (1=working, 0=off), Mon-Sun',
  `specialization` varchar(100) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sitio1_staff`
--

INSERT INTO `sitio1_staff` (`id`, `username`, `password`, `full_name`, `position`, `created_by`, `created_at`, `status`, `is_active`, `work_days`, `specialization`, `license_number`) VALUES
(1, 'Lance', '$2y$10$61keyKT9UVY4PQIN1jD2TOLwqv2i5C0cpE82vyi4lBeajqmJg8EbS', 'Lance Christine Gallardo', 'Nurse', 1, '2025-05-01 21:25:31', 'active', 1, '1111100', NULL, '1'),
(2, 'Archiel', '$2y$10$6JkB04nXJ2E14yUo5einmusdXo1hIJdLSLgrim2w51DMh8f7T7en.', 'Archiel  Rosel Cabanag', 'Health Worker', 1, '2025-05-01 23:05:06', 'active', 1, '1111100', NULL, '1');

-- --------------------------------------------------------

--
-- Table structure for table `sitio1_users`
--

CREATE TABLE `sitio1_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `sitio` varchar(100) DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `approved` tinyint(1) DEFAULT 0,
  `approved_by` int(11) DEFAULT NULL,
  `unique_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('pending','approved','declined') DEFAULT 'pending',
  `role` varchar(20) DEFAULT 'patient',
  `specialization` varchar(255) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `verification_method` enum('manual_verification','id_upload') DEFAULT 'manual_verification',
  `id_image_path` varchar(255) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `verification_notes` text DEFAULT NULL,
  `verification_consent` tinyint(1) DEFAULT 0,
  `id_verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `account_linked` tinyint(1) DEFAULT 0,
  `patient_record_id` int(11) DEFAULT NULL,
  `patient_record_uid` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sitio1_users`
--

INSERT INTO `sitio1_users` (`id`, `username`, `password`, `email`, `full_name`, `gender`, `age`, `date_of_birth`, `address`, `sitio`, `contact`, `civil_status`, `occupation`, `approved`, `approved_by`, `unique_number`, `created_at`, `last_login`, `status`, `role`, `specialization`, `license_number`, `updated_at`, `verification_method`, `id_image_path`, `profile_image`, `verification_notes`, `verification_consent`, `id_verified`, `verified_at`, `account_linked`, `patient_record_id`, `patient_record_uid`) VALUES
(178, 'Warren', '$2y$10$KHf5CYe6kk6jW2RZkY10DuEZ.EBZVOPUcW0kT743i8sb/TAc3aQmm', 'warrenmiras@gmail.com', 'Warren Miguel Miras', 'male', 0, '2026-01-19', NULL, 'Panganiban', '09206001470', NULL, NULL, 1, NULL, 'RESPAN202601444', '2026-01-25 08:42:08', NULL, 'approved', 'patient', NULL, NULL, '2026-01-27 13:27:29', 'manual_verification', NULL, NULL, NULL, 0, 1, '2026-01-25 08:42:08', 0, NULL, 'PAT-20260127-WAR-9681'),
(179, 'Russel', '$2y$10$jDlPRD/2QG.XLFTpG16qZOOsFfSfB2hwV40unXoVrIz8b.2dJSSQy', 'russel@gmail.com', 'Russel Evan Loquinario', 'male', 0, '2026-01-07', NULL, 'Panganiban', '09206001470', NULL, NULL, 1, NULL, 'RESPAN202601713', '2026-01-25 09:27:16', NULL, 'approved', 'patient', NULL, NULL, '2026-01-26 03:00:55', 'manual_verification', NULL, NULL, NULL, 0, 1, '2026-01-25 09:27:16', 0, NULL, 'PAT-20260126-RUS-6055'),
(180, 'Archiel', '$2y$10$4NS8QMOW4t74kq1S7XgSlOsCSMBHNu06h6.A78bkh1fVVYOk4YmIO', 'cabanagarchielrosel@gmail.com', 'Archiel R. Cabanag', 'male', 0, '2026-01-13', NULL, 'Carbon', '09816497664', NULL, NULL, 1, NULL, 'RESCAR202601055', '2026-01-26 05:56:51', NULL, 'approved', 'patient', NULL, NULL, '2026-01-27 14:19:19', 'manual_verification', NULL, NULL, NULL, 0, 1, '2026-01-26 05:56:51', 0, NULL, 'PAT-20260127-ARC-5015'),
(181, 'Jerecho', '$2y$10$khi1U3O3HGc10VYhgNpBnOiKNKKtrCU/zlX8GYH.tLH6eoY3WgZLa', 'jerecholatosa@gmail.com', 'Jerecho Latosa', 'male', 0, '2026-01-20', NULL, 'Luz Heights', '09816497664', NULL, NULL, 1, NULL, 'RESLUZ202601021', '2026-01-26 10:37:28', NULL, 'approved', 'patient', NULL, NULL, '2026-01-26 11:41:29', 'manual_verification', NULL, NULL, NULL, 0, 1, '2026-01-26 10:37:28', 0, NULL, 'PAT-20260126-JER-7155'),
(183, 'Jaycar', '$2y$10$r4yMdBPW9zWE01pGpukpWe6.qlH3nVE0o76oL4/gAVB.sU97F/Lqm', 'jaycarotida@gmail.com', 'Jaycar Otida', 'male', 0, '2026-01-21', NULL, 'Panganiban', '09206001470', NULL, NULL, 1, NULL, 'RESPAN202601406', '2026-01-26 22:16:48', NULL, 'approved', 'patient', NULL, NULL, '2026-01-26 22:55:51', 'manual_verification', NULL, NULL, NULL, 0, 1, '2026-01-26 22:16:48', 0, NULL, 'PAT-20260127-JAY-8404');

-- --------------------------------------------------------

--
-- Table structure for table `staff_documents`
--

CREATE TABLE `staff_documents` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL COMMENT 'in bytes',
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_documents`
--

INSERT INTO `staff_documents` (`id`, `title`, `description`, `file_name`, `file_type`, `file_size`, `uploaded_by`, `uploaded_at`, `is_public`, `created_at`, `updated_at`) VALUES
(7, 'Health Records - 2025-2026', 'For Keeps', '1746553255_consultations_report_2025-05-01_to_2025-05-31.csv', '', 0, 1, '2025-05-06 17:40:55', 0, '2025-05-06 17:40:55', '2025-05-06 17:40:55');

-- --------------------------------------------------------

--
-- Table structure for table `user_announcements`
--

CREATE TABLE `user_announcements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `status` enum('accepted','dismissed') NOT NULL,
  `response_date` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_announcements`
--

INSERT INTO `user_announcements` (`id`, `user_id`, `announcement_id`, `status`, `response_date`, `updated_at`) VALUES
(63, 179, 58, 'accepted', '2026-01-25 09:31:16', '2026-01-25 09:31:16');

-- --------------------------------------------------------

--
-- Table structure for table `user_appointments`
--

CREATE TABLE `user_appointments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `status` enum('pending','approved','completed','rejected') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_linking_history`
--
ALTER TABLE `account_linking_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resident` (`resident_id`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `announcement_targets`
--
ALTER TABLE `announcement_targets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `announcement_id` (`announcement_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `consultation_notes`
--
ALTER TABLE `consultation_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `deleted_patients`
--
ALTER TABLE `deleted_patients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `existing_info_patients`
--
ALTER TABLE `existing_info_patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `patient_consultation_notes`
--
ALTER TABLE `patient_consultation_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_consultation_notes` (`patient_id`,`consultation_date`);

--
-- Indexes for table `patient_visits`
--
ALTER TABLE `patient_visits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `sitio1_account_linking_history`
--
ALTER TABLE `sitio1_account_linking_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_linked_by` (`linked_by`),
  ADD KEY `patient_record_id` (`patient_record_id`);

--
-- Indexes for table `sitio1_announcements`
--
ALTER TABLE `sitio1_announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `sitio1_appointments`
--
ALTER TABLE `sitio1_appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `sitio1_consultations`
--
ALTER TABLE `sitio1_consultations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `responded_by` (`responded_by`);

--
-- Indexes for table `sitio1_patients`
--
ALTER TABLE `sitio1_patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `idx_patients_user_id` (`user_id`);

--
-- Indexes for table `sitio1_staff`
--
ALTER TABLE `sitio1_staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `sitio1_users`
--
ALTER TABLE `sitio1_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email_UNIQUE` (`email`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_users_patient_id` (`patient_record_id`);

--
-- Indexes for table `staff_documents`
--
ALTER TABLE `staff_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `user_announcements`
--
ALTER TABLE `user_announcements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_announcement` (`user_id`,`announcement_id`),
  ADD KEY `user_announcements_ibfk_2` (`announcement_id`);

--
-- Indexes for table `user_appointments`
--
ALTER TABLE `user_appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_linking_history`
--
ALTER TABLE `account_linking_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `announcement_targets`
--
ALTER TABLE `announcement_targets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `consultation_notes`
--
ALTER TABLE `consultation_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `deleted_patients`
--
ALTER TABLE `deleted_patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `existing_info_patients`
--
ALTER TABLE `existing_info_patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=171;

--
-- AUTO_INCREMENT for table `patient_consultation_notes`
--
ALTER TABLE `patient_consultation_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_visits`
--
ALTER TABLE `patient_visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sitio1_account_linking_history`
--
ALTER TABLE `sitio1_account_linking_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sitio1_announcements`
--
ALTER TABLE `sitio1_announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `sitio1_appointments`
--
ALTER TABLE `sitio1_appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sitio1_consultations`
--
ALTER TABLE `sitio1_consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sitio1_patients`
--
ALTER TABLE `sitio1_patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=209;

--
-- AUTO_INCREMENT for table `sitio1_staff`
--
ALTER TABLE `sitio1_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sitio1_users`
--
ALTER TABLE `sitio1_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=184;

--
-- AUTO_INCREMENT for table `staff_documents`
--
ALTER TABLE `staff_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_announcements`
--
ALTER TABLE `user_announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `user_appointments`
--
ALTER TABLE `user_appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcement_targets`
--
ALTER TABLE `announcement_targets`
  ADD CONSTRAINT `announcement_targets_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `sitio1_announcements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_targets_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `sitio1_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `consultation_notes`
--
ALTER TABLE `consultation_notes`
  ADD CONSTRAINT `consultation_notes_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `sitio1_patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_consultation_notes_created_by_staff` FOREIGN KEY (`created_by`) REFERENCES `sitio1_staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `existing_info_patients`
--
ALTER TABLE `existing_info_patients`
  ADD CONSTRAINT `existing_info_patients_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `sitio1_patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_visits`
--
ALTER TABLE `patient_visits`
  ADD CONSTRAINT `patient_visits_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `sitio1_patients` (`id`),
  ADD CONSTRAINT `patient_visits_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `sitio1_staff` (`id`);

--
-- Constraints for table `sitio1_account_linking_history`
--
ALTER TABLE `sitio1_account_linking_history`
  ADD CONSTRAINT `sitio1_account_linking_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `sitio1_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sitio1_account_linking_history_ibfk_2` FOREIGN KEY (`patient_record_id`) REFERENCES `sitio1_patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sitio1_account_linking_history_ibfk_3` FOREIGN KEY (`linked_by`) REFERENCES `sitio1_staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sitio1_announcements`
--
ALTER TABLE `sitio1_announcements`
  ADD CONSTRAINT `sitio1_announcements_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `sitio1_staff` (`id`);

--
-- Constraints for table `sitio1_appointments`
--
ALTER TABLE `sitio1_appointments`
  ADD CONSTRAINT `sitio1_appointments_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `sitio1_staff` (`id`);

--
-- Constraints for table `sitio1_consultations`
--
ALTER TABLE `sitio1_consultations`
  ADD CONSTRAINT `sitio1_consultations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `sitio1_users` (`id`),
  ADD CONSTRAINT `sitio1_consultations_ibfk_2` FOREIGN KEY (`responded_by`) REFERENCES `sitio1_staff` (`id`);

--
-- Constraints for table `sitio1_patients`
--
ALTER TABLE `sitio1_patients`
  ADD CONSTRAINT `fk_patient_user` FOREIGN KEY (`user_id`) REFERENCES `sitio1_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sitio1_patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `sitio1_users` (`id`),
  ADD CONSTRAINT `sitio1_patients_ibfk_2` FOREIGN KEY (`added_by`) REFERENCES `sitio1_staff` (`id`);

--
-- Constraints for table `sitio1_staff`
--
ALTER TABLE `sitio1_staff`
  ADD CONSTRAINT `sitio1_staff_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admin` (`id`);

--
-- Constraints for table `sitio1_users`
--
ALTER TABLE `sitio1_users`
  ADD CONSTRAINT `sitio1_users_ibfk_1` FOREIGN KEY (`approved_by`) REFERENCES `sitio1_staff` (`id`);

--
-- Constraints for table `staff_documents`
--
ALTER TABLE `staff_documents`
  ADD CONSTRAINT `staff_documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `admin` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_announcements`
--
ALTER TABLE `user_announcements`
  ADD CONSTRAINT `user_announcements_ibfk_2` FOREIGN KEY (`announcement_id`) REFERENCES `sitio1_announcements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_appointments`
--
ALTER TABLE `user_appointments`
  ADD CONSTRAINT `user_appointments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `sitio1_users` (`id`),
  ADD CONSTRAINT `user_appointments_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `sitio1_appointments` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
