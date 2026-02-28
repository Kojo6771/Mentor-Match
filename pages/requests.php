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

// Only mentors can access this page
if ($user_role !== 'mentor') {
    header('Location: dashboard.php');
    exit;
}

// Get mentor_id from mentor_profiles table
$mentor_id = null;
try {
    $stmt = $pdo->prepare('SELECT mentor_id FROM mentor_profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $mentorRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($mentorRow) {
        $mentor_id = $mentorRow['mentor_id'];
    } else {
        // No mentor profile - redirect to dashboard
        header('Location: dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: dashboard.php');
    exit;
}

// Handle AJAX request for accepting/declining requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $request_id = intval($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($request_id && in_array($action, ['accept', 'decline'])) {
        try {
            // Verify this request belongs to the current mentor
            $checkStmt = $pdo->prepare('SELECT student_id, mentor_id FROM mentor_requests WHERE id = ? AND mentor_id = ?');
            $checkStmt->execute([$request_id, $mentor_id]);
            $request = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                echo json_encode(['success' => false, 'error' => 'Request not found or unauthorized']);
                exit;
            }
            
            $student_id = $request['student_id'];
            
            if ($action === 'accept') {
                // Start transaction
                $pdo->beginTransaction();
                
                // Update request status
                $updateStmt = $pdo->prepare('UPDATE mentor_requests SET status = ?, responded_at = CURRENT_TIMESTAMP WHERE id = ?');
                $updateStmt->execute(['accepted', $request_id]);
                
                // Insert into mentor_student_matches
                $matchStmt = $pdo->prepare('INSERT INTO mentor_student_matches (mentor_id, student_id) VALUES (?, ?)');
                $matchStmt->execute([$mentor_id, $student_id]);
                
                // Update student's mentor_id
                $studentStmt = $pdo->prepare('UPDATE students SET mentor_id = ? WHERE student_id = ?');
                $studentStmt->execute([$mentor_id, $student_id]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'action' => 'accepted']);
            } else {
                // Decline - just update status
                $updateStmt = $pdo->prepare('UPDATE mentor_requests SET status = ?, responded_at = CURRENT_TIMESTAMP WHERE id = ?');
                $updateStmt->execute(['rejected', $request_id]);
                echo json_encode(['success' => true, 'action' => 'declined']);
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
    }
    exit;
}

