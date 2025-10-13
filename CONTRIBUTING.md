# Contributing to FowarD LMS

Thank you for considering contributing to FowarD LMS! This document provides guidelines for contributing to the project.

## Code of Conduct

- Be respectful and inclusive
- Welcome newcomers
- Focus on constructive feedback
- Maintain professionalism

## How to Contribute

### Reporting Bugs

1. Check if the bug has already been reported in [Issues](https://github.com/yourusername/forward-lms/issues)
2. If not, create a new issue with:
   - Clear title and description
   - Steps to reproduce
   - Expected vs actual behavior
   - Screenshots if applicable
   - Environment details (PHP version, OS, browser)

### Suggesting Features

1. Check existing feature requests
2. Create a new issue with:
   - Clear use case
   - Expected behavior
   - Why this feature would be useful
   - Possible implementation approach

### Pull Requests

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes
4. Write/update tests if applicable
5. Update documentation
6. Commit with clear messages: `git commit -m 'Add amazing feature'`
7. Push to your fork: `git push origin feature/amazing-feature`
8. Open a Pull Request

### Coding Standards

#### PHP
- Follow PSR-12 coding standard
- Use meaningful variable and function names
- Add comments for complex logic
- Use prepared statements for database queries
- Validate and sanitize all user input

#### JavaScript
- Use ES6+ syntax
- Use meaningful variable names
- Add JSDoc comments for functions
- Handle errors gracefully

#### CSS
- Use BEM naming convention
- Keep selectors specific but not overly complex
- Use CSS variables for theming
- Mobile-first responsive design

### Testing

- Test your changes thoroughly
- Include unit tests for new features
- Test on multiple browsers
- Test on mobile devices

### Documentation

- Update README.md if needed
- Add inline code comments
- Update API documentation
- Include examples for new features

## Development Setup

\`\`\`bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/forward-lms.git
cd forward-lms

# Set up database
mysql -u root -p < database/schema.sql

# Configure
cp backend/config/db.php.example backend/config/db.php
# Edit with your credentials

# Start development server
php -S localhost:8000
\`\`\`

## Project Structure

\`\`\`
forward-lms/
├── backend/          # Server-side logic
│   ├── api/         # REST API endpoints
│   ├── auth/        # Authentication
│   ├── config/      # Configuration
│   └── ...
├── frontend/        # Client-side code
│   ├── assets/      # CSS, JS, images
│   ├── student/     # Student pages
│   ├── teacher/     # Teacher pages
│   └── admin/       # Admin pages
├── database/        # Database schemas
└── docs/           # Documentation
\`\`\`

## Commit Message Guidelines

Format: `type(scope): subject`

Types:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation
- `style`: Formatting
- `refactor`: Code restructuring
- `test`: Adding tests
- `chore`: Maintenance

Examples:
\`\`\`
feat(quiz): add multiple choice questions
fix(auth): resolve session timeout issue
docs(api): update endpoint documentation
\`\`\`

## Review Process

1. Maintainers review PRs within 3-5 business days
2. Address feedback and requested changes
3. Once approved, PR will be merged
4. Your contribution will be credited

## Questions?

- Open a discussion in GitHub Discussions
- Email: dev@forward.lms
- Join our Slack channel

Thank you for contributing! 🎉
