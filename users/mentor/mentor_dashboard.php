<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../pages/login.php');
    exit;
}

// Ensure user is a mentor
if ($_SESSION['role'] !== 'mentor') {
    header('Location: ../../pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = htmlspecialchars($_SESSION['first_name'] ?? 'there');
$avatar_url = '';
$fallback_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($first_name) . '&background=3b82f6&color=fff&size=128';

// Fetch user's profile picture directly from database
$profile_picture = null;
try {
    $stmt = $pdo->prepare('SELECT profile_picture FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $profile_picture = $stmt->fetchColumn();
} catch (PDOException $e) {
    $profile_picture = null;
}

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

// Fetch stats
$active_students = 0;
$pending_requests = 0;
$messages = 0;
$avg_rating = null;

// Count active students (sessions with confirmed/completed status)
$sql = "SELECT COUNT(DISTINCT student_id) FROM sessions WHERE mentor_id = ? AND status IN ('confirmed','completed')";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$active_students = (int)$stmt->fetchColumn();

// Count pending requests (sessions with pending status)
$sql = "SELECT COUNT(*) FROM sessions WHERE mentor_id = ? AND status = 'pending'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$pending_requests = (int)$stmt->fetchColumn();

// Count messages
$sql = "SELECT COUNT(*) FROM messages WHERE sender_id = ? OR receiver_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $user_id]);
$messages = (int)$stmt->fetchColumn();

// Get average rating
$sql = "SELECT AVG(rating) FROM reviews WHERE mentor_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$avg_rating = $stmt->fetchColumn();
$avg_rating = $avg_rating ? number_format((float)$avg_rating, 1) : 'N/A';

// Fetch avatar if available
if (!empty($profile_picture)) {
    $avatar_url = '../../' . htmlspecialchars($profile_picture);
} else {
    $avatar_url = $fallback_avatar;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mentor Dashboard — Mentor Match</title>
    <meta name="description" content="Your mentor dashboard for Mentor Match.">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
                        <div class="stat-label">Active Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $pending_requests; ?></div>
                        <div class="stat-label">Requests</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $avg_rating; ?></div>
                        <div class="stat-label">Rating</div>
                    </div>
                </div>

                <h2 class="section-title">Quick Actions</h2>
                <div class="actions-grid">
                    <a href="../../pages/requests.php" class="action-card">
                        <div class="action-icon">📥</div>
                        <div>
                            <div class="action-title">View Requests</div>
                            <div class="action-desc">Review student connection requests</div>
                        </div>
                    </a>
                    <a href="../../pages/chat.php" class="action-card white">
                        <div class="action-icon">💬</div>
                        <div>
                            <div class="action-title">My Chats</div>
                            <div class="action-desc">Message your connected students</div>
                        </div>
                    </a>
                    <a href="../../pages/availability.php" class="action-card white">
                        <div class="action-icon">📅</div>
                        <div>
                            <div class="action-title">Set Availability</div>
                            <div class="action-desc">Manage your available time slots</div>
                        </div>
                    </a>
                    <a href="mentor_profile.php" class="action-card white">
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
                    <a href="mentor_application.php" class="btn" style="margin-top:12px;">Apply Now</a>
                </div>
            <?php endif; ?>

            <a href="../../pages/login.php?logout=1" class="logout-link">Log out</a>
        </section>
    </main>

    <style>
        .status-card {
            background: var(--bg);
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin: 20px 0;
        }
        .status-card.pending {
            background: #fef3c7;
            border: 1px solid #f59e0b;
        }
        .status-card.rejected {
            background: #fee2e2;
            border: 1px solid #ef4444;
        }
        .status-icon {
            font-size: 3rem;
            margin-bottom: 12px;
        }
        .status-card h2 {
            font-size: 1.1rem;
            margin: 0 0 8px;
            color: #111827;
        }
        .status-card p {
            font-size: 0.9rem;
            color: var(--muted);
            margin: 0;
        }
    </style>

    <?php include '../../includes/nav.php'; ?>
</body>
</html>
