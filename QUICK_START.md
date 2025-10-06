# Forward LMS - Quick Start Guide

## 🚀 Get Started in 5 Minutes

### Step 1: Install XAMPP
Download and install XAMPP from https://www.apachefriends.org/

### Step 2: Copy Files
Copy this `forward` folder to:
- **Windows**: `C:\xampp\htdocs\forward\`
- **Linux**: `/opt/lampp/htdocs/forward/`
- **Mac**: `/Applications/XAMPP/htdocs/forward/`

### Step 3: Add Hosts Entry
Add this line to your hosts file:
```
127.0.0.1    forward.local
```

**Hosts file location:**
- Windows: `C:\Windows\System32\drivers\etc\hosts`
- Linux/Mac: `/etc/hosts`

### Step 4: Configure Apache
Add to `xampp/apache/conf/extra/httpd-vhosts.conf`:
```apache
<VirtualHost *:80>
    ServerName forward.local
    DocumentRoot "C:/xampp/htdocs/forward"
    <Directory "C:/xampp/htdocs/forward">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Step 5: Enable PHP Extensions
Open `xampp/php/php.ini` and enable:
```ini
extension=pdo_pgsql
extension=pgsql
```

### Step 6: Start Apache
1. Open XAMPP Control Panel
2. Click "Start" next to Apache
3. Wait for green indicator

### Step 7: Access the Site
Open your browser and go to: **http://forward.local**

## 🎓 Test Accounts

Login with these accounts (password: `password123`):

| Role | Email | Password |
|------|-------|----------|
| Student | student1@forward.local | password123 |
| Teacher | teacher1@forward.local | password123 |
| Admin | admin@forward.local | password123 |

## ✨ What to Try

### As a Student:
1. Browse available courses
2. Enroll in a course
3. Track your progress
4. View your certificates
5. Check reward points

### As a Teacher:
1. Create a new course
2. Add lessons and materials
3. Schedule live classes
4. Grade assignments
5. Issue certificates to students

### As an Admin:
1. Manage users
2. Approve courses
3. View audit logs
4. Configure plugins
5. Monitor system activity

## 🔧 Troubleshooting

**Can't access forward.local?**
- Check hosts file entry
- Verify Apache is running
- Clear browser cache

**Database connection error?**
- Verify PostgreSQL extension is enabled
- Check credentials in `backend/config/db.php`

**404 errors?**
- Ensure `.htaccess` exists
- Check Apache `mod_rewrite` is enabled
- Verify virtual host configuration

## 📚 Next Steps

1. **Read Documentation**: Check `docs/` folder for detailed guides
2. **Customize**: Add your logo and branding
3. **Create Content**: Build your first course
4. **Invite Users**: Share registration link with your students
5. **Explore Features**: Test blockchain certificates, live classes, etc.

## 🆘 Need Help?

- **Setup Guide**: See `xampp-setup-guide.txt` for detailed instructions
- **Installation**: Check `INSTALLATION_CHECKLIST.md` for step-by-step
- **Architecture**: Read `docs/architecture.md` to understand the system
- **API Reference**: See `docs/api-docs.md` for API documentation
- **Features**: Review `PROJECT_SUMMARY.md` for complete feature list

## 🎉 You're Ready!

Your Forward LMS platform is now running. Start creating courses and engaging with your learning community!

---

**Forward LMS v1.0.0** - Community-Driven Learning Platform
