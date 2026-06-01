-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 01, 2026 at 09:49 AM
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

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `parent_id`, `department_name`, `division_name`, `code`, `email`, `created_at`) VALUES
(1, NULL, 'Administrators Office', 'Office of the Administrator', 'AO', 'ao@nfa.gov.ph', '2026-02-12 00:49:02'),
(2, 1, 'Administrators Office', 'Public Affairs Division', 'AO-PAD', 'publicaffairs@nfa.gov.ph', '2026-02-12 00:49:02'),
(3, NULL, 'Office of the Deputy Administrator', 'Office of the Deputy Administrator', 'ODA', 'oda@nfa.gov.ph', '2026-02-12 00:49:02'),
(4, NULL, 'Office of the Assistant Administrator for Finance and Administration', 'Office of the Assistant Administrator for Finance and Administration', 'OOAFA', 'oaafa@nfa.gov.ph', '2026-02-12 00:49:02'),
(5, NULL, 'Office of the Assistant Administrator for Operations', 'Office of the Assistant Administrator for Operations', 'OAAO', 'oaao@nfa.gov.ph', '2026-02-12 00:49:02'),
(6, NULL, 'Office of the Council Secretariat', 'Office of the Council Secretariat', 'OCS', 'councilsecretariat@nfa.gov.ph', '2026-02-12 00:49:02'),
(7, NULL, 'Internal Audit Department', 'Internal Audit Department', 'IAD', 'internalaudit@nfa.gov.ph', '2026-02-12 00:49:02'),
(8, 7, 'Internal Audit Department', 'Management Audit Division', 'IAD-MAD', 'management.audit@nfa.gov.ph', '2026-02-12 00:49:02'),
(9, 7, 'Internal Audit Department', 'Operations Audit Division', 'IAD-OAD', 'operation.audit@nfa.gov.ph', '2026-02-12 00:49:02'),
(10, NULL, 'Corporate Planning and Management Services Department', 'Corporate Planning and Management Services Department', 'CPMSD', 'cpmsd@nfa.gov.ph', '2026-02-12 00:49:02'),
(11, 10, 'Corporate Planning and Management Services Department', 'Corporate Planning Division', 'CPMSD-CPD', 'cpd.cpmsd@nfa.gov.ph', '2026-02-12 00:49:02'),
(12, 10, 'Corporate Planning and Management Services Department', 'Information and Communications Technology Division', 'CPMSD-ICTSD', 'ictsd@nfa.gov.ph', '2026-02-12 00:49:02'),
(13, NULL, 'Legal Department', 'Legal Department', 'LD', 'legalaffairs@nfa.gov.ph', '2026-02-12 00:49:02'),
(14, 13, 'Legal Department', 'Investigation and Documentation Division', 'LD-IDD', 'idd.legal@nfa.gov.ph', '2026-02-12 00:49:02'),
(15, 13, 'Legal Department', 'Litigation and Prosecution Division', 'LD-LPD', 'litigation.legal@nfa.gov.ph', '2026-02-12 00:49:02'),
(16, NULL, 'Operations Coordination Department', 'Operations Coordination Department', 'OCD', 'ocd@nfa.gov.ph', '2026-02-12 00:49:02'),
(17, 16, 'Operations Coordination Department', 'Operations Planning and Monitoring Division', 'OCD-OPMD', 'opmd.ocd@nfa.gov.ph', '2026-02-12 00:49:02'),
(18, 16, 'Operations Coordination Department', 'Technical Services Division', 'OCD-TSD', 'ts.ocd@nfa.gov.ph', '2026-02-12 00:49:02'),
(19, NULL, 'Finance Department', 'Finance Department', 'FD', 'finance@nfa.gov.ph', '2026-02-12 00:49:02'),
(20, 19, 'Finance Department', 'Accounting Division', 'FD-AD', 'accounting@nfa.gov.ph', '2026-02-12 00:49:02'),
(21, 19, 'Finance Department', 'Budget Division', 'FD-BD', 'budget@nfa.gov.ph', '2026-02-12 00:49:02'),
(22, NULL, 'Administrative and General Services Department', 'Administrative and General Services Department', 'AGSD', 'agsd@nfa.gov.ph', '2026-02-12 00:49:02'),
(23, 22, 'Administrative and General Services Division', 'Human Resource Development and Services Division', 'AGSD-HRDSD', 'humanresource@nfa.gov.ph', '2026-02-12 00:49:02'),
(24, 22, 'Administrative and General Services Division', 'General Services Division', 'AGSD-GSD', 'generalservices@nfa.gov.ph', '2026-02-12 00:49:02');

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

--
-- Dumping data for table `department_action_slips`
--

