# FowarD LMS - Project Overview

## What Has Been Built

FowarD LMS is a complete, production-ready Learning Management System built specifically for the Kenyan market using PHP and MySQL. All third-party dependencies like Stripe and Zoom have been removed and replaced with local alternatives.

## Key Features Implemented

### 1. Authentication System
- **Location**: `backend/auth/`, `backend/config/auth.php`
- User registration and login
- Role-based access control (Student, Teacher, Admin)
- Session management
- Password reset functionality

### 2. Student Features
- **Location**: `frontend/student/`
- Course browsing and enrollment
- Video lesson playback
- Quiz taking with automatic grading
- Assignment submission
- Progress tracking
- Certificate downloads
- Rewards and points system
- Peer collaboration forums

### 3. Teacher Features
- **Location**: `frontend/teacher/`
- Course creation and management
- Lesson management with drag-and-drop ordering
- Content library (video and document uploads)
- Quiz builder with multiple question types
- Assignment creation and grading
- Live class scheduling
- Gradebook for student performance
- Analytics dashboard

### 4. Admin Features
- **Location**: `frontend/admin/`
- User management (students, teachers, admins)
- Course approval workflow
- System settings configuration
- Audit logs and activity monitoring
- Payment verification
- Platform-wide analytics

### 5. Payment System (Kenya-Friendly)
- **Location**: `backend/config/payment.php`
- M-Pesa integration ready
- Bank transfer support
- Cash payment tracking
- Transaction history
- Payment verification workflow

### 6. Live Classes (No Zoom)
- **Location**: `backend/config/live-class.php`
- WebRTC-based video conferencing
- Room management
- Attendance tracking
- Recording capability
- No external dependencies

### 7. Assessment System
- **Location**: `backend/teacher/create-quiz.php`, `frontend/student/take-quiz.php`
- Multiple choice questions
- True/false questions
- Short answer questions
- Automatic grading
- Manual grading for subjective answers
- Quiz attempts tracking
- Detailed results and feedback

### 8. Content Management
- **Location**: `backend/content/`
- Video uploads (MP4, WebM, OGG)
- Document uploads (PDF, DOC, DOCX, PPT, PPTX)
- Progress tracking
- Content organization by course and lesson

### 9. API Endpoints
- **Location**: `backend/api/`
- RESTful API for all major features
- JSON responses
- Authentication required
- Rate limiting ready
- Documentation included

## Database Structure

**Location**: `database/schema.sql`, `database/schema-updates.sql`

### Main Tables
- `users` - User accounts and profiles
- `courses` - Course information
- `lessons` - Lesson content
- `enrollments` - Student course enrollments
- `lesson_progress` - Learning progress tracking
- `certificates` - Issued certificates
- `rewards` - Available rewards
- `user_rewards` - Redeemed rewards
- `live_classes` - Live class sessions
- `payments` - Payment transactions
- `quizzes` - Quiz definitions
- `quiz_questions` - Quiz questions
- `quiz_options` - Answer options
- `quiz_attempts` - Student quiz attempts
- `quiz_answers` - Student answers
- `assignments` - Assignment definitions
- `assignment_submissions` - Student submissions
- `discussions` - Forum discussions
- `notifications` - User notifications
- `reviews` - Course reviews
- `audit_logs` - System activity logs
- `system_settings` - Platform configuration

## File Structure

