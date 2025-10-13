/*
  # Rewards System and User Interactions
  
  1. New Tables
    - `rewards`
      - `id` (uuid, primary key)
      - `student_id` (uuid, foreign key)
      - `points` (integer)
      - `reason` (text)
      - `awarded_at` (timestamp)
    
    - `reward_redemptions`
      - `id` (uuid, primary key)
      - `student_id` (uuid, foreign key)
      - `points_used` (integer)
      - `reward_type` (text)
      - `description` (text)
      - `redeemed_at` (timestamp)
    
    - `discussion_forums`
      - `id` (uuid, primary key)
      - `course_id` (uuid, foreign key)
      - `title` (text)
      - `created_at` (timestamp)
    
    - `forum_posts`
      - `id` (uuid, primary key)
      - `forum_id` (uuid, foreign key)
      - `user_id` (uuid, foreign key)
      - `content` (text)
      - `created_at` (timestamp)
    
    - `submissions`
      - `id` (uuid, primary key)
      - `assignment_id` (uuid, foreign key)
      - `student_id` (uuid, foreign key)
      - `file_url` (text)
      - `content` (text)
      - `grade` (integer)
      - `feedback` (text)
      - `submitted_at` (timestamp)
      - `graded_at` (timestamp)
  
  2. Security
    - Enable RLS on all tables
    - Add policies for role-based access
*/

CREATE TABLE IF NOT EXISTS rewards (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  student_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  points integer NOT NULL DEFAULT 0,
  reason text NOT NULL,
  awarded_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS reward_redemptions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  student_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  points_used integer NOT NULL,
  reward_type text NOT NULL,
  description text DEFAULT '',
  redeemed_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS discussion_forums (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  course_id uuid NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  title text NOT NULL,
  created_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS forum_posts (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  forum_id uuid NOT NULL REFERENCES discussion_forums(id) ON DELETE CASCADE,
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  content text NOT NULL,
  created_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS submissions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  assignment_id uuid NOT NULL REFERENCES assignments(id) ON DELETE CASCADE,
  student_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  file_url text DEFAULT '',
  content text DEFAULT '',
  grade integer,
  feedback text DEFAULT '',
  submitted_at timestamptz DEFAULT now(),
  graded_at timestamptz,
  UNIQUE(assignment_id, student_id)
);

ALTER TABLE rewards ENABLE ROW LEVEL SECURITY;
ALTER TABLE reward_redemptions ENABLE ROW LEVEL SECURITY;
ALTER TABLE discussion_forums ENABLE ROW LEVEL SECURITY;
ALTER TABLE forum_posts ENABLE ROW LEVEL SECURITY;
ALTER TABLE submissions ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Students can view own rewards"
  ON rewards FOR SELECT
  USING (student_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Students can view own redemptions"
  ON reward_redemptions FOR SELECT
  USING (student_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Students can redeem points"
  ON reward_redemptions FOR INSERT
  WITH CHECK (student_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Anyone can view forums"
  ON discussion_forums FOR SELECT
  USING (true);

CREATE POLICY "Anyone can view forum posts"
  ON forum_posts FOR SELECT
  USING (true);

CREATE POLICY "Authenticated users can create forum posts"
  ON forum_posts FOR INSERT
  WITH CHECK (user_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Students can view own submissions"
  ON submissions FOR SELECT
  USING (student_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Students can submit assignments"
  ON submissions FOR INSERT
  WITH CHECK (student_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Teachers can grade submissions"
  ON submissions FOR UPDATE
  USING (assignment_id IN (SELECT id FROM assignments WHERE course_id IN (SELECT id FROM courses WHERE teacher_id = (current_setting('app.user_id', true))::uuid)));