INSERT INTO `department_action_slips` (`id`, `slip_number`, `external_source`, `date_received`, `subject`, `reference_number`, `receiving_level`, `urgent`, `attachment`, `required_action`, `deadline`, `receiving_department_id`, `receiving_division_id`, `current_department_id`, `current_division_id`, `assigned_staff_id`, `remarks`, `created_by`, `status`, `created_at`, `updated_at`, `completed_at`, `completed_by`, `closed_at`, `closed_by`) VALUES
(1, 'DAS-CPMSD-2026-05-0001', 'Department of Agriculture', '2026-05-13', 'DA-INTERACT Meeting', NULL, 'Division', 0, 'action_slips/20260513021223_d35c63500d0a.pdf', 'Register yourself', '2026-05-14', 10, 12, 10, 12, NULL, 'None', 8, 'Received', '2026-05-13 08:12:23', '2026-05-13 08:47:36', NULL, NULL, NULL, NULL),
(2, 'DAS-CPMSD-2026-05-0002', 'Internal Action Slip', '2026-05-13', 'For appropriate action', NULL, 'Division', 1, 'action_slips/20260513030952_4223568830a2.pdf', 'For appropriate action', '2026-05-13', 10, 12, 10, 12, 10, 'Gary', 5, 'Completed', '2026-05-13 09:09:52', '2026-05-13 09:12:50', '2026-05-13 03:12:50', 10, NULL, NULL),
(3, 'DAS-CPMSD-2026-05-0003', 'Internal Action Slip', '2026-05-13', 'For appropriate action', NULL, 'Division', 1, 'action_slips/20260513032207_ee48cbc0a1b2.pdf', 'For appropriate action', '2026-05-13', 10, 12, 10, 12, NULL, 'Please see me before applying for action', 5, 'Completed', '2026-05-13 09:22:07', '2026-05-14 08:41:11', '2026-05-14 02:32:30', 10, NULL, NULL),
(4, 'DAS-CPMSD-2026-05-0004', 'Internal Action Slip', '2026-05-13', 'For appropriate action', NULL, 'Division', 1, 'action_slips/20260513032523_dc4bb6bbdce8.pdf', 'For appropriate action', '2026-05-13', 10, 11, 10, 11, NULL, NULL, 5, 'Completed', '2026-05-13 09:25:23', '2026-05-14 08:40:25', '2026-05-14 02:40:01', 2, NULL, NULL),
(5, 'DAS-FD-2026-05-0001', 'Internal Action Slip', '2026-05-15', 'For meeting attendance', NULL, 'Department', 0, 'action_slips/20260515012516_9b010f28d8cf.pdf', 'For meeting attendance', '2026-05-15', 10, NULL, 10, NULL, NULL, 'Emergency meeting, requesting supporting documents', 11, 'Completed', '2026-05-15 07:25:16', '2026-05-15 08:21:57', '2026-05-15 02:21:57', 8, NULL, NULL),
(6, 'DAS-CPMSD-ICTSD-2026-05-0001', 'Internal Action Slip', '2026-05-22', 'For coordination', NULL, 'Staff', 0, 'action_slips/20260522045939_65107b7c1630.pdf', 'For coordination', '2026-05-22', 10, 12, 10, 12, NULL, 'Please coordinate to DBM', 8, 'Completed', '2026-05-22 10:59:39', '2026-05-22 11:01:56', '2026-05-22 05:00:48', 10, NULL, NULL);

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

--
-- Dumping data for table `department_action_slip_events`
--

INSERT INTO `department_action_slip_events` (`id`, `slip_id`, `action`, `actor_user_id`, `actor_department_id`, `from_department_id`, `to_department_id`, `from_user_id`, `to_user_id`, `old_status`, `new_status`, `remarks`, `attachment`, `created_at`) VALUES
(1, 1, 'Created', 8, 12, NULL, 12, NULL, NULL, NULL, 'Created', 'None', NULL, '2026-05-13 08:12:23'),
(2, 1, 'Received by Division', 8, 12, NULL, NULL, NULL, NULL, 'Created', 'Received by Division', NULL, NULL, '2026-05-13 08:13:15'),
(3, 2, 'Created', 5, 10, NULL, 10, NULL, NULL, NULL, 'Draft', 'Action slip created.', NULL, '2026-05-13 09:09:52'),
(4, 2, 'Released to Division', 5, 10, 10, 12, NULL, NULL, 'Draft', 'Released', 'Gary', NULL, '2026-05-13 09:09:52'),
(5, 2, 'Received by Division', 8, 12, NULL, NULL, NULL, NULL, 'Released', 'Received', NULL, NULL, '2026-05-13 09:10:39'),
(6, 2, 'Further Delegated to Staff', 8, 12, 12, 12, NULL, 10, 'Received', 'Delegated', 'Please check and comment', NULL, '2026-05-13 09:10:59'),
(7, 2, 'Received by Staff', 10, 12, NULL, NULL, NULL, NULL, 'Delegated', 'For Action', NULL, NULL, '2026-05-13 09:12:47'),
(8, 2, 'Completed by Staff', 10, 12, NULL, NULL, NULL, NULL, 'For Action', 'Completed', NULL, NULL, '2026-05-13 09:12:50'),
(9, 3, 'Created', 5, 10, NULL, 10, NULL, NULL, NULL, 'Draft', 'Action slip created.', NULL, '2026-05-13 09:22:07'),
(10, 3, 'Released to Division', 5, 10, 10, 12, NULL, NULL, 'Draft', 'Released', 'Please see me before applying for action', NULL, '2026-05-13 09:22:07'),
(11, 3, 'Received by Division', 8, 12, NULL, NULL, NULL, NULL, 'Released', 'Received', NULL, NULL, '2026-05-13 09:23:15'),
(12, 3, 'Further Delegated to Staff', 8, 12, 12, 12, NULL, 10, 'Received', 'Delegated', NULL, NULL, '2026-05-13 09:23:29'),
(13, 4, 'Created', 5, 10, NULL, 10, NULL, NULL, NULL, 'Draft', 'Action slip created.', NULL, '2026-05-13 09:25:23'),
(14, 4, 'Released to Division', 5, 10, 10, 11, NULL, NULL, 'Draft', 'Released', NULL, NULL, '2026-05-13 09:25:23'),
(15, 4, 'Received by Division', 6, 11, NULL, NULL, NULL, NULL, 'Released', 'Received', NULL, NULL, '2026-05-13 09:26:29'),
(16, 4, 'Further Delegated to Staff', 6, 11, 11, 11, NULL, 2, 'Received', 'Delegated', NULL, NULL, '2026-05-13 09:29:15'),
(17, 3, 'Received by Staff', 10, 12, NULL, NULL, NULL, NULL, 'Delegated', 'For Action', NULL, NULL, '2026-05-14 08:32:10'),
(18, 3, 'Completed by Staff', 10, 12, NULL, NULL, NULL, NULL, 'For Action', 'Completed', NULL, NULL, '2026-05-14 08:32:30'),
(19, 4, 'Received by Staff', 2, 11, NULL, NULL, NULL, NULL, 'Delegated', 'For Action', NULL, NULL, '2026-05-14 08:39:31'),
(20, 4, 'Completed by Staff', 2, 11, NULL, NULL, NULL, NULL, 'For Action', 'Completed', 'This is already acted', NULL, '2026-05-14 08:40:01'),
(21, 4, 'Completed by Division', 6, 11, NULL, NULL, NULL, NULL, 'Completed', 'Completed', NULL, NULL, '2026-05-14 08:40:25'),
(22, 3, 'Completed by Division', 8, 12, NULL, NULL, NULL, NULL, 'Completed', 'Completed', NULL, NULL, '2026-05-14 08:41:11'),
(23, 5, 'Created', 11, 19, NULL, 10, NULL, NULL, NULL, 'Draft', 'Action slip created.', NULL, '2026-05-15 07:25:16'),
(24, 5, 'Released to Department', 11, 19, 19, 10, NULL, NULL, 'Draft', 'Released', 'Emergency meeting, requesting supporting documents', NULL, '2026-05-15 07:25:16'),
(25, 5, 'Received by Department', 5, 10, NULL, NULL, NULL, NULL, 'Released', 'Received', NULL, NULL, '2026-05-15 07:26:13'),
(26, 5, 'Delegated to Division', 5, 10, 10, 12, NULL, NULL, 'Received', 'Delegated', 'Join the meeting', NULL, '2026-05-15 07:27:33'),
(27, 5, 'Received by Division', 8, 12, NULL, NULL, NULL, NULL, 'Delegated', 'Received', NULL, NULL, '2026-05-15 08:01:52'),
(28, 5, 'Completed by Division', 8, 12, NULL, NULL, NULL, NULL, 'Received', 'Completed', 'I will join the meeting sir', NULL, '2026-05-15 08:21:57'),
(29, 6, 'Created', 8, 12, NULL, 10, NULL, 10, NULL, 'Draft', 'Action slip created.', NULL, '2026-05-22 10:59:39'),
(30, 6, 'Released to Staff', 8, 12, 12, 12, NULL, 10, 'Draft', 'Released', 'Please coordinate to DBM', NULL, '2026-05-22 10:59:39'),
(31, 6, 'Received by Staff', 10, 12, NULL, NULL, NULL, NULL, 'Released', 'For Action', NULL, NULL, '2026-05-22 11:00:09'),
(32, 6, 'Completed by Staff', 10, 12, NULL, NULL, NULL, NULL, 'For Action', 'Completed', 'Already coordinated to DBM', 'action_slips/completions/20260522050048_c95f897d5e5e.pdf', '2026-05-22 11:00:48'),
(33, 6, 'Completed by Division', 8, 12, NULL, NULL, NULL, NULL, 'Completed', 'Completed', NULL, NULL, '2026-05-22 11:01:56');

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

