# Club Hub - Enhanced Multi-Club Management System

A complete, production-ready club management system with authentication, role-based permissions, multi-club support, and super owner dashboard.

## 🚀 Features

### ✅ Authentication & User Management
- User registration and login with secure password hashing (bcrypt)
- Session management
- Multi-club support (users can join multiple clubs)
- System owner (super admin) functionality

### ✅ Super Owner Dashboard
- System-wide view of all clubs and users
- Approve/reject club creation requests
- Manage users (activate/deactivate)
- Access any club's dashboard
- View system statistics

### ✅ Club Creation System
- Users can request to create new clubs
- Requires super owner approval
- Clubs get unique access codes upon approval
- Creator automatically becomes club president

### ✅ Club Join System
- Users join clubs using access codes
- Join requests require officer approval
- Officers can assign roles upon approval

### ✅ Advanced Role Management
- Create unlimited custom roles per club
- 19 granular permissions per role
- Real-time role preview showing UI visibility
- Assign/reassign roles to members
- Cannot delete roles with active members

### ✅ Permission System
19 granular permissions:
- **Announcements**: view, create, edit, delete
- **Events**: view, create, edit, delete
- **Members**: view, manage, remove
- **Attendance**: view, export
- **Stats**: view statistics
- **Settings**: modify club settings
- **Roles**: create, assign, manage
- **Chat**: access chat rooms

### ✅ Core Club Features
- **Dashboard** - Real-time stats and overview
- **Announcements** - Create and manage club announcements
- **Events** - Schedule and track events
- **Members** - Member directory and management
- **Sign-In System** - Daily attendance tracking with scanner support
- **Attendance Reports** - View attendance statistics
- **Theme Customization** - Light/Dark mode
- **Fully Responsive** - Works on all devices

## 📁 File Structure

```
club-hub-enhanced/
├── index.html              # Main application
├── login.html              # Login page
├── register.html           # Registration page
├── super-owner-dashboard.html  # Super owner dashboard
├── styles.css              # Modern minimalist styles
├── app.js                  # Frontend JavaScript
├── database/
│   ├── db.php             # Database connection
│   └── schema.sql         # Complete database schema
└── api/                    # API endpoints
    ├── auth.php           # Authentication
    ├── stats.php          # Dashboard statistics
    ├── announcements.php  # Announcements management
    ├── events.php         # Events management
    ├── members.php        # Members management
    ├── signin.php         # Daily sign-in
    ├── attendance.php     # Attendance reports
    ├── club_requests.php  # Club creation requests
    ├── club_join.php      # Join club requests
    ├── roles.php          # Role management
    ├── super_owner.php    # Super owner operations
    └── user_preferences.php  # User settings
```

## 🔧 Installation

### 1. Database Setup

```bash
# Create database
mysql -u root -p

CREATE DATABASE club_hub;
USE club_hub;

# Import schema
mysql -u root -p club_hub < database/schema.sql
```

### 2. Configure Database Connection

Edit `database/db.php` and update the credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'club_hub');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 3. Web Server Setup

#### Apache

```apache
<VirtualHost *:80>
    DocumentRoot "/path/to/club-hub-enhanced"
    ServerName clubhub.local
    
    <Directory "/path/to/club-hub-enhanced">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name clubhub.local;
    root /path/to/club-hub-enhanced;
    index index.html;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### 4. Create First Super Owner

After installation, you'll need to manually create the first super owner account in the database:

```sql
INSERT INTO users (email, password_hash, first_name, last_name, is_system_owner, email_verified) 
VALUES ('admin@clubhub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super', 'Admin', TRUE, TRUE);

-- Password is: password123
-- ⚠️ CHANGE THIS IMMEDIATELY IN PRODUCTION!
```

## 🎯 User Workflows

### For Regular Users

1. **Register Account**
   - Go to `/register.html`
   - Create account with email and password
   - Automatically logged in after registration

2. **Request to Create a Club**
   - Click "Request New Club"
   - Fill out club information
   - Wait for super owner approval

3. **Join an Existing Club**
   - Get access code from club officer
   - Click "Join Club"
   - Enter access code
   - Wait for officer approval

### For Club Officers

1. **Manage Join Requests**
   - View pending requests in Members section
   - Assign a role and approve, or reject

2. **Create Custom Roles**
   - Go to Club Settings → Roles
   - Click "Create Role"
   - Set granular permissions
   - Preview what users will see

3. **Assign Roles**
   - Go to Members section
   - Select member and assign role

### For Super Owner

1. **Access Super Dashboard**
   - Login as super owner
   - Automatically redirected to super dashboard
   - View system-wide statistics

2. **Approve Club Requests**
   - View pending requests
   - Approve or reject with reason
   - Approved clubs get unique access code

3. **Manage All Clubs and Users**
   - View all clubs in system
   - Access any club's dashboard
   - Activate/deactivate users

## 🔐 API Endpoints

### Authentication (`/api/auth.php`)

**Register**
```
POST /api/auth.php?action=register
{
  "email": "user@example.com",
  "password": "password123",
  "first_name": "John",
  "last_name": "Doe"
}
```

**Login**
```
POST /api/auth.php?action=login
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Check Session**
```
GET /api/auth.php?action=check
```

