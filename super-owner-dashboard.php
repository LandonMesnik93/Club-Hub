<?php
session_start();
require_once __DIR__ . '/database/db.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Load user and verify super owner status
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, is_system_owner FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // Redirect non-super owners
    if (!$user['is_system_owner']) {
        header('Location: index.php');
        exit;
    }

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
    <title>Super Owner Dashboard - Club Hub</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="app-container">
        <main class="main-content" style="width: 100%;">
            <header class="top-bar">
                <div>
                    <h1 style="font-size: 1.5rem; font-weight: 700;">
                        <i class="fas fa-crown" style="color: var(--warning);"></i> 
                        Super Owner Dashboard
                    </h1>
                </div>
                <div class="top-bar-actions">
                    <div class="user-menu">
                        <div class="user-avatar">
                            <?php echo strtoupper($user['first_name'][0] . $user['last_name'][0]); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                            <div class="user-role">System Owner</div>
                        </div>
                    </div>
                    <button class="btn btn-secondary" onclick="logout()">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </div>
            </header>

            <div class="content-area">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">System Overview</h1>
                        <p class="page-subtitle">Manage clubs, users, and system-wide settings</p>
                    </div>
                    <button class="btn btn-primary" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>

                <!-- System Stats -->
                <div class="stats-grid" id="systemStats">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" id="totalUsers">—</div>
                            <div class="stat-label">Total Users</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon accent">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" id="totalClubs">—</div>
                            <div class="stat-label">Total Clubs</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon secondary">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" id="pendingRequests">—</div>
                            <div class="stat-label">Pending Requests</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" id="activeClubs">—</div>
                            <div class="stat-label">Active Clubs</div>
                        </div>
                    </div>
                </div>

                <!-- Pending Club Requests -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-hourglass-half"></i> Pending Club Requests
                        </h2>
                    </div>
                    <div id="pendingRequestsList">
                        <div class="loading">Loading requests...</div>
                    </div>
                </div>

                <!-- All Clubs -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-users-cog"></i> All Clubs
                        </h2>
                    </div>
                    <div id="clubsList">
                        <div class="loading">Loading clubs...</div>
                    </div>
                </div>

                <!-- All Users -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-users"></i> All Users
                        </h2>
                    </div>
                    <div id="usersList">
                        <div class="loading">Loading users...</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Load dashboard on page load
        document.addEventListener('DOMContentLoaded', loadDashboard);

        async function loadDashboard() {
            try {
                // Load stats
                const statsRes = await fetch('api/super_owner.php?action=stats');
                const stats = await statsRes.json();
                if (stats.success) {
                    document.getElementById('totalUsers').textContent = stats.data.total_users || 0;
                    document.getElementById('totalClubs').textContent = stats.data.total_clubs || 0;
                    document.getElementById('pendingRequests').textContent = stats.data.pending_requests || 0;
                    document.getElementById('activeClubs').textContent = stats.data.active_clubs || 0;
                }

                // Load pending requests
                const requestsRes = await fetch('api/super_owner.php?action=pending-requests');
                const requests = await requestsRes.json();
                displayPendingRequests(requests.data || []);

                // Load all clubs
                const clubsRes = await fetch('api/super_owner.php?action=clubs');
                const clubs = await clubsRes.json();
                displayClubs(clubs.data || []);

                // Load all users
                const usersRes = await fetch('api/super_owner.php?action=users');
                const users = await usersRes.json();
                displayUsers(users.data || []);

            } catch (error) {
                console.error('Error loading dashboard:', error);
                alert('Error loading dashboard data. Please refresh the page.');
            }
        }

        function displayPendingRequests(requests) {
            const container = document.getElementById('pendingRequestsList');
            
            if (requests.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No pending requests</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = requests.map(r => `
                <div style="padding: 1.5rem; background: var(--bg-tertiary); border-radius: var(--radius-md); margin-bottom: 1rem; border-left: 3px solid var(--warning);">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div style="flex: 1;">
                            <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">
                                ${escapeHtml(r.club_name)}
                            </h3>
                            <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                <i class="fas fa-user"></i> Requested by: 
                                <strong>${escapeHtml(r.requester_first_name)} ${escapeHtml(r.requester_last_name)}</strong>
                                (${escapeHtml(r.requester_email)})
                            </p>
                            <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                <i class="fas fa-calendar"></i> 
                                ${formatDate(r.requested_at)}
                            </p>
                            ${r.description ? `
                                <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                    <i class="fas fa-info-circle"></i> 
                                    <strong>Description:</strong> ${escapeHtml(r.description)}
                                </p>
                            ` : ''}
                            ${r.staff_advisor ? `
                                <p style="font-size: 0.875rem; color: var(--text-secondary);">
                                    <i class="fas fa-chalkboard-teacher"></i> 
                                    <strong>Advisor:</strong> ${escapeHtml(r.staff_advisor)}
                                </p>
                            ` : ''}
                        </div>
                        <div style="display: flex; gap: 0.5rem; margin-left: 1rem;">
                            <button class="btn btn-primary" onclick="approveClub(${r.id})" title="Approve">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-secondary" onclick="rejectClub(${r.id})" title="Reject">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function displayClubs(clubs) {
            const container = document.getElementById('clubsList');
            
            if (clubs.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-users-cog"></i>
                        <p>No clubs yet</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border);">
                                <th style="padding: 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Club Name</th>
                                <th style="padding: 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">President</th>
                                <th style="padding: 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Members</th>
                                <th style="padding: 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Access Code</th>
                                <th style="padding: 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${clubs.map(c => `
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 1rem;">
                                        <div style="font-weight: 600;">${escapeHtml(c.name)}</div>
                                        ${c.description ? `<div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">${escapeHtml(c.description).substring(0, 60)}...</div>` : ''}
                                    </td>
                                    <td style="padding: 1rem;">
                                        ${c.president_first_name ? escapeHtml(c.president_first_name + ' ' + c.president_last_name) : '<em style="color: var(--text-secondary);">None</em>'}
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <span style="background: var(--bg-tertiary); padding: 0.25rem 0.75rem; border-radius: var(--radius-md); font-weight: 600;">
                                            ${c.member_count}
                                        </span>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <code style="background: var(--bg-tertiary); padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.875rem;">
                                            ${escapeHtml(c.access_code)}
                                        </code>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <span style="color: ${c.is_active ? 'var(--success)' : 'var(--danger)'}; font-weight: 600;">
                                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i> ${c.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        function displayUsers(users) {
            const container = document.getElementById('usersList');
            
            if (users.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No users yet</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border);">
                                <th style="padding: 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Name</th>
                                <th style="padding: 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Email</th>
                                <th style="padding: 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Clubs</th>
                                <th style="padding: 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Registered</th>
                                <th style="padding: 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${users.map(u => `
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 1rem;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.875rem;">
                                                ${getInitials(u.first_name, u.last_name)}
                                            </div>
                                            <div>
                                                <div style="font-weight: 600;">${escapeHtml(u.first_name)} ${escapeHtml(u.last_name)}</div>
                                                ${u.is_system_owner ? '<div style="font-size: 0.75rem; color: var(--warning);"><i class="fas fa-crown"></i> System Owner</div>' : ''}
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 1rem;">${escapeHtml(u.email)}</td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <span style="background: var(--bg-tertiary); padding: 0.25rem 0.75rem; border-radius: var(--radius-md); font-weight: 600;">
                                            ${u.club_count}
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; font-size: 0.875rem; color: var(--text-secondary);">
                                        ${formatDate(u.created_at)}
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <span style="color: ${u.is_active ? 'var(--success)' : 'var(--danger)'}; font-weight: 600;">
                                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i> ${u.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        async function approveClub(requestId) {
            if (!confirm('Approve this club creation request?')) return;
            
            try {
                const response = await fetch('api/super_owner.php?action=approve-club', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ request_id: requestId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(`Club approved successfully!\n\nAccess Code: ${data.data.access_code}\n\nPlease share this code with the club president.`);
                    loadDashboard();
                } else {
                    alert('Error: ' + (data.message || 'Failed to approve club'));
                }
            } catch (error) {
                console.error('Error approving club:', error);
                alert('An error occurred while approving the club.');
            }
        }

        async function rejectClub(requestId) {
            const reason = prompt('Enter rejection reason (optional):');
            if (reason === null) return;
            
            try {
                const response = await fetch('api/super_owner.php?action=reject-club', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        request_id: requestId, 
                        reason: reason || 'No reason provided'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Club request rejected successfully.');
                    loadDashboard();
                } else {
                    alert('Error: ' + (data.message || 'Failed to reject club'));
                }
            } catch (error) {
                console.error('Error rejecting club:', error);
                alert('An error occurred while rejecting the club.');
            }
        }

        async function logout() {
            if (!confirm('Are you sure you want to logout?')) return;
            
            try {
                await fetch('api/auth.php?action=logout', { method: 'POST' });
                window.location.href = 'login.php';
            } catch (error) {
                window.location.href = 'login.php';
            }
        }

        function refreshData() {
            loadDashboard();
        }

        // Utility functions
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function getInitials(firstName, lastName) {
            return (firstName.charAt(0) + lastName.charAt(0)).toUpperCase();
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric',
                year: 'numeric'
            });
        }
    </script>
</body>
</html>