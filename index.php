<?php

// index.php - COMPLETE VERSION WITH MEMBERS SECTION

session_start();
require_once __DIR__ . '/database/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Load user data
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, is_system_owner FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // Redirect system owners to their dashboard
    if ($user['is_system_owner']) {
        header('Location: super-owner-dashboard.php');
        exit;
    }

    // Load user's clubs with permissions
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.name,
            c.access_code,
            c.description,
            cm.is_president,
            cr.role_name,
            cr.id as role_id
        FROM club_members cm
        JOIN clubs c ON c.id = cm.club_id
        JOIN club_roles cr ON cr.id = cm.role_id
        WHERE cm.user_id = ? AND cm.status = 'active' AND c.is_active = TRUE
        ORDER BY c.name
    ");
    $stmt->execute([$user['id']]);
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($clubs)) {
        header('Location: no-clubs.php');
        exit;
    }

    // Set active club
    if (!isset($_SESSION['active_club_id']) || !in_array($_SESSION['active_club_id'], array_column($clubs, 'id'))) {
        $_SESSION['active_club_id'] = $clubs[0]['id'];
    }

    // Get active club details
    $activeClub = null;
    foreach ($clubs as $club) {
        if ($club['id'] == $_SESSION['active_club_id']) {
            $activeClub = $club;
            break;
        }
    }

    // Load permissions for active club
    $stmt = $pdo->prepare("SELECT permission_key, permission_value FROM role_permissions WHERE role_id = ?");
    $stmt->execute([$activeClub['role_id']]);
    $permissionsArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to associative array
    $permissions = [];
    foreach ($permissionsArray as $perm) {
        $permissions[$perm['permission_key']] = (bool)$perm['permission_value'];
    }

    // Load user notifications
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $unreadCount = count($notifications);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Hub - <?php echo htmlspecialchars($activeClub['name']); ?></title>
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="styles.css">
    
    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: var(--radius-md);
            transition: all var(--transition);
        }
        
        .close-btn:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        /* Dropdown Menu Styles */
        .dropdown {
            position: relative;
        }
        
        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 20px var(--shadow-lg);
            min-width: 250px;
            margin-top: 0.5rem;
            z-index: 100;
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: all var(--transition);
            color: var(--text-primary);
            text-decoration: none;
            border-bottom: 1px solid var(--border);
        }
        
        .dropdown-item:last-child {
            border-bottom: none;
        }
        
        .dropdown-item:hover {
            background: var(--bg-tertiary);
        }
        
        .dropdown-item i {
            width: 20px;
            text-align: center;
        }
        
        /* Club List Styles */
        .club-list-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all var(--transition);
            border: 2px solid transparent;
        }
        
        .club-list-item:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .club-list-item.active {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .club-list-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .club-list-info {
            flex: 1;
        }
        
        .club-list-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .club-list-role {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        /* Notification Panel Styles */
        .notification-panel {
            max-width: 400px;
        }
        
        .notification-item {
            padding: 1rem;
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            margin-bottom: 0.75rem;
            border-left: 3px solid var(--primary);
        }
        
        .notification-item.success {
            border-left-color: var(--success);
        }
        
        .notification-item.warning {
            border-left-color: var(--warning);
        }
        
        .notification-item.error {
            border-left-color: var(--danger);
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .notification-message {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        
        .notification-time {
            font-size: 0.75rem;
            color: var(--text-tertiary);
        }
        
        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            display: none;
        }
        
        .alert.show {
            display: block;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }
        
        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            color: var(--text-primary);
            font-family: inherit;
            transition: all var(--transition);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn-full {
            width: 100%;
            justify-content: center;
        }
        
        /* Members Section Styles */
        .tabs-container {
            border-bottom: 2px solid var(--border);
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
        }

        .tab-btn {
            padding: 0.875rem 1.5rem;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab-btn:hover {
            color: var(--text-primary);
            background: var(--bg-tertiary);
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem 0.5rem;
            background: var(--primary);
            color: white;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            min-width: 20px;
        }

        .search-input-wrapper {
            position: relative;
        }

        .search-input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .member-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
            transition: all var(--transition);
            cursor: pointer;
        }

        .member-card:last-child {
            border-bottom: none;
        }

        .member-card:hover {
            background: var(--bg-tertiary);
        }

        .member-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .member-email {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .member-role {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .member-role.president {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .member-actions {
            display: flex;
            gap: 0.5rem;
        }

        .join-request-card {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1.5rem;
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            margin-bottom: 1rem;
            transition: all var(--transition);
        }

        .join-request-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
        }

        .join-request-header {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .join-request-info {
            flex: 1;
        }

        .join-request-name {
            font-weight: 600;
            font-size: 1.125rem;
            margin-bottom: 0.25rem;
        }

        .join-request-email {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .join-request-meta {
            display: flex;
            gap: 1.5rem;
            padding: 1rem;
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
        }

        .join-request-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
        }

        .join-request-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 1rem;
            margin-bottom: 0;
        }

        .role-select-group {
            margin-bottom: 1.5rem;
        }

        .role-option {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius-md);
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all var(--transition);
        }

        .role-option:hover {
            border-color: var(--primary);
            background: var(--bg-tertiary);
        }

        .role-option input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .role-option-info {
            flex: 1;
        }

        .role-option-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .role-option-description {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
    </style>
    
    <!-- App Context -->
    <script>
        window.APP_CONTEXT = {
            user: {
                id: <?php echo $user['id']; ?>,
                firstName: <?php echo json_encode($user['first_name']); ?>,
                lastName: <?php echo json_encode($user['last_name']); ?>,
                fullName: <?php echo json_encode($user['first_name'] . ' ' . $user['last_name']); ?>
            },
            activeClub: {
                id: <?php echo $activeClub['id']; ?>,
                name: <?php echo json_encode($activeClub['name']); ?>,
                roleName: <?php echo json_encode($activeClub['role_name']); ?>,
                isPresident: <?php echo $activeClub['is_president'] ? 'true' : 'false'; ?>
            },
            clubs: <?php echo json_encode($clubs); ?>,
            permissions: <?php echo json_encode($permissions); ?>,
            unreadNotifications: <?php echo $unreadCount; ?>
        };
    </script>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-users-cog"></i>
                    <span class="logo-text">Club Hub</span>
                </div>
            </div>

            <!-- Club Selector -->
            <div class="club-selector" onclick="openClubSwitcher()">
                <div class="club-logo">
                    <i class="fas fa-users"></i>
                </div>
                <div class="club-info">
                    <h3 id="clubName"><?php echo htmlspecialchars($activeClub['name']); ?></h3>
                    <p id="clubRole"><?php echo htmlspecialchars($activeClub['role_name']); ?></p>
                </div>
                <i class="fas fa-chevron-down"></i>
            </div>

            <!-- Navigation -->
            <nav class="nav-menu">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="#" class="nav-item active" data-view="dashboard">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                    <?php if ($permissions['view_announcements'] ?? false): ?>
                    <a href="#" class="nav-item" data-view="announcements">
                        <i class="fas fa-bullhorn"></i>
                        <span>Announcements</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($permissions['view_events'] ?? false): ?>
                    <a href="#" class="nav-item" data-view="events">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Events</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($permissions['view_members'] ?? false): ?>
                    <a href="#" class="nav-item" data-view="members">
                        <i class="fas fa-user-friends"></i>
                        <span>Members</span>
                        <?php if ($permissions['manage_members'] ?? false): ?>
                        <span id="membersNavBadge" class="badge" style="display: none; margin-left: auto;">0</span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    <a href="#" class="nav-item" data-view="signin">
                        <i class="fas fa-id-card"></i>
                        <span>Sign-In</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Tools</div>
                    <?php if ($permissions['view_attendance'] ?? false): ?>
                    <a href="#" class="nav-item" data-view="attendance">
                        <i class="fas fa-check-circle"></i>
                        <span>Attendance</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($permissions['access_chat'] ?? false): ?>
                    <a href="#" class="nav-item" data-view="chat">
                        <i class="fas fa-comments"></i>
                        <span>Chat</span>
                    </a>
                    <?php endif; ?>
                </div>

                <?php if (($permissions['modify_club_settings'] ?? false) || ($permissions['manage_roles'] ?? false)): ?>
                <div class="nav-section">
                    <div class="nav-section-title">Settings</div>
                    <?php if ($permissions['modify_club_settings'] ?? false): ?>
                    <a href="#" class="nav-item" data-view="club-settings">
                        <i class="fas fa-cog"></i>
                        <span>Club Settings</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($permissions['manage_roles'] ?? false): ?>
                    <a href="#" class="nav-item" data-view="roles">
                        <i class="fas fa-user-shield"></i>
                        <span>Roles</span>
                    </a>
                    <?php endif; ?>
                    <a href="#" class="nav-item" data-view="theme">
                        <i class="fas fa-palette"></i>
                        <span>Theme</span>
                    </a>
                </div>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <button class="theme-btn" onclick="toggleTheme()" id="themeBtn">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-bar">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search..." id="searchInput" />
                </div>

                <div class="top-bar-actions">
                    <div class="dropdown">
                        <button class="icon-btn" onclick="toggleNotifications()" id="notificationBtn">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                            <span class="notification-dot"></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu notification-panel" id="notificationPanel">
                            <div style="padding: 1rem; border-bottom: 1px solid var(--border);">
                                <h3 style="font-weight: 600; margin-bottom: 0.5rem;">Notifications</h3>
                                <p style="font-size: 0.75rem; color: var(--text-secondary);"><?php echo $unreadCount; ?> unread</p>
                            </div>
                            <div id="notificationList" style="padding: 1rem; max-height: 400px; overflow-y: auto;">
                                <?php if (empty($notifications)): ?>
                                <div class="empty-state" style="padding: 2rem;">
                                    <i class="fas fa-bell-slash"></i>
                                    <p>No new notifications</p>
                                </div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notif): ?>
                                    <div class="notification-item <?php echo htmlspecialchars($notif['type']); ?>">
                                        <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                        <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                        <div class="notification-time">
                                            <i class="fas fa-clock"></i> 
                                            <?php 
                                            $time = strtotime($notif['created_at']);
                                            echo date('M j, g:i A', $time);
                                            ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div style="padding: 1rem; border-top: 1px solid var(--border); text-align: center;">
                                <button class="btn btn-secondary btn-full" onclick="markAllRead()">
                                    <i class="fas fa-check-double"></i> Mark All Read
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="dropdown">
                        <div class="user-menu" onclick="toggleUserMenu()">
                            <div class="user-avatar" id="userAvatar">
                                <?php echo strtoupper($user['first_name'][0] . $user['last_name'][0]); ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name" id="userName"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                <div class="user-role"><?php echo htmlspecialchars($activeClub['role_name']); ?></div>
                            </div>
                        </div>
                        <div class="dropdown-menu" id="userMenu">
                            <a href="#" class="dropdown-item" onclick="openProfile(); return false;">
                                <i class="fas fa-user"></i>
                                <span>My Profile</span>
                            </a>
                            <a href="#" class="dropdown-item" onclick="openSettings(); return false;">
                                <i class="fas fa-cog"></i>
                                <span>Settings</span>
                            </a>
                            <a href="#" class="dropdown-item" onclick="openMyClubs(); return false;">
                                <i class="fas fa-users"></i>
                                <span>My Clubs</span>
                            </a>
                            <a href="#" class="dropdown-item" style="color: var(--danger);" onclick="logout(); return false;">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content-area" id="contentArea">
                <!-- Dashboard View -->
                <div id="dashboard-view" class="view-content">
                    <div class="page-header">
                        <div>
                            <h1 class="page-title">Dashboard</h1>
                            <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</p>
                        </div>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value" id="totalMembers">—</div>
                                <div class="stat-label">Total Members</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon accent">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value" id="upcomingEvents">—</div>
                                <div class="stat-label">Upcoming Events</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon success">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value" id="attendanceRate">—</div>
                                <div class="stat-label">Attendance Rate</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon secondary">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value" id="messagesToday">—</div>
                                <div class="stat-label">Messages Today</div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Recent Announcements</h2>
                        </div>
                        <div id="dashboardAnnouncements">
                            <div class="loading">Loading announcements...</div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Upcoming Events</h2>
                        </div>
                        <div id="dashboardEvents">
                            <div class="loading">Loading events...</div>
                        </div>
                    </div>
                </div>

                <!-- Other views will be loaded dynamically -->
                <div id="announcements-view" class="view-content hidden"></div>
                <div id="events-view" class="view-content hidden"></div>
                
                <!-- MEMBERS VIEW - COMPLETE IMPLEMENTATION -->
                <div id="members-view" class="view-content hidden">
                    <div class="page-header">
                        <div>
                            <h1 class="page-title">Members</h1>
                            <p class="page-subtitle">Manage club members and join requests</p>
                        </div>
                        <?php if ($permissions['manage_members'] ?? false): ?>
                        <button class="btn btn-primary" onclick="showJoinRequestsSection()">
                            <i class="fas fa-user-plus"></i> 
                            <span>Join Requests</span>
                            <span id="joinRequestBadge" class="badge" style="display: none;">0</span>
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="tabs-container" style="margin-bottom: 2rem;">
                        <div class="tabs">
                            <button class="tab-btn active" onclick="switchMemberTab('active-members')">
                                <i class="fas fa-users"></i> Active Members
                            </button>
                            <?php if ($permissions['manage_members'] ?? false): ?>
                            <button class="tab-btn" onclick="switchMemberTab('join-requests')" id="joinRequestsTab">
                                <i class="fas fa-user-clock"></i> Join Requests
                                <span id="joinRequestTabBadge" class="badge" style="display: none; margin-left: 0.5rem;">0</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Active Members Tab -->
                    <div id="active-members-tab" class="tab-content active">
                        <!-- Search and Filter -->
                        <div class="card" style="margin-bottom: 1.5rem;">
                            <div class="card-body" style="padding: 1rem;">
                                <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                                    <div style="flex: 1; min-width: 250px;">
                                        <div class="search-input-wrapper">
                                            <i class="fas fa-search"></i>
                                            <input type="text" id="memberSearchInput" class="form-input" placeholder="Search members by name or email..." style="padding-left: 2.5rem;">
                                        </div>
                                    </div>
                                    <select id="roleFilterSelect" class="form-input" style="width: auto; min-width: 150px;">
                                        <option value="">All Roles</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Members List -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">
                                    <i class="fas fa-users"></i> Club Members
                                    <span id="memberCount" class="badge">0</span>
                                </h2>
                            </div>
                            <div id="membersList">
                                <div class="loading">Loading members...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Join Requests Tab -->
                    <?php if ($permissions['manage_members'] ?? false): ?>
                    <div id="join-requests-tab" class="tab-content">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">
                                    <i class="fas fa-user-clock"></i> Pending Join Requests
                                </h2>
                            </div>
                            <div id="joinRequestsList">
                                <div class="loading">Loading join requests...</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div id="signin-view" class="view-content hidden"></div>
                <div id="attendance-view" class="view-content hidden"></div>
                <div id="chat-view" class="view-content hidden"></div>
                <div id="club-settings-view" class="view-content hidden"></div>
                <div id="roles-view" class="view-content hidden">
                    <iframe id="rolesIframe" 
                            src="manage-role-permissions.php" 
                            style="width: 100%; height: calc(100vh - 100px); border: none; border-radius: var(--radius-lg); opacity: 0; transition: opacity 0.3s;"
                            frameborder="0"
                            onload="this.style.opacity = 1;"></iframe>
                </div>
                <div id="theme-view" class="view-content hidden"></div>
            </div>
        </main>
    </div>

    <!-- Club Switcher Modal -->
    <div id="clubSwitcherModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Switch Club</h2>
                <button class="close-btn" onclick="closeClubSwitcher()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="clubList">
                <?php foreach ($clubs as $club): ?>
                <div class="club-list-item <?php echo $club['id'] == $activeClub['id'] ? 'active' : ''; ?>" 
                     onclick="switchClub(<?php echo $club['id']; ?>)">
                    <div class="club-list-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="club-list-info">
                        <div class="club-list-name"><?php echo htmlspecialchars($club['name']); ?></div>
                        <div class="club-list-role"><?php echo htmlspecialchars($club['role_name']); ?></div>
                    </div>
                    <?php if ($club['id'] == $activeClub['id']): ?>
                    <i class="fas fa-check" style="color: var(--success);"></i>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
                <button class="btn btn-primary btn-full" onclick="window.location.href='no-clubs.php'">
                    <i class="fas fa-plus"></i> Join or Create Club
                </button>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">My Profile</h2>
                <button class="close-btn" onclick="closeProfile()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="profileAlert" class="alert"></div>
            <form id="profileForm">
                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-input" id="profileFirstName" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-input" id="profileLastName" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" id="profileEmail" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary btn-full">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
                <h3 style="font-weight: 600; margin-bottom: 1rem;">Change Password</h3>
                <form id="passwordForm">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-input" id="currentPassword" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-input" id="newPassword" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-full">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Member Details Modal -->
    <div id="memberDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Member Details</h2>
                <button class="close-btn" onclick="closeMemberDetails()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="memberDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Join Request Approval Modal -->
    <div id="joinRequestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Review Join Request</h2>
                <button class="close-btn" onclick="closeJoinRequestModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="joinRequestAlert" class="alert"></div>
            <div id="joinRequestContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <script src="app.js"></script>
    
    <script>
    // ===========================
    // GENERAL FUNCTIONALITY
    // ===========================
    
    // Toggle Notifications
    function toggleNotifications() {
        const panel = document.getElementById('notificationPanel');
        const userMenu = document.getElementById('userMenu');
        userMenu.classList.remove('show');
        panel.classList.toggle('show');
    }
    
    // Toggle User Menu
    function toggleUserMenu() {
        const menu = document.getElementById('userMenu');
        const notifPanel = document.getElementById('notificationPanel');
        notifPanel.classList.remove('show');
        menu.classList.toggle('show');
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
    
    // Open Club Switcher
    function openClubSwitcher() {
        document.getElementById('clubSwitcherModal').classList.add('show');
    }
    
    function closeClubSwitcher() {
        document.getElementById('clubSwitcherModal').classList.remove('show');
    }
    
    // Switch Club
    async function switchClub(clubId) {
        try {
            const response = await fetch('api/switch_club.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ club_id: clubId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error switching club: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error switching club:', error);
            alert('Error switching club. Please try again.');
        }
    }
    
    // Open Profile
    function openProfile() {
        document.getElementById('userMenu').classList.remove('show');
        document.getElementById('profileModal').classList.add('show');
    }
    
    function closeProfile() {
        document.getElementById('profileModal').classList.remove('show');
    }
    
    // Open Settings
    function openSettings() {
        document.getElementById('userMenu').classList.remove('show');
        navigateTo('theme');
    }
    
    // Open My Clubs
    function openMyClubs() {
        document.getElementById('userMenu').classList.remove('show');
        openClubSwitcher();
    }
    
    // Profile Form Submission
    document.getElementById('profileForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const data = {
            first_name: document.getElementById('profileFirstName').value,
            last_name: document.getElementById('profileLastName').value,
            email: document.getElementById('profileEmail').value
        };
        
        try {
            const response = await fetch('api/user_preferences.php?action=update-profile', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                showProfileAlert('success', 'Profile updated successfully!');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showProfileAlert('error', result.message || 'Failed to update profile');
            }
        } catch (error) {
            console.error('Error updating profile:', error);
            showProfileAlert('error', 'Error updating profile. Please try again.');
        }
    });
    
    // Password Form Submission
    document.getElementById('passwordForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const data = {
            current_password: document.getElementById('currentPassword').value,
            new_password: document.getElementById('newPassword').value
        };
        
        try {
            const response = await fetch('api/user_preferences.php?action=change-password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                showProfileAlert('success', 'Password changed successfully!');
                document.getElementById('passwordForm').reset();
            } else {
                showProfileAlert('error', result.message || 'Failed to change password');
            }
        } catch (error) {
            console.error('Error changing password:', error);
            showProfileAlert('error', 'Error changing password. Please try again.');
        }
    });
    
    function showProfileAlert(type, message) {
        const alert = document.getElementById('profileAlert');
        alert.className = `alert alert-${type} show`;
        alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i> ${message}`;
        setTimeout(() => alert.classList.remove('show'), 5000);
    }
    
    // Mark All Notifications Read
    async function markAllRead() {
        try {
            const response = await fetch('api/notifications.php?action=mark-all-read', {
                method: 'POST'
            });
            
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('notificationList').innerHTML = `
                    <div class="empty-state" style="padding: 2rem;">
                        <i class="fas fa-bell-slash"></i>
                        <p>No new notifications</p>
                    </div>
                `;
                document.querySelector('.notification-dot')?.remove();
            }
        } catch (error) {
            console.error('Error marking notifications read:', error);
        }
    }
    
    // Search Functionality
    document.getElementById('searchInput')?.addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query) {
                performSearch(query);
            }
        }
    });
    
    function performSearch(query) {
        console.log('Searching for:', query);
        // Implement search functionality based on current view
        alert('Search functionality coming soon! Query: ' + query);
    }
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('show');
        }
    });
    
    // ===========================
    // MEMBERS SECTION JAVASCRIPT
    // ===========================
    
    let currentMembers = [];
    let currentJoinRequests = [];
    let selectedRequestId = null;

    // Switch between member tabs
    function switchMemberTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        event.target.closest('.tab-btn').classList.add('active');
        
        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.getElementById(tabName + '-tab').classList.add('active');
        
        // Load data if needed
        if (tabName === 'join-requests') {
            loadJoinRequests();
        }
    }

    // Load members list
    async function loadMembers() {
        try {
            const response = await fetch(`api/members.php?action=list&club_id=${window.APP_CONTEXT.activeClub.id}`);
            const data = await response.json();
            
            if (data.success) {
                currentMembers = data.data;
                renderMembers(currentMembers);
                populateRoleFilter(currentMembers);
                document.getElementById('memberCount').textContent = currentMembers.length;
            } else {
                document.getElementById('membersList').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Error loading members: ${data.message}</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading members:', error);
            document.getElementById('membersList').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Error loading members</p>
                </div>
            `;
        }
    }

    // Render members list
    function renderMembers(members) {
        const container = document.getElementById('membersList');
        
        if (members.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <p>No members found</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = members.map(member => {
            const initials = (member.first_name[0] + member.last_name[0]).toUpperCase();
            const isPresident = member.is_president == 1;
            
            return `
                <div class="member-card" onclick="viewMemberDetails(${member.user_id})">
                    <div class="member-avatar">${initials}</div>
                    <div class="member-info">
                        <div class="member-name">
                            ${escapeHtml(member.first_name + ' ' + member.last_name)}
                            ${isPresident ? '<i class="fas fa-crown" style="color: var(--warning); margin-left: 0.5rem;"></i>' : ''}
                        </div>
                        <div class="member-email">${escapeHtml(member.email)}</div>
                        <div class="member-role ${isPresident ? 'president' : ''}">
                            <i class="fas fa-shield-alt"></i>
                            ${escapeHtml(member.role_name)}
                        </div>
                    </div>
                    ${window.APP_CONTEXT.permissions.manage_members && !isPresident ? `
                    <div class="member-actions" onclick="event.stopPropagation();">
                        <button class="btn btn-sm btn-secondary" onclick="editMemberRole(${member.user_id}, '${escapeHtml(member.first_name + ' ' + member.last_name)}')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="removeMember(${member.user_id}, '${escapeHtml(member.first_name + ' ' + member.last_name)}')">
                            <i class="fas fa-user-times"></i>
                        </button>
                    </div>
                    ` : ''}
                </div>
            `;
        }).join('');
    }

    // Populate role filter
    function populateRoleFilter(members) {
        const roleFilter = document.getElementById('roleFilterSelect');
        const roles = [...new Set(members.map(m => m.role_name))];
        
        roleFilter.innerHTML = '<option value="">All Roles</option>' + 
            roles.map(role => `<option value="${escapeHtml(role)}">${escapeHtml(role)}</option>`).join('');
    }

    // Filter members
    function filterMembers() {
        const searchTerm = document.getElementById('memberSearchInput').value.toLowerCase();
        const roleFilter = document.getElementById('roleFilterSelect').value;
        
        const filtered = currentMembers.filter(member => {
            const matchesSearch = !searchTerm || 
                member.first_name.toLowerCase().includes(searchTerm) ||
                member.last_name.toLowerCase().includes(searchTerm) ||
                member.email.toLowerCase().includes(searchTerm);
            
            const matchesRole = !roleFilter || member.role_name === roleFilter;
            
            return matchesSearch && matchesRole;
        });
        
        renderMembers(filtered);
    }

    // Event listeners for filtering
    document.getElementById('memberSearchInput')?.addEventListener('input', filterMembers);
    document.getElementById('roleFilterSelect')?.addEventListener('change', filterMembers);

    // Load join requests
    async function loadJoinRequests() {
        try {
            const response = await fetch(`api/club_join.php?action=pending&club_id=${window.APP_CONTEXT.activeClub.id}`);
            const data = await response.json();
            
            if (data.success) {
                currentJoinRequests = data.data;
                renderJoinRequests(currentJoinRequests);
                updateJoinRequestBadges(currentJoinRequests.length);
            } else {
                document.getElementById('joinRequestsList').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Error loading join requests</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading join requests:', error);
            document.getElementById('joinRequestsList').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Error loading join requests</p>
                </div>
            `;
        }
    }

    // Render join requests
    function renderJoinRequests(requests) {
        const container = document.getElementById('joinRequestsList');
        
        if (requests.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-user-check"></i>
                    <p>No pending join requests</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = requests.map(request => {
            const initials = (request.first_name[0] + request.last_name[0]).toUpperCase();
            const requestDate = new Date(request.created_at).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            return `
                <div class="join-request-card">
                    <div class="join-request-header">
                        <div class="member-avatar">${initials}</div>
                        <div class="join-request-info">
                            <div class="join-request-name">${escapeHtml(request.first_name + ' ' + request.last_name)}</div>
                            <div class="join-request-email">${escapeHtml(request.email)}</div>
                        </div>
                    </div>
                    
                    <div class="join-request-meta">
                        <div class="join-request-meta-item">
                            <i class="fas fa-calendar"></i>
                            <span>Requested: ${requestDate}</span>
                        </div>
                        <div class="join-request-meta-item">
                            <i class="fas fa-key"></i>
                            <span>Code: ${escapeHtml(request.access_code_used)}</span>
                        </div>
                    </div>
                    
                    ${request.message ? `
                    <div style="padding: 1rem; background: var(--bg-tertiary); border-radius: var(--radius-md); font-size: 0.875rem;">
                        <strong>Message:</strong><br>
                        ${escapeHtml(request.message)}
                    </div>
                    ` : ''}
                    
                    <div class="join-request-actions">
                        <button class="btn btn-danger" onclick="rejectJoinRequest(${request.id}, '${escapeHtml(request.first_name + ' ' + request.last_name)}')">
                            <i class="fas fa-times"></i> Reject
                        </button>
                        <button class="btn btn-success" onclick="openApprovalModal(${request.id}, '${escapeHtml(request.first_name + ' ' + request.last_name)}')">
                            <i class="fas fa-check"></i> Approve
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Update join request badges
    function updateJoinRequestBadges(count) {
        const badges = ['joinRequestBadge', 'joinRequestTabBadge', 'membersNavBadge'];
        badges.forEach(badgeId => {
            const badge = document.getElementById(badgeId);
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'inline-flex';
                } else {
                    badge.style.display = 'none';
                }
            }
        });
    }

    // Show join requests section
    function showJoinRequestsSection() {
        document.getElementById('joinRequestsTab')?.click();
    }

    // Open approval modal
    async function openApprovalModal(requestId, memberName) {
        selectedRequestId = requestId;
        
        try {
            // Load available roles
            const response = await fetch(`api/roles.php?action=list&club_id=${window.APP_CONTEXT.activeClub.id}`);
            const data = await response.json();
            
            if (data.success && data.data) {
                const roles = data.data.filter(role => !role.is_president);
                
                document.getElementById('joinRequestContent').innerHTML = `
                    <p style="margin-bottom: 1.5rem;">Select a role for <strong>${escapeHtml(memberName)}</strong>:</p>
                    
                    <div class="role-select-group">
                        ${roles.map(role => `
                            <label class="role-option">
                                <input type="radio" name="selectedRole" value="${role.id}">
                                <div class="role-option-info">
                                    <div class="role-option-name">${escapeHtml(role.role_name)}</div>
                                    ${role.description ? `<div class="role-option-description">${escapeHtml(role.description)}</div>` : ''}
                                </div>
                            </label>
                        `).join('')}
                    </div>
                    
                    <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                        <button class="btn btn-secondary" onclick="closeJoinRequestModal()">
                            Cancel
                        </button>
                        <button class="btn btn-success" onclick="approveJoinRequest()">
                            <i class="fas fa-check"></i> Approve Member
                        </button>
                    </div>
                `;
                
                document.getElementById('joinRequestModal').classList.add('show');
            }
        } catch (error) {
            console.error('Error loading roles:', error);
            alert('Error loading roles. Please try again.');
        }
    }

    // Approve join request
    async function approveJoinRequest() {
        const selectedRole = document.querySelector('input[name="selectedRole"]:checked');
        
        if (!selectedRole) {
            showJoinRequestAlert('error', 'Please select a role');
            return;
        }
        
        try {
            const response = await fetch('api/club_join.php?action=approve', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    request_id: selectedRequestId,
                    role_id: parseInt(selectedRole.value)
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showJoinRequestAlert('success', 'Member approved successfully!');
                setTimeout(() => {
                    closeJoinRequestModal();
                    loadJoinRequests();
                    loadMembers();
                }, 1500);
            } else {
                showJoinRequestAlert('error', data.message || 'Failed to approve member');
            }
        } catch (error) {
            console.error('Error approving member:', error);
            showJoinRequestAlert('error', 'Error approving member. Please try again.');
        }
    }

    // Reject join request
    async function rejectJoinRequest(requestId, memberName) {
        const reason = prompt(`Why are you rejecting ${memberName}'s request? (Optional)`);
        
        if (reason === null) return;
        
        try {
            const response = await fetch('api/club_join.php?action=reject', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    request_id: requestId,
                    reason: reason
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                loadJoinRequests();
            } else {
                alert('Error: ' + (data.message || 'Failed to reject request'));
            }
        } catch (error) {
            console.error('Error rejecting request:', error);
            alert('Error rejecting request. Please try again.');
        }
    }

    // Close join request modal
    function closeJoinRequestModal() {
        document.getElementById('joinRequestModal').classList.remove('show');
        selectedRequestId = null;
    }

    // Show join request alert
    function showJoinRequestAlert(type, message) {
        const alert = document.getElementById('joinRequestAlert');
        alert.className = `alert alert-${type} show`;
        alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i> ${message}`;
        setTimeout(() => alert.classList.remove('show'), 5000);
    }

    // View member details
    function viewMemberDetails(userId) {
        console.log('View member details:', userId);
    }

    // Edit member role
    function editMemberRole(userId, memberName) {
        alert('Role editing feature coming soon for ' + memberName);
    }

    // Remove member
    async function removeMember(userId, memberName) {
        if (!confirm(`Are you sure you want to remove ${memberName} from the club?`)) {
            return;
        }
        
        try {
            const response = await fetch('api/members.php?action=remove', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    club_id: window.APP_CONTEXT.activeClub.id,
                    user_id: userId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Member removed successfully');
                loadMembers();
            } else {
                alert('Error: ' + (data.message || 'Failed to remove member'));
            }
        } catch (error) {
            console.error('Error removing member:', error);
            alert('Error removing member. Please try again.');
        }
    }

    // Close member details modal
    function closeMemberDetails() {
        document.getElementById('memberDetailsModal').classList.remove('show');
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize members view
    function initializeMembersView() {
        loadMembers();
        if (window.APP_CONTEXT.permissions.manage_members) {
            loadJoinRequests();
        }
    }

    // Add navigation listener for members view
    document.querySelector('.nav-item[data-view="members"]')?.addEventListener('click', function() {
        initializeMembersView();
    });
    
    // Load join requests on page load if user has permission
    if (window.APP_CONTEXT.permissions.manage_members) {
        // Check for join requests every 30 seconds
        loadJoinRequests();
        setInterval(loadJoinRequests, 30000);
    }

    // ===========================
    // ROLES VIEW INTEGRATION
    // ===========================
    
    // Load roles iframe when roles view is activated
    document.querySelector('.nav-item[data-view="roles"]')?.addEventListener('click', function() {
        const iframe = document.getElementById('rolesIframe');
        
        // Only load iframe once (lazy loading for performance)
        if (!iframe.src || iframe.src === '') {
            iframe.src = 'manage-role-permissions.php';
            console.log('✅ Roles management loaded');
        }
    });
    </script>
</body>
</html>