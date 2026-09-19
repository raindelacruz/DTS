-- DTS production schema-only migration for September 2026 revisions.
--
-- Purpose:
--   Add only the database structures needed by the current application code.
--   This script intentionally does not migrate, rewrite, consolidate, delete,
--   or backfill existing production workflow data.
--
-- Important:
--   1. Run this only after a full production database backup.
--   2. Run this first on a staging database restored from production.
--   3. Do not run database/migrate.php for a no-data-touch deployment.
--   4. MariaDB/MySQL DDL statements cause implicit commits. A transaction cannot
--      reliably roll back these ALTER/CREATE statements. Use a tested backup
--      and restore plan as the rollback method.
--   5. This script assumes the production schema matches dts_db_production.sql
--      generated on 2026-09-05.
--
-- Explicitly excluded from this schema-only migration:
--   - UPDATE department_action_slips SET title = slip_number
--   - UPDATE departments SET office_level/can_release classifications
--   - parent/child action slip linking
--   - generated department_action_slip_assignments rows
--   - copied/deleted/consolidated department_action_slip_events rows
--   - assignment status recalculation

ALTER TABLE `department_action_slips`
  ADD COLUMN `title` varchar(255) DEFAULT NULL AFTER `slip_number`,
  ADD COLUMN `parent_slip_id` int(11) DEFAULT NULL AFTER `receiving_division_id`,
  ADD COLUMN `current_unit_id` int(11) DEFAULT NULL AFTER `current_division_id`;

ALTER TABLE `departments`
  ADD COLUMN `office_level` varchar(20) NOT NULL DEFAULT 'department' AFTER `parent_id`,
  ADD COLUMN `can_release` tinyint(1) NOT NULL DEFAULT 1 AFTER `office_level`;

ALTER TABLE `document_assignments`
  ADD COLUMN `parent_assignment_id` int(11) DEFAULT NULL AFTER `document_id`,
  MODIFY `assignment_type` enum('INTERNAL','UNIT') NOT NULL DEFAULT 'INTERNAL';

CREATE TABLE IF NOT EXISTS `user_access_change_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `changed_by_user_id` int(11) NOT NULL,
  `old_role` varchar(40) DEFAULT NULL,
  `new_role` varchar(40) DEFAULT NULL,
  `old_department_id` int(11) DEFAULT NULL,
  `new_department_id` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_access_history_user` (`user_id`),
  KEY `idx_access_history_changed_by` (`changed_by_user_id`),
  KEY `idx_access_history_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `department_action_slip_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slip_id` int(11) NOT NULL,
  `assignment_type` varchar(20) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_by_department_id` int(11) NOT NULL,
  `status` varchar(80) NOT NULL,
  `remarks` text DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `received_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `completion_attachment` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_das_assignments_slip` (`slip_id`),
  KEY `idx_das_assignments_department` (`department_id`),
  KEY `idx_das_assignments_user` (`user_id`),
  KEY `idx_das_assignments_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `departments`
  ADD KEY `idx_departments_office_level` (`office_level`);

ALTER TABLE `department_action_slips`
  ADD KEY `idx_das_parent_slip` (`parent_slip_id`),
  ADD KEY `idx_das_current_unit` (`current_unit_id`);

ALTER TABLE `document_assignments`
  ADD KEY `idx_document_assignments_parent` (`parent_assignment_id`);
