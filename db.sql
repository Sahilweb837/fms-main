-- =====================================================================
-- FMS ENTERPRISE — Multi-Industry Management System
-- Complete Database Schema v2.0
-- Industries: School, College, Restaurant, Hotel, Shop, Clinic
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Drop old tables safely (order matters for FK)
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `fees`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `branches`;

-- =====================================================================
-- Table: branches
-- Multi-tenant core — each branch = one business center
-- =====================================================================
CREATE TABLE `branches` (
  `id`            INT(11)       NOT NULL AUTO_INCREMENT,
  `branch_name`   VARCHAR(120)  NOT NULL,
  `business_type` ENUM('school','college','restaurant','hotel','shop','dispensary','inventory','company','it_institution','other') NOT NULL DEFAULT 'other',
  `location`      VARCHAR(255)  DEFAULT NULL,
  `phone`         VARCHAR(20)   DEFAULT NULL,
  `email`         VARCHAR(100)  DEFAULT NULL,
  `logo_url`      VARCHAR(255)  DEFAULT NULL,
  `description`   TEXT          DEFAULT NULL,
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `is_pro`        TINYINT(1)    NOT NULL DEFAULT 0,
  `pro_utr_id`    VARCHAR(100)  DEFAULT NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- Table: users
-- All system users. branch_id = NULL means universal (super_admin).
-- employee_id is auto-generated per business_type on creation.
-- =====================================================================
CREATE TABLE `users` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `employee_id`    VARCHAR(50)  DEFAULT NULL COMMENT 'e.g. SCH-ADM-001, REST-EMP-042',
  `full_name`      VARCHAR(100) DEFAULT NULL,
  `username`       VARCHAR(50)  NOT NULL,
  `password`       VARCHAR(255) NOT NULL COMMENT 'Bcrypt hash',
  `plain_password` VARCHAR(255) DEFAULT NULL COMMENT 'Stored for admin reference only',
  `role`           ENUM('super_admin','admin','employee') NOT NULL DEFAULT 'employee',
  `branch_id`      INT(11)      DEFAULT NULL COMMENT 'NULL = universal (super_admin)',
  `created_by`     INT(11)      DEFAULT NULL COMMENT 'Who created this user',
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- Table: courses
-- Service/course catalog. branch_id links to specific branch.
-- Super admin creates global; branch admin creates branch-specific.
-- =====================================================================
CREATE TABLE `courses` (
  `id`               INT(11)       NOT NULL AUTO_INCREMENT,
  `branch_id`        INT(11)       DEFAULT NULL COMMENT 'NULL = available to all',
  `course_name`      VARCHAR(150)  NOT NULL COMMENT 'Course / Plan / Package / Menu Item',
  `course_code`      VARCHAR(50)   DEFAULT NULL,
  `total_fee`        DECIMAL(10,2) DEFAULT 0.00,
  `monthly_fee`      DECIMAL(10,2) DEFAULT 0.00,
  `registration_fee` DECIMAL(10,2) DEFAULT 0.00,
  `duration_months`  INT(4)        DEFAULT NULL,
  `is_active`        TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- Table: students
-- Generic entity: Student / Patient / Guest / Customer / Employee.
-- entity_id is auto-generated (e.g. SCH-2025-0001, REST-ORD-0001).
-- industry_field_1/2/ref adapt labels to business context.
-- =====================================================================
CREATE TABLE `students` (
  `id`               INT(11)       NOT NULL AUTO_INCREMENT,
  `entity_id`        VARCHAR(30)   DEFAULT NULL COMMENT 'e.g. SCH-2025-0001, HTL-GST-0042',
  `student_name`     VARCHAR(100)  NOT NULL COMMENT 'Student / Patient / Guest / Customer name',
  `father_name`      VARCHAR(100)  DEFAULT NULL COMMENT 'Guardian / Doctor / Agent / Manager',
  `contact`          VARCHAR(20)   NOT NULL,
  `email`            VARCHAR(100)  DEFAULT NULL,
  `address`          TEXT          DEFAULT NULL,
  `college`          VARCHAR(255)  DEFAULT NULL COMMENT 'School / College / Hotel / Company / Source',
  `branch_id`        INT(11)       DEFAULT NULL,
  `course_id`        INT(11)       DEFAULT NULL,
  `total_fees`       DECIMAL(10,2) DEFAULT 0.00,
  `duration`         ENUM('30_days','45_days','3_months','6_months','1_year','custom') NOT NULL DEFAULT '30_days',
  `status`           ENUM('active','inactive','completed') NOT NULL DEFAULT 'active',
  `industry_field_1` VARCHAR(100)  DEFAULT NULL COMMENT 'Class/Semester/Room Type/Dept/Table',
  `industry_field_2` VARCHAR(100)  DEFAULT NULL COMMENT 'Section/Year/Room No/SKU/Order ID',
  `industry_ref`     VARCHAR(255)  DEFAULT NULL COMMENT 'Extra contextual reference',
  `gender`           ENUM('male','female','other')  DEFAULT NULL,
  `dob`              DATE          DEFAULT NULL,
  `added_by`         INT(11)       NOT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entity_id` (`entity_id`),
  KEY `branch_id` (`branch_id`),
  KEY `course_id` (`course_id`),
  KEY `added_by` (`added_by`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `students_ibfk_2` FOREIGN KEY (`course_id`)  REFERENCES `courses` (`id`)  ON DELETE SET NULL,
  CONSTRAINT `students_ibfk_3` FOREIGN KEY (`added_by`)   REFERENCES `users` (`id`)    ON DELETE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- Table: fees
-- Payment transactions for all industry types.
-- =====================================================================
CREATE TABLE `fees` (
  `id`             INT(11)       NOT NULL AUTO_INCREMENT,
  `student_id`     INT(11)       NOT NULL,
  `fee_type`       ENUM('monthly','registration','exam','full_payment','service','advance','other') NOT NULL DEFAULT 'monthly',
  `amount`         DECIMAL(10,2) NOT NULL,
  `status`         ENUM('paid','unpaid','pending','refunded') NOT NULL DEFAULT 'paid',
  `collected_by`   INT(11)       DEFAULT NULL,
  `payment_mode`   ENUM('cash','online','upi','cheque','card','neft','other') NOT NULL DEFAULT 'cash',
  `utr_number`     VARCHAR(100)  DEFAULT NULL COMMENT 'UPI/Cheque/NEFT reference',
  `is_verified`    TINYINT(1)    NOT NULL DEFAULT 0,
  `notes`          TEXT          DEFAULT NULL,
  `date_collected` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `collected_by` (`collected_by`),
  CONSTRAINT `fees_ibfk_1` FOREIGN KEY (`student_id`)   REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fees_ibfk_2` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- Table: attendance
-- =====================================================================
CREATE TABLE `attendance` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `student_id`      INT(11) NOT NULL,
  `status`          ENUM('present','absent','late') NOT NULL DEFAULT 'present',
  `method`          ENUM('manual','biometric') NOT NULL DEFAULT 'manual',
  `attendance_date` DATE    NOT NULL,
  `attendance_time` TIME    NOT NULL,
  `noted_by`        INT(11) DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- Table: expenses
-- Branch-specific operational expenditures
-- =====================================================================
CREATE TABLE `expenses` (
  `id`           INT(11)       NOT NULL AUTO_INCREMENT,
  `branch_id`    INT(11)       NOT NULL,
  `amount`       DECIMAL(10,2) NOT NULL,
  `category`     VARCHAR(100)  NOT NULL,
  `description`  TEXT,
  `expense_date` DATE          NOT NULL,
  `added_by`     INT(11)       NOT NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `branch_id` (`branch_id`),
  KEY `added_by` (`added_by`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- Table: activity_logs
-- Audit trail for all user actions
-- =====================================================================
CREATE TABLE `activity_logs` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)      NOT NULL,
  `action`     VARCHAR(255) NOT NULL,
  `details`    TEXT         DEFAULT NULL,
  `ip_address` VARCHAR(45)  DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- Default Super Admin (password: admin123)
-- =====================================================================
INSERT INTO `users` (`id`, `employee_id`, `full_name`, `username`, `password`, `plain_password`, `role`, `branch_id`) VALUES
(1, 'SUP-001', 'Super Administrator', 'superadmin', '$2y$10$CVWDI2oGCZ4j7SMhpUHb3OVE/tHWQqfgKYyhr.uJ21UAdw.2EuTd2', 'admin123', 'super_admin', NULL);

COMMIT;
