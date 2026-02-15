<?php
session_start();
require_once '../../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../pages/login.php');
    exit;
}

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../pages/dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
    $action = $_POST['action'] ?? '';
    
    if ($application_id > 0) {
        try {
            if ($action === 'approve') {
                // Update application status
                $stmt = $pdo->prepare("UPDATE mentor_applications SET status = 'approved', reviewed_at = NOW() WHERE id = ?");
                $stmt->execute([$application_id]);
                
                // Get user_id from application
                $stmt = $pdo->prepare("SELECT user_id, experience_years FROM mentor_applications WHERE id = ?");
                $stmt->execute([$application_id]);
                $app = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($app) {
                    // Update user role to mentor
                    $stmt = $pdo->prepare("UPDATE users SET role = 'mentor' WHERE id = ?");
                    $stmt->execute([$app['user_id']]);
                    
                    // Create mentor profile
                    $stmt = $pdo->prepare("INSERT INTO mentor_profiles (user_id, experience_years, verified) VALUES (?, ?, 1)");
                    $stmt->execute([$app['user_id'], $app['experience_years']]);
                    $mentor_id = $pdo->lastInsertId();
                    
                    // Copy subjects from application to mentor_subjects
                    $stmt = $pdo->prepare("SELECT subject_id FROM mentor_application_subjects WHERE application_id = ?");
                    $stmt->execute([$application_id]);
                    $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if ($subjects) {
                        $insert_stmt = $pdo->prepare("INSERT INTO mentor_subjects (mentor_id, subject_id) VALUES (?, ?)");
                        foreach ($subjects as $subject_id) {
                            $insert_stmt->execute([$mentor_id, $subject_id]);
                        }
                    }
                }
                
                $message = "Application approved successfully!";
                
            } elseif ($action === 'reject') {
                $rejection_reason = trim($_POST['rejection_reason'] ?? '');
                if (empty($rejection_reason)) {
                    $rejection_reason = "Your application did not meet our current criteria.";
                }
                
                $stmt = $pdo->prepare("UPDATE mentor_applications SET status = 'rejected', admin_notes = ?, reviewed_at = NOW() WHERE id = ?");
                $stmt->execute([$rejection_reason, $application_id]);
                
                $message = "Application rejected.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred. Please try again.";
        }
    }
}

