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
} catch (PDOException $e) {
    $requests = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connection Requests — Mentor Match</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        :root {
            --bg-black: #000000;
            --bg-zinc-900: #18181b;
            --bg-zinc-800: #27272a;
            --border-zinc-800: #27272a;
            --border-zinc-700: #3f3f46;
            --text-white: #ffffff;
            --text-zinc-300: #d4d4d8;
            --text-zinc-400: #a1a1aa;
            --text-zinc-500: #71717a;
            --text-zinc-600: #52525b;
            --green-500: #22c55e;
            --green-600: #16a34a;
            --yellow-500: #eab308;
            --yellow-400: #facc15;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--bg-black);
            color: var(--text-white);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            min-height: 100vh;
        }

        /* Header */
        .page-header {
            border-bottom: 1px solid var(--border-zinc-800);
            background: rgba(24, 24, 27, 0.5);
            backdrop-filter: blur(10px);
        }

        .header-container {
            max-width: 896px;
            margin: 0 auto;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .back-btn {
            background: transparent;
            border: none;
            color: var(--text-zinc-400);
            font-size: 0.95rem;
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: color 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .back-btn:hover {
            color: var(--text-white);
            background: rgba(255, 255, 255, 0.05);
        }

        .page-title {
            font-size: 1.25rem;
            font-weight: 700;
        }

        .spacer {
            width: 80px;
        }

        /* Main Content */
        .main-content {
            max-width: 896px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        /* Request Cards */
        .requests-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .request-card {
            background: var(--bg-zinc-900);
            border: 1px solid var(--border-zinc-800);
            border-radius: 12px;
            padding: 1.5rem;
        }

        .card-content {
            display: flex;
            gap: 1.5rem;
            align-items: flex-start;
        }

        .student-info {
            flex: 1;
            display: flex;
            gap: 1rem;
        }

        .avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .avatar-placeholder {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--bg-zinc-800);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .avatar-icon {
            width: 32px;
            height: 32px;
            color: var(--text-zinc-600);
        }

        .student-details {
            flex: 1;
        }

        .student-name {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .student-meta {
            color: var(--text-zinc-400);
            font-size: 0.875rem;
            margin-bottom: 0.75rem;
        }

        .student-bio {
            color: var(--text-zinc-300);
            font-size: 0.875rem;
            margin-top: 0.75rem;
            line-height: 1.5;
        }

        .request-time {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-zinc-500);
            font-size: 0.75rem;
            margin-top: 0.75rem;
        }

        .clock-icon {
            width: 12px;
            height: 12px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-accept {
            background: var(--green-500);
            color: white;
        }

        .btn-accept:hover:not(:disabled) {
            background: var(--green-600);
        }

        .btn-decline {
            background: transparent;
            color: var(--text-zinc-400);
            border: 1px solid var(--border-zinc-700);
        }

        .btn-decline:hover:not(:disabled) {
            background: var(--bg-zinc-800);
        }

        .btn-icon {
            width: 16px;
            height: 16px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 5rem 1rem;
        }

        .empty-icon-wrapper {
            width: 80px;
            height: 80px;
            background: var(--bg-zinc-900);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .empty-icon {
            width: 40px;
            height: 40px;
            color: var(--text-zinc-600);
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .empty-description {
            color: var(--text-zinc-400);
            margin-bottom: 2rem;
        }

        .btn-primary {
            background: var(--yellow-500);
            color: black;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: background 0.2s;
        }

        .btn-primary:hover {
            background: var(--yellow-400);
        }

        /* Loading Spinner */
        .spinner {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .card-content {
                flex-direction: column;
            }

            .action-buttons {
                flex-direction: row;
                width: 100%;
            }

            .btn {
                flex: 1;
                justify-content: center;
            }

            .header-container {
                padding: 0.75rem 1rem;
            }

            .page-title {
                font-size: 1.125rem;
            }

            .spacer {
                width: 64px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="page-header">
        <div class="header-container">
            <a href="dashboard.php" class="back-btn">← Back</a>
            <h1 class="page-title">Connection Requests</h1>
            <div class="spacer"></div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <?php if (count($requests) > 0): ?>
            <div class="requests-list">
                <?php foreach ($requests as $request): ?>
                    <div class="request-card" data-request-id="<?php echo $request['id']; ?>">
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

                                    <?php if (!empty($request['bio'])): ?>
                                        <p class="student-bio">
                                            <?php echo htmlspecialchars($request['bio']); ?>
                                        </p>
                                    <?php endif; ?>

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

            fetch('mentor_request.php', {
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
    </script>
</body>
</html>
