<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
	header('Location: ../../pages/login.php');
	exit;
}

$user_id = $_SESSION['user_id'];
$first_name = htmlspecialchars($_SESSION['first_name'] ?? 'there');
$avatar_url = '';

// Fetch student profile
$profile = null;
try {
	$stmt = $pdo->prepare('SELECT * FROM students WHERE student_id = ?');
	$stmt->execute([$user_id]);
	$profile = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
	$profile = null;
}

// Fetch stats
$connections = 0;
$pending = 0;
$messages = 0;
if ($profile) {
	// Count accepted mentor connections (sessions with confirmed/completed status)
	$sql = "SELECT COUNT(DISTINCT mentor_id) FROM sessions WHERE student_id = ? AND status IN ('confirmed','completed')";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([$user_id]);
	$connections = (int)$stmt->fetchColumn();

	// Count pending requests (sessions with pending status)
	$sql = "SELECT COUNT(*) FROM sessions WHERE student_id = ? AND status = 'pending'";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([$user_id]);
	$pending = (int)$stmt->fetchColumn();

	// Count messages (all messages sent or received)
	$sql = "SELECT COUNT(*) FROM messages WHERE sender_id = ? OR receiver_id = ?";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([$user_id, $user_id]);
	$messages = (int)$stmt->fetchColumn();
}

// Fetch avatar if available
if (!empty($_SESSION['profile_picture'])) {
	$avatar_url = '../../uploads/profile_pictures/' . htmlspecialchars($_SESSION['profile_picture']);
} else {
	// fallback avatar
	$avatar_url = 'https://ui-avatars.com/api/?name=' . urlencode($first_name) . '&background=3b82f6&color=fff&size=128';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Dashboard — Mentor Match</title>
    <meta name="description" content="Your student dashboard for Mentor Match.">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <style>
        .container{padding:16px;align-items:flex-start;}
        .card{width:100%;max-width:480px;margin:0 auto;}
        .header-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:6px;}
        .avatar{width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid var(--accent);}
        h1{font-size:1.25rem;margin:0 0 4px;}
        .lead{margin:0 0 18px;}
        .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px;}
        .stat-card{background:var(--bg);border-radius:10px;padding:14px 10px;text-align:center;}
        .stat-value{font-size:1.5rem;font-weight:700;color:#111827;}
        .stat-label{font-size:0.8rem;color:var(--muted);margin-top:2px;}
        .section-title{font-size:1rem;font-weight:700;margin:0 0 10px;color:#111827;}
        .actions-grid{display:grid;gap:10px;}
        .action-card{display:flex;align-items:center;gap:12px;background:linear-gradient(90deg,var(--accent) 0%,#06b6d4 100%);color:#fff;border-radius:10px;padding:14px;text-decoration:none;transition:transform 0.15s,box-shadow 0.15s;}
        .action-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(59,130,246,0.18);}
        .action-icon{width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.2);border-radius:50%;font-size:1.2rem;flex-shrink:0;}
        .action-title{font-size:0.95rem;font-weight:600;}
        .action-desc{font-size:0.8rem;opacity:0.9;}
        .action-card.white{background:#fff;color:#111827;border:1px solid #e6e9ef;}
        .action-card.white .action-icon{background:#e0f2fe;color:#06b6d4;}
        .logout-link{display:block;text-align:center;margin-top:18px;color:var(--muted);font-size:0.85rem;}
        @media(max-width:400px){
            .stats-grid{grid-template-columns:1fr 1fr 1fr;}
            .stat-card{padding:10px 6px;}
            .stat-value{font-size:1.25rem;}
        }
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
                <img src="<?php echo $avatar_url; ?>" alt="Profile" class="avatar" />
            </div>
            <h1>Welcome back, <?php echo $first_name; ?>!</h1>
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
                    <div class="stat-value"><?php echo $messages; ?></div>
                    <div class="stat-label">Messages</div>
                </div>
            </div>

            <h2 class="section-title">Quick Actions</h2>
            <div class="actions-grid">
                <a href="../../pages/mentor_swipe.php" class="action-card">
                    <div class="action-icon">✨</div>
                    <div>
                        <div class="action-title">Find Mentors</div>
                        <div class="action-desc">Swipe and connect with mentors</div>
                    </div>
                </a>
                <a href="../../pages/chat.php" class="action-card white">
                    <div class="action-icon">💬</div>
                    <div>
                        <div class="action-title">My Chats</div>
                        <div class="action-desc">Message your connected mentors</div>
                    </div>
                </a>
                <a href="../../pages/chatbot.php" class="action-card white">
                    <div class="action-icon">🤖</div>
                    <div>
                        <div class="action-title">University Assistant</div>
                        <div class="action-desc">Get help with university questions</div>
                    </div>
                </a>
                <a href="student_profile_setup.php" class="action-card white">
                    <div class="action-icon">👤</div>
                    <div>
                        <div class="action-title">My Profile</div>
                        <div class="action-desc">Update your info and preferences</div>
                    </div>
                </a>
            </div>

            <a href="../../pages/login.php?logout=1" class="logout-link">Log out</a>
        </section>
    </main>
</body>
</html>