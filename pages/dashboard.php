<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'student';
$first_name = htmlspecialchars($_SESSION['first_name'] ?? 'there');
$avatar_url = '';
$fallback_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($first_name) . '&background=3b82f6&color=fff&size=128';

function getStudentUnreadMessagesCount(PDO $pdo, int $userId): int
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND read_at IS NULL');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

if ($user_role === 'student' && (($_GET['live_recent_messages'] ?? '') === '1')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $recent_count = getStudentUnreadMessagesCount($pdo, (int)$user_id);
    echo json_encode(['count' => $recent_count]);
    exit;
}

// Fetch user's profile picture directly from database
$profile_picture = null;
try {
    $stmt = $pdo->prepare('SELECT profile_picture FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $profile_picture = $stmt->fetchColumn();
} catch (PDOException $e) {
    $profile_picture = null;
}

// Fetch avatar if available
if (!empty($profile_picture)) {
    $avatar_url = '../' . htmlspecialchars($profile_picture);
} else {
    $avatar_url = $fallback_avatar;
}

// ============ STUDENT DATA ============
if ($user_role === 'student') {
    // Fetch student profile
    $profile = null;
    try {
        $stmt = $pdo->prepare('SELECT * FROM students WHERE student_id = ?');
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $profile = null;
    }

    // Fetch student stats
    $connections = 0;
    $pending = 0;
    $messages = getStudentUnreadMessagesCount($pdo, (int)$user_id);
    if ($profile) {
        
        // Count accepted mentor connections
        $sql = "SELECT COUNT(DISTINCT mentor_id) FROM mentor_requests WHERE student_id = ? AND status IN ('accepted')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $connections = (int)$stmt->fetchColumn();

        // Count pending requests
        $sql = "SELECT COUNT(*) FROM mentor_requests WHERE student_id = ? AND status = 'pending'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $pending = (int)$stmt->fetchColumn();
    }
}

// ============ MENTOR DATA ============
if ($user_role === 'mentor') {
    // Check mentor application status
    $application_status = null;
    try {
        $stmt = $pdo->prepare('SELECT status FROM mentor_applications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1');
        $stmt->execute([$user_id]);
        $application_status = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $application_status = null;
    }

    // Fetch mentor profile
    $mentor_profile = null;
    try {
        $stmt = $pdo->prepare('SELECT * FROM mentor_profiles WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $mentor_profile = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mentor_profile = null;
    }

    // Fetch mentor stats
    $active_students = 0;
    $pending_requests = 0;
    $messages = 0;
    $avg_rating = null;

    // Count active students
    $sql = "SELECT COUNT(DISTINCT student_id) FROM mentor_student_matches WHERE mentor_id = ? AND active = '1'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $active_students = (int)$stmt->fetchColumn();

    // Count pending requests
    $sql = "SELECT COUNT(*) FROM mentor_requests WHERE mentor_id = ? AND status = 'pending'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $pending_requests = (int)$stmt->fetchColumn();

    // Count messages
    $sql = "SELECT COUNT(*) FROM messages WHERE sender_id = ? OR receiver_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_id]);
    $messages = (int)$stmt->fetchColumn();

    // Get average rating (supports both legacy `reviews` and newer `mentor_ratings` schemas)
    $hasMentorRatingsTable = false;
    $hasReviewsTable = false;

    try {
        $tableStmt = $pdo->query("SHOW TABLES LIKE 'mentor_ratings'");
        $hasMentorRatingsTable = (bool)$tableStmt->fetchColumn();
    } catch (PDOException $e) {
        $hasMentorRatingsTable = false;
    }

    try {
        $tableStmt = $pdo->query("SHOW TABLES LIKE 'reviews'");
        $hasReviewsTable = (bool)$tableStmt->fetchColumn();
    } catch (PDOException $e) {
        $hasReviewsTable = false;
    }

    try {
        if ($hasMentorRatingsTable) {
            $stmt = $pdo->prepare('SELECT ROUND(AVG(rating), 1) FROM mentor_ratings WHERE mentor_id = ?');
            $stmt->execute([$user_id]);
            $avg_rating = $stmt->fetchColumn();
        }

        if (($avg_rating === null || $avg_rating === false || $avg_rating === '') && $hasReviewsTable) {
            $stmt = $pdo->prepare('SELECT ROUND(AVG(rating), 1) FROM reviews WHERE mentor_id = ?');
            $stmt->execute([$user_id]);
            $avg_rating = $stmt->fetchColumn();
        }
    } catch (PDOException $e) {
        $avg_rating = null;
    }

    $avg_rating = ($avg_rating !== null && $avg_rating !== false && $avg_rating !== '')
        ? number_format((float)$avg_rating, 1)
        : 'N/A';
}

// ============ ADMIN DATA ============
if ($user_role === 'admin') {
    // Count pending mentor applications
    $pending_applications = 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM mentor_applications WHERE status = 'pending'");
        $pending_applications = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $pending_applications = 0;
    }

    // Count total users
    $total_users = 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $total_users = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $total_users = 0;
    }

    // Count total mentors
    $total_mentors = 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM mentor_profiles WHERE verified = 1");
        $total_mentors = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $total_mentors = 0;
    }

    // Count total students
    $total_students = 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
        $total_students = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $total_students = 0;
    }

    // Count total sessions
    $total_sessions = 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM sessions");
        $total_sessions = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $total_sessions = 0;
    }

    // Count active sessions (confirmed)
    $active_sessions = 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM sessions WHERE status = 'confirmed'");
        $active_sessions = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $active_sessions = 0;
    }
}
?>

