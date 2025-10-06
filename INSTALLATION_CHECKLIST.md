# Forward LMS - Installation Checklist

## ✅ Pre-Installation Requirements

- [ ] XAMPP installed (with Apache and PHP 7.4+)
- [ ] PostgreSQL extension enabled in PHP (pdo_pgsql)
- [ ] Text editor installed (optional, for configuration)
- [ ] Administrative access to modify hosts file

## ✅ Step 1: File Setup

- [ ] Copy entire `forward/` folder to XAMPP htdocs directory
  - Windows: `C:\xampp\htdocs\forward\`
  - Linux: `/opt/lampp/htdocs/forward/`
  - Mac: `/Applications/XAMPP/htdocs/forward/`

## ✅ Step 2: Hosts Configuration

- [ ] Open hosts file with administrator/root privileges
  - Windows: `C:\Windows\System32\drivers\etc\hosts`
  - Linux/Mac: `/etc/hosts`
- [ ] Add this line: `127.0.0.1    forward.local`
- [ ] Save and close the file

## ✅ Step 3: Apache Virtual Host

- [ ] Open Apache vhosts configuration
  - Path: `xampp/apache/conf/extra/httpd-vhosts.conf`
- [ ] Copy contents from `xampp-vhosts.conf` file
- [ ] Update DocumentRoot path if needed
- [ ] Save the file

## ✅ Step 4: PHP Configuration (Optional but Recommended)

- [ ] Open `xampp/php/php.ini`
- [ ] Enable these extensions (remove semicolon if present):
  - [ ] `extension=pdo_pgsql`
  - [ ] `extension=pgsql`
  - [ ] `extension=curl`
  - [ ] `extension=gd`
  - [ ] `extension=mbstring`
  - [ ] `extension=openssl`
- [ ] Set recommended values (or use provided `php.ini`):
  - [ ] `upload_max_filesize = 50M`
  - [ ] `post_max_size = 50M`
  - [ ] `max_execution_time = 300`
  - [ ] `memory_limit = 256M`
- [ ] Save the file

## ✅ Step 5: Database Verification

The database is already set up! Verify:
- [ ] Migrations have been applied to Supabase (already done ✓)
- [ ] Connection details are correct in `backend/config/db.php`
- [ ] Tables exist in your Supabase dashboard

## ✅ Step 6: File Permissions (Linux/Mac only)

Create required directories and set permissions:
```bash
cd /path/to/forward
mkdir -p backend/logs
mkdir -p frontend/assets/uploads
mkdir -p frontend/assets/certificates
chmod 755 backend/logs
chmod 755 frontend/assets/uploads
chmod 755 frontend/assets/certificates
```

## ✅ Step 7: Start Services

- [ ] Start Apache in XAMPP Control Panel
- [ ] Verify Apache is running (green indicator)
- [ ] Check for any error messages in XAMPP logs

## ✅ Step 8: Test Installation

- [ ] Open browser
- [ ] Navigate to: `http://forward.local`
- [ ] You should see the Forward LMS homepage
- [ ] If you see an error, check:
  - [ ] Apache is running
  - [ ] Virtual host is configured correctly
  - [ ] Hosts file has the entry
  - [ ] No other service is using port 80

## ✅ Step 9: Test Login

Try logging in with seed accounts (password: `password123`):
- [ ] Student: `student1@forward.local`
- [ ] Teacher: `teacher1@forward.local`
- [ ] Admin: `admin@forward.local`

## ✅ Step 10: Verify Functionality

Test core features:
- [ ] Registration works (create a test account)
- [ ] Login/logout works
- [ ] Student dashboard loads
- [ ] Teacher dashboard loads
- [ ] About/Contact pages load

## 🔧 Troubleshooting Guide

### Issue: "Cannot connect to database"
**Solution:**
1. Verify PostgreSQL extension is enabled in PHP
2. Check Supabase credentials in `backend/config/db.php`
3. Test connection from command line: `php -r "new PDO('pgsql:host=db.dcqcwzxioeqvreqlsakl.supabase.co;port=5432;dbname=postgres', 'postgres', 'your_password');"`

### Issue: "404 Not Found"
**Solution:**
1. Verify `.htaccess` file exists in project root
2. Check Apache has `mod_rewrite` enabled
3. Verify virtual host DocumentRoot is correct
4. Restart Apache

### Issue: "Permission denied" errors
**Solution:**
1. Check file permissions on `backend/logs/` directory
2. Ensure Apache user has write access
3. On Linux: `sudo chown -R www-data:www-data /path/to/forward`

### Issue: "Session not working"
**Solution:**
1. Check session save path is writable
2. Verify session settings in `php.ini`
3. Check PHP error logs

### Issue: forward.local doesn't resolve
**Solution:**
1. Verify hosts file entry is correct
2. Try `ping forward.local` in terminal
3. Clear DNS cache:
   - Windows: `ipconfig /flushdns`
   - Mac: `sudo dscacheutil -flushcache`
   - Linux: `sudo systemctl restart nscd`

### Issue: Virtual host not working
**Solution:**
1. Verify `httpd-vhosts.conf` is included in main Apache config
2. Check for syntax errors: `httpd -t` or `apache2ctl -t`
3. Ensure virtual host is enabled
4. Restart Apache

### Issue: Upload not working
**Solution:**
1. Check `upload_max_filesize` and `post_max_size` in php.ini
2. Verify upload directory exists and is writable
3. Check `.htaccess` upload limits

## 📝 Post-Installation Tasks

### Security (Production)
- [ ] Change default admin password
- [ ] Update all API keys in `backend/config/third-party/`
- [ ] Set `display_errors = Off` in php.ini
- [ ] Enable HTTPS with SSL certificate
- [ ] Set up firewall rules
- [ ] Configure regular database backups

### Optional Enhancements
- [ ] Configure email settings for notifications
- [ ] Set up CDN for static assets
- [ ] Enable caching (Redis/Memcached)
- [ ] Configure monitoring tools
- [ ] Set up automated backups

### Testing
- [ ] Create test course as teacher
- [ ] Enroll in course as student
- [ ] Test certificate generation
- [ ] Test file uploads
- [ ] Test discussion forums
- [ ] Test reward system

## 🎉 Installation Complete!

If all checkboxes are marked, your Forward LMS installation is complete!

**Next Steps:**
1. Read the documentation in `docs/` folder
2. Customize the platform for your needs
3. Add your own branding/logo
4. Create your first real course
5. Invite users to join

**Support:**
- Check `xampp-setup-guide.txt` for detailed instructions
- Review `docs/architecture.md` for system architecture
- See `docs/api-docs.md` for API reference
- Consult `PROJECT_SUMMARY.md` for feature overview

---

**Forward LMS v1.0.0**
Empowering Educators, Engaging Students
