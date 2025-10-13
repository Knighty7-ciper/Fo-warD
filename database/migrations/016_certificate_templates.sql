-- Certificate Templates System Migration
-- Adds certificate template management

CREATE TABLE IF NOT EXISTS certificate_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    orientation ENUM('portrait', 'landscape') DEFAULT 'landscape',
    background_color VARCHAR(7) DEFAULT '#FFFFFF',
    background_image VARCHAR(500),
    border_style ENUM('none', 'simple', 'elegant', 'modern') DEFAULT 'elegant',
    border_color VARCHAR(7) DEFAULT '#000000',
    title_text VARCHAR(255) DEFAULT 'Certificate of Completion',
    title_font_size INT DEFAULT 36,
    title_color VARCHAR(7) DEFAULT '#000000',
    body_template TEXT,
    signature_fields JSON,
    logo_url VARCHAR(500),
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Add template_id to certificates table
ALTER TABLE certificates ADD COLUMN template_id INT NULL AFTER blockchain_hash;
ALTER TABLE certificates ADD FOREIGN KEY (template_id) REFERENCES certificate_templates(id) ON DELETE SET NULL;

-- Insert default certificate template
INSERT INTO certificate_templates 
(name, description, orientation, title_text, body_template, signature_fields, is_default, is_active) 
VALUES 
('Default Certificate', 'Standard certificate template', 'landscape', 
'Certificate of Completion',
'This is to certify that <strong>{{student_name}}</strong> has successfully completed the course <strong>{{course_title}}</strong> on {{completion_date}}.',
'[{"label": "Instructor", "name": "{{instructor_name}}"}, {"label": "Administrator", "name": "FowarD LMS"}]',
TRUE, TRUE);
