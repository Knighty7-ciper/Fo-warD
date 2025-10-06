# Forward LMS

Community-driven online learning platform designed to replicate and enhance the flexibility of platforms like Gnomio, with added features for real-world engagement, certification, and immersive learning.

## Features

### For Teachers
- Create and manage courses with drag-and-drop interface
- Schedule live classes with integrated calendar
- Grade assignments and issue certificates
- Track student progress and analytics

### For Students
- Browse and enroll in courses
- Track learning progress
- Earn blockchain-backed certificates (NFT simulation)
- Participate in discussion forums
- Redeem reward points for perks

### For Administrators
- Manage users and roles
- Approve/revoke courses and certificates
- Monitor platform activity via audit logs
- Install and manage plugins

## Unique Features

- **Metaverse Campus**: 3D virtual space for immersive learning
- **Blockchain Certificates**: Verifiable, portable credentials
- **Live Classes**: WebRTC-powered real-time video/audio
- **Reward System**: Points for engagement and achievement
- **Plugin Architecture**: Extensible with third-party integrations

## Tech Stack

- **Frontend**: HTML5, CSS3, JavaScript (ES6+), Three.js, WebRTC
- **Backend**: PHP 7.4+
- **Database**: Supabase (PostgreSQL)
- **Hosting**: XAMPP (Local) / Any PHP hosting
- **Libraries**: Chart.js, FPDF, Three.js

## Installation

See `xampp-setup-guide.txt` for detailed setup instructions.

### Quick Start

1. Install XAMPP
2. Copy project to `htdocs/forward`
3. Configure Supabase credentials in `backend/config/db.php`
4. Run database migrations
5. Access at `http://forward.local`

## Project Structure

```
forward/
├── frontend/          # User-facing pages
├── backend/          # Server-side logic
├── database/         # Migrations and seeds
├── shared/           # Utilities and templates
├── configs/          # Server configurations
└── docs/            # Documentation
```

## Default Credentials

**Admin**: admin@forward.local / admin123

## Security

- Role-based access control (RBAC)
- Session management
- SQL injection protection
- XSS prevention
- CSRF tokens

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## License

MIT License - See LICENSE file for details

## Support

For issues and questions, check the documentation in the `docs/` folder.

---

Forward LMS v1.0.0 - Empowering Educators, Engaging Students