// Fetch pending mentor applications with user data and subjects
$pending_applications = [];
try {
    $sql = "
        SELECT 
            ma.id,
            ma.user_id,
            ma.motivation,
            ma.experience_years,
            ma.status,
            ma.submitted_at,
            u.first_name,
            u.last_name,
            u.email,
            u.profile_picture
        FROM mentor_applications ma
        JOIN users u ON ma.user_id = u.id
        WHERE ma.status = 'pending'
        ORDER BY ma.submitted_at ASC
    ";
    $stmt = $pdo->query($sql);
    $pending_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch subjects for each application
    foreach ($pending_applications as &$app) {
        $stmt = $pdo->prepare("
            SELECT s.name 
            FROM mentor_application_subjects mas 
            JOIN subjects s ON mas.subject_id = s.id 
            WHERE mas.application_id = ?
        ");
        $stmt->execute([$app['id']]);
        $app['subjects'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    unset($app);
    
} catch (PDOException $e) {
    $error = "Failed to load applications.";
}

$pending_count = count($pending_applications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#06b6d4">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Admin Panel — Mentor Match</title>
    <meta name="description" content="Admin panel for managing mentor applications.">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="../../assets/css/admin_dashboard.css">
    <style>
        /* Prevent text selection on buttons for touch */
        button, .btn-back, .btn-dashboard {
            -webkit-tap-highlight-color: transparent;
            -webkit-touch-callout: none;
            user-select: none;
        }
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <header class="admin-header">
            <div class="admin-header-inner">
                <div>
                    <h1>Admin Panel</h1>
                    <p>Manage mentor applications</p>
                </div>
                <a href="../../pages/dashboard.php" class="btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                    <span>Dashboard</span>
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="admin-content">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($pending_count > 0): ?>
                <!-- Section Header -->
                <div class="section-header">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <h2>Pending Applications (<?php echo $pending_count; ?>)</h2>
                </div>

                <!-- Applications List -->
                <div class="applications-list">
                    <?php foreach ($pending_applications as $app): ?>
                        <article class="application-card">
                            <div class="application-card-content">
                                <!-- Header -->
                                <div class="applicant-header">
                                    <div class="applicant-info">
                                        <?php 
                                        $avatar_url = '';
                                        if (!empty($app['profile_picture'])) {
                                            $avatar_url = '../../' . htmlspecialchars($app['profile_picture']);
                                        }
                                        $full_name = htmlspecialchars($app['first_name'] . ' ' . $app['last_name']);
                                        $fallback_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($full_name) . '&background=06b6d4&color=fff&size=128';
                                        ?>
                                        
                                        <?php if ($avatar_url): ?>
                                            <img src="<?php echo $avatar_url; ?>" alt="<?php echo $full_name; ?>" class="applicant-avatar" onerror="this.onerror=null;this.src='<?php echo $fallback_avatar; ?>';">
                                        <?php else: ?>
                                            <div class="applicant-avatar-placeholder">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="applicant-details">
                                            <h3><?php echo $full_name; ?></h3>
                                            <div class="applicant-email">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                                                </svg>
                                                <span><?php echo htmlspecialchars($app['email']); ?></span>
                                            </div>
                                            <span class="badge badge-pending">Pending Review</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Subjects -->
                                <?php if (!empty($app['subjects'])): ?>
                                <div class="info-section">
                                    <h4>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                        </svg>
                                        Subjects
                                    </h4>
                                    <div class="subjects-list">
                                        <?php foreach ($app['subjects'] as $subject): ?>
                                            <span class="badge badge-subject"><?php echo htmlspecialchars($subject); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Motivation/Bio -->
                                <div class="info-section">
                                    <h4>Motivation</h4>
                                    <p><?php echo nl2br(htmlspecialchars($app['motivation'])); ?></p>
                                </div>

                                <!-- Experience -->
                                <?php if (!empty($app['experience_years'])): ?>
                                <div class="info-section">
                                    <h4>Experience</h4>
                                    <p><?php echo htmlspecialchars($app['experience_years']); ?> year<?php echo $app['experience_years'] != 1 ? 's' : ''; ?> of experience</p>
                                </div>
                                <?php endif; ?>

                                <!-- Application Date -->
                                <div class="application-date">
                                    Applied on <?php echo date('F j, Y', strtotime($app['submitted_at'])); ?>
                                </div>

                                <!-- Action Buttons -->
                                <div id="actions-<?php echo $app['id']; ?>">
                                    <div class="action-buttons">
                                        <form method="POST" style="flex: 1; display: contents;">
                                            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn-approve">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                                                </svg>
                                                Approve
                                            </button>
                                        </form>
                                        <button type="button" class="btn-reject" onclick="showRejectForm(<?php echo $app['id']; ?>)">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                                            </svg>
                                            Reject
                                        </button>
                                    </div>
                                </div>

                                <!-- Rejection Form (Hidden by default) -->
                                <div id="reject-form-<?php echo $app['id']; ?>" class="rejection-form" style="display: none;">
                                    <form method="POST">
                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <textarea name="rejection_reason" placeholder="Enter rejection reason (optional)" rows="3"></textarea>
                                        <div class="rejection-actions">
                                            <button type="submit" class="btn-confirm-reject">Confirm Rejection</button>
                                            <button type="button" class="btn-cancel" onclick="hideRejectForm(<?php echo $app['id']; ?>)">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <h2>All caught up!</h2>
                    <p>No pending mentor applications to review.</p>
                    <a href="../../pages/dashboard.php" class="btn-dashboard">Back to Dashboard</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function showRejectForm(id) {
            document.getElementById('actions-' + id).style.display = 'none';
            document.getElementById('reject-form-' + id).style.display = 'block';
        }
        
        function hideRejectForm(id) {
            document.getElementById('reject-form-' + id).style.display = 'none';
            document.getElementById('actions-' + id).style.display = 'block';
        }
    </script>
</body>
</html>
