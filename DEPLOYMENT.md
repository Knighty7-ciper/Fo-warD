# FowarD LMS - Production Deployment Guide

This guide covers deploying FowarD LMS to a production environment in Kenya.

## Pre-Deployment Checklist

- [ ] Domain name registered and DNS configured
- [ ] SSL certificate obtained (Let's Encrypt recommended)
- [ ] Server provisioned (VPS or dedicated server)
- [ ] Database backup strategy in place
- [ ] M-Pesa API credentials obtained (if using payments)
- [ ] Email service configured (SMTP)
- [ ] Monitoring tools set up

## Server Requirements (Production)

### Recommended Specifications
- **CPU**: 4 cores minimum
- **RAM**: 8GB minimum, 16GB recommended
- **Storage**: 100GB SSD minimum
- **Bandwidth**: Unmetered or 5TB+/month
- **OS**: Ubuntu 22.04 LTS (recommended)

### Software Stack
- **Web Server**: Nginx 1.18+ (recommended) or Apache 2.4+
- **PHP**: 8.0+ with OPcache enabled
- **Database**: MySQL 8.0+ or MariaDB 10.6+
- **Cache**: Redis 6.0+ (optional but recommended)
- **SSL**: Let's Encrypt (free)

## Step-by-Step Deployment

### 1. Server Setup

\`\`\`bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y nginx mysql-server php8.1-fpm php8.1-mysql php8.1-mbstring \
    php8.1-json php8.1-curl php8.1-gd php8.1-zip php8.1-xml php8.1-opcache \
    redis-server certbot python3-certbot-nginx git unzip

# Secure MySQL
sudo mysql_secure_installation
\`\`\`

### 2. Create Database

\`\`\`bash
# Login to MySQL
sudo mysql -u root -p

# Create production database
CREATE DATABASE forward_lms_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'forward_prod'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON forward_lms_prod.* TO 'forward_prod'@'localhost';
FLUSH PRIVILEGES;
EXIT;
\`\`\`

### 3. Deploy Application

\`\`\`bash
# Create application directory
sudo mkdir -p /var/www/forward-lms
cd /var/www/forward-lms

# Clone repository (or upload files)
sudo git clone https://github.com/yourusername/forward-lms.git .

# Set ownership
sudo chown -R www-data:www-data /var/www/forward-lms

# Set permissions
sudo find /var/www/forward-lms -type d -exec chmod 755 {} \;
sudo find /var/www/forward-lms -type f -exec chmod 644 {} \;

# Secure sensitive files
sudo chmod 600 /var/www/forward-lms/backend/config/db.php
sudo chmod 600 /var/www/forward-lms/backend/config/payment.php

# Create upload directories
sudo mkdir -p /var/www/forward-lms/uploads/{videos,documents,images,certificates}
sudo chown -R www-data:www-data /var/www/forward-lms/uploads
sudo chmod -R 755 /var/www/forward-lms/uploads
\`\`\`

### 4. Configure Database Connection

\`\`\`bash
sudo nano /var/www/forward-lms/backend/config/db.php
\`\`\`

Update with production credentials:
\`\`\`php
<?php
$host = 'localhost';
$dbname = 'forward_lms_prod';
$username = 'forward_prod';
$password = 'STRONG_PASSWORD_HERE';
// ... rest of file
?>
\`\`\`

### 5. Import Database Schema

\`\`\`bash
mysql -u forward_prod -p forward_lms_prod < /var/www/forward-lms/database/schema.sql
mysql -u forward_prod -p forward_lms_prod < /var/www/forward-lms/database/schema-updates.sql
\`\`\`

### 6. Configure Nginx

\`\`\`bash
sudo nano /etc/nginx/sites-available/forward-lms
\`\`\`

Add configuration:
\`\`\`nginx
server {
    listen 80;
    server_name forward-lms.co.ke www.forward-lms.co.ke;
    root /var/www/forward-lms;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # Logging
    access_log /var/log/nginx/forward-lms-access.log;
    error_log /var/log/nginx/forward-lms-error.log;

    # Max upload size
    client_max_body_size 500M;

    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Increase timeouts for large uploads
        fastcgi_read_timeout 300;
    }

    # Deny access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~ /backend/config/ {
        deny all;
    }

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
\`\`\`

Enable site:
\`\`\`bash
sudo ln -s /etc/nginx/sites-available/forward-lms /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
\`\`\`

### 7. Configure PHP for Production

\`\`\`bash
sudo nano /etc/php/8.1/fpm/php.ini
\`\`\`

Update settings:
\`\`\`ini
; Performance
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

; File uploads
upload_max_filesize = 500M
post_max_size = 500M

; Security
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; OPcache (performance)
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
\`\`\`

Restart PHP-FPM:
\`\`\`bash
sudo systemctl restart php8.1-fpm
\`\`\`

### 8. Install SSL Certificate

\`\`\`bash
# Get certificate
sudo certbot --nginx -d forward-lms.co.ke -d www.forward-lms.co.ke

# Test auto-renewal
sudo certbot renew --dry-run
\`\`\`

### 9. Configure Firewall

\`\`\`bash
# Enable UFW
sudo ufw allow 'Nginx Full'
sudo ufw allow OpenSSH
sudo ufw enable
sudo ufw status
\`\`\`

### 10. Set Up Cron Jobs

\`\`\`bash
sudo crontab -e -u www-data
\`\`\`

Add:
\`\`\`cron
# Clean up expired sessions (daily at 2 AM)
0 2 * * * /usr/bin/php /var/www/forward-lms/backend/cron/cleanup-sessions.php

# Send email notifications (every 15 minutes)
*/15 * * * * /usr/bin/php /var/www/forward-lms/backend/cron/send-notifications.php

# Generate reports (daily at 3 AM)
0 3 * * * /usr/bin/php /var/www/forward-lms/backend/cron/generate-reports.php

# Database backup (daily at 1 AM)
0 1 * * * /usr/bin/mysqldump -u forward_prod -pPASSWORD forward_lms_prod | gzip > /var/backups/forward-lms-$(date +\%Y\%m\%d).sql.gz
