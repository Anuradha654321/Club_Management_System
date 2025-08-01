# College Club Management System

A comprehensive web-based platform for managing college clubs, events, and student participation. This system provides a centralized solution for students, club leaders, and administrators to interact and manage club activities efficiently.

## 📋 Features

### 👥 For Students
- Browse and search all campus clubs
- Join clubs with different membership roles
- Enroll in club events and activities
- Track event participation and upload proof
- Rate clubs and provide feedback
- View and download event reports and materials

### 🎯 For Club Leaders
- Secure authentication and authorization
- Create and manage club events
- Approve/Reject event participation requests
- Manage club members and executive roles
- Upload and manage meeting minutes
- Generate and manage event reports
- Handle club media and resources

### 👨‍💼 For Administrators
- Oversee all clubs and activities
- Manage club registrations
- Generate comprehensive reports
- Monitor system usage and statistics
- Handle user roles and permissions

## 🛠 Technology Stack
- **Frontend**: HTML5, CSS3, JavaScript, jQuery
- **Backend**: PHP 7.4+
- **Database**: MySQL
- **Web Server**: Apache (XAMPP/WAMP)
- **UI Framework**: Bootstrap 5
- **Additional Libraries**:
  - PHPMailer (for email functionality)
  - FPDF (for PDF generation)

## 🚀 Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (for dependency management)

### Setup Instructions
1. Clone the repository to your web server's root directory:
   ```bash
   git clone [repository-url] clubhub
   ```

2. Import the database:
   - Create a new MySQL database
   - Import the SQL file from `database/club_db.sql`

3. Configure database connection:
   - Update `includes/db_connection.php` with your database credentials
   - Configure SMTP settings in `includes/functions.php` for email functionality

4. Set file permissions:
   ```bash
   chmod 755 -R uploads/
   chmod 644 includes/*.php
   ```

5. Access the application:
   - Open `http://localhost/clubhub` in your web browser
   - Login with admin credentials (if available) or register a new account

## 🔒 Security Features
- Password hashing using PHP's `password_hash()`
- Prepared statements to prevent SQL injection
- Input validation and sanitization
- CSRF protection
- Secure file upload validation
- Session management and timeout
- Role-based access control

## 📁 Project Structure
```
clubhub/
├── admin/                  # Admin-specific functionality
├── assets/                # Static assets (CSS, JS, images)
│   ├── css/               # Stylesheets
│   └── js/                # JavaScript files
├── clubs/                 # Club-related files
├── database/              # Database schema and migrations
├── includes/              # Core PHP includes
│   ├── auth.php           # Authentication functions
│   ├── db.php             # Database connection
│   └── functions.php      # Utility functions
├── uploads/               # User-uploaded files
│   ├── reports/           # Event reports
│   ├── minutes/           # Meeting minutes
│   └── proofs/            # Participation proofs
└── *.php                  # Main application files
```

## 📄 Documentation

### Database Schema
- `users`: Stores user accounts and authentication details
- `clubs`: Contains club information
- `events`: Manages club events
- `memberships`: Tracks club memberships
- `participations`: Manages event participation
- `reports`: Stores event reports and documents

### API Endpoints
- Authentication: `/login.php`, `/register.php`
- Club Management: `/clubs.php`, `/create_club.php`, `/edit_club.php`
- Event Management: `/events.php`, `/create_event.php`, `/edit_event.php`
- User Management: `/register.php`, `/profile.php`

## 🤝 Contributing
1. Fork the repository
2. Create a new branch for your feature
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## 📝 License
This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 📧 Contact
For support or queries, please contact the development team.

## 📊 Project Status
Active Development - Version 1.0.0
