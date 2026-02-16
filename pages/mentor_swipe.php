<?php
session_start();
require_once '../includes/db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'student';

// Only students can access this page
if ($user_role !== 'student') {
    header('Location: dashboard.php');
    exit;
}

// Handle AJAX swipe request - MUST be before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $mentor_id = intval($_POST['mentor_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($mentor_id && in_array($action, ['like', 'pass'])) {
        try {
            $status = $action === 'like' ? 'pending' : 'cancelled';
            
            // Check if request already exists
            $checkSql = "SELECT id FROM mentor_requests WHERE student_id = ? AND mentor_id = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$user_id, $mentor_id]);
            $existing = $checkStmt->fetch();
            
            if ($existing) {
                // Update existing request
                $sql = "UPDATE mentor_requests SET status = ?, requested_at = CURRENT_TIMESTAMP WHERE student_id = ? AND mentor_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$status, $user_id, $mentor_id]);
            } else {
                // Insert new request
                $sql = "INSERT INTO mentor_requests (student_id, mentor_id, status) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_id, $mentor_id, $status]);
            }
            
            echo json_encode(['success' => true, 'action' => $action, 'mentor_id' => $mentor_id]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid request', 'mentor_id' => $mentor_id, 'action' => $action]);
    }
    exit;
}

// Include the swipe card component
require_once '../components/swipe/swipe_card.php';

// Fetch mentors that the student hasn't swiped on yet
$mentors = [];
try {
    $sql = "
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.profile_picture,
            mp.bio,
            mp.experience_years,
            COALESCE(AVG(r.rating), 5) as rating,
            GROUP_CONCAT(DISTINCT s.name SEPARATOR '|||') as subjects
        FROM users u
        INNER JOIN mentor_profiles mp ON mp.user_id = u.id AND mp.verified = 1
        LEFT JOIN mentor_subjects ms ON ms.mentor_id = u.id
        LEFT JOIN subjects s ON s.id = ms.subject_id
        LEFT JOIN reviews r ON r.mentor_id = u.id
        WHERE u.role = 'mentor'
        AND u.id NOT IN (
            SELECT mentor_id FROM mentor_requests WHERE student_id = ?
        )
        GROUP BY u.id
        ORDER BY rating DESC, u.first_name ASC
        LIMIT 20
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $mentors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process subjects from GROUP_CONCAT
    foreach ($mentors as &$mentor) {
        $mentor['subjects'] = $mentor['subjects'] 
            ? explode('|||', $mentor['subjects']) 
            : ['General Tutoring', 'Academic Support', 'Study Skills'];
        $mentor['course'] = $mentor['subjects'][0] ?? 'General Studies';
        $mentor['year_of_study'] = 3; // Default, could be fetched from profile
        $mentor['linkedin_url'] = '#';
        $mentor['github_url'] = '#';
    }
    unset($mentor);
} catch (PDOException $e) {
    $mentors = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Find a Mentor — Mentor Match</title>
    <meta name="description" content="Swipe to find your perfect mentor match.">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/mentor_swipe.css">

</head>
<body>
    <main class="container">
        <div class="swipe-wrapper">
            <a href="dashboard.php" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Dashboard
            </a>

            <div class="page-header">
                <h1>Find Your Mentor</h1>
                <p>Swipe right to connect, left to pass</p>
            </div>

            <?php render_swipe_card_styles(); ?>

            <div class="swipe-container">
                <?php if (empty($mentors)): ?>
                    <div class="swipe-empty visible">
                        <div class="swipe-empty-icon">🎓</div>
                        <h3>No more mentors</h3>
                        <p>Check back later for new mentors!</p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_reverse($mentors) as $index => $mentor): ?>
                        <?php render_swipe_card($mentor, $index); ?>
                    <?php endforeach; ?>
                    
                    <div class="swipe-empty">
                        <div class="swipe-empty-icon">🎓</div>
                        <h3>You've seen all mentors!</h3>
                        <p>Check back later for new matches.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($mentors)): ?>
                <div class="swipe-actions">
                    <button class="swipe-btn swipe-btn-pass" onclick="swipePass()" title="Pass">
                        ✕
                    </button>
                    <button class="swipe-btn swipe-btn-like" onclick="swipeLike()" title="Like">
                        ♥
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php render_swipe_card_scripts(); ?>

    <script>
    // Handle swipe actions - send to server
    window.onMentorSwipe = function(mentorId, action) {
        fetch('mentor_swipe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `mentor_id=${mentorId}&action=${action}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(`Mentor ${mentorId}: ${action}`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    };
    </script>
</body>
</html>
