# Database Setup Instructions

## Prerequisites
- MySQL 5.7+ or MariaDB 10.3+
- PHP 7.4+ with PDO MySQL extension

## Setup Steps

### 1. Create Database
\`\`\`bash
mysql -u root -p < database/schema.sql
\`\`\`

Or manually:
\`\`\`sql
mysql -u root -p
source database/schema.sql;
\`\`\`

### 2. Configure Database Connection
Edit `backend/config/db.php` and update the following:

\`\`\`php
$host = 'localhost';        // Your database host
$dbname = 'forward_lms';    // Database name
$username = 'root';         // Your MySQL username
$password = '';             // Your MySQL password
\`\`\`

Or use environment variables:
- `DB_HOST` - Database host
- `DB_NAME` - Database name
- `DB_USER` - Database username
- `DB_PASSWORD` - Database password

### 3. Run Additional Schema Updates
\`\`\`bash
mysql -u root -p forward_lms < database/schema-updates.sql
\`\`\`

### 4. Verify Installation
- Default admin credentials:
  - Email: admin@forward.lms
  - Password: admin123

**Important:** Change the admin password immediately after first login!

## Troubleshooting

### Connection Issues
- Verify MySQL service is running
- Check database credentials
- Ensure PHP PDO MySQL extension is installed: `php -m | grep pdo_mysql`

### Permission Issues
\`\`\`sql
GRANT ALL PRIVILEGES ON forward_lms.* TO 'your_user'@'localhost';
FLUSH PRIVILEGES;
\`\`\`

### Import Errors
- Check MySQL version compatibility
- Ensure sufficient disk space
- Review error logs in `/var/log/mysql/error.log`
