<?php
// Determine base path based on current directory depth
$navBasePath = '';
if (strpos($_SERVER['PHP_SELF'], '/users/') !== false) {
    $navBasePath = '../../';
} elseif (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) {
    $navBasePath = '../';
}

// Determine current page for active state
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Get user role from session
$userRole = $_SESSION['role'] ?? 'student';

// Define nav items based on role
if ($userRole === 'mentor') {
    $navItems = [
        ['name' => 'dashboard', 'label' => 'Home', 'path' => $navBasePath . 'pages/dashboard.php', 'icon' => 'home'],
        ['name' => 'calendar', 'label' => 'Calendar', 'path' => $navBasePath . 'pages/calendar.php', 'icon' => 'calendar'],
        ['name' => 'requests', 'label' => 'Requests', 'path' => $navBasePath . 'pages/requests.php', 'icon' => 'requests'],
        ['name' => 'chat', 'label' => 'Chats', 'path' => $navBasePath . 'pages/chat.php', 'icon' => 'chat'],
        ['name' => 'mentor_profile', 'label' => 'Settings', 'path' => $navBasePath . 'users/mentor/mentor_profile.php', 'icon' => 'settings'],
    ];
} elseif ($userRole === 'student') {
    $navItems = [
        ['name' => 'dashboard', 'label' => 'Home', 'path' => $navBasePath . 'pages/dashboard.php', 'icon' => 'home'],
        ['name' => 'calendar', 'label' => 'Calendar', 'path' => $navBasePath . 'pages/calendar.php', 'icon' => 'calendar'],
        ['name' => 'mentor_swipe', 'label' => 'Mentors', 'path' => $navBasePath . 'pages/mentor_swipe.php', 'icon' => 'mentors'],
        ['name' => 'chatbot', 'label' => 'Chatbot', 'path' => $navBasePath . 'pages/chatbot.php', 'icon' => 'chat'],
        ['name' => 'student_profile_setup', 'label' => 'Settings', 'path' => $navBasePath . 'users/student/student_profile_setup.php', 'icon' => 'settings'],
    ];
} elseif ($userRole === 'admin') {
    $navItems = [
        ['name' => 'dashboard', 'label' => 'Home', 'path' => $navBasePath . 'pages/dashboard.php', 'icon' => 'home'],
        ['name' => 'admin_dashboard', 'label' => 'Applications', 'path' => $navBasePath . 'users/admin/admin_dashboard.php', 'icon' => 'requests'],
        ['name' => 'admin_users', 'label' => 'Users', 'path' => $navBasePath . 'pages/admin_users.php', 'icon' => 'users'],
        ['name' => 'admin_sessions', 'label' => 'Sessions', 'path' => $navBasePath . 'pages/admin_sessions.php', 'icon' => 'calendar'],
        ['name' => 'admin_reports', 'label' => 'Reports', 'path' => $navBasePath . 'pages/admin_reports.php', 'icon' => 'reports'],
    ];

}

// SVG icons
$icons = [
    'home' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
    'calendar' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
    'mentors' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>',
    'requests' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
    'chat' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
    'settings' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    'users' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'reports' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
];
?>

<style>
.bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #fff;
    border-top: 4px solid #06b6d4;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.08);
    z-index: 1000;
}
.bottom-nav-inner {
    max-width: 480px;
    margin: 0 auto;
    display: flex;
    justify-content: space-around;
    align-items: center;
    height: 72px;
    padding: 0 8px;
}
.nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
    gap: 4px;
    text-decoration: none;
    color: #9ca3af;
    transition: color 0.15s;
}
.nav-item:hover {
    color: #6b7280;
}
.nav-item.active {
    color: #111827;
}
.nav-item.active svg {
    stroke-width: 2.5;
}
.nav-label {
    font-size: 0.7rem;
    font-weight: 500;
}
.nav-item.active .nav-label {
    font-weight: 600;
}
body {
    padding-bottom: 80px;
}
</style>

<nav class="bottom-nav">
    <div class="bottom-nav-inner">
        <?php foreach ($navItems as $item): 
            $isActive = ($currentPage === $item['name']);
        ?>
        <a href="<?php echo $item['path']; ?>" class="nav-item <?php echo $isActive ? 'active' : ''; ?>">
            <?php echo $icons[$item['icon']]; ?>
            <span class="nav-label"><?php echo $item['label']; ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</nav>
