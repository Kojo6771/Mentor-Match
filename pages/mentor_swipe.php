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

// Get the student_id from students table
$student_id = null;
try {
    $studentStmt = $pdo->prepare('SELECT student_id FROM students WHERE user_id = ?');
    $studentStmt->execute([$user_id]);
    $studentRow = $studentStmt->fetch();
    if ($studentRow) {
        $student_id = $studentRow['student_id'];
    } else {
        // No student profile - redirect to profile setup
        header('Location: ../users/student/student_profile_setup.php');
        exit;
    }
} catch (PDOException $e) {
    // Database error
    header('Location: dashboard.php');
    exit;
}

// Handle AJAX swipe request 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'error' => 'Student profile not found. Please complete your profile first.']);
        exit;
    }
    
    $mentor_id = intval($_POST['mentor_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($mentor_id && in_array($action, ['like', 'pass'])) {
        try {
            $status = $action === 'like' ? 'pending' : 'cancelled';
            
            // Verify mentor exists
            $mentorCheck = $pdo->prepare("SELECT mentor_id FROM mentor_profiles WHERE mentor_id = ?");
            $mentorCheck->execute([$mentor_id]);
            if (!$mentorCheck->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Mentor not found', 'mentor_id' => $mentor_id]);
                exit;
            }
            
            // Check if request already exists
            $checkSql = "SELECT id FROM mentor_requests WHERE student_id = ? AND mentor_id = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$student_id, $mentor_id]);
            $existing = $checkStmt->fetch();
            
            if ($existing) {
                // Update existing request
                $sql = "UPDATE mentor_requests SET status = ?, requested_at = CURRENT_TIMESTAMP WHERE student_id = ? AND mentor_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$status, $student_id, $mentor_id]);
                echo json_encode(['success' => true, 'action' => $action, 'mentor_id' => $mentor_id, 'operation' => 'updated']);
            } else {
                // Insert new request
                $sql = "INSERT INTO mentor_requests (student_id, mentor_id, status) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$student_id, $mentor_id, $status]);
                echo json_encode(['success' => true, 'action' => $action, 'mentor_id' => $mentor_id, 'operation' => 'inserted', 'request_id' => $pdo->lastInsertId()]);
            }
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false, 
                'error' => 'Database error: ' . $e->getMessage(),
                'student_id' => $student_id,
                'mentor_id' => $mentor_id,
                'status' => $status ?? null
            ]);
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
            mp.mentor_id as id,
            u.first_name,
            u.last_name,
            u.profile_picture,
            mp.bio,
            mp.experience_years,
            mp.linkedin,
            mp.github,
            (SELECT ROUND(AVG(rating), 1) FROM mentor_ratings WHERE mentor_id = mp.mentor_id) as avg_rating,
            GROUP_CONCAT(DISTINCT s.name SEPARATOR '|||') as subjects
        FROM users u
        INNER JOIN mentor_profiles mp ON mp.user_id = u.id AND mp.verified = 1
        LEFT JOIN mentor_subjects ms ON ms.mentor_id = mp.mentor_id
        LEFT JOIN subjects s ON s.id = ms.subject_id
        WHERE u.role = 'mentor'
        AND NOT EXISTS (
            SELECT 1
            FROM mentor_requests mr
            WHERE mr.student_id = ?
              AND mr.mentor_id = mp.mentor_id
        )
        GROUP BY mp.mentor_id
        ORDER BY u.first_name ASC
        LIMIT 20
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id]);
    $mentors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process subjects from GROUP_CONCAT
    foreach ($mentors as &$mentor) {
        $mentor['subjects'] = $mentor['subjects'] 
            ? explode('|||', $mentor['subjects']) 
            : ['General Tutoring', 'Academic Support', 'Study Skills'];
        $mentor['course'] = $mentor['subjects'][0] ?? 'General Studies';
        $mentor['year_of_study'] = intval($mentor['experience_years'] ?? 1);
    }
    unset($mentor);
} catch (PDOException $e) {
    $mentors = [];
}
?>


<!-- HTML -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Find a Mentor — Mentor Match</title>
    <meta name="description" content="Swipe to find your perfect mentor match.">
    <link rel="stylesheet" href="../assets/css/mentor_swipe.css">
</head>
<body>

<!-- Sticky header -->
<header class="swipe-page-header">
    <div class="swipe-page-header-inner">
        <h1>Find Your Mentor</h1>
        <p>Swipe right to connect, left to pass</p>
    </div>
</header>

<main>
    <div class="swipe-wrapper">

        <?php render_swipe_card_styles(); ?>

        <div class="swipe-container">
            <?php if (empty($mentors)): ?>
                <div class="swipe-empty visible">
                    <div class="swipe-empty-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    </div>
                    <h3>No more mentors</h3>
                    <p>Check back later for new mentors!</p>
                </div>
            <?php else: ?>
                <?php foreach (array_reverse($mentors) as $index => $mentor): ?>
                    <?php render_swipe_card($mentor, $index); ?>
                <?php endforeach; ?>

                <div class="swipe-empty">
                    <div class="swipe-empty-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    </div>
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

    <?php include '../includes/nav.php'; ?>
</main>

    <?php render_swipe_card_scripts(); ?>

    <script>
    // Handle swipe actions - send to server
    window.onMentorSwipe = function(mentorId, action) {
        console.log('Sending swipe request:', { mentorId, action });
        
        fetch('mentor_swipe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `mentor_id=${mentorId}&action=${action}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Server response:', data);
            if (data.success) {
                console.log(`✓ Mentor ${mentorId}: ${action} - Request saved successfully`);
            } else {
                console.error('Failed to save request:', data.error);
                alert('Failed to save your choice: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error sending swipe request:', error);
            alert('Failed to save your choice. Please try again.');
        });
    };
    </script>
</body>
</html>
