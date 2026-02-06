/**
 * Club Hub - Main Application JavaScript
 * Handles navigation, authentication, and core functionality
 */

console.log('🚀 Club Hub app.js loaded');

// Configuration
const API_BASE = 'api/';
let CLUB_ID = null;

// Application State
const appState = {
    currentView: 'dashboard',
    currentUser: null,
    currentClub: null,
    userClubs: [],
    theme: localStorage.getItem('theme') || 'light'
};

// ============================================================================
// INITIALIZATION
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('⚙️ Initializing Club Hub...');
    initializeApp();
});

async function initializeApp() {
    // Load theme
    loadThemePreference();
    
    // Setup navigation
    setupNavigation();
    
    // Check if we have APP_CONTEXT from index.php
    if (typeof window.APP_CONTEXT !== 'undefined') {
        appState.currentUser = window.APP_CONTEXT.user;
        appState.currentClub = window.APP_CONTEXT.activeClub;
        CLUB_ID = window.APP_CONTEXT.activeClub.id;
        console.log('✅ App context loaded from PHP');
    }
    
    // Navigate to initial view
    setTimeout(() => {
        navigateTo('dashboard');
    }, 100);
    
    console.log('✅ Club Hub initialized successfully');
}

// ============================================================================
// NAVIGATION SYSTEM
// ============================================================================

/**
 * Navigate to a specific view
 */
window.navigateTo = function(viewName) {
    console.log('📍 Navigating to:', viewName);
    
    // Hide all views
    document.querySelectorAll('.view-content').forEach(view => {
        view.classList.add('hidden');
        view.style.display = 'none';
    });
    
    // Show selected view
    const targetView = document.getElementById(viewName + '-view');
    if (targetView) {
        targetView.classList.remove('hidden');
        targetView.style.display = 'block';
        console.log('✅ View shown:', viewName);
        
        // Load view-specific content
        loadViewContent(viewName);
    } else {
        console.error('❌ View not found:', viewName + '-view');
    }
    
    // Update active nav item
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    
    const activeNav = document.querySelector(`.nav-item[data-view="${viewName}"]`);
    if (activeNav) {
        activeNav.classList.add('active');
    }
    
    appState.currentView = viewName;
};

/**
 * Set up navigation click handlers
 */
function setupNavigation() {
    const navItems = document.querySelectorAll('.nav-item[data-view]');
    console.log(`Setting up ${navItems.length} navigation items...`);
    
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const viewName = this.getAttribute('data-view');
            console.log('🖱️ Nav item clicked:', viewName);
            navigateTo(viewName);
        });
    });
    
    console.log('✅ Navigation handlers set up');
}

/**
 * Load view-specific content
 */
function loadViewContent(viewName) {
    console.log('Loading content for:', viewName);
    
    switch(viewName) {
        case 'roles':
            loadRolesView();
            break;
        case 'members':
            if (typeof initializeMembersView === 'function') {
                initializeMembersView();
            }
            break;
        case 'dashboard':
            if (typeof loadDashboard === 'function') {
                loadDashboard();
            }
            break;
        case 'announcements':
            if (typeof loadAnnouncements === 'function') {
                loadAnnouncements();
            }
            break;
        case 'events':
            if (typeof loadEvents === 'function') {
                loadEvents();
            }
            break;
        case 'attendance':
            if (typeof loadAttendance === 'function') {
                loadAttendance();
            }
            break;
        case 'chat':
            if (typeof loadChat === 'function') {
                loadChat();
            }
            break;
        case 'signin':
            if (typeof loadSignIn === 'function') {
                loadSignIn();
            }
            break;
    }
}

/**
 * Load the roles management iframe
 */
function loadRolesView() {
    console.log('🔐 Loading roles view...');
    
    const iframe = document.getElementById('rolesIframe');
    if (!iframe) {
        console.error('❌ Roles iframe element not found');
        return;
    }
    
    // Set iframe source if not already set (lazy loading)
    const currentSrc = iframe.getAttribute('src');
    if (!currentSrc || currentSrc === '' || currentSrc === window.location.href) {
        iframe.src = 'manage-role-permissions.php';
        console.log('✅ Roles iframe loaded:', iframe.src);
    } else {
        console.log('ℹ️ Roles iframe already loaded');
    }
    
    // Ensure iframe is visible
    iframe.style.opacity = '1';
    iframe.style.display = 'block';
    iframe.style.visibility = 'visible';
}

// ============================================================================
// DASHBOARD
// ============================================================================

