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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — Mentor Match</title>
    <meta name="description" content="Your dashboard for Mentor Match.">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>

    </style>
</head>
<body>
    <main class="container">
        <section class="card">
            <div class="header-row">
                <div class="logo">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3" fill="var(--accent)"/><path d="M3 20c0-3.866 3.582-7 9-7s9 3.134 9 7" stroke="#111827" stroke-opacity=".06" stroke-width="1.5"/></svg>
                    <div class="brand">Mentor Match</div>
                </div>
                <img src="<?php echo $avatar_url; ?>" alt="Profile" class="avatar" data-fallback="<?php echo $fallback_avatar; ?>" onerror="this.onerror=null;this.src=this.dataset.fallback;" />
            </div>
            <h1>Welcome back, <?php echo $first_name; ?>!</h1>

            <?php if ($user_role === 'student'): ?>
                <!-- ============ STUDENT VIEW ============ -->
                <p class="lead">Discover amazing mentors</p>

                <div class="stats-grid">
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
                    <a href="mentor_swipe.php" class="action-card">
                        <div class="action-icon">✨</div>
                        <div>
                            <div class="action-title">Find Mentors</div>
                            <div class="action-desc">Swipe and connect with mentors</div>
                        </div>
                    </a>
                    <a href="chat.php" class="action-card white">
                        <div class="action-icon">💬</div>
                        <div>
                            <div class="action-title">My Chats</div>
                            <div class="action-desc">Message your connected mentors</div>
                        </div>
                    </a>
                    <a href="chatbot.php" class="action-card white">
                        <div class="action-icon">🤖</div>
                        <div>
                            <div class="action-title">University Assistant</div>
                            <div class="action-desc">Get help with university questions</div>
                        </div>
                    </a>
                    <a href="profile.php" class="action-card white">
                        <div class="action-icon">👤</div>
                        <div>
                            <div class="action-title">My Profile</div>
                            <div class="action-desc">Update your info and preferences</div>
                        </div>
                    </a>
                </div>

            <?php elseif($user_role === 'admin'): ?>
                <!-- ============ ADMIN VIEW ============ -->
                <p class="lead">Platform administration</p>

                <div class="stats-grid">
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
                    <a href="../users/admin/manage_applications.php" class="action-card">
                        <div class="action-icon">📋</div>
                        <div>
                            <div class="action-title">Mentor Applications</div>
                            <div class="action-desc">Review pending mentor applications</div>
                        </div>
                    </a>
                    <a href="platform_report.php" class="action-card white">
                        <div class="action-icon">📊</div>
                        <div>
                            <div class="action-title">Reports</div>
                            <div class="action-desc">View platform analytics</div>
                        </div>
                    </a>
                    <a href="manage_users.php" class="action-card white">
                        <div class="action-icon">👥</div>
                        <div>
                            <div class="action-title">Manage Users</div>
                            <div class="action-desc">View and manage all users</div>
                        </div>
                    </a>
                    <a href="session_monitor.php" class="action-card white">
                        <div class="action-icon">📅</div>
                        <div>
                            <div class="action-title">Sessions</div>
                            <div class="action-desc">Monitor mentoring sessions</div>
                        </div>
                    </a>
                </div>

            <?php else: ?>
                <!-- ============ MENTOR VIEW ============ -->
                <?php if ($application_status === 'pending'): ?>
                    <p class="lead" style="color:#f59e0b;">Your mentor application is under review</p>
                <?php elseif ($application_status === 'rejected'): ?>
                    <p class="lead" style="color:#ef4444;">Your application needs attention</p>
                <?php else: ?>
                    <p class="lead">Help students reach their potential</p>
                <?php endif; ?>

                <?php if ($application_status === 'approved' || $mentor_profile): ?>
                    <!-- Approved Mentor Stats -->
                    <div class="stats-grid">
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
                        <a href="requests.php" class="action-card">
                            <div class="action-icon">📥</div>
                            <div>
                                <div class="action-title">View Requests</div>
                                <div class="action-desc">Review student connection requests</div>
                            </div>
                        </a>
                        <a href="chat.php" class="action-card white">
                            <div class="action-icon">💬</div>
                            <div>
                                <div class="action-title">My Chats</div>
                                <div class="action-desc">Message your connected students</div>
                            </div>
                        </a>
                        <a href="availability.php" class="action-card white">
                            <div class="action-icon">📅</div>
                            <div>
                                <div class="action-title">Set Availability</div>
                                <div class="action-desc">Manage your available time slots</div>
                            </div>
                        </a>
                        <a href="profile.php" class="action-card white">
                            <div class="action-icon">👤</div>
                            <div>
                                <div class="action-title">My Profile</div>
                                <div class="action-desc">Update your mentor profile</div>
                            </div>
                        </a>
                    </div>

                <?php elseif ($application_status === 'pending'): ?>
                    <!-- Pending Application -->
                    <div class="status-card pending">
                        <div class="status-icon">⏳</div>
                        <h2>Application Under Review</h2>
                        <p>Thank you for applying to be a mentor! Our team is reviewing your application. You'll receive an email within 24-48 hours.</p>
                    </div>

                <?php elseif ($application_status === 'rejected'): ?>
                    <!-- Rejected Application -->
                    <div class="status-card rejected">
                        <div class="status-icon">❌</div>
                        <h2>Application Not Approved</h2>
                        <p>Unfortunately, your mentor application was not approved at this time.</p>
                        <a href="mailto:support@mentormatch.com" class="btn" style="margin-top:12px;">Contact Support</a>
                    </div>

                <?php else: ?>
                    <!-- No Application Yet -->
                    <div class="status-card">
                        <div class="status-icon">📝</div>
                        <h2>Become a Mentor</h2>
                        <p>Complete your mentor application to start helping students succeed.</p>
                        <a href="../users/mentor/mentor_application.php" class="btn" style="margin-top:12px;">Apply Now</a>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </section>
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