--
-- Dumping data for table `department_action_slip_sequences`
--

INSERT INTO `department_action_slip_sequences` (`id`, `department_id`, `year`, `month`, `last_number`) VALUES
(1, 10, 2026, 5, 4),
(2, 19, 2026, 5, 1),
(3, 12, 2026, 5, 1);

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
  `status` enum('Draft','Released','Received','Returned','Re-released') DEFAULT 'Draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `prefix`, `sequence_number`, `title`, `particulars`, `attachment`, `qr_token`, `type`, `origin_department_id`, `destination_department_id`, `reference_document_id`, `created_by`, `created_at`, `released_by`, `released_at`, `received_by`, `received_at`, `status`) VALUES
(1, 'CPMSD-2026-05-001', 1, 'Test 1', 'Instructions', '7d356394c5f3c0780598ab28729a2c14.pdf', NULL, 'Memorandum', 10, 11, NULL, 4, '2026-05-05 09:41:24', 4, '2026-05-05 09:42:01', 2, '2026-05-05 12:44:27', 'Received'),
(2, 'CPMSD-ICTSD-2026-05-001', 1, 'Test 2', 'Test2', '5e278446ecfc3d9d996225ce9f62b1dc.pdf', NULL, 'Memorandum', 12, 10, 1, 1, '2026-05-05 09:49:01', 1, '2026-05-05 09:49:17', 4, '2026-05-05 09:49:43', 'Received'),
(3, 'FD-2026-05-001', 1, 'Test Staff Delegation', 'for delegation', 'e94f333e332f187b1101497e8b330298.pdf', NULL, 'Memorandum', 19, 11, NULL, 7, '2026-05-05 12:47:46', 7, '2026-05-05 12:47:50', NULL, NULL, 'Released'),
(4, 'FD-2026-05-002', 2, 'Test 4', 'Test for Delegation', 'fc109f07d0daccf48f36f358d7205384.pdf', NULL, 'Memorandum', 19, 11, NULL, 7, '2026-05-06 07:22:37', 7, '2026-05-06 07:22:48', NULL, NULL, 'Released'),
(5, 'FD-2026-05-003', 3, 'Test 05', 'Memo', '0f1bbd7d295c09c51a2c1e75153c36ed.pdf', NULL, 'Memorandum', 19, 11, NULL, 7, '2026-05-12 07:26:13', 7, '2026-05-12 07:26:24', NULL, NULL, 'Released');

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
  `status` enum('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_assignments`
--

INSERT INTO `document_assignments` (`id`, `document_id`, `assigned_by_user_id`, `assigned_by_department_id`, `assigned_to_user_id`, `assigned_to_department_id`, `assignment_type`, `instructions`, `status`, `assigned_at`, `completed_at`, `completed_by`) VALUES
(1, 3, 6, 11, 2, 11, 'INTERNAL', 'Please facilitate request', 'Completed', '2026-05-05 12:51:15', '2026-05-05 12:52:12', 2),
(2, 4, 6, 11, 9, 11, 'INTERNAL', 'Draft Notice of Meeting for Signature of Dr Mar', 'Completed', '2026-05-06 07:26:42', '2026-05-06 07:30:04', 9),
(3, 5, 6, 11, 2, 11, 'INTERNAL', 'Comment on the forwarded action slip', 'Completed', '2026-05-12 07:53:46', '2026-05-12 10:55:09', 2),
(4, 4, 8, 12, 10, 12, 'INTERNAL', 'Prepare conference room', 'Completed', '2026-05-22 10:55:04', '2026-05-22 10:56:04', 10);

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

--
-- Dumping data for table `document_logs`
--

INSERT INTO `document_logs` (`id`, `document_id`, `action`, `action_by`, `department_id`, `remarks`, `timestamp`) VALUES
(1, 1, 'Created', 4, 10, 'Document created with routing', '2026-05-05 09:41:24'),
(2, 1, 'Released', 4, 10, 'Document released', '2026-05-05 09:42:01'),
(3, 1, 'Received', 1, 12, 'Document received', '2026-05-05 09:43:45'),
(4, 2, 'Created', 1, 12, 'Document created with routing', '2026-05-05 09:49:01'),
(5, 2, 'Released', 1, 12, 'Document released', '2026-05-05 09:49:17'),
(6, 2, 'Received', 4, 10, 'Document received', '2026-05-05 09:49:43'),
(7, 1, 'Manager Received', 5, 10, 'Document received by manager', '2026-05-05 09:53:12'),
(8, 2, 'Manager Received', 5, 10, 'Document received by manager', '2026-05-05 09:54:21'),
(9, 1, 'Received', 2, 11, 'Document received', '2026-05-05 12:44:27'),
(10, 1, 'Manager Received', 6, 11, 'Document received by manager', '2026-05-05 12:44:51'),
(11, 3, 'Created', 7, 19, 'Document created with routing', '2026-05-05 12:47:46'),
(12, 3, 'Released', 7, 19, 'Document released', '2026-05-05 12:47:50'),
(13, 3, 'Received', 3, 10, 'Document received', '2026-05-05 12:48:11'),
(14, 3, 'Manager Received', 5, 10, 'Document received by manager', '2026-05-05 12:48:37'),
(15, 3, 'Forwarded', 5, 10, 'Document forwarded to departments: CPMSD-CPD\nUrgent: Yes\nAction: For coordination\nDeadline: 2026-05-06\nInstruction: Please coordinate your concert to finance', '2026-05-05 12:49:07'),
(16, 3, 'Received', 2, 11, 'Document received', '2026-05-05 12:50:44'),
(17, 3, 'Manager Received', 6, 11, 'Document received by manager', '2026-05-05 12:50:59'),
(18, 3, 'Internally Delegated', 6, 11, 'Assigned to Job Pilapil\nInstruction: Please facilitate request', '2026-05-05 12:51:15'),
(19, 3, 'Internal Assignment Completed', 2, 11, 'The response for the documents has now been sent to your table for signature.', '2026-05-05 12:52:12'),
(20, 4, 'Created', 7, 19, 'Document created with routing', '2026-05-06 07:22:37'),
(21, 4, 'Released', 7, 19, 'Document released', '2026-05-06 07:22:48'),
(22, 4, 'Received', 4, 10, 'Document received', '2026-05-06 07:23:17'),
(23, 4, 'Manager Received', 5, 10, 'Document received by manager', '2026-05-06 07:24:49'),
(24, 4, 'Forwarded', 5, 10, 'Document forwarded to departments: CPMSD-CPD, CPMSD-ICTSD\nUrgent: Yes\nAction: For meeting attendance\nDeadline: 2026-05-07\nInstruction: Lets meet and discuss this matter', '2026-05-06 07:25:12'),
(25, 4, 'Received', 9, 11, 'Document received', '2026-05-06 07:25:48'),
(26, 4, 'Manager Received', 6, 11, 'Document received by manager', '2026-05-06 07:26:14'),
(27, 4, 'Internally Delegated', 6, 11, 'Assigned to Grace Verceles\nInstruction: Draft Notice of Meeting for Signature of Dr Mar', '2026-05-06 07:26:42'),
(28, 4, 'Internal Assignment Completed', 9, 11, 'Send to your email the draft notice of meeting for your review and endorsement', '2026-05-06 07:30:04'),
(29, 4, 'Received', 1, 12, 'Document received', '2026-05-11 10:48:28'),
(30, 4, 'Manager Received', 8, 12, 'Document received by manager', '2026-05-11 10:50:17'),
(31, 5, 'Created', 7, 19, 'Document created with routing', '2026-05-12 07:26:13'),
(32, 5, 'Released', 7, 19, 'Document released', '2026-05-12 07:26:24'),
(33, 5, 'Received', 4, 10, 'Document received', '2026-05-12 07:29:09'),
(34, 5, 'Manager Received', 5, 10, 'Document received by manager', '2026-05-12 07:32:23'),
(35, 5, 'Forwarded', 5, 10, 'Document forwarded to departments: CPMSD-CPD\nUrgent: Yes\nAction: For review/comments\nDeadline: 2026-05-12\nInstruction: Pls comment on this', '2026-05-12 07:33:03'),
(36, 5, 'Received', 2, 11, 'Document received', '2026-05-12 07:34:20'),
(37, 5, 'Manager Received', 6, 11, 'Document received by manager', '2026-05-12 07:51:08'),
(38, 5, 'Internally Delegated', 6, 11, 'Assigned to Job Pilapil\nInstruction: Comment on the forwarded action slip', '2026-05-12 07:53:46'),
(39, 5, 'Internal Assignment Completed', 2, 11, 'Internal assignment completed', '2026-05-12 10:55:09'),
(40, 4, 'Internally Delegated', 8, 12, 'Assigned to Jewell Mayrena\nInstruction: Prepare conference room', '2026-05-22 10:55:04'),
(41, 4, 'Internal Assignment Completed', 10, 12, 'prepared conference room', '2026-05-22 10:56:04');

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

--
-- Dumping data for table `document_routes`
--

INSERT INTO `document_routes` (`id`, `document_id`, `from_department_id`, `to_department_id`, `routing_type`, `instructions`, `status`, `routed_at`, `received_at`) VALUES
(1, 1, 10, 11, 'DELEGATE', NULL, 'Received', '2026-05-05 09:41:24', '2026-05-05 12:44:27'),
(2, 1, 10, 12, 'DELEGATE', NULL, 'Received', '2026-05-05 09:41:24', '2026-05-05 09:43:45'),
(3, 2, 12, 10, 'TO', NULL, 'Received', '2026-05-05 09:49:01', '2026-05-05 09:49:43'),
(4, 3, 19, 10, 'TO', NULL, 'Received', '2026-05-05 12:47:46', '2026-05-05 12:48:11'),
(5, 3, 10, 11, 'DELEGATE', 'Urgent: Yes\nAction: For coordination\nDeadline: 2026-05-06\nInstruction: Please coordinate your concert to finance', 'Received', '2026-05-05 12:49:07', '2026-05-05 12:50:44'),
(6, 4, 19, 10, 'TO', NULL, 'Received', '2026-05-06 07:22:37', '2026-05-06 07:23:17'),
(7, 4, 10, 11, 'DELEGATE', 'Urgent: Yes\nAction: For meeting attendance\nDeadline: 2026-05-07\nInstruction: Lets meet and discuss this matter', 'Received', '2026-05-06 07:25:12', '2026-05-06 07:25:48'),
(8, 4, 10, 12, 'DELEGATE', 'Urgent: Yes\nAction: For meeting attendance\nDeadline: 2026-05-07\nInstruction: Lets meet and discuss this matter', 'Received', '2026-05-06 07:25:12', '2026-05-11 10:48:28'),
(9, 5, 19, 10, 'TO', NULL, 'Received', '2026-05-12 07:26:13', '2026-05-12 07:29:09'),
(10, 5, 10, 11, 'DELEGATE', 'Urgent: Yes\nAction: For review/comments\nDeadline: 2026-05-12\nInstruction: Pls comment on this', 'Received', '2026-05-12 07:33:03', '2026-05-12 07:34:20');

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

--
-- Dumping data for table `document_sequences`
--

INSERT INTO `document_sequences` (`id`, `department_id`, `year`, `month`, `last_number`) VALUES
(1, 10, 2026, 5, 1),
(2, 12, 2026, 5, 1),
(3, 19, 2026, 5, 3);

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

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `link`, `is_read`, `created_at`, `read_at`) VALUES
(1, 1, 'New registration', 'Job Pilapil submitted a registration request.', '/users', 0, '2026-05-04 14:46:33', NULL),
(2, 1, 'New registration', 'Melody Francia submitted a registration request.', '/users', 1, '2026-05-04 14:47:09', '2026-05-04 14:47:28'),
(3, 3, 'Account activated', 'Your account has been activated.', '/dashboard', 0, '2026-05-04 14:47:30', NULL),
(4, 2, 'Account activated', 'Your account has been activated.', '/dashboard', 0, '2026-05-04 14:47:32', NULL),
(5, 1, 'New registration', 'Pearl Cabatic submitted a registration request.', '/users', 1, '2026-05-05 09:20:05', '2026-05-05 09:20:18'),
(6, 4, 'Account activated', 'Your account has been activated.', '/dashboard', 1, '2026-05-05 09:20:26', '2026-05-05 16:06:50'),
(7, 2, 'Document released', 'CPMSD-2026-05-001 has been released.', '/documents/show/1', 1, '2026-05-05 09:42:01', '2026-05-05 12:42:06'),
(8, 4, 'Document received', 'CPMSD-2026-05-001 has been received.', '/documents/show/1', 1, '2026-05-05 09:43:45', '2026-05-05 16:06:50'),
(9, 3, 'Document released', 'CPMSD-ICTSD-2026-05-001 has been released.', '/documents/show/2', 0, '2026-05-05 09:49:17', NULL),
(10, 4, 'Document released', 'CPMSD-ICTSD-2026-05-001 has been released.', '/documents/show/2', 1, '2026-05-05 09:49:17', '2026-05-05 09:49:37'),
(11, 1, 'Document received', 'CPMSD-ICTSD-2026-05-001 has been received.', '/documents/show/2', 0, '2026-05-05 09:49:43', NULL),
(12, 1, 'New registration', 'Mario Andrada submitted a registration request.', '/users', 1, '2026-05-05 09:50:47', '2026-05-05 09:51:00'),
(13, 5, 'Account activated', 'Your account has been activated.', '/dashboard', 1, '2026-05-05 09:51:06', '2026-05-05 09:51:20'),
(14, 5, 'Role updated', 'Your account role has been updated to Manager.', '/dashboard', 1, '2026-05-05 09:52:44', '2026-05-05 09:52:56'),
(15, 4, 'Manager received document', 'CPMSD-2026-05-001 was received by the manager.', '/documents/show/1', 1, '2026-05-05 09:53:12', '2026-05-05 16:06:50'),
(16, 1, 'Manager received document', 'CPMSD-ICTSD-2026-05-001 was received by the manager.', '/documents/show/2', 0, '2026-05-05 09:54:21', NULL),
(17, 1, 'New registration', 'Maria Luisa Quiroz submitted a registration request.', '/users', 1, '2026-05-05 12:39:59', '2026-05-05 12:40:13'),
(18, 6, 'Role updated', 'Your account role has been updated to Manager.', '/dashboard', 1, '2026-05-05 12:40:17', '2026-05-05 12:49:34'),
(19, 6, 'Account activated', 'Your account has been activated.', '/dashboard', 1, '2026-05-05 12:41:04', '2026-05-05 12:49:34'),
(20, 6, 'Manager action required', 'CPMSD-2026-05-001 was received by staff and is ready for manager action.', '/documents/show/1', 1, '2026-05-05 12:44:27', '2026-05-05 12:44:40'),
(21, 4, 'Document received', 'CPMSD-2026-05-001 has been received.', '/documents/show/1', 1, '2026-05-05 12:44:27', '2026-05-05 16:06:50'),
(22, 4, 'Manager received document', 'CPMSD-2026-05-001 was received by the manager.', '/documents/show/1', 1, '2026-05-05 12:44:51', '2026-05-05 16:06:50'),
(23, 1, 'New registration', 'Kirby Yasol submitted a registration request.', '/users', 0, '2026-05-05 12:46:32', NULL),
(24, 7, 'Account activated', 'Your account has been activated.', '/dashboard', 0, '2026-05-05 12:46:49', NULL),
(25, 3, 'Document released', 'FD-2026-05-001 has been released.', '/documents/show/3', 1, '2026-05-05 12:47:50', '2026-05-05 12:48:06'),
(26, 4, 'Document released', 'FD-2026-05-001 has been released.', '/documents/show/3', 1, '2026-05-05 12:47:50', '2026-05-05 16:06:50'),
(27, 5, 'Manager action required', 'FD-2026-05-001 was received by staff and is ready for manager action.', '/documents/show/3', 1, '2026-05-05 12:48:11', '2026-05-05 12:48:35'),
(28, 7, 'Document received', 'FD-2026-05-001 has been received.', '/documents/show/3', 0, '2026-05-05 12:48:11', NULL),
(29, 7, 'Manager received document', 'FD-2026-05-001 was received by the manager.', '/documents/show/3', 0, '2026-05-05 12:48:37', NULL),
(30, 2, 'Document forwarded', 'FD-2026-05-001 has been forwarded to your department.', '/documents/show/3', 1, '2026-05-05 12:49:07', '2026-05-05 12:50:32'),
(31, 6, 'Manager action required', 'FD-2026-05-001 was received by staff and is ready for manager action.', '/documents/show/3', 1, '2026-05-05 12:50:44', '2026-05-05 12:50:54'),
(32, 7, 'Document received', 'FD-2026-05-001 has been received.', '/documents/show/3', 0, '2026-05-05 12:50:44', NULL),
(33, 7, 'Manager received document', 'FD-2026-05-001 was received by the manager.', '/documents/show/3', 0, '2026-05-05 12:50:59', NULL),
(34, 2, 'Document assigned to you', 'FD-2026-05-001 was internally delegated to you.', '/documents/show/3', 1, '2026-05-05 12:51:15', '2026-05-05 12:51:35'),
(35, 6, 'Internal assignment completed', 'FD-2026-05-001 internal assignment was completed.', '/documents/show/3', 1, '2026-05-05 12:52:12', '2026-05-05 14:41:06'),
(36, 1, 'New registration', 'Gary Riparip submitted a registration request.', '/users', 0, '2026-05-06 07:10:40', NULL),
(37, 1, 'New registration', 'Grace Verceles submitted a registration request.', '/users', 1, '2026-05-06 07:11:15', '2026-05-06 07:12:53'),
(38, 8, 'Role updated', 'Your account role has been updated to Manager.', '/dashboard', 0, '2026-05-06 07:12:59', NULL),
(39, 9, 'Account activated', 'Your account has been activated.', '/dashboard', 0, '2026-05-06 07:13:04', NULL),
(40, 8, 'Account activated', 'Your account has been activated.', '/dashboard', 0, '2026-05-06 07:13:05', NULL),
(41, 3, 'Document released', 'FD-2026-05-002 has been released.', '/documents/show/4', 0, '2026-05-06 07:22:48', NULL),
(42, 4, 'Document released', 'FD-2026-05-002 has been released.', '/documents/show/4', 1, '2026-05-06 07:22:48', '2026-05-06 07:23:14'),
(43, 5, 'Manager action required', 'FD-2026-05-002 was received by staff and is ready for manager action.', '/documents/show/4', 1, '2026-05-06 07:23:17', '2026-05-06 07:24:44'),
(44, 7, 'Document received', 'FD-2026-05-002 has been received.', '/documents/show/4', 0, '2026-05-06 07:23:17', NULL),
(45, 7, 'Manager received document', 'FD-2026-05-002 was received by the manager.', '/documents/show/4', 0, '2026-05-06 07:24:49', NULL),
(46, 2, 'Document forwarded', 'FD-2026-05-002 has been forwarded to your department.', '/documents/show/4', 0, '2026-05-06 07:25:12', NULL),
(47, 9, 'Document forwarded', 'FD-2026-05-002 has been forwarded to your department.', '/documents/show/4', 1, '2026-05-06 07:25:12', '2026-05-06 07:25:40'),
(48, 6, 'Manager action required', 'FD-2026-05-002 was received by staff and is ready for manager action.', '/documents/show/4', 1, '2026-05-06 07:25:48', '2026-05-06 07:26:08'),
(49, 7, 'Document received', 'FD-2026-05-002 has been received.', '/documents/show/4', 0, '2026-05-06 07:25:48', NULL),
(50, 7, 'Manager received document', 'FD-2026-05-002 was received by the manager.', '/documents/show/4', 0, '2026-05-06 07:26:14', NULL),
(51, 9, 'Document assigned to you', 'FD-2026-05-002 was internally delegated to you.', '/documents/show/4', 1, '2026-05-06 07:26:42', '2026-05-06 07:27:00'),
(52, 6, 'Internal assignment completed', 'FD-2026-05-002 internal assignment was completed.', '/documents/show/4', 0, '2026-05-06 07:30:04', NULL),
(53, 8, 'Manager action required', 'FD-2026-05-002 was received by staff and is ready for manager action.', '/documents/show/4', 1, '2026-05-11 10:48:28', '2026-05-11 10:50:10'),
(54, 7, 'Document received', 'FD-2026-05-002 has been received.', '/documents/show/4', 0, '2026-05-11 10:48:28', NULL),
(55, 7, 'Manager received document', 'FD-2026-05-002 was received by the manager.', '/documents/show/4', 1, '2026-05-11 10:50:17', '2026-05-12 07:25:08'),
(56, 3, 'Document released', 'FD-2026-05-003 has been released.', '/documents/show/5', 0, '2026-05-12 07:26:24', NULL),
(57, 4, 'Document released', 'FD-2026-05-003 has been released.', '/documents/show/5', 1, '2026-05-12 07:26:24', '2026-05-12 07:26:47'),
(58, 5, 'Manager action required', 'FD-2026-05-003 was received by staff and is ready for manager action.', '/documents/show/5', 1, '2026-05-12 07:29:09', '2026-05-12 07:32:19'),
(59, 7, 'Document received', 'FD-2026-05-003 has been received.', '/documents/show/5', 0, '2026-05-12 07:29:09', NULL),
(60, 7, 'Manager received document', 'FD-2026-05-003 was received by the manager.', '/documents/show/5', 0, '2026-05-12 07:32:23', NULL),
(61, 2, 'Document forwarded', 'FD-2026-05-003 has been forwarded to your department.', '/documents/show/5', 1, '2026-05-12 07:33:03', '2026-05-12 07:34:00'),
(62, 9, 'Document forwarded', 'FD-2026-05-003 has been forwarded to your department.', '/documents/show/5', 0, '2026-05-12 07:33:03', NULL),
(63, 6, 'Manager action required', 'FD-2026-05-003 was received by staff and is ready for manager action.', '/documents/show/5', 1, '2026-05-12 07:34:20', '2026-05-12 07:46:37'),
(64, 7, 'Document received', 'FD-2026-05-003 has been received.', '/documents/show/5', 0, '2026-05-12 07:34:20', NULL),
(65, 7, 'Manager received document', 'FD-2026-05-003 was received by the manager.', '/documents/show/5', 0, '2026-05-12 07:51:08', NULL),
(66, 2, 'Document assigned to you', 'FD-2026-05-003 was internally delegated to you.', '/documents/show/5', 1, '2026-05-12 07:53:46', '2026-05-12 07:54:17'),
(67, 6, 'Internal assignment completed', 'FD-2026-05-003 internal assignment was completed.', '/documents/show/5', 0, '2026-05-12 10:55:09', NULL),
(68, 1, 'New registration', 'Jewell Mayrena submitted a registration request.', '/users', 1, '2026-05-13 08:15:42', '2026-05-13 08:15:55'),
(69, 10, 'Account activated', 'Your account has been activated.', '/dashboard', 0, '2026-05-13 08:15:59', NULL),
(70, 8, 'Action slip released', 'A new action slip was released to your division.', '/actionSlips/show/2', 1, '2026-05-13 09:09:52', '2026-05-13 09:10:35'),
(71, 10, 'Action slip assigned', 'DAS-CPMSD-2026-05-0002 was assigned to you.', '/actionSlips/show/2', 1, '2026-05-13 09:10:59', '2026-05-13 09:12:44'),
(72, 8, 'Action slip completed by staff', 'DAS-CPMSD-2026-05-0002 was marked completed by staff.', '/actionSlips/show/2', 0, '2026-05-13 09:12:50', NULL),
(73, 8, 'Action slip released', 'A new action slip was released to your division.', '/actionSlips/show/3', 1, '2026-05-13 09:22:07', '2026-05-13 09:23:08'),
(74, 10, 'Action slip assigned', 'DAS-CPMSD-2026-05-0003 was assigned to you.', '/actionSlips/show/3', 1, '2026-05-13 09:23:29', '2026-05-14 08:32:06'),
(75, 6, 'Action slip released', 'A new action slip was released to your division.', '/actionSlips/show/4', 1, '2026-05-13 09:25:23', '2026-05-13 09:26:14'),
(76, 2, 'Action slip assigned', 'DAS-CPMSD-2026-05-0004 was assigned to you.', '/actionSlips/show/4', 1, '2026-05-13 09:29:15', '2026-05-14 08:39:11'),
(77, 8, 'Action slip completed by staff', 'DAS-CPMSD-2026-05-0003 was marked completed by staff.', '/actionSlips/show/3', 1, '2026-05-14 08:32:30', '2026-05-14 08:33:37'),
(78, 6, 'Action slip completed by staff', 'DAS-CPMSD-2026-05-0004 was marked completed by staff.', '/actionSlips/show/4', 1, '2026-05-14 08:40:01', '2026-05-14 08:40:20'),
(79, 5, 'Action slip confirmed', 'DAS-CPMSD-2026-05-0004 was confirmed by the division manager.', '/actionSlips/show/4', 1, '2026-05-14 08:40:25', '2026-05-14 08:40:54'),
(80, 5, 'Action slip confirmed', 'DAS-CPMSD-2026-05-0003 was confirmed by the division manager.', '/actionSlips/show/3', 0, '2026-05-14 08:41:11', NULL),
(81, 1, 'New registration', 'Jing Tayactac submitted a registration request.', '/users', 1, '2026-05-15 07:22:59', '2026-05-15 07:23:13'),
(82, 11, 'Account activated', 'Your account has been activated.', '/dashboard', 0, '2026-05-15 07:23:15', NULL),
(83, 11, 'Role updated', 'Your account role has been updated to Manager.', '/dashboard', 0, '2026-05-15 07:23:28', NULL),
(84, 5, 'Action slip released', 'A new action slip was released to your department.', '/actionSlips/show/5', 1, '2026-05-15 07:25:16', '2026-05-15 07:25:58'),
(85, 8, 'Action slip delegated', 'DAS-FD-2026-05-0001 was delegated to your division.', '/actionSlips/show/5', 1, '2026-05-15 07:27:33', '2026-05-15 08:01:37'),
(86, 5, 'Action slip completed by division', 'DAS-FD-2026-05-0001 was marked completed by the division manager.', '/actionSlips/show/5', 0, '2026-05-15 08:21:57', NULL),
(87, 10, 'Document assigned to you', 'FD-2026-05-002 was internally delegated to you.', '/documents/show/4', 1, '2026-05-22 10:55:04', '2026-05-22 10:55:47'),
(88, 8, 'Internal assignment completed', 'FD-2026-05-002 internal assignment was completed.', '/documents/show/4', 1, '2026-05-22 10:56:04', '2026-05-22 10:56:34'),
(89, 10, 'Action slip released', 'A new action slip was released to you.', '/actionSlips/show/6', 1, '2026-05-22 10:59:39', '2026-05-22 11:00:04'),
(90, 8, 'Action slip completed by staff', 'DAS-CPMSD-ICTSD-2026-05-0001 was marked completed by staff.', '/actionSlips/show/6', 1, '2026-05-22 11:00:48', '2026-05-22 11:01:35'),
(91, 5, 'Action slip confirmed', 'DAS-CPMSD-ICTSD-2026-05-0001 was confirmed by the division manager.', '/actionSlips/show/6', 0, '2026-05-22 11:01:56', NULL);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `id_number`, `firstname`, `lastname`, `middle_initial`, `department_id`, `role`, `email`, `password`, `status`, `created_at`) VALUES
(1, '939908', 'Rainier John', 'Dela Cruz', 'J', 12, 'admin', 'rainier.delacruz@nfa.gov.ph', '$2y$10$bls1Uxyqdv6KGFOOZl9Vl.xWxZ0AWdyUUcDEs93EYLklSBfs3QzsG', 'active', '2026-05-04 01:42:44'),
(2, '939910', 'Job', 'Pilapil', NULL, 11, 'staff', 'j.pilapil@nfa.gov.ph', '$2y$10$4nVLT8hhobYgbt3n0bfupOsHDXwko.5Nuua3.4Q2mLazNO9Z/y6IW', 'active', '2026-05-04 06:46:33'),
(3, '939911', 'Melody', 'Francia', NULL, 10, 'staff', 'm.francia@test.com', '$2y$10$nYrdTx9/D2.hBkYh6lo.PudcYikiyuT5RvWTKV5JOFWPvZk4ypY2e', 'active', '2026-05-04 06:47:09'),
(4, '939912', 'Pearl', 'Cabatic', NULL, 10, 'staff', 'pearl@test.com', '$2y$10$Dffvku.ELpUHv/aJYHaAM.pccTZLHLatrOalhEy4mgNgAo4iddo2.', 'active', '2026-05-05 01:20:05'),
(5, '939913', 'Mario', 'Andrada', NULL, 10, 'manager', 'mario@gmail.com', '$2y$10$Uf0vLG/ziXSbS3ZGsv8Ze.yDY4PdSO4LC1bOyZpe1UG6mF9uJJjie', 'active', '2026-05-05 01:50:47'),
(6, '939914', 'Maria Luisa', 'Quiroz', NULL, 11, 'manager', 'chinky@test.com', '$2y$10$USEBlgGn.5AlgQ22kdWXaOIYbpoG7EAgGdN0L/lpkjGN8thNY.Ty2', 'active', '2026-05-05 04:39:59'),
(7, '939915', 'Kirby', 'Yasol', NULL, 19, 'staff', 'kyasol@test.com', '$2y$10$bls1Uxyqdv6KGFOOZl9Vl.xWxZ0AWdyUUcDEs93EYLklSBfs3QzsG', 'active', '2026-05-05 04:46:32'),
(8, '939916', 'Gary', 'Riparip', NULL, 12, 'manager', 'gary@test.com', '$2y$10$vOkEIclzl4FEGPnjqxVATeJJOwzlQ1sORER4CZBRIngjKyiVvS0tC', 'active', '2026-05-05 23:10:40'),
(9, '939917', 'Grace', 'Verceles', NULL, 11, 'staff', 'grace@test.com', '$2y$10$tyAIVZlkTfNnVBSk9365uOvO4ZZfhka0Aj.ke426ezGNijZMfLYb6', 'active', '2026-05-05 23:11:15'),
(10, '939918', 'Jewell', 'Mayrena', NULL, 12, 'staff', 'jewell@test.com', '$2y$10$7lVNXqIpDupCqeCUFUh35O01te5hqiwIX6HDkUhHQ0iOiHZi5x9Cy', 'active', '2026-05-13 00:15:42'),
(11, '939919', 'Jing', 'Tayactac', NULL, 19, 'manager', 'jing@test.com', '$2y$10$8X91rV0hznDVlCjXqQTJuOWx42k1jFBRRK7t9vk3SktQz/FoU6lDO', 'active', '2026-05-14 23:22:59');

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
  ADD KEY `idx_das_deadline` (`deadline`);

