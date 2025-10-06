-- Seed data for users table
-- Default password for all users: password123

INSERT INTO users (email, password_hash, first_name, last_name, role, status) VALUES
('admin@forward.local', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5.Ep6Y8Q8V8Zi', 'Admin', 'User', 'admin', 'active'),
('teacher1@forward.local', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5.Ep6Y8Q8V8Zi', 'John', 'Doe', 'teacher', 'active'),
('teacher2@forward.local', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5.Ep6Y8Q8V8Zi', 'Jane', 'Smith', 'teacher', 'active'),
('student1@forward.local', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5.Ep6Y8Q8V8Zi', 'Alice', 'Johnson', 'student', 'active'),
('student2@forward.local', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5.Ep6Y8Q8V8Zi', 'Bob', 'Williams', 'student', 'active'),
('student3@forward.local', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5.Ep6Y8Q8V8Zi', 'Charlie', 'Brown', 'student', 'active');
