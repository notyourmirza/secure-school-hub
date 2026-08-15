USE school_saas;

-- PHP admin upgrade: keep tenant ownership explicit and remove the legacy section model.
ALTER TABLE timetable_entries DROP FOREIGN KEY IF EXISTS fk_timetable_section;
ALTER TABLE timetable_entries DROP COLUMN IF EXISTS section_id;
DROP TABLE IF EXISTS sections;

CREATE TABLE IF NOT EXISTS admissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    school_id BIGINT UNSIGNED NOT NULL,
    application_no VARCHAR(80) NOT NULL,
    applicant_name VARCHAR(160) NOT NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    status ENUM('submitted','under_review','approved','rejected','converted') NOT NULL DEFAULT 'submitted',
    notes TEXT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE KEY uq_admission_school_no (school_id, application_no),
    INDEX idx_admission_school_status (school_id, status)
) ENGINE=InnoDB;

ALTER TABLE subscription_plans ADD COLUMN IF NOT EXISTS max_teachers INT UNSIGNED NOT NULL DEFAULT 10;
INSERT INTO permissions (name) VALUES
('teachers.view'),('teachers.create'),('teachers.update'),('classes.view'),('classes.create'),('classes.update'),('subjects.view'),('subjects.create'),('subjects.update'),('attendance.view'),('attendance.create'),('attendance.update'),('exams.view'),('exams.create'),('fees.view'),('fees.create'),('admissions.view'),('admissions.create'),('notices.view'),('notices.create'),('reports.view')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Remove section permissions from existing role grants without affecting other RBAC entries.
DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE p.name LIKE 'sections.%';
DELETE FROM permissions WHERE name LIKE 'sections.%';
