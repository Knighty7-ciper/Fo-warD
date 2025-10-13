/*
  # Forward LMS - Initial Schema
  
  1. New Tables
    - `users`
      - `id` (uuid, primary key)
      - `email` (text, unique)
      - `password_hash` (text)
      - `role` (enum: admin, teacher, student)
      - `first_name` (text)
      - `last_name` (text)
      - `avatar_url` (text)
      - `status` (text, default: active)
      - `created_at` (timestamp)
      - `updated_at` (timestamp)
    
    - `courses`
      - `id` (uuid, primary key)
      - `teacher_id` (uuid, foreign key)
      - `title` (text)
      - `description` (text)
      - `thumbnail_url` (text)
      - `status` (enum: draft, published, archived)
      - `price` (decimal)
      - `created_at` (timestamp)
      - `updated_at` (timestamp)
    
    - `lessons`
      - `id` (uuid, primary key)
      - `course_id` (uuid, foreign key)
      - `title` (text)
      - `content` (text)
      - `video_url` (text)
      - `order_index` (integer)
      - `created_at` (timestamp)
    
    - `enrollments`
      - `id` (uuid, primary key)
      - `student_id` (uuid, foreign key)
      - `course_id` (uuid, foreign key)
      - `progress` (integer, default: 0)
      - `enrolled_at` (timestamp)
      - `completed_at` (timestamp)
    
    - `quizzes`
      - `id` (uuid, primary key)
      - `course_id` (uuid, foreign key)
      - `title` (text)
      - `passing_score` (integer)
      - `created_at` (timestamp)
    
    - `assignments`
      - `id` (uuid, primary key)
      - `course_id` (uuid, foreign key)
      - `title` (text)
      - `description` (text)
      - `due_date` (timestamp)
      - `max_points` (integer)
      - `created_at` (timestamp)
  
  2. Security
    - Enable RLS on all tables
    - Add policies for role-based access
*/

CREATE TABLE IF NOT EXISTS users (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  email text UNIQUE NOT NULL,
  password_hash text NOT NULL,
  role text NOT NULL CHECK (role IN ('admin', 'teacher', 'student')),
  first_name text NOT NULL,
  last_name text NOT NULL,
  avatar_url text DEFAULT '',
  status text DEFAULT 'active',
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS courses (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  teacher_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  title text NOT NULL,
  description text DEFAULT '',
  thumbnail_url text DEFAULT '',
  status text DEFAULT 'draft' CHECK (status IN ('draft', 'published', 'archived')),
  price decimal DEFAULT 0,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS lessons (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  course_id uuid NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  title text NOT NULL,
  content text DEFAULT '',
  video_url text DEFAULT '',
  order_index integer DEFAULT 0,
  created_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS enrollments (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  student_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  course_id uuid NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  progress integer DEFAULT 0,
  enrolled_at timestamptz DEFAULT now(),
  completed_at timestamptz,
  UNIQUE(student_id, course_id)
);

CREATE TABLE IF NOT EXISTS quizzes (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  course_id uuid NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  title text NOT NULL,
  passing_score integer DEFAULT 70,
  created_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS assignments (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  course_id uuid NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  title text NOT NULL,
  description text DEFAULT '',
  due_date timestamptz,
  max_points integer DEFAULT 100,
  created_at timestamptz DEFAULT now()
);

ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE courses ENABLE ROW LEVEL SECURITY;
ALTER TABLE lessons ENABLE ROW LEVEL SECURITY;
ALTER TABLE enrollments ENABLE ROW LEVEL SECURITY;
ALTER TABLE quizzes ENABLE ROW LEVEL SECURITY;
ALTER TABLE assignments ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can view all users"
  ON users FOR SELECT
  USING (true);

CREATE POLICY "Users can update own profile"
  ON users FOR UPDATE
  USING (id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Anyone can view published courses"
  ON courses FOR SELECT
  USING (status = 'published' OR teacher_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Teachers can create courses"
  ON courses FOR INSERT
  WITH CHECK (teacher_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Teachers can update own courses"
  ON courses FOR UPDATE
  USING (teacher_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Teachers can delete own courses"
  ON courses FOR DELETE
  USING (teacher_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Anyone can view lessons of enrolled or published courses"
  ON lessons FOR SELECT
  USING (true);

CREATE POLICY "Teachers can manage lessons in own courses"
  ON lessons FOR ALL
  USING (course_id IN (SELECT id FROM courses WHERE teacher_id = (current_setting('app.user_id', true))::uuid));

CREATE POLICY "Students can view own enrollments"
  ON enrollments FOR SELECT
  USING (student_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Students can enroll in courses"
  ON enrollments FOR INSERT
  WITH CHECK (student_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Anyone can view quizzes"
  ON quizzes FOR SELECT
  USING (true);

CREATE POLICY "Teachers can manage quizzes"
  ON quizzes FOR ALL
  USING (course_id IN (SELECT id FROM courses WHERE teacher_id = (current_setting('app.user_id', true))::uuid));

CREATE POLICY "Anyone can view assignments"
  ON assignments FOR SELECT
  USING (true);

CREATE POLICY "Teachers can manage assignments"
  ON assignments FOR ALL
  USING (course_id IN (SELECT id FROM courses WHERE teacher_id = (current_setting('app.user_id', true))::uuid));
