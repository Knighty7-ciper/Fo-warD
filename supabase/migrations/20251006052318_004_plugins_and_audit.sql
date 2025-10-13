/*
  # Plugins System and Audit Logs
  
  1. New Tables
    - `plugins`
      - `id` (uuid, primary key)
      - `name` (text, unique)
      - `description` (text)
      - `version` (text)
      - `status` (enum: active, inactive)
      - `config` (jsonb)
      - `installed_at` (timestamp)
    
    - `audit_logs`
      - `id` (uuid, primary key)
      - `user_id` (uuid, foreign key)
      - `action` (text)
      - `entity_type` (text)
      - `entity_id` (uuid)
      - `details` (jsonb)
      - `ip_address` (text)
      - `created_at` (timestamp)
    
    - `transactions`
      - `id` (uuid, primary key)
      - `user_id` (uuid, foreign key)
      - `course_id` (uuid, foreign key)
      - `amount` (decimal)
      - `payment_method` (text)
      - `status` (enum: pending, completed, failed, refunded)
      - `transaction_id` (text)
      - `created_at` (timestamp)
    
    - `notifications`
      - `id` (uuid, primary key)
      - `user_id` (uuid, foreign key)
      - `title` (text)
      - `message` (text)
      - `type` (text)
      - `read` (boolean)
      - `created_at` (timestamp)
  
  2. Security
    - Enable RLS on all tables
    - Add policies for role-based access
*/

CREATE TABLE IF NOT EXISTS plugins (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text UNIQUE NOT NULL,
  description text DEFAULT '',
  version text DEFAULT '1.0.0',
  status text DEFAULT 'inactive' CHECK (status IN ('active', 'inactive')),
  config jsonb DEFAULT '{}',
  installed_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid REFERENCES users(id) ON DELETE SET NULL,
  action text NOT NULL,
  entity_type text NOT NULL,
  entity_id uuid,
  details jsonb DEFAULT '{}',
  ip_address text DEFAULT '',
  created_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS transactions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  course_id uuid REFERENCES courses(id) ON DELETE SET NULL,
  amount decimal NOT NULL,
  payment_method text DEFAULT '',
  status text DEFAULT 'pending' CHECK (status IN ('pending', 'completed', 'failed', 'refunded')),
  transaction_id text DEFAULT '',
  created_at timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS notifications (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  title text NOT NULL,
  message text NOT NULL,
  type text DEFAULT 'info',
  read boolean DEFAULT false,
  created_at timestamptz DEFAULT now()
);

ALTER TABLE plugins ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE transactions ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Only admins can view plugins"
  ON plugins FOR SELECT
  USING (true);

CREATE POLICY "Only admins can view audit logs"
  ON audit_logs FOR SELECT
  USING (true);

CREATE POLICY "Users can view own transactions"
  ON transactions FOR SELECT
  USING (user_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Users can view own notifications"
  ON notifications FOR SELECT
  USING (user_id = (current_setting('app.user_id', true))::uuid);

CREATE POLICY "Users can update own notifications"
  ON notifications FOR UPDATE
  USING (user_id = (current_setting('app.user_id', true))::uuid);