// Fetch mentor subjects
$mentorSubjects = [];
try {
    $stmt = $pdo->prepare('SELECT subject_id FROM mentor_subjects WHERE mentor_id = ?');
    $stmt->execute([$mentor_id]);
    $mentorSubjects = array_map(function($row) { return $row['subject_id']; }, $stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    $mentorSubjects = [];
}

// Fetch pending requests for this mentor
$requests = [];
try {
    $sql = "
        SELECT 
            mr.id,
            mr.student_id,
            mr.requested_at,
            s.course,
            s.year_of_study,
            s.learning_preference,
            s.bio,
            u.first_name,
            u.last_name,
            u.profile_picture
        FROM mentor_requests mr
        JOIN students s ON mr.student_id = s.student_id
        JOIN users u ON s.user_id = u.id
        WHERE mr.mentor_id = ? AND mr.status = 'pending'
        ORDER BY mr.requested_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$mentor_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch student interests for each request
    foreach ($requests as &$request) {
        $interestStmt = $pdo->prepare('SELECT interest_id FROM student_interests WHERE student_id = ?');
        $interestStmt->execute([$request['student_id']]);
        $request['student_interests'] = array_map(function($row) { return $row['interest_id']; }, $interestStmt->fetchAll(PDO::FETCH_ASSOC));
    }
} catch (PDOException $e) {
    $requests = [];
}
?>


<!-- HTML -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connection Requests — Mentor Match</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/requests.css">
</head>
<body>
    <main class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="header-row">
                <a href="dashboard.php" class="back-btn">← Back</a>
                <h1 class="page-title">Connection Requests</h1>
                <div class="filter-toggle-wrapper">
                    <label class="filter-label">Filter</label>
                    <button id="filterBtn" class="filter-toggle" title="Toggle filter by subject">
                        <span class="toggle-track"></span>
                        <span class="toggle-thumb"></span>
                    </button>
                </div>
            </div>
        </div>

        <div id="filterStatus" class="filter-status">Showing all requests</div>

        <!-- Main Content -->
        <div class="requests-container">
        <?php if (count($requests) > 0): ?>
            <div class="requests-list">
                <?php foreach ($requests as $request): ?>
                    <div class="request-card" data-request-id="<?php echo $request['id']; ?>" data-interests="<?php echo htmlspecialchars(json_encode($request['student_interests'])); ?>">
                        <div class="card-content">
                            <div class="student-info">
                                <?php if (!empty($request['profile_picture'])): ?>
                                    <img src="../<?php echo htmlspecialchars($request['profile_picture']); ?>" 
                                         alt="<?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>" 
                                         class="avatar">
                                <?php else: ?>
                                    <div class="avatar-placeholder">
                                        <svg class="avatar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                    </div>
                                <?php endif; ?>

                                <div class="student-details">
                                    <h3 class="student-name">
                                        <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                    </h3>
                                    <p class="student-meta">
                                        <?php echo htmlspecialchars($request['course']); ?> • Year <?php echo htmlspecialchars($request['year_of_study']); ?>
                                    </p>
                                    <?php if (!empty($request['learning_preference'])): ?>
                                    <p class="student-preference">
                                        📚 Prefers: <?php echo htmlspecialchars($request['learning_preference']); ?>
                                    </p>
                                    <?php endif; ?>

                                    <p class="student-bio">
                                        <?php echo !empty($request['bio']) ? htmlspecialchars($request['bio']) : 'No bio provided'; ?>
                                    </p>

                                    <div class="request-time">
                                        <svg class="clock-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span>Requested <?php echo date('M j, Y', strtotime($request['requested_at'])); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button class="btn btn-accept" onclick="handleRequest(<?php echo $request['id']; ?>, 'accept', this)">
                                    <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Accept
                                </button>
                                <button class="btn btn-decline" onclick="handleRequest(<?php echo $request['id']; ?>, 'decline', this)">
                                    <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Decline
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon-wrapper">
                    <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="empty-title">No pending requests</h2>
                <p class="empty-description">You're all caught up! New requests will appear here.</p>
                <a href="dashboard.php" class="btn-primary">Back to Dashboard</a>
            </div>
        <?php endif; ?>
        </div>
    </main>

    <script>
        function handleRequest(requestId, action, buttonElement) {
            // Disable both buttons in the card
            const card = buttonElement.closest('.request-card');
            const buttons = card.querySelectorAll('.btn');
            buttons.forEach(btn => {
                btn.disabled = true;
                // Show spinner in the clicked button
                if (btn === buttonElement) {
                    const originalContent = btn.innerHTML;
                    btn.innerHTML = '<div class="spinner"></div>';
                    btn.dataset.originalContent = originalContent;
                }
            });

            // Send AJAX request
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('request_id', requestId);
            formData.append('action', action);

            fetch('requests.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Fade out and remove the card
                    card.style.transition = 'opacity 0.3s, transform 0.3s';
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(20px)';
                    
                    setTimeout(() => {
                        card.remove();
                        
                        // Check if there are no more cards
                        const remainingCards = document.querySelectorAll('.request-card');
                        if (remainingCards.length === 0) {
                            // Reload to show empty state
                            window.location.reload();
                        }
                    }, 300);
                } else {
                    // Show error and re-enable buttons
                    alert('Error: ' + (data.error || 'Unknown error occurred'));
                    buttons.forEach(btn => {
                        btn.disabled = false;
                        if (btn.dataset.originalContent) {
                            btn.innerHTML = btn.dataset.originalContent;
                            delete btn.dataset.originalContent;
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                buttons.forEach(btn => {
                    btn.disabled = false;
                    if (btn.dataset.originalContent) {
                        btn.innerHTML = btn.dataset.originalContent;
                        delete btn.dataset.originalContent;
                    }
                });
            });
        }

        // Filter functionality
        const mentorSubjects = <?php echo json_encode($mentorSubjects); ?>;
        const filterBtn = document.getElementById('filterBtn');
        const filterStatus = document.getElementById('filterStatus');
        let filterBySubject = false;

        filterBtn.addEventListener('click', function() {
            filterBySubject = !filterBySubject;
            updateFilter();
        });

        function updateFilter() {
            const cards = document.querySelectorAll('.request-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const studentInterests = JSON.parse(card.dataset.interests || '[]');
                const hasMatchingSubject = studentInterests.some(interest => mentorSubjects.includes(interest));
                
                if (filterBySubject) {
                    // Show only cards with matching subjects
                    if (hasMatchingSubject) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                } else {
                    // Show all cards
                    card.style.display = '';
                    visibleCount++;
                }
            });

            // Update filter status and button
            if (filterBySubject) {
                filterStatus.textContent = 'Showing matching subjects only (' + visibleCount + ')';
                filterBtn.classList.add('filter-active');
            } else {
                filterStatus.textContent = 'Showing all requests (' + visibleCount + ')';
                filterBtn.classList.remove('filter-active');
            }

            // Show message if no cards match
            const requestsList = document.querySelector('.requests-list');
            if (requestsList && visibleCount === 0) {
                let emptyMsg = document.getElementById('filterEmptyMsg');
                if (!emptyMsg) {
                    emptyMsg = document.createElement('div');
                    emptyMsg.id = 'filterEmptyMsg';
                    emptyMsg.className = 'filter-empty-msg';
                    emptyMsg.textContent = 'No requests match your subjects';
                    requestsList.parentNode.insertBefore(emptyMsg, requestsList);
                }
                requestsList.style.display = 'none';
            } else if (requestsList) {
                requestsList.style.display = '';
                const emptyMsg = document.getElementById('filterEmptyMsg');
                if (emptyMsg) emptyMsg.remove();
            }
        }
    </script>

    <?php include '../includes/nav.php'; ?>
</body>
</html>
