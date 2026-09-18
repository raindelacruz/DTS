-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 05, 2026 at 09:17 AM
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
-- Database: `dts_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `custodians`
--

CREATE TABLE `custodians` (
  `id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `department_name` varchar(150) NOT NULL,
  `division_name` varchar(150) NOT NULL,
  `code` varchar(50) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `department_action_slips`
--

CREATE TABLE `department_action_slips` (
  `id` int(11) NOT NULL,
  `slip_number` varchar(100) NOT NULL,
  `external_source` varchar(255) NOT NULL,
  `date_received` date NOT NULL,
  `subject` varchar(255) NOT NULL,
  `reference_number` varchar(120) DEFAULT NULL,
  `receiving_level` varchar(20) NOT NULL DEFAULT 'Department',
  `urgent` tinyint(1) NOT NULL DEFAULT 0,
  `attachment` varchar(255) DEFAULT NULL,
  `required_action` text NOT NULL,
  `deadline` date DEFAULT NULL,
  `receiving_department_id` int(11) NOT NULL,
  `receiving_division_id` int(11) DEFAULT NULL,
  `current_department_id` int(11) DEFAULT NULL,
  `current_division_id` int(11) DEFAULT NULL,
  `assigned_staff_id` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `status` varchar(80) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `department_action_slip_events`
--

CREATE TABLE `department_action_slip_events` (
  `id` int(11) NOT NULL,
  `slip_id` int(11) NOT NULL,
  `action` varchar(120) NOT NULL,
  `actor_user_id` int(11) NOT NULL,
  `actor_department_id` int(11) NOT NULL,
  `from_department_id` int(11) DEFAULT NULL,
  `to_department_id` int(11) DEFAULT NULL,
  `from_user_id` int(11) DEFAULT NULL,
  `to_user_id` int(11) DEFAULT NULL,
  `old_status` varchar(80) DEFAULT NULL,
  `new_status` varchar(80) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `department_action_slip_sequences`
--

CREATE TABLE `department_action_slip_sequences` (
  `id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `prefix` varchar(100) NOT NULL,
  `sequence_number` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `particulars` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `qr_token` varchar(64) DEFAULT NULL,
  `type` enum('Memorandum','Special Order','Internal Memorandum','Financial Documents') NOT NULL,
  `origin_department_id` int(11) NOT NULL,
  `destination_department_id` int(11) NOT NULL,
  `reference_document_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `released_by` int(11) DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `status` enum('Draft','Released','Received','Returned','Re-released','Cancelled') DEFAULT 'Draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_assignments`
--

CREATE TABLE `document_assignments` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `assigned_by_user_id` int(11) NOT NULL,
  `assigned_by_department_id` int(11) NOT NULL,
  `assigned_to_user_id` int(11) NOT NULL,
  `assigned_to_department_id` int(11) NOT NULL,
  `assignment_type` enum('INTERNAL') NOT NULL DEFAULT 'INTERNAL',
  `instructions` text DEFAULT NULL,
  `status` enum('Pending','Received','Completed','Confirmed','Returned','Cancelled') NOT NULL DEFAULT 'Pending',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `completion_attachment` varchar(255) DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `return_remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_attachment_history`
--

CREATE TABLE `document_attachment_history` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `return_id` int(11) DEFAULT NULL,
  `old_filename` varchar(255) DEFAULT NULL,
  `new_filename` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `replacement_reason` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_logs`
--

CREATE TABLE `document_logs` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `action_by` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_returns`
--

CREATE TABLE `document_returns` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `route_id` int(11) DEFAULT NULL,
  `returned_by` int(11) NOT NULL,
  `returned_department_id` int(11) NOT NULL,
  `releasing_department_id` int(11) NOT NULL,
  `return_reason` varchar(150) NOT NULL,
  `attachment_issue` varchar(80) DEFAULT NULL,
  `remarks` text NOT NULL,
  `status` enum('Open','Resolved') NOT NULL DEFAULT 'Open',
  `returned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_routes`
--

CREATE TABLE `document_routes` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `from_department_id` int(11) NOT NULL,
  `to_department_id` int(11) NOT NULL,
  `routing_type` enum('TO','THRU','CC','DELEGATE') NOT NULL,
  `instructions` text DEFAULT NULL,
  `status` enum('Pending','Received','Returned') DEFAULT 'Pending',
  `routed_at` datetime DEFAULT current_timestamp(),
  `received_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_sequences`
--

CREATE TABLE `document_sequences` (
  `id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `last_number` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `identifier_hash` char(64) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `first_failed_at` datetime NOT NULL,
  `last_failed_at` datetime NOT NULL,
  `locked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mfa_trusted_devices`
--

CREATE TABLE `mfa_trusted_devices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `user_agent_hash` char(64) NOT NULL,
  `session_version` int(11) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schema_migrations`
--

CREATE TABLE `schema_migrations` (
  `migration` varchar(190) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_audit_log`
--

CREATE TABLE `security_audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `middle_initial` varchar(5) DEFAULT NULL,
  `department_id` int(11) NOT NULL,
  `role` enum('admin','custodian','staff','manager') DEFAULT 'staff',
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `password_changed_at` datetime DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `session_version` int(11) NOT NULL DEFAULT 0,
  `mfa_secret` text DEFAULT NULL,
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `custodians`
--
ALTER TABLE `custodians`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_id` (`department_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `fk_parent_department` (`parent_id`);

--
-- Indexes for table `department_action_slips`
--
ALTER TABLE `department_action_slips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_department_action_slips_number` (`slip_number`),
  ADD KEY `idx_das_receiving_department` (`receiving_department_id`),
  ADD KEY `idx_das_current_division` (`current_division_id`),
  ADD KEY `idx_das_assigned_staff` (`assigned_staff_id`),
  ADD KEY `idx_das_status` (`status`),
  ADD KEY `idx_das_date_received` (`date_received`),
  ADD KEY `idx_das_deadline` (`deadline`),
  ADD KEY `fk_das_receiving_division` (`receiving_division_id`),
  ADD KEY `fk_das_current_department` (`current_department_id`),
  ADD KEY `fk_das_created_by` (`created_by`),
  ADD KEY `fk_das_completed_by` (`completed_by`),
  ADD KEY `fk_das_closed_by` (`closed_by`);

--
-- Indexes for table `department_action_slip_events`
--
ALTER TABLE `department_action_slip_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_das_events_slip` (`slip_id`),
  ADD KEY `idx_das_events_actor` (`actor_user_id`),
  ADD KEY `idx_das_events_to_department` (`to_department_id`),
  ADD KEY `idx_das_events_to_user` (`to_user_id`),
  ADD KEY `fk_das_event_actor_department` (`actor_department_id`),
  ADD KEY `fk_das_event_from_department` (`from_department_id`),
  ADD KEY `fk_das_event_from_user` (`from_user_id`);

--
-- Indexes for table `department_action_slip_sequences`
--
ALTER TABLE `department_action_slip_sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_das_sequence` (`department_id`,`year`,`month`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `prefix` (`prefix`),
  ADD UNIQUE KEY `uq_documents_qr_token` (`qr_token`),
  ADD KEY `origin_department_id` (`origin_department_id`),
  ADD KEY `destination_department_id` (`destination_department_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `released_by` (`released_by`),
  ADD KEY `received_by` (`received_by`),
  ADD KEY `idx_documents_status` (`status`),
  ADD KEY `idx_documents_created_at` (`created_at`);

--
-- Indexes for table `document_assignments`
--
ALTER TABLE `document_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document_assignments_document` (`document_id`),
  ADD KEY `idx_document_assignments_assignee` (`assigned_to_user_id`),
  ADD KEY `idx_document_assignments_department` (`assigned_to_department_id`),
  ADD KEY `idx_document_assignments_status` (`status`),
  ADD KEY `fk_assignment_by_user` (`assigned_by_user_id`),
  ADD KEY `fk_assignment_by_department` (`assigned_by_department_id`),
  ADD KEY `fk_assignment_completed_by` (`completed_by`);

--
-- Indexes for table `document_attachment_history`
--
ALTER TABLE `document_attachment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document_attachment_history_document` (`document_id`),
  ADD KEY `idx_document_attachment_history_return` (`return_id`),
  ADD KEY `idx_document_attachment_history_active` (`document_id`,`is_active`),
  ADD KEY `fk_attachment_history_user` (`uploaded_by`);

--
-- Indexes for table `document_logs`
--
ALTER TABLE `document_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `action_by` (`action_by`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `idx_logs_document` (`document_id`);

--
-- Indexes for table `document_returns`
--
ALTER TABLE `document_returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document_returns_document` (`document_id`),
  ADD KEY `idx_document_returns_status` (`status`),
  ADD KEY `idx_document_returns_route` (`route_id`),
  ADD KEY `fk_returned_by` (`returned_by`),
  ADD KEY `fk_returned_department` (`returned_department_id`),
  ADD KEY `fk_releasing_department` (`releasing_department_id`),
  ADD KEY `fk_return_resolved_by` (`resolved_by`);

--
-- Indexes for table `document_routes`
--
ALTER TABLE `document_routes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from_department_id` (`from_department_id`),
  ADD KEY `to_department_id` (`to_department_id`),
  ADD KEY `idx_routes_document` (`document_id`);

--
-- Indexes for table `document_sequences`
--
ALTER TABLE `document_sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sequence` (`department_id`,`year`,`month`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`identifier_hash`,`ip_address`),
  ADD KEY `idx_login_attempts_locked_until` (`locked_until`);

--
-- Indexes for table `mfa_trusted_devices`
--
ALTER TABLE `mfa_trusted_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mfa_trusted_token_hash` (`token_hash`),
  ADD KEY `idx_mfa_trusted_user` (`user_id`),
  ADD KEY `idx_mfa_trusted_expiry` (`expires_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_password_reset_token_hash` (`token_hash`),
  ADD KEY `idx_password_reset_user` (`user_id`),
  ADD KEY `idx_password_reset_expiry` (`expires_at`);

--
-- Indexes for table `schema_migrations`
--
ALTER TABLE `schema_migrations`
  ADD PRIMARY KEY (`migration`);

--
-- Indexes for table `security_audit_log`
--
ALTER TABLE `security_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_security_audit_event` (`event_type`),
  ADD KEY `idx_security_audit_actor` (`actor_user_id`),
  ADD KEY `idx_security_audit_target` (`target_user_id`),
  ADD KEY `idx_security_audit_created` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `custodians`
--
ALTER TABLE `custodians`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `department_action_slips`
--
ALTER TABLE `department_action_slips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `department_action_slip_events`
--
ALTER TABLE `department_action_slip_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `department_action_slip_sequences`
--
ALTER TABLE `department_action_slip_sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_assignments`
--
ALTER TABLE `document_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_attachment_history`
--
ALTER TABLE `document_attachment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_logs`
--
ALTER TABLE `document_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_returns`
--
ALTER TABLE `document_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_routes`
--
ALTER TABLE `document_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_sequences`
--
ALTER TABLE `document_sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mfa_trusted_devices`
--
ALTER TABLE `mfa_trusted_devices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `security_audit_log`
--
ALTER TABLE `security_audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `custodians`
--
ALTER TABLE `custodians`
  ADD CONSTRAINT `custodians_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `custodians_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_parent_department` FOREIGN KEY (`parent_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `department_action_slips`
--
ALTER TABLE `department_action_slips`
  ADD CONSTRAINT `fk_das_assigned_staff` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_das_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_das_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_das_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_das_current_department` FOREIGN KEY (`current_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_das_current_division` FOREIGN KEY (`current_division_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_das_receiving_department` FOREIGN KEY (`receiving_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_das_receiving_division` FOREIGN KEY (`receiving_division_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `department_action_slip_events`
--
ALTER TABLE `department_action_slip_events`
  ADD CONSTRAINT `fk_das_event_actor_department` FOREIGN KEY (`actor_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_das_event_actor_user` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_das_event_from_department` FOREIGN KEY (`from_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_das_event_from_user` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_das_event_slip` FOREIGN KEY (`slip_id`) REFERENCES `department_action_slips` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_das_event_to_department` FOREIGN KEY (`to_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_das_event_to_user` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `department_action_slip_sequences`
--
ALTER TABLE `department_action_slip_sequences`
  ADD CONSTRAINT `fk_das_sequence_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`origin_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`destination_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `documents_ibfk_4` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `documents_ibfk_5` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `document_assignments`
--
ALTER TABLE `document_assignments`
  ADD CONSTRAINT `fk_assignment_by_department` FOREIGN KEY (`assigned_by_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_assignment_by_user` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_assignment_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_assignment_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignment_to_department` FOREIGN KEY (`assigned_to_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_assignment_to_user` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `document_attachment_history`
--
ALTER TABLE `document_attachment_history`
  ADD CONSTRAINT `fk_attachment_history_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attachment_history_return` FOREIGN KEY (`return_id`) REFERENCES `document_returns` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_attachment_history_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `document_logs`
--
ALTER TABLE `document_logs`
  ADD CONSTRAINT `document_logs_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_logs_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `document_logs_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `document_returns`
--
ALTER TABLE `document_returns`
  ADD CONSTRAINT `fk_releasing_department` FOREIGN KEY (`releasing_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_return_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_return_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_return_route` FOREIGN KEY (`route_id`) REFERENCES `document_routes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_returned_by` FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_returned_department` FOREIGN KEY (`returned_department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `document_routes`
--
ALTER TABLE `document_routes`
  ADD CONSTRAINT `document_routes_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_routes_ibfk_2` FOREIGN KEY (`from_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `document_routes_ibfk_3` FOREIGN KEY (`to_department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `document_sequences`
--
ALTER TABLE `document_sequences`
  ADD CONSTRAINT `document_sequences_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mfa_trusted_devices`
--
ALTER TABLE `mfa_trusted_devices`
  ADD CONSTRAINT `fk_mfa_trusted_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
