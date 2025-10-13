-- Gradebook System
-- Comprehensive grade management and calculation

-- Grade categories table (for weighted grading)
CREATE TABLE IF NOT EXISTS grade_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    weight DECIMAL(5,2) DEFAULT 0.00,
    drop_lowest INT DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grade items table (assignments, quizzes, exams, participation, etc.)
CREATE TABLE IF NOT EXISTS grade_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    category_id INT,
    item_type ENUM('assignment', 'quiz', 'exam', 'participation', 'project', 'other') NOT NULL,
    item_id INT,
    title VARCHAR(255) NOT NULL,
    max_points DECIMAL(10,2) NOT NULL,
    due_date DATETIME,
    is_extra_credit BOOLEAN DEFAULT FALSE,
    weight DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES grade_categories(id) ON DELETE SET NULL,
    INDEX idx_course (course_id),
    INDEX idx_category (category_id),
    INDEX idx_type_id (item_type, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student grades table
CREATE TABLE IF NOT EXISTS student_grades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    grade_item_id INT NOT NULL,
    points_earned DECIMAL(10,2),
    percentage DECIMAL(5,2),
    letter_grade VARCHAR(5),
    feedback TEXT,
    graded_by INT,
    graded_at TIMESTAMP NULL,
    is_excused BOOLEAN DEFAULT FALSE,
    is_missing BOOLEAN DEFAULT FALSE,
    is_late BOOLEAN DEFAULT FALSE,
    late_penalty DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (grade_item_id) REFERENCES grade_items(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_student_item (student_id, grade_item_id),
    INDEX idx_student (student_id),
    INDEX idx_item (grade_item_id),
    INDEX idx_graded_by (graded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Course grade calculations table
CREATE TABLE IF NOT EXISTS course_grades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    current_percentage DECIMAL(5,2),
    current_letter_grade VARCHAR(5),
    final_percentage DECIMAL(5,2),
    final_letter_grade VARCHAR(5),
    is_final BOOLEAN DEFAULT FALSE,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_course (student_id, course_id),
    INDEX idx_student (student_id),
    INDEX idx_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grade scale table (customizable per course)
CREATE TABLE IF NOT EXISTS grade_scales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT,
    letter_grade VARCHAR(5) NOT NULL,
    min_percentage DECIMAL(5,2) NOT NULL,
    max_percentage DECIMAL(5,2) NOT NULL,
    gpa_value DECIMAL(3,2),
    is_passing BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grade history/audit table
CREATE TABLE IF NOT EXISTS grade_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_grade_id INT NOT NULL,
    old_points DECIMAL(10,2),
    new_points DECIMAL(10,2),
    old_percentage DECIMAL(5,2),
    new_percentage DECIMAL(5,2),
    changed_by INT NOT NULL,
    change_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_grade_id) REFERENCES student_grades(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_grade (student_grade_id),
    INDEX idx_changed_by (changed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default grade scale (standard US grading)
INSERT INTO grade_scales (course_id, letter_grade, min_percentage, max_percentage, gpa_value, is_passing) VALUES
(NULL, 'A+', 97.00, 100.00, 4.00, TRUE),
(NULL, 'A', 93.00, 96.99, 4.00, TRUE),
(NULL, 'A-', 90.00, 92.99, 3.70, TRUE),
(NULL, 'B+', 87.00, 89.99, 3.30, TRUE),
(NULL, 'B', 83.00, 86.99, 3.00, TRUE),
(NULL, 'B-', 80.00, 82.99, 2.70, TRUE),
(NULL, 'C+', 77.00, 79.99, 2.30, TRUE),
(NULL, 'C', 73.00, 76.99, 2.00, TRUE),
(NULL, 'C-', 70.00, 72.99, 1.70, TRUE),
(NULL, 'D+', 67.00, 69.99, 1.30, TRUE),
(NULL, 'D', 63.00, 66.99, 1.00, TRUE),
(NULL, 'D-', 60.00, 62.99, 0.70, TRUE),
(NULL, 'F', 0.00, 59.99, 0.00, FALSE)
ON DUPLICATE KEY UPDATE id=id;