async function loadDashboard() {
    console.log('Loading dashboard...');
    
    if (!CLUB_ID) {
        console.warn('No club ID set');
        return;
    }
    
    try {
        // Load stats
        const stats = await fetchAPI('stats.php', { club_id: CLUB_ID });
        
        if (stats.success) {
            updateElement('totalMembers', stats.data.total_members || 0);
            updateElement('upcomingEvents', stats.data.upcoming_events || 0);
            updateElement('attendanceRate', (stats.data.attendance_rate || 0) + '%');
            updateElement('messagesToday', stats.data.messages_today || 0);
        }
        
        // Load recent announcements
        const announcements = await fetchAPI('announcements.php', { 
            club_id: CLUB_ID, 
            limit: 3 
        });
        displayDashboardAnnouncements(announcements.data || []);
        
        // Load upcoming events
        const events = await fetchAPI('events.php', { 
            club_id: CLUB_ID, 
            limit: 3,
            upcoming: true
        });
        displayDashboardEvents(events.data || []);
        
    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
}

function displayDashboardAnnouncements(announcements) {
    const container = document.getElementById('dashboardAnnouncements');
    if (!container) return;
    
    if (announcements.length === 0) {
        container.innerHTML = '<div class="empty-state"><i class="fas fa-bullhorn"></i><p>No announcements yet.</p></div>';
        return;
    }
    
    container.innerHTML = announcements.map(a => `
        <div class="announcement-item">
            <div class="announcement-header">
                <div class="announcement-title">${escapeHtml(a.title)}</div>
                ${a.priority !== 'normal' ? `<div class="announcement-priority ${a.priority}">${a.priority}</div>` : ''}
            </div>
            <div class="announcement-content">${escapeHtml(a.content)}</div>
            <div class="announcement-time"><i class="fas fa-clock"></i> ${formatTimeAgo(a.created_at)}</div>
        </div>
    `).join('');
}

function displayDashboardEvents(events) {
    const container = document.getElementById('dashboardEvents');
    if (!container) return;
    
    if (events.length === 0) {
        container.innerHTML = '<div class="empty-state"><i class="fas fa-calendar-alt"></i><p>No upcoming events.</p></div>';
        return;
    }
    
    container.innerHTML = events.map(e => `
        <div class="announcement-item">
            <div class="announcement-header">
                <div class="announcement-title">${escapeHtml(e.title)}</div>
                <div class="announcement-priority" style="background: var(--accent);">${formatDate(e.event_date)}</div>
            </div>
            <div class="announcement-content">${escapeHtml(e.description || '')}</div>
            <div class="announcement-time"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(e.location || 'TBA')}</div>
        </div>
    `).join('');
}

// ============================================================================
// THEME MANAGEMENT
// ============================================================================

window.toggleTheme = function() {
    const currentTheme = appState.theme;
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    appState.theme = newTheme;
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    updateThemeIcon();
    console.log('Theme changed to:', newTheme);
};

function loadThemePreference() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    appState.theme = savedTheme;
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeIcon();
    console.log('Theme loaded:', savedTheme);
}

function updateThemeIcon() {
    const icon = document.getElementById('themeIcon');
    if (icon) {
        icon.className = appState.theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
    }
}

// ============================================================================
// SIDEBAR MANAGEMENT
// ============================================================================

window.toggleSidebar = function() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('collapsed');
        sidebar.classList.toggle('mobile-open');
        console.log('Sidebar toggled');
    }
};

// ============================================================================
// USER ACTIONS
// ============================================================================

window.logout = function() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php';
    }
};

window.openClubSwitcher = function() {
    const modal = document.getElementById('clubSwitcherModal');
    if (modal) {
        modal.classList.add('show');
    }
};

window.closeClubSwitcher = function() {
    const modal = document.getElementById('clubSwitcherModal');
    if (modal) {
        modal.classList.remove('show');
    }
};

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

async function fetchAPI(endpoint, data = {}, method = 'GET') {
    try {
        const url = API_BASE + endpoint;
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            }
        };
        
        if (method === 'GET') {
            const params = new URLSearchParams(data);
            const response = await fetch(url + '?' + params, options);
            return await response.json();
        } else {
            options.body = JSON.stringify(data);
            const response = await fetch(url, options);
            return await response.json();
        }
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTimeAgo(dateString) {
    const date = new Date(dateString);
    const seconds = Math.floor((new Date() - date) / 1000);
    
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
    return Math.floor(seconds / 86400) + ' days ago';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric',
        year: 'numeric'
    });
}

function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit'
    });
}

function updateElement(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = value;
    }
}

function showMessage(type, message, containerId) {
    const icons = {
        'success': 'fa-check-circle',
        'error': 'fa-exclamation-circle',
        'info': 'fa-info-circle'
    };
    
    const colors = {
        'success': 'var(--success)',
        'error': 'var(--danger)',
        'info': 'var(--primary)'
    };
    
    const container = document.getElementById(containerId);
    if (container) {
        container.innerHTML = `
            <div style="padding: 1rem; background: ${colors[type]}15; border: 1px solid ${colors[type]}; border-radius: var(--radius-md); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem; color: ${colors[type]};">
                <i class="fas ${icons[type]}"></i>
                <span>${message}</span>
            </div>
        `;
    }
}

window.showToast = function(message, type = 'info') {
    console.log(`Toast [${type}]:`, message);
    alert(message); // Can be enhanced with a toast UI library
};

console.log('✅ App.js loaded - All functions available');