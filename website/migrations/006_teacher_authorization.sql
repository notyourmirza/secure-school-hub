USE school_saas;

ALTER TABLE teachers ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL;
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS qualification VARCHAR(160) NULL;
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS joining_date DATE NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS class_id BIGINT UNSIGNED NULL;
ALTER TABLE students ADD CONSTRAINT fk_student_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL;
CREATE INDEX idx_students_school_class ON students (school_id, class_id);
CREATE INDEX idx_teachers_school_user ON teachers (school_id, user_id);