--
-- Indexes for table `department_action_slip_events`
--
ALTER TABLE `department_action_slip_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_das_events_slip` (`slip_id`),
  ADD KEY `idx_das_events_actor` (`actor_user_id`),
  ADD KEY `idx_das_events_to_department` (`to_department_id`),
  ADD KEY `idx_das_events_to_user` (`to_user_id`);

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
  ADD KEY `idx_document_assignments_status` (`status`);

--
-- Indexes for table `document_attachment_history`
--
ALTER TABLE `document_attachment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document_attachment_history_document` (`document_id`),
  ADD KEY `idx_document_attachment_history_return` (`return_id`),
  ADD KEY `idx_document_attachment_history_active` (`document_id`,`is_active`);

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
  ADD KEY `idx_document_returns_route` (`route_id`);

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
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `department_action_slips`
--
ALTER TABLE `department_action_slips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `department_action_slip_events`
--
ALTER TABLE `department_action_slip_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `department_action_slip_sequences`
--
ALTER TABLE `department_action_slip_sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `document_assignments`
--
ALTER TABLE `document_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `document_attachment_history`
--
ALTER TABLE `document_attachment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_logs`
--
ALTER TABLE `document_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `document_returns`
--
ALTER TABLE `document_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_routes`
--
ALTER TABLE `document_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `document_sequences`
--
ALTER TABLE `document_sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`origin_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`destination_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `documents_ibfk_4` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `documents_ibfk_5` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `document_logs`
--
ALTER TABLE `document_logs`
  ADD CONSTRAINT `document_logs_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_logs_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `document_logs_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

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
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