<!-- HTML -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | Mentor Match</title>
    <meta name="description" content="Your dashboard for Mentor Match.">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

<!--  Sticky Header -->
<header class="dash-header">
    <div class="dash-header-inner">
        <a href="dashboard.php" class="dash-brand">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="8" r="3" fill="#3b82f6"/>
                <path d="M3 20c0-3.866 3.582-7 9-7s9 3.134 9 7" stroke="#111827" stroke-opacity=".06" stroke-width="1.5"/>
            </svg>
            <span class="dash-brand-text">Mentor Match</span>
        </a>
        <img src="<?php echo $avatar_url; ?>" alt="Profile" class="dash-avatar" data-fallback="<?php echo $fallback_avatar; ?>" onerror="this.onerror=null;this.src=this.dataset.fallback;" />
    </div>
</header>

<!-- Main Content -->
<main class="dash-main">

    <!-- Welcome -->
    <div class="welcome-section">
        <h1 class="welcome-greeting">Welcome back, <?php echo $first_name; ?>!</h1>

        <?php if ($user_role === 'student'): ?>
            <p class="welcome-lead">Discover amazing mentors</p>
        <?php elseif ($user_role === 'admin'): ?>
            <p class="welcome-lead">Platform administration</p>
        <?php else: ?>
            <?php if ($application_status === 'pending'): ?>
                <p class="welcome-lead pending-status">Your mentor application is under review</p>
            <?php elseif ($application_status === 'rejected'): ?>
                <p class="welcome-lead rejected-status">Your application needs attention</p>
            <?php else: ?>
                <p class="welcome-lead">Help students reach their potential</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($user_role === 'student'): ?>
        <!-- ============ STUDENT VIEW ============ -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?php echo $connections; ?></div>
                <div class="stat-label">Active Mentors</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $pending; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="recent-messages-count"><?php echo $messages; ?></div>
                <?php if ($messages === 1): ?>
                    <div class="stat-label" id="recent-messages-label">Message</div>
                <?php else: ?>
                    <div class="stat-label" id="recent-messages-label">Messages</div>
                <?php endif; ?>
            </div>
        </div>

        <h2 class="section-title">Quick Actions</h2>
        <div class="actions-grid">
            <a href="mentor_swipe.php" class="action-card primary">
                <div class="action-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                </div>
                <div class="action-body">
                    <div class="action-title">Find Mentors</div>
                    <div class="action-desc">Swipe and connect with mentors</div>
                </div>
                <svg class="action-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            <a href="chat.php" class="action-card secondary">
                <div class="action-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                </div>
                <div class="action-body">
                    <div class="action-title">My Chats</div>
                    <div class="action-desc">Message your connected mentors</div>
                </div>
                <svg class="action-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            <a href="chatbot.php" class="action-card secondary">
                <div class="action-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/></svg>
                </div>
                <div class="action-body">
                    <div class="action-title">University Assistant</div>
                    <div class="action-desc">Get help with university questions</div>
                </div>
                <svg class="action-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            <a href="profile.php" class="action-card secondary">
                <div class="action-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg>
                </div>
                <div class="action-body">
                    <div class="action-title">My Profile</div>
                    <div class="action-desc">Update your info and preferences</div>
                </div>
                <svg class="action-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        </div>

    <?php elseif ($user_role === 'admin'): ?>
        <!-- ============ ADMIN VIEW ============ -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?php echo $pending_applications; ?></div>
                <?php if ($pending_applications === 1): ?>
                    <div class="stat-label">Pending App</div>
                <?php else: ?>
                    <div class="stat-label">Pending Apps</div>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_mentors; ?></div>
                <?php if ($total_mentors === 1): ?>
                    <div class="stat-label">Mentor</div>
                <?php else: ?>
                    <div class="stat-label">Mentors</div>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_students; ?></div>
                <?php if ($total_students === 1): ?>
                    <div class="stat-label">Student</div>
                <?php else: ?>
                    <div class="stat-label">Students</div>
                <?php endif; ?>
            </div>
        </div>

        <h2 class="section-title">Quick Actions</h2>
        <div class="actions-grid">
            <a href="../users/admin/manage_applications.php" class="action-card primary">
                <div class="action-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <div class="action-body">
                    <div class="action-title">Mentor Applications</div>
                    <div class="action-desc">Review pending mentor applications</div>
                </div>
                <svg class="action-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            <a href="platform_report.php" class="action-card secondary">
                <div class="action-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                </div>
                <div class="action-body">
                    <div class="action-title">Reports</div>
                    <div class="action-desc">View platform analytics</div>
                </div>
                <svg class="action-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            <a href="manage_users.php" class="action-card secondary">
                <div class="action-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div class="action-body">
                    <div class="action-title">Manage Users</div>
                    <div class="action-desc">View and manage all users</div>
                </div>
                <svg class="action-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </a>
            <a href="session_monitor.php" class="action-card secondary">
                <div class="action-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <div class="action-body">
                    <div class="action-title">Sessions</div>
                    <div class="action-desc">Monitor mentoring sessions</div>
                </div>
                <svg class="action-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        </div>

    <?php else: ?>
        <!-- ============ MENTOR VIEW ============ -->
        <?php if ($application_status === 'approved' || $mentor_profile): ?>
            <!-- Approved Mentor Stats -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $active_students; ?></div>
                    <?php if ($active_students === 1): ?>
                        <div class="stat-label">Active Student</div>
                    <?php else: ?>
                        <div class="stat-label">Active Students</div>
                    <?php endif; ?>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $pending_requests; ?></div>
                    <?php if ($pending_requests === 1): ?>
                        <div class="stat-label">Pending Request</div>
                    <?php else: ?>
                        <div class="stat-label">Pending Requests</div>
                    <?php endif; ?>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $avg_rating; ?></div>
                    <div class="stat-label">Rating</div>
                </div>
            </div>

            <h2 class="section-title">Quick Actions</h2>
            <div class="actions-grid">
                <a href="requests.php" class="action-card primary">
                    <div class="action-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    </div>
                    <div class="action-body">
                        <div class="action-title">View Requests</div>
                        <div class="action-desc">Review student connection requests</div>
                    </div>
                    <svg class="action-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </a>
                <a href="chat.php" class="action-card secondary">
                    <div class="action-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    </div>
                    <div class="action-body">
                        <div class="action-title">My Chats</div>
                        <div class="action-desc">Message your connected students</div>
                    </div>
                    <svg class="action-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </a>
                <a href="availability.php" class="action-card secondary">
                    <div class="action-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div class="action-body">
                        <div class="action-title">Set Availability</div>
                        <div class="action-desc">Manage your available time slots</div>
                    </div>
                    <svg class="action-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </a>
                <a href="profile.php" class="action-card secondary">
                    <div class="action-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg>
                    </div>
                    <div class="action-body">
                        <div class="action-title">My Profile</div>
                        <div class="action-desc">Update your mentor profile</div>
                    </div>
                    <svg class="action-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            </div>

        <?php elseif ($application_status === 'pending'): ?>
            <!-- Pending Application -->
            <div class="status-card pending">
                <div class="status-icon">&#9203;</div>
                <h2>Application Under Review</h2>
                <p>Thank you for applying to be a mentor! Our team is reviewing your application. You'll receive an email within 24-48 hours.</p>
            </div>

        <?php elseif ($application_status === 'rejected'): ?>
            <!-- Rejected Application -->
            <div class="status-card rejected">
                <div class="status-icon">&#10060;</div>
                <h2>Application Not Approved</h2>
                <p>Unfortunately, your mentor application was not approved at this time.</p>
                <a href="mailto:support@mentormatch.com" class="btn-status">Contact Support</a>
            </div>

        <?php else: ?>
            <!-- No Application Yet -->
            <div class="status-card">
                <div class="status-icon">&#128221;</div>
                <h2>Become a Mentor</h2>
                <p>Complete your mentor application to start helping students succeed.</p>
                <a href="../users/mentor/mentor_application.php" class="btn-status">Apply Now</a>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</main>

<?php include '../includes/nav.php'; ?>

<?php if ($user_role === 'student'): ?>
<script>
    (function () {
        const countEl = document.getElementById('recent-messages-count');
        const labelEl = document.getElementById('recent-messages-label');

        if (!countEl || !labelEl) {
            return;
        }

        const updateRecentMessagesCount = async function () {
            try {
                const response = await fetch('dashboard.php?live_recent_messages=1&_=' + Date.now(), {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                const nextCount = Number(payload.count);

                if (!Number.isFinite(nextCount) || nextCount < 0) {
                    return;
                }

                countEl.textContent = String(nextCount);
                labelEl.textContent = nextCount === 1 ? 'Message' : 'Messages';
            } catch (error) {
                return;
            }
        };

        setInterval(updateRecentMessagesCount, 15000);
    })();
</script>
<?php endif; ?>
</body>
</html>