**Get My Clubs**
```
GET /api/auth.php?action=my-clubs
```

**Logout**
```
POST /api/auth.php?action=logout
```

### Stats (`/api/stats.php`)

```
GET /api/stats.php?club_id=1
```

Response:
```json
{
  "success": true,
  "data": {
    "total_members": 156,
    "upcoming_events": 8,
    "attendance_rate": 92,
    "messages_today": 234
  }
}
```

### Announcements (`/api/announcements.php`)

**Get announcements**
```
GET /api/announcements.php?club_id=1&limit=10
```

**Create announcement**
```
POST /api/announcements.php
{
  "club_id": 1,
  "title": "Important Meeting",
  "content": "Don't forget about tomorrow's meeting!",
  "priority": "high"
}
```

### Events (`/api/events.php`)

**Get events**
```
GET /api/events.php?club_id=1&upcoming=true
```

**Create event**
```
POST /api/events.php
{
  "club_id": 1,
  "title": "Spring Performance",
  "description": "Our annual spring play",
  "event_date": "2024-05-15 19:00:00",
  "location": "School Auditorium"
}
```

### Sign-In (`/api/signin.php`)

**Lookup student**
```
GET /api/signin.php?action=lookup&club_id=1&student_id=12345
```

**Sign in**
```
POST /api/signin.php
{
  "action": "signin",
  "club_id": 1,
  "student_id": "12345",
  "first_name": "John",
  "last_name": "Doe",
  "grade_level": "11"
}
```

**Get today's sign-ins**
```
GET /api/signin.php?action=today&club_id=1
```

### Club Requests (`/api/club_requests.php`)

**Create club request**
```
POST /api/club_requests.php?action=create
{
  "club_name": "Robotics Club",
  "description": "Build and program robots",
  "staff_advisor": "Mr. Thompson",
  "president_name": "Jane Smith"
}
```

### Role Management (`/api/roles.php`)

**List roles**
```
GET /api/roles.php?action=list&club_id=1
```

**Create role**
```
POST /api/roles.php?action=create
{
  "club_id": 1,
  "role_name": "Treasurer",
  "permissions": {
    "view_announcements": true,
    "create_announcements": true,
    "view_stats": true
  }
}
```

**Assign role**
```
POST /api/roles.php?action=assign
{
  "club_id": 1,
  "user_id": 5,
  "role_id": 3
}
```

## 🔒 Security Features

- ✅ Password hashing with bcrypt
- ✅ Session management
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (input sanitization)
- ✅ Permission-based access control
- ✅ Role validation before actions
- ✅ President requirement (always one president per club)

## 🌐 Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## 📊 Database Structure

### Core Tables
- **users** - User accounts with system owner flag
- **user_preferences** - Personal settings per user
- **clubs** - Club information with access codes
- **club_roles** - Custom roles per club
- **role_permissions** - Granular permissions per role
- **club_members** - User membership in clubs

### Request Tables
- **club_creation_requests** - Pending club requests
- **club_join_requests** - Pending join requests

### Feature Tables
- **announcements** - Club announcements
- **events** - Club events
- **event_attendees** - Event RSVP tracking
- **club_attendance** - Daily sign-in records
- **chat_rooms** - Chat rooms per club
- **chat_messages** - Chat message history

## 🎨 Customization

### Theme Colors

The theme uses CSS custom properties in `styles.css`:

```css
:root {
    --primary: #3b82f6;
    --secondary: #8b5cf6;
    --accent: #f59e0b;
    --success: #10b981;
    --danger: #ef4444;
}
```

### Light/Dark Mode

- Toggle between themes with the theme button
- Theme preference is saved in localStorage
- Dark mode optimized for reduced eye strain

## 📝 License

MIT License - feel free to use for your club management needs!

## 🆘 Support

For issues or questions:
1. Check the API documentation above
2. Review the database schema comments
3. Examine the code comments for implementation details

## 🚧 Next Steps

Optional enhancements you can add:
- Email verification for new accounts
- Password reset functionality
- 2FA authentication
- Email notifications
- Real-time chat implementation
- File uploads for club logos
- Bulk member import
- Advanced reporting
- Calendar integration
- Mobile app version

---

**Built with:** PHP 8.0+, MySQL 8.0+, Vanilla JavaScript, Modern CSS

**Created:** February 2026