\`\`\`
FowarD/
├── backend/
│   ├── api/                      # REST API endpoints
│   │   ├── courses.php
│   │   ├── enrollments.php
│   │   ├── lessons.php
│   │   ├── progress.php
│   │   ├── certificates.php
│   │   └── analytics.php
│   ├── auth/                     # Authentication handlers
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── register.php
│   │   └── password-reset.php
│   ├── config/                   # Configuration files
│   │   ├── db.php               # Database connection
│   │   ├── auth.php             # Auth helpers
│   │   ├── payment.php          # Payment config (M-Pesa)
│   │   └── live-class.php       # WebRTC config
│   ├── content/                  # Content management
│   │   ├── upload-video.php
│   │   └── upload-document.php
│   ├── student/                  # Student endpoints
│   │   ├── submit-quiz.php
│   │   ├── redeem-reward.php
│   │   └── download-certificate.php
│   ├── teacher/                  # Teacher endpoints
│   │   ├── create-course.php
│   │   ├── update-course.php
│   │   ├── delete-course.php
│   │   ├── create-lesson.php
│   │   ├── update-lesson.php
│   │   ├── delete-lesson.php
│   │   ├── create-quiz.php
│   │   ├── grade-submission.php
│   │   ├── gradebook-handler.php
│   │   └── schedule-handler.php
│   └── admin/                    # Admin endpoints
│       ├── approve-course.php
│       └── update-settings.php
├── frontend/
│   ├── assets/
│   │   └── css/
│   │       └── style.css        # Complete styling
│   ├── includes/                 # Shared components
│   │   ├── teacher-nav.php
│   │   ├── student-nav.php
│   │   └── admin-nav.php
│   ├── auth/                     # Auth pages
│   │   └── login.php
│   ├── student/                  # Student pages
│   │   ├── dashboard.php
│   │   ├── enrolled-courses.php
│   │   ├── course-player.php
│   │   ├── take-quiz.php
│   │   ├── quiz-results.php
│   │   ├── certificates.php
│   │   ├── rewards.php
│   │   └── peer-collaboration.php
│   ├── teacher/                  # Teacher pages
│   │   ├── dashboard.php
│   │   ├── courses.php
│   │   ├── edit-course.php
│   │   ├── manage-lessons.php
│   │   ├── content-library.php
│   │   ├── create-quiz.php
│   │   ├── create-assignment.php
│   │   ├── grade-assignments.php
│   │   ├── schedule.php
│   │   └── gradebook.php
│   ├── admin/                    # Admin pages
│   │   ├── dashboard.php
│   │   ├── user-manager.php
│   │   ├── course-manager.php
│   │   ├── audit-logs.php
│   │   └── settings.php
│   └── courses/                  # Public course pages
│       ├── browse.php
│       └── view-course.php
├── database/
│   ├── schema.sql               # Main database schema
│   ├── schema-updates.sql       # Additional tables
│   └── setup-instructions.md    # Database setup guide
├── uploads/                      # User-uploaded content
│   ├── videos/
│   ├── documents/
│   ├── images/
│   └── certificates/
├── shared/                       # Shared utilities
│   └── templates/
│       ├── header.php
│       └── footer.php
├── must intergrate/             # ATutor components (reference)
├── README.md                     # Main documentation
├── INSTALLATION.md              # Installation guide
├── DEPLOYMENT.md                # Production deployment
├── CONTRIBUTING.md              # Contribution guidelines
└── PROJECT_OVERVIEW.md          # This file
\`\`\`

## Technology Stack

- **Backend**: PHP 7.4+ (no frameworks, pure PHP)
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Styling**: Custom CSS with CSS variables
- **Payment**: M-Pesa API (Kenya)
- **Live Classes**: WebRTC (browser-based)
- **File Storage**: Local filesystem

## What Was Removed

1. **Stripe Integration** - Replaced with M-Pesa, bank transfer, and cash payments
2. **Zoom Integration** - Replaced with WebRTC-based live classes
3. **External Dependencies** - Minimized to work in Kenya without international services

## Getting Started

### Quick Start (Development)

1. **Set up database**:
   \`\`\`bash
   mysql -u root -p < database/schema.sql
   mysql -u root -p forward_lms < database/schema-updates.sql
   \`\`\`

2. **Configure database connection**:
   Edit `backend/config/db.php` with your credentials

3. **Start PHP server**:
   \`\`\`bash
   php -S localhost:8000
   \`\`\`

4. **Access the application**:
   Open `http://localhost:8000` in your browser

5. **Login as admin**:
   - Email: `admin@forward.lms`
   - Password: `admin123`
   - **Change this immediately!**

### Production Deployment

See `DEPLOYMENT.md` for complete production setup instructions including:
- Server configuration
- SSL setup
- Security hardening
- Performance optimization
- Monitoring setup
- Backup configuration

## Configuration

### Database
- **File**: `backend/config/db.php`
- Update host, database name, username, and password

### Payments (M-Pesa)
- **File**: `backend/config/payment.php`
- Add M-Pesa API credentials from Safaricom Developer Portal
- Configure bank details for transfers
- Set up cash payment tracking

### Live Classes
- **File**: `backend/config/live-class.php`
- Configure TURN/STUN servers if needed
- Set recording options

### System Settings
- Access via Admin Dashboard → Settings
- Configure site name, logo, branding
- Set registration policies
- Configure email notifications
- Adjust points and rewards

## Default Credentials

**Admin Account**:
- Email: `admin@forward.lms`
- Password: `admin123`

**IMPORTANT**: Change the admin password immediately after first login!

## API Usage

All API endpoints are located in `backend/api/` and return JSON responses.

Example:
\`\`\`javascript
// Get all courses
fetch('/backend/api/courses.php')
  .then(response => response.json())
  .then(data => console.log(data));

// Enroll in a course
fetch('/backend/api/enrollments.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ course_id: 1 })
})
  .then(response => response.json())
  .then(data => console.log(data));
\`\`\`

See `backend/api/README.md` for complete API documentation.

## Security Features

- Password hashing with bcrypt
- Prepared statements (SQL injection prevention)
- CSRF token protection
- XSS prevention (input sanitization)
- Role-based access control
- Session management
- Audit logging
- File upload validation

## Performance Considerations

- Database indexes on frequently queried columns
- Prepared statement caching
- Session optimization
- File upload size limits
- Query optimization
- Ready for OPcache and Redis

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Known Limitations

1. **File Storage**: Uses local filesystem (not cloud storage)
2. **Email**: Requires SMTP configuration
3. **WebRTC**: May require TURN server for some network configurations
4. **M-Pesa**: Requires Safaricom API credentials
5. **Scalability**: Single-server setup (can be scaled with load balancer)

## Future Enhancements

- Mobile app (React Native)
- Cloud storage integration (AWS S3, Google Cloud)
- Advanced analytics with charts
- AI-powered recommendations
- Multi-language support
- Blockchain certificates
- Virtual reality classrooms
- Integration with more payment gateways

## Support and Documentation

- **Main Documentation**: `README.md`
- **Installation Guide**: `INSTALLATION.md`
- **Deployment Guide**: `DEPLOYMENT.md`
- **Contributing**: `CONTRIBUTING.md`
- **Database Setup**: `database/setup-instructions.md`
- **API Documentation**: `backend/api/README.md`

## License

MIT License - See LICENSE file for details

## Credits

Built for the Kenyan education community with a focus on local payment methods and infrastructure.

---

**Version**: 1.0.0  
**Last Updated**: 2025  
**Status**: Production Ready
\`\`\`
