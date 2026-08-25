-- ==========================================================
-- University Meeting Management System (UoH-MMS)
-- Database Schema: meeting_management_system
-- Engine: InnoDB, Charset: utf8mb4, Collation: utf8mb4_unicode_ci
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `meeting_management_system` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `meeting_management_system`;

-- Disable Foreign Key Checks during table creation
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------
-- 1. ROLES & PERMISSIONS SYSTEM
-- ----------------------------------------------------------

DROP TABLE IF EXISTS `user_permissions`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

CREATE TABLE `roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `module` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- 2. UNIVERSITY ORGANIZATION (DEPARTMENTS & OFFICES)
-- ----------------------------------------------------------

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL UNIQUE,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `offices`;
CREATE TABLE `offices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL UNIQUE,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- 3. USERS & USER-SPECIFIC PERMISSIONS
-- ----------------------------------------------------------

CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT UNSIGNED NOT NULL,
  `department_id` INT UNSIGNED NULL,
  `office_id` INT UNSIGNED NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `designation` VARCHAR(100) NULL,
  `phone` VARCHAR(30) NULL,
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL,
  INDEX `idx_user_email` (`email`),
  INDEX `idx_user_role` (`role_id`),
  INDEX `idx_user_dept` (`department_id`),
  INDEX `idx_user_office` (`office_id`)
) ENGINE=InnoDB;

CREATE TABLE `user_permissions` (
  `user_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  `is_granted` TINYINT(1) NOT NULL DEFAULT 1,
  `assigned_by` INT UNSIGNED NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `permission_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- 4. APPROVAL AUTHORITIES & HIERARCHY
-- ----------------------------------------------------------

DROP TABLE IF EXISTS `approval_authorities`;
CREATE TABLE `approval_authorities` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `scope_type` ENUM('university', 'department', 'office') NOT NULL,
  `department_id` INT UNSIGNED NULL,
  `office_id` INT UNSIGNED NULL,
  `level_order` INT UNSIGNED NOT NULL DEFAULT 1,
  `description` VARCHAR(255) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE CASCADE,
  INDEX `idx_auth_scope` (`scope_type`, `department_id`, `office_id`)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- 5. MEETING ROOMS, MANAGERS & BLOCKS
-- ----------------------------------------------------------

DROP TABLE IF EXISTS `room_blocks`;
DROP TABLE IF EXISTS `room_managers`;
DROP TABLE IF EXISTS `rooms`;

CREATE TABLE `rooms` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `building` VARCHAR(100) NOT NULL,
  `floor` VARCHAR(50) NULL,
  `capacity` INT UNSIGNED NOT NULL DEFAULT 20,
  `facilities` TEXT NULL,
  `description` TEXT NULL,
  `requires_approval` TINYINT(1) NOT NULL DEFAULT 1,
  `status` ENUM('available', 'unavailable', 'maintenance') NOT NULL DEFAULT 'available',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_room_status` (`status`)
) ENGINE=InnoDB;

CREATE TABLE `room_managers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `room_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_room_user` (`room_id`, `user_id`),
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `room_blocks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `room_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `reason` TEXT NULL,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `status` ENUM('active', 'released') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  INDEX `idx_block_time` (`room_id`, `start_time`, `end_time`)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- 6. MEETINGS, PARTICIPANTS & APPROVAL WORKFLOW
-- ----------------------------------------------------------

DROP TABLE IF EXISTS `meeting_status_history`;
DROP TABLE IF EXISTS `meeting_approvals`;
DROP TABLE IF EXISTS `meeting_participants`;
DROP TABLE IF EXISTS `meetings`;

CREATE TABLE `meetings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `requester_id` INT UNSIGNED NOT NULL,
  `chair_id` INT UNSIGNED NULL,
  `department_id` INT UNSIGNED NULL,
  `office_id` INT UNSIGNED NULL,
  `room_id` INT UNSIGNED NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `agenda` TEXT NULL,
  `meeting_type` ENUM('departmental', 'office', 'committee', 'university') NOT NULL DEFAULT 'departmental',
  `priority` ENUM('normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
  `mode` ENUM('in_person', 'online', 'hybrid') NOT NULL DEFAULT 'in_person',
  `online_link` VARCHAR(255) NULL,
  `meeting_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `status` ENUM('draft', 'pending_approval', 'approved', 'rejected', 'cancelled', 'completed') NOT NULL DEFAULT 'draft',
  `submission_time` DATETIME NULL,
  `approval_time` DATETIME NULL,
  `rejection_reason` TEXT NULL,
  `cancellation_reason` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`chair_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  INDEX `idx_mtg_schedule` (`meeting_date`, `start_time`, `end_time`),
  INDEX `idx_mtg_status` (`status`),
  INDEX `idx_mtg_room` (`room_id`),
  INDEX `idx_mtg_requester` (`requester_id`)
) ENGINE=InnoDB;

CREATE TABLE `meeting_participants` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `meeting_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `participant_type` ENUM('required', 'optional') NOT NULL DEFAULT 'required',
  `meeting_role` ENUM('chair', 'secretary', 'member', 'attendee', 'guest') NOT NULL DEFAULT 'member',
  `invitation_status` ENUM('pending', 'accepted', 'declined', 'tentative') NOT NULL DEFAULT 'pending',
  `response_notes` TEXT NULL,
  `response_time` DATETIME NULL,
  `attended` TINYINT(1) NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_meeting_user` (`meeting_id`, `user_id`),
  FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  INDEX `idx_part_user` (`user_id`)
) ENGINE=InnoDB;

CREATE TABLE `meeting_approvals` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `meeting_id` INT UNSIGNED NOT NULL,
  `approver_id` INT UNSIGNED NOT NULL,
  `authority_id` INT UNSIGNED NULL,
  `approval_level` INT UNSIGNED NOT NULL DEFAULT 1,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `comments` TEXT NULL,
  `action_time` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`authority_id`) REFERENCES `approval_authorities` (`id`) ON DELETE SET NULL,
  INDEX `idx_appr_meeting` (`meeting_id`),
  INDEX `idx_appr_approver` (`approver_id`)
) ENGINE=InnoDB;

CREATE TABLE `meeting_status_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `meeting_id` INT UNSIGNED NOT NULL,
  `old_status` ENUM('draft', 'pending_approval', 'approved', 'rejected', 'cancelled', 'completed') NULL,
  `new_status` ENUM('draft', 'pending_approval', 'approved', 'rejected', 'cancelled', 'completed') NOT NULL,
  `changed_by` INT UNSIGNED NOT NULL,
  `reason` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- 7. POST-SUBMISSION MEETING CHANGE REQUESTS
-- ----------------------------------------------------------

DROP TABLE IF EXISTS `change_request_participants`;
DROP TABLE IF EXISTS `meeting_change_requests`;

CREATE TABLE `meeting_change_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `meeting_id` INT UNSIGNED NOT NULL,
  `requester_id` INT UNSIGNED NOT NULL,
  `request_type` ENUM('participant_change', 'reschedule', 'room_change', 'cancellation') NOT NULL,
  `requested_data` JSON NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT UNSIGNED NULL,
  `review_comments` TEXT NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_chg_meeting` (`meeting_id`),
  INDEX `idx_chg_status` (`status`)
) ENGINE=InnoDB;

