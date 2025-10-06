/*
  # Certificates and Schedules
  
  1. New Tables
    - `certificates`
      - `id` (uuid, primary key)
      - `student_id` (uuid, foreign key)
      - `course_id` (uuid, foreign key)
      - `issued_by` (uuid, foreign key to admin/teacher)
      - `certificate_number` (text, unique)
      - `blockchain_hash` (text) - NFT simulation
      - `issued_at` (timestamp)
    
    - `schedules`
      - `id` (uuid, primary key)
      - `teacher_id` (uuid, foreign key)
      - `course_id` (uuid, foreign key)
      - `title` (text)
      - `start_time` (timestamp)
      - `end_time` (timestamp)
      - `meeting_url` (text)
      - `status` (enum: scheduled, ongoing, completed, cancelled)
    
    - `schedule_bookings`
      - `id` (uuid, primary key)
      - `schedule_id` (uuid, foreign key)
      - `student_id` (uuid, foreign key)
      - `booked_at` (timestamp)
  
  2. Security
    - Enable RLS on all tables
    - Add policies for role-based access
*/

CREATE TABLE IF NOT EXISTS certificates (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  student_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  course_id uuid NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  issued_by uuid NOT NULL REFERENCES users(id),
  certificate_number text UNIQUE NOT NULL,
  blockchain_hash text DEFAULT '',
  issued_at timestamptz DEFAULT now(),
  UNIQUE(student_id, course_id)
);

CREATE TABLE IF NOT EXISTS schedules (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  teacher_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  course_id uuid REFERENCES courses(id) ON DELETE SET NULL,
  title text NOT NULL,
  start_time timestamptz NOT NULL,
  end_time timestamptz NOT NULL,
  meeting_url text DEFAULT '',
  status text DEFAULT 'scheduled' CHECK (status IN ('scheduled', 'ongoing', 'completed', 'cancelled')),
  created_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS schedule_bookings (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  schedule_id uuid NOT NULL REFERENCES schedules(id) ON DELETE CASCADE,
  student_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  booked_at timestamptz DEFAULT now(),
  UNIQUE(schedule_id, student_id)
);

ALTER TABLE certificates ENABLE ROW LEVEL SECURITY;
ALTER TABLE schedules ENABLE ROW LEVEL SECURITY;
ALTER TABLE schedule_bookings ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Students can view own certificates"
  ON certificates FOR SELECT
  USING (student_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Teachers and admins can issue certificates"
  ON certificates FOR INSERT
  WITH CHECK (issued_by = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Anyone can view schedules"
  ON schedules FOR SELECT
  USING (true);

CREATE POLICY "Teachers can manage own schedules"
  ON schedules FOR ALL
  USING (teacher_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Students can view own bookings"
  ON schedule_bookings FOR SELECT
  USING (student_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Students can book schedules"
  ON schedule_bookings FOR INSERT
  WITH CHECK (student_id = (current_setting('app.user_id', true))::uuid);