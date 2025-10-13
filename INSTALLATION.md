# FowarD LMS - Detailed Installation Guide

## System Requirements

### Minimum Requirements
- **OS**: Ubuntu 20.04 LTS, CentOS 8, or Windows Server 2019
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher (or MariaDB 10.3+)
- **RAM**: 2GB minimum, 4GB recommended
- **Storage**: 10GB minimum, 50GB+ recommended for content
- **Web Server**: Apache 2.4+ or Nginx 1.18+

### PHP Extensions Required
- pdo_mysql
- mbstring
- json
- curl
- gd or imagick (for image processing)
- zip
- xml

## Installation Methods

### Method 1: Manual Installation (Recommended)

#### 1. Install LAMP Stack (Ubuntu/Debian)
\`\`\`bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Apache
sudo apt install apache2 -y

# Install MySQL
sudo apt install mysql-server -y
sudo mysql_secure_installation

# Install PHP and extensions
sudo apt install php7.4 php7.4-mysql php7.4-mbstring php7.4-json php7.4-curl php7.4-gd php7.4-zip php7.4-xml -y

# Enable Apache modules
sudo a2enmod rewrite
sudo systemctl restart apache2
\`\`\`

#### 2. Download and Extract
\`\`\`bash
# Navigate to web root
cd /var/www/html

# Clone or download the project
git clone https://github.com/yourusername/forward-lms.git
cd forward-lms

# Set permissions
sudo chown -R www-data:www-data /var/www/html/forward-lms
sudo chmod -R 755 /var/www/html/forward-lms
\`\`\`

#### 3. Create Database
\`\`\`bash
# Login to MySQL
sudo mysql -u root -p

# Create database and user
CREATE DATABASE forward_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'forward_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON forward_lms.* TO 'forward_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import schema
mysql -u forward_user -p forward_lms < database/schema.sql
mysql -u forward_user -p forward_lms < database/schema-updates.sql
\`\`\`

#### 4. Configure Application
\`\`\`bash
# Copy and edit database config
cp backend/config/db.php.example backend/config/db.php
nano backend/config/db.php

# Update with your database credentials:
# $host = 'localhost';
# $dbname = 'forward_lms';
# $username = 'forward_user';
# $password = 'strong_password_here';
\`\`\`

#### 5. Create Upload Directories
\`\`\`bash
mkdir -p uploads/{videos,documents,images,certificates}
chmod -R 755 uploads/
chown -R www-data:www-data uploads/
\`\`\`

#### 6. Configure Apache Virtual Host
\`\`\`bash
sudo nano /etc/apache2/sites-available/forward-lms.conf
\`\`\`

Add:
\`\`\`apache
<VirtualHost *:80>
    ServerName forward-lms.local
    ServerAlias www.forward-lms.local
    DocumentRoot /var/www/html/forward-lms
    
    <Directory /var/www/html/forward-lms>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/forward-lms-error.log
    CustomLog ${APACHE_LOG_DIR}/forward-lms-access.log combined
    
    # PHP settings
    php_value upload_max_filesize 500M
    php_value post_max_size 500M
    php_value max_execution_time 300
    php_value memory_limit 256M
</VirtualHost>
\`\`\`

Enable site:
\`\`\`bash
sudo a2ensite forward-lms.conf
sudo systemctl reload apache2
\`\`\`

#### 7. Update Hosts File (for local development)
\`\`\`bash
sudo nano /etc/hosts
\`\`\`

Add:
\`\`\`
127.0.0.1   forward-lms.local
\`\`\`

#### 8. Test Installation
Open browser and navigate to: `http://forward-lms.local`

Default login:
- Email: `admin@forward.lms`
- Password: `admin123`

**IMPORTANT**: Change admin password immediately!

### Method 2: Docker Installation

\`\`\`bash
# Clone repository
git clone https://github.com/yourusername/forward-lms.git
cd forward-lms

# Build and run with Docker Compose
docker-compose up -d

# Import database
docker-compose exec db mysql -u root -p forward_lms < database/schema.sql
\`\`\`

Access at: `http://localhost:8080`

### Method 3: Shared Hosting

1. Download ZIP file from releases
2. Extract to public_html or www directory
3. Create MySQL database via cPanel
4. Import `database/schema.sql` via phpMyAdmin
5. Edit `backend/config/db.php` with database credentials
6. Set folder permissions via FTP (755 for directories, 644 for files)
7. Access via your domain

## Post-Installation Steps

### 1. Security Hardening
\`\`\`bash
# Change default admin password
# Remove or rename database setup files
rm -f database/schema.sql database/schema-updates.sql

# Disable directory listing
echo "Options -Indexes" > .htaccess

# Set secure permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 600 backend/config/db.php
\`\`\`

### 2. Configure Cron Jobs
\`\`\`bash
crontab -e
\`\`\`

Add:
\`\`\`cron
# Clean up expired sessions (daily at 2 AM)
0 2 * * * php /var/www/html/forward-lms/backend/cron/cleanup-sessions.php

# Send email notifications (every 15 minutes)
*/15 * * * * php /var/www/html/forward-lms/backend/cron/send-notifications.php

# Generate reports (daily at 3 AM)
0 3 * * * php /var/www/html/forward-lms/backend/cron/generate-reports.php
\`\`\`

### 3. Configure Email (Optional)
Edit `backend/config/email.php`:
\`\`\`php
$smtp_host = 'smtp.gmail.com';
$smtp_port = 587;
$smtp_user = 'your-email@gmail.com';
$smtp_pass = 'your-app-password';
\`\`\`

### 4. Configure M-Pesa (Kenya)
1. Register at [Safaricom Developer Portal](https://developer.safaricom.co.ke/)
2. Get API credentials
3. Edit `backend/config/payment.php`
4. Test with sandbox first

### 5. SSL Certificate (Production)
\`\`\`bash
# Install Certbot
sudo apt install certbot python3-certbot-apache -y

# Get certificate
sudo certbot --apache -d forward-lms.com -d www.forward-lms.com

# Auto-renewal
sudo certbot renew --dry-run
\`\`\`

## Verification Checklist

- [ ] Database connection successful
- [ ] Admin login works
- [ ] File uploads work (test with small image)
- [ ] Student registration works
- [ ] Teacher can create course
- [ ] Email notifications work (if configured)
- [ ] Payment integration works (if configured)
- [ ] SSL certificate installed (production)
- [ ] Backups configured
- [ ] Cron jobs running

## Troubleshooting

### "Database connection failed"
- Check MySQL service: `sudo systemctl status mysql`
- Verify credentials in `backend/config/db.php`
- Check MySQL user permissions

### "Permission denied" errors
\`\`\`bash
sudo chown -R www-data:www-data /var/www/html/forward-lms
sudo chmod -R 755 /var/www/html/forward-lms
\`\`\`

### File upload fails
- Check PHP settings: `php -i | grep upload`
- Increase limits in `/etc/php/7.4/apache2/php.ini`
- Restart Apache: `sudo systemctl restart apache2`

### 404 errors on pages
- Enable mod_rewrite: `sudo a2enmod rewrite`
- Check .htaccess file exists
- Verify AllowOverride All in Apache config

## Getting Help

- Documentation: [docs.forward.lms](https://docs.forward.lms)
- Community Forum: [forum.forward.lms](https://forum.forward.lms)
- Email Support: support@forward.lms
- GitHub Issues: [github.com/yourusername/forward-lms/issues](https://github.com/yourusername/forward-lms/issues)
\`\`\`