CREATE TABLE `change_request_participants` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `change_request_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `action_type` ENUM('add', 'remove') NOT NULL,
  `participant_type` ENUM('required', 'optional') NOT NULL DEFAULT 'required',
  `meeting_role` ENUM('chair', 'secretary', 'member', 'attendee', 'guest') NOT NULL DEFAULT 'member',
  FOREIGN KEY (`change_request_id`) REFERENCES `meeting_change_requests` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- 8. UNIVERSITY / DEPARTMENT / OFFICE CALENDAR EVENTS
-- ----------------------------------------------------------

DROP TABLE IF EXISTS `calendar_events`;
CREATE TABLE `calendar_events` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `event_scope` ENUM('university', 'department', 'office') NOT NULL DEFAULT 'university',
  `department_id` INT UNSIGNED NULL,
  `office_id` INT UNSIGNED NULL,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `status` ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  INDEX `idx_event_time` (`start_time`, `end_time`)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- 9. NOTIFICATIONS & REMINDERS (GMAIL SMTP INTEGRATED)
-- ----------------------------------------------------------

DROP TABLE IF EXISTS `meeting_reminders`;
DROP TABLE IF EXISTS `notifications`;

CREATE TABLE `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `recipient_id` INT UNSIGNED NOT NULL,
  `meeting_id` INT UNSIGNED NULL,
  `change_request_id` INT UNSIGNED NULL,
  `notification_type` VARCHAR(60) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `channel` ENUM('system', 'email', 'both') NOT NULL DEFAULT 'both',
  `email_status` ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME NULL,
  `sent_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`change_request_id`) REFERENCES `meeting_change_requests` (`id`) ON DELETE SET NULL,
  INDEX `idx_notif_recipient` (`recipient_id`, `is_read`)
) ENGINE=InnoDB;

CREATE TABLE `meeting_reminders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `meeting_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `reminder_time` DATETIME NOT NULL,
  `reminder_type` ENUM('1_day_before', '1_hour_before', 'custom') NOT NULL DEFAULT '1_day_before',
  `status` ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  `sent_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_rem_status` (`status`, `reminder_time`)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- 10. MEETING RECORDS (MINUTES, DECISIONS, ACTION ITEMS)
-- ----------------------------------------------------------

DROP TABLE IF EXISTS `meeting_records`;
CREATE TABLE `meeting_records` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `meeting_id` INT UNSIGNED NOT NULL UNIQUE,
  `recorder_id` INT UNSIGNED NOT NULL,
  `minutes_summary` MEDIUMTEXT NOT NULL,
  `key_decisions` MEDIUMTEXT NULL,
  `action_items` JSON NULL,
  `status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'published',
  `published_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recorder_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- 11. AUDIT LOGGING SYSTEM (IMMUTABLE LOGS)
-- ----------------------------------------------------------

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED NULL,
  `old_values` JSON NULL,
  `new_values` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
  INDEX `idx_audit_user` (`user_id`),
  INDEX `idx_audit_action` (`action`),
  INDEX `idx_audit_time` (`created_at`)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- 12. INITIAL SYSTEM PERMISSIONS & ROLES SETUP
-- (No dummy application data: creates only core RBAC definition)
-- ----------------------------------------------------------

INSERT INTO `roles` (`name`, `description`) VALUES
('Super Admin', 'Full system administration and configuration rights'),
('Registrar', 'University executive authority for all statutory and general meetings'),
('Dean', 'Faculty level academic executive and meeting approval authority'),
('HOD', 'Head of Department meeting requester and departmental approval authority'),
('Director', 'Administrative directorate head and meeting approval authority'),
('Room Manager', 'Authorized manager for campus halls, auditoriums, and conference rooms'),
('Faculty/Staff', 'Standard university academic faculty or administrative staff member');

INSERT INTO `permissions` (`name`, `module`, `description`) VALUES
-- Meetings
('meetings.create', 'meetings', 'Create new meeting requests'),
('meetings.view_all', 'meetings', 'View all university meeting schedules and details'),
('meetings.view_department', 'meetings', 'View departmental meetings'),
('meetings.view_assigned', 'meetings', 'View meetings where user is participant or requester'),
('meetings.edit_draft', 'meetings', 'Edit own draft meetings before submission'),
('meetings.delete_draft', 'meetings', 'Delete own draft meetings'),
('meetings.request_change', 'meetings', 'Submit participant change, reschedule, or cancellation requests'),
-- Approvals
('approvals.review_department', 'approvals', 'Review and approve/reject departmental meeting requests'),
('approvals.review_office', 'approvals', 'Review and approve/reject administrative office meeting requests'),
('approvals.review_university', 'approvals', 'Review and approve/reject university-wide meetings (Registrar scope)'),
('approvals.review_changes', 'approvals', 'Review and approve post-submission meeting change requests'),
-- Rooms
('rooms.manage', 'rooms', 'Create, update, and manage university rooms'),
('rooms.block', 'rooms', 'Place temporary maintenance or administrative blocks on rooms'),
('rooms.assign_manager', 'rooms', 'Assign room managers to specific rooms'),
-- Meeting Records
('records.create', 'records', 'Record and publish official minutes, decisions, and action items'),
('records.view', 'records', 'View published meeting records and minutes'),
-- University Structure
('structure.manage_departments', 'admin', 'Create, edit, and deactivate academic departments'),
('structure.manage_offices', 'admin', 'Create, edit, and deactivate administrative offices'),
('structure.manage_authorities', 'admin', 'Configure approval authorities and escalation hierarchy'),
('structure.manage_events', 'admin', 'Create official university calendar events'),
-- Users & Roles
('users.manage', 'users', 'Create and edit users, activate/deactivate accounts'),
('users.assign_permissions', 'users', 'Grant individual permissions to subordinate users'),
('roles.manage', 'roles', 'Manage roles and role permissions'),
-- Audit Logs
('audit.view', 'audit', 'View system-wide immutable audit trail and change diffs');

-- Map Default Permissions to Roles
-- 1. Super Admin (All permissions)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- 2. Registrar (Executive approvals, meetings, records, events, audit view)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `permissions` WHERE `name` IN (
  'meetings.create', 'meetings.view_all', 'meetings.edit_draft', 'meetings.request_change',
  'approvals.review_department', 'approvals.review_office', 'approvals.review_university', 'approvals.review_changes',
  'rooms.block', 'records.create', 'records.view', 'structure.manage_events', 'audit.view'
);

-- 3. Dean (Faculty level approvals, meetings, records)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions` WHERE `name` IN (
  'meetings.create', 'meetings.view_all', 'meetings.view_department', 'meetings.edit_draft', 'meetings.request_change',
  'approvals.review_department', 'approvals.review_changes', 'records.create', 'records.view'
);

-- 4. HOD (Departmental meeting approvals and creation)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions` WHERE `name` IN (
  'meetings.create', 'meetings.view_department', 'meetings.view_assigned', 'meetings.edit_draft', 'meetings.request_change',
  'approvals.review_department', 'approvals.review_changes', 'records.create', 'records.view'
);

-- 5. Director (Office approvals and creation)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, id FROM `permissions` WHERE `name` IN (
  'meetings.create', 'meetings.view_all', 'meetings.edit_draft', 'meetings.request_change',
  'approvals.review_office', 'approvals.review_changes', 'records.create', 'records.view'
);

-- 6. Room Manager (Room management, blocks, view schedules)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions` WHERE `name` IN (
  'meetings.view_all', 'rooms.manage', 'rooms.block'
);

-- 7. Faculty/Staff (Standard meeting creation, RSVP, view records)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 7, id FROM `permissions` WHERE `name` IN (
  'meetings.create', 'meetings.view_assigned', 'meetings.edit_draft', 'meetings.request_change', 'records.view'
);

SET FOREIGN_KEY_CHECKS = 1;
