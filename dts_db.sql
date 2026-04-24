-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 24, 2026 at 06:13 AM
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
  `status` enum('Draft','Released','Received') DEFAULT 'Draft'
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
-- Table structure for table `document_routes`
--

CREATE TABLE `document_routes` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `from_department_id` int(11) NOT NULL,
  `to_department_id` int(11) NOT NULL,
  `routing_type` enum('TO','THRU','CC','DELEGATE') NOT NULL,
  `instructions` text DEFAULT NULL,
  `status` enum('Pending','Received') DEFAULT 'Pending',
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
(1, '2026001', 'System', 'Administrator', 'A', 1, 'admin', 'admin@nfa.gov.ph', '$2y$10$kcJwrfEP.FI77CIr0lp8H.3McUILtRXfma70P14WJ5aKvXu1YlmPO', 'active', '2026-02-12 05:10:30'),
(2, '939908', 'Rainier John', 'Dela Cruz', 'J', 12, 'staff', 'rainier@test.com.ph', '$2y$10$Sj/gH5Un9q6K47BuhfuCKO6DbuYiFLf8xdKUqJqzxsCve2JqvW50e', 'active', '2026-02-19 03:01:56'),
(3, '939909', 'Kristine', 'Elenzano', 'DC', 19, 'staff', 'kelenzano@test.com', '$2y$10$Sj/gH5Un9q6K47BuhfuCKO6DbuYiFLf8xdKUqJqzxsCve2JqvW50e', 'active', '2026-02-19 03:01:56'),
(4, '939911', 'Ahn Pearl', 'Cabatic', 'C', 10, 'staff', 'apc@test.com', '$2y$10$MdtYTmHb.bCt4fdbBXAGpuhcpBWDH1kgBZmiyfen.T9TnO6Zs1FG2', 'active', '2026-02-20 05:28:50'),
(7, '939913', 'Charlene', 'Gonzales', 'M', 22, 'staff', 'charlene@test.com', '$2y$10$6jvjWhoZaxUkv7FX3VqRfeF/kQLPqKP4A5ne.5Ynh/fvyKdxdytBi', 'active', '2026-02-20 10:42:52'),
(8, '000001', 'Larry', 'Lacson', 'R', 1, 'manager', 'admin@test.com', '$2y$10$FeCzYweZCJUGlFCth1iH4OfXUzYkyZj1aCQreoJMQkyC7i/kx7rcy', 'active', '2026-02-21 12:28:59'),
(9, '000005', 'Roy', 'Untiveros', 'Q', 16, 'manager', 'roi@test.com', '$2y$10$FeCzYweZCJUGlFCth1iH4OfXUzYkyZj1aCQreoJMQkyC7i/kx7rcy', 'active', '2026-02-21 12:32:33'),
(10, '000002', 'Robert', 'Hermano', 'J', 3, 'manager', 'jrh@test.com', '$2y$10$mUcZw1My6G1HMepp77qF3OIf/lvuI6kQhyqAqSNgwwxbUrzu83iLq', 'active', '2026-03-09 02:50:29'),
(11, '939915', 'Jeff', 'Felipe', 'M', 21, 'staff', 'jeff@test.com', '$2y$10$FME3ocRBjGU0BB1BcLQDAOo2b2trHc34k1OwMxZpP8kl76dj8sBKe', 'active', '2026-03-09 05:28:17'),
(12, '939916', 'Kirby', 'Yasol', 'N', 20, 'staff', 'kann@test.com', '$2y$10$FME3ocRBjGU0BB1BcLQDAOo2b2trHc34k1OwMxZpP8kl76dj8sBKe', 'active', '2026-03-09 05:30:27'),
(13, '939917', 'Chrisopher', 'Alingod', NULL, 17, 'staff', 'CA@test.com', '$2y$10$hidTeEegfStUK2SrNm1RsuehY6D6iY8TmzjgqkkEjqBVTm4xz4WHS', 'active', '2026-03-10 04:50:52'),
(14, '000006', 'Mario', 'Andrada', 'G', 10, 'manager', 'mga@test.com', '$2y$10$TNOReKxI7he1kCUrL/flPO8DJQP3DhE0A71J7VCe.N4aH5zdeaxrO', 'active', '2026-03-10 06:34:26'),
(15, '939918', 'Gary', 'Riparip', 'L', 12, 'staff', 'glr@test.com', '$2y$10$TNOReKxI7he1kCUrL/flPO8DJQP3DhE0A71J7VCe.N4aH5zdeaxrO', 'active', '2026-03-10 06:35:17'),
(16, '939919', 'Maria Luisa', 'Quiroz', 'DL', 11, 'staff', 'chinky@test.com', '$2y$10$TNOReKxI7he1kCUrL/flPO8DJQP3DhE0A71J7VCe.N4aH5zdeaxrO', 'active', '2026-03-10 06:37:29'),
(17, '939920', 'Jeff', 'Biason', NULL, 16, 'staff', 'jb@test.com', '$2y$10$TNOReKxI7he1kCUrL/flPO8DJQP3DhE0A71J7VCe.N4aH5zdeaxrO', 'active', '2026-03-10 07:00:09'),
(18, '000007', 'Mark Jing', 'Tayactac', 'D', 19, 'manager', 'mjdt@test.com', '$2y$10$TNOReKxI7he1kCUrL/flPO8DJQP3DhE0A71J7VCe.N4aH5zdeaxrO', 'active', '2026-03-10 07:01:23'),
(19, '939921', 'Ado', 'Ado', NULL, 3, 'staff', 'ado@test.com', '$2y$10$TNOReKxI7he1kCUrL/flPO8DJQP3DhE0A71J7VCe.N4aH5zdeaxrO', 'active', '2026-03-10 07:07:13'),
(20, '000008', 'Allan Joseph', 'Abapo', NULL, 22, 'manager', 'aja@test.com', '$2y$10$81.jrHlXdEnKYjy5PitYRueDDSp1m9wWeOGWBAai7btHvsxPjxfCu', 'active', '2026-03-11 03:52:49'),
(21, '939922', 'Miya', 'Ongkit', NULL, 1, 'staff', 'miya@test.com', '$2y$10$m/3rtdSh6MUKGir823W9jeA5bY3vGgCbNET5yU4Mt4O.13g3cbbYS', 'active', '2026-03-11 03:55:33');

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
-- Indexes for table `document_logs`
--
ALTER TABLE `document_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `action_by` (`action_by`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `idx_logs_document` (`document_id`);

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
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_logs`
--
ALTER TABLE `document_logs`
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
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

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
