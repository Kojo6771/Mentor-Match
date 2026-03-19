<?php
session_start();
require_once '../includes/db.php';

/* ── Admin only ── */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$admin_id = (int)$_SESSION['user_id'];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'send_warning') {
    header('Content-Type: application/json; charset=utf-8');

    $receiver_id = (int)($_POST['receiver_id'] ?? 0);
    $message     = trim($_POST['message'] ?? '');

    if ($receiver_id <= 0 || $message === '') {
        echo json_encode(['ok' => false, 'error' => 'Invalid recipient or empty message.']);
        exit;
    }

    // Verify recipient exists and is a mentor
    try {
        $chk = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role = ?');
        $chk->execute([$receiver_id, 'mentor']);
        if (!$chk->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Recipient not found.']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => 'Database error.']);
        exit;
    }

    // Insert admin message
    try {
        $ins = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message, sent_at) VALUES (?, ?, ?, NOW())');
        $ins->execute([$admin_id, $receiver_id, $message]);
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => 'Failed to send message.']);
    }
    exit;
}

/* ================================================================
   DATA QUERIES
   ================================================================ */

/* ── 1. All sessions with participant names ── */
$sessions = [];
try {
    $stmt = $pdo->query("
        SELECT s.id, s.title, s.session_date, s.start_time, s.end_time, s.status, s.created_at,
               CONCAT(um.first_name, ' ', um.last_name) AS mentor_name,
               um.id AS mentor_user_id,
               um.profile_picture AS mentor_picture,
               CONCAT(us.first_name, ' ', us.last_name) AS student_name,
               us.id AS student_user_id,
               sub.name AS subject_name
        FROM sessions s
        JOIN users um ON um.id = s.mentor_id
        JOIN users us ON us.id = s.student_id
        LEFT JOIN subjects sub ON sub.id = s.subject_id
        ORDER BY s.session_date DESC, s.start_time DESC
    ");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sessions = []; }

/* ── 2. Session counts by status ── */
$statusCounts = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
foreach ($sessions as $s) {
    $st = $s['status'] ?? '';
    if (isset($statusCounts[$st])) $statusCounts[$st]++;
}
$totalSessions = count($sessions);

/* ── 3. Active matches ── */
$activeMatches = 0;
try {
    $activeMatches = (int)$pdo->query("SELECT COUNT(*) FROM mentor_student_matches WHERE active = 1")->fetchColumn();
} catch (PDOException $e) { /* silent */ }

/* ── 4. Smart Alerts ── */

/* Alert A: Matches with no scheduled session (matched ≥ 7 days ago) */
$noSessionAlerts = [];
try {
    $stmt = $pdo->query("
        SELECT msm.id AS match_id, msm.matched_at,
               msm.mentor_id, msm.student_id,
               CONCAT(um.first_name, ' ', um.last_name) AS mentor_name,
               um.id AS mentor_user_id,
               um.profile_picture AS mentor_picture,
               CONCAT(us.first_name, ' ', us.last_name) AS student_name
        FROM mentor_student_matches msm
        JOIN users um ON um.id = msm.mentor_id
        JOIN users us ON us.id = msm.student_id
        WHERE msm.active = 1
          AND msm.matched_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND NOT EXISTS (
              SELECT 1 FROM sessions ses
              WHERE ses.mentor_id = msm.mentor_id
                AND ses.student_id = msm.student_id
                AND ses.status NOT IN ('cancelled')
          )
        ORDER BY msm.matched_at ASC
    ");
    $noSessionAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $noSessionAlerts = []; }

/* Alert B: Overdue sessions (date+time has passed, still pending/confirmed) */
$overdueAlerts = [];
try {
    $stmt = $pdo->query("
        SELECT s.id, s.title, s.session_date, s.start_time, s.end_time, s.status,
               CONCAT(um.first_name, ' ', um.last_name) AS mentor_name,
               um.id AS mentor_user_id,
               um.profile_picture AS mentor_picture,
               CONCAT(us.first_name, ' ', us.last_name) AS student_name
        FROM sessions s
        JOIN users um ON um.id = s.mentor_id
        JOIN users us ON us.id = s.student_id
        WHERE s.status IN ('pending', 'confirmed')
          AND CONCAT(s.session_date, ' ', s.start_time) < NOW()
        ORDER BY s.session_date ASC, s.start_time ASC
    ");
    $overdueAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $overdueAlerts = []; }

/* Alert C: Matches ≥ 3 days with no pending/confirmed session */
$noPendingAlerts = [];
try {
    $stmt = $pdo->query("
        SELECT msm.id AS match_id, msm.matched_at,
               msm.mentor_id, msm.student_id,
               CONCAT(um.first_name, ' ', um.last_name) AS mentor_name,
               um.id AS mentor_user_id,
               um.profile_picture AS mentor_picture,
               CONCAT(us.first_name, ' ', us.last_name) AS student_name
        FROM mentor_student_matches msm
        JOIN users um ON um.id = msm.mentor_id
        JOIN users us ON us.id = msm.student_id
        WHERE msm.active = 1
          AND msm.matched_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)
          AND NOT EXISTS (
              SELECT 1 FROM sessions ses
              WHERE ses.mentor_id = msm.mentor_id
                AND ses.student_id = msm.student_id
                AND ses.status IN ('pending', 'confirmed', 'completed')
          )
        ORDER BY msm.matched_at ASC
    ");
    $noPendingAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $noPendingAlerts = []; }

/* Remove duplicates: exclude matches already covered by the 7-day no-session alert */
$noSessionMatchIds = array_column($noSessionAlerts, 'match_id');
$noPendingAlerts = array_filter($noPendingAlerts, function ($a) use ($noSessionMatchIds) {
    return !in_array($a['match_id'], $noSessionMatchIds);
});
$noPendingAlerts = array_values($noPendingAlerts);

$totalAlerts = count($noSessionAlerts) + count($overdueAlerts) + count($noPendingAlerts);

//  Mentor lookup for warning modal (all mentors with profile pictures) 
$mentors = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.profile_picture
        FROM users u
        WHERE u.role = 'mentor'
        ORDER BY u.first_name, u.last_name
    ");
    $mentors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $mentors = []; }

/* Pre-selected warning messages */
$warningMessages = [
    'Please schedule a session with your matched student as soon as possible.',
    'Your session has passed and hasn\'t been marked as completed. Please update its status.',
    'Reminder: Regular sessions are key to student progress. Please stay engaged.',
    'A scheduled session appears overdue. Please mark it as completed or reschedule.',
    'Please ensure you are actively communicating with your assigned student(s).',
    'You currently have no upcoming sessions. Please propose a session with your student soon.',
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session Monitor – Mentor Match</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/session_monitor.css">
</head>
<body>
<main class="sm-container">
<div class="sm-wrapper">

    <!-- ─ ─ Header ─ ─ -->
    <div class="sm-header">
        <h1>Session Monitor</h1>
        <span class="sm-header__badge">
            <strong><?php echo $totalSessions; ?></strong> session<?php echo $totalSessions !== 1 ? 's' : ''; ?>
        </span>
        <p>Track sessions, spot issues, and keep mentors accountable.</p>
    </div>

    <!--  Stats Row  -->
    <div class="sm-stats">
        <div class="sm-stat sm-stat--accent">
            <div class="sm-stat__value"><?php echo $activeMatches; ?></div>
            <div class="sm-stat__label">Matches</div>
        </div>
        <div class="sm-stat sm-stat--green">
            <div class="sm-stat__value"><?php echo $statusCounts['completed']; ?></div>
            <div class="sm-stat__label">Completed</div>
        </div>
        <div class="sm-stat sm-stat--amber">
            <div class="sm-stat__value"><?php echo $statusCounts['pending'] + $statusCounts['confirmed']; ?></div>
            <div class="sm-stat__label">Upcoming</div>
        </div>
        <div class="sm-stat sm-stat--red">
            <div class="sm-stat__value"><?php echo $totalAlerts; ?></div>
            <div class="sm-stat__label">Alerts</div>
        </div>
    </div>

    <!-- Smart Alerts ─── -->
    <div class="sm-card">
        <h2 class="sm-card__title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Smart Alerts
            <span class="sm-card__count <?php echo $totalAlerts === 0 ? 'sm-card__count--zero' : ''; ?>">
                <?php echo $totalAlerts; ?>
            </span>
        </h2>

        <?php if ($totalAlerts === 0): ?>
            <div class="sm-no-alerts">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Everything looks good! No issues found.
            </div>
        <?php else: ?>
            <div class="sm-alerts-list">

                <?php foreach ($noSessionAlerts as $alert): ?>
                    <?php
                        $days = (int)round((time() - strtotime($alert['matched_at'])) / 86400);
                        $mentorAvatar = !empty($alert['mentor_picture'])
                            ? '../' . htmlspecialchars($alert['mentor_picture'])
                            : 'https://ui-avatars.com/api/?name=' . urlencode($alert['mentor_name']) . '&background=3b82f6&color=fff&size=64';
                    ?>
                    <div class="sm-alert">
                        <div class="sm-alert__icon">⏳</div>
                        <div class="sm-alert__body">
                            <p class="sm-alert__title">No sessions scheduled</p>
                            <p class="sm-alert__desc">
                                <strong><?php echo htmlspecialchars($alert['mentor_name']); ?></strong> &amp;
                                <strong><?php echo htmlspecialchars($alert['student_name']); ?></strong>
                                have been matched for <?php echo $days; ?> day<?php echo $days !== 1 ? 's' : ''; ?> with no session created.
                            </p>
                            <div class="sm-alert__meta">
                                <span class="sm-alert__tag">Matched <?php echo date('M j', strtotime($alert['matched_at'])); ?></span>
                            </div>
                            <button type="button" class="sm-alert__btn js-warn-btn"
                                    data-mentor-id="<?php echo (int)$alert['mentor_user_id']; ?>"
                                    data-mentor-name="<?php echo htmlspecialchars($alert['mentor_name']); ?>"
                                    data-mentor-avatar="<?php echo htmlspecialchars($mentorAvatar); ?>"
                                    data-preselect="0">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                                Send Warning
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($noPendingAlerts as $alert): ?>
                    <?php
                        $days = (int)round((time() - strtotime($alert['matched_at'])) / 86400);
                        $mentorAvatar = !empty($alert['mentor_picture'])
                            ? '../' . htmlspecialchars($alert['mentor_picture'])
                            : 'https://ui-avatars.com/api/?name=' . urlencode($alert['mentor_name']) . '&background=3b82f6&color=fff&size=64';
                    ?>
                    <div class="sm-alert sm-alert--nopending">
                        <div class="sm-alert__icon">📭</div>
                        <div class="sm-alert__body">
                            <p class="sm-alert__title">No upcoming session proposed</p>
                            <p class="sm-alert__desc">
                                <strong><?php echo htmlspecialchars($alert['mentor_name']); ?></strong> &amp;
                                <strong><?php echo htmlspecialchars($alert['student_name']); ?></strong>
                                have been matched for <?php echo $days; ?> day<?php echo $days !== 1 ? 's' : ''; ?> with no pending or confirmed session.
                            </p>
                            <div class="sm-alert__meta">
                                <span class="sm-alert__tag">Matched <?php echo date('M j', strtotime($alert['matched_at'])); ?></span>
                                <span class="sm-alert__tag">No upcoming</span>
                            </div>
                            <button type="button" class="sm-alert__btn js-warn-btn"
                                    data-mentor-id="<?php echo (int)$alert['mentor_user_id']; ?>"
                                    data-mentor-name="<?php echo htmlspecialchars($alert['mentor_name']); ?>"
                                    data-mentor-avatar="<?php echo htmlspecialchars($mentorAvatar); ?>"
                                    data-preselect="5">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                                Send Warning
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($overdueAlerts as $alert): ?>
                    <?php
                        $sessionTitle = !empty($alert['title']) ? $alert['title'] : 'Untitled Session';
                        $mentorAvatar = !empty($alert['mentor_picture'])
                            ? '../' . htmlspecialchars($alert['mentor_picture'])
                            : 'https://ui-avatars.com/api/?name=' . urlencode($alert['mentor_name']) . '&background=3b82f6&color=fff&size=64';
                    ?>
                    <div class="sm-alert sm-alert--overdue">
                        <div class="sm-alert__icon">🔴</div>
                        <div class="sm-alert__body">
                            <p class="sm-alert__title">Overdue session: <?php echo htmlspecialchars($sessionTitle); ?></p>
                            <p class="sm-alert__desc">
                                Session between <strong><?php echo htmlspecialchars($alert['mentor_name']); ?></strong>
                                &amp; <strong><?php echo htmlspecialchars($alert['student_name']); ?></strong>
                                on <?php echo date('M j, Y', strtotime($alert['session_date'])); ?> at
                                <?php echo date('g:i A', strtotime($alert['start_time'])); ?>
                                is still marked as <em><?php echo htmlspecialchars($alert['status']); ?></em>.
                            </p>
                            <div class="sm-alert__meta">
                                <span class="sm-alert__tag"><?php echo ucfirst(htmlspecialchars($alert['status'])); ?></span>
                                <span class="sm-alert__tag"><?php echo date('M j', strtotime($alert['session_date'])); ?></span>
                            </div>
                            <button type="button" class="sm-alert__btn js-warn-btn"
                                    data-mentor-id="<?php echo (int)$alert['mentor_user_id']; ?>"
                                    data-mentor-name="<?php echo htmlspecialchars($alert['mentor_name']); ?>"
                                    data-mentor-avatar="<?php echo htmlspecialchars($mentorAvatar); ?>"
                                    data-preselect="1">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                                Send Warning
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php endif; ?>
    </div>

    <!-- ─── All Sessions ─── -->
    <div class="sm-card">
        <h2 class="sm-card__title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            All Sessions
        </h2>

        <!-- Filter Tabs -->
        <div class="sm-tabs" id="sessionTabs">
            <button class="sm-tab active" data-filter="all">
                All <span class="sm-tab__badge"><?php echo $totalSessions; ?></span>
            </button>
            <button class="sm-tab" data-filter="pending">
                Pending <span class="sm-tab__badge"><?php echo $statusCounts['pending']; ?></span>
            </button>
            <button class="sm-tab" data-filter="confirmed">
                Confirmed <span class="sm-tab__badge"><?php echo $statusCounts['confirmed']; ?></span>
            </button>
            <button class="sm-tab" data-filter="completed">
                Completed <span class="sm-tab__badge"><?php echo $statusCounts['completed']; ?></span>
            </button>
            <button class="sm-tab" data-filter="cancelled">
                Cancelled <span class="sm-tab__badge"><?php echo $statusCounts['cancelled']; ?></span>
            </button>
        </div>

        <!-- Sessions List -->
        <div class="sm-sessions-list" id="sessionsList" style="margin-top: 12px;">
            <?php if (empty($sessions)): ?>
                <div class="sm-no-sessions">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    No sessions have been created yet.
                </div>
            <?php else: ?>
                <?php foreach ($sessions as $s): ?>
                    <?php
                        $statusIcons = [
                            'pending'   => '🕐',
                            'confirmed' => '✅',
                            'completed' => '🏆',
                            'cancelled' => '✖️',
                        ];
                        $icon = $statusIcons[$s['status']] ?? '📅';
                        $title = !empty($s['title']) ? $s['title'] : 'Session #' . $s['id'];
                        $dateStr = date('M j, Y', strtotime($s['session_date']));
                        $timeStr = date('g:i A', strtotime($s['start_time']));
                        if (!empty($s['end_time'])) {
                            $timeStr .= ' – ' . date('g:i A', strtotime($s['end_time']));
                        }
                    ?>
                    <div class="sm-session" data-status="<?php echo htmlspecialchars($s['status']); ?>">
                        <div class="sm-session__icon sm-session__icon--<?php echo htmlspecialchars($s['status']); ?>">
                            <?php echo $icon; ?>
                        </div>
                        <div class="sm-session__body">
                            <p class="sm-session__title"><?php echo htmlspecialchars($title); ?></p>
                            <p class="sm-session__people">
                                <?php echo htmlspecialchars($s['mentor_name']); ?> &rarr; <?php echo htmlspecialchars($s['student_name']); ?>
                                <?php if (!empty($s['subject_name'])): ?>
                                    &middot; <?php echo htmlspecialchars($s['subject_name']); ?>
                                <?php endif; ?>
                            </p>
                            <div class="sm-session__details">
                                <span class="sm-session__date">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    <?php echo $dateStr; ?>
                                </span>
                                <span class="sm-session__time">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <?php echo $timeStr; ?>
                                </span>
                            </div>
                        </div>
                        <span class="sm-session__status sm-session__status--<?php echo htmlspecialchars($s['status']); ?>">
                            <?php echo ucfirst(htmlspecialchars($s['status'])); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ─── Logout ─── -->
    <a href="login.php?logout=1" class="sm-logout-btn">Log Out</a>

</div>
</main>

<!-- ─── Warning Modal ─── -->
<div class="sm-modal" id="warnModal">
    <div class="sm-modal__backdrop"></div>
    <div class="sm-modal__panel">
        <div class="sm-modal__header">
            <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Send Warning
            </h3>
            <button type="button" class="sm-modal__close" aria-label="Close">&times;</button>
        </div>
        <div class="sm-modal__body">
            <!-- Recipient -->
            <div class="sm-modal__recipient">
                <img src="" alt="" class="sm-modal__recipient-avatar" id="modalAvatar">
                <div>
                    <div class="sm-modal__recipient-name" id="modalName"></div>
                    <div class="sm-modal__recipient-role">Mentor</div>
                </div>
            </div>

            <!-- Pre-selected messages -->
            <p class="sm-modal__label">Choose a warning message</p>
            <div class="sm-modal__messages" id="modalMessages">
                <?php foreach ($warningMessages as $i => $msg): ?>
                    <div class="sm-modal__msg-option" data-index="<?php echo $i; ?>">
                        <div class="sm-modal__msg-radio"></div>
                        <div class="sm-modal__msg-text"><?php echo htmlspecialchars($msg); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Custom message -->
            <div class="sm-modal__custom">
                <p class="sm-modal__label" style="margin-top:0;">Or write a custom message</p>
                <textarea id="customMessage" placeholder="Type a custom warning message…" maxlength="1000"></textarea>
            </div>

            <!-- Send button -->
            <button type="button" class="sm-modal__send" id="sendWarningBtn" disabled>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                Send Warning Message
            </button>
        </div>
    </div>
</div>

<!-- ─── Toast ─── -->
<div class="sm-toast" id="smToast"></div>

<?php include '../includes/nav.php'; ?>

<script>
(function () {
    /* ── Filter Tabs ── */
    const tabs = document.querySelectorAll('.sm-tab');
    const sessionItems = document.querySelectorAll('.sm-session');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');

            const filter = tab.dataset.filter;
            sessionItems.forEach(function (item) {
                if (filter === 'all' || item.dataset.status === filter) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });

    /* ── Warning Modal ── */
    const modal       = document.getElementById('warnModal');
    const backdrop     = modal.querySelector('.sm-modal__backdrop');
    const closeBtn     = modal.querySelector('.sm-modal__close');
    const modalAvatar  = document.getElementById('modalAvatar');
    const modalName    = document.getElementById('modalName');
    const msgOptions   = document.querySelectorAll('.sm-modal__msg-option');
    const customTA     = document.getElementById('customMessage');
    const sendBtn      = document.getElementById('sendWarningBtn');

    let selectedMentorId = 0;
    let selectedMsgIndex = -1;

    /* Pre-selected warning messages from PHP */
    const warningMessages = <?php echo json_encode($warningMessages); ?>;

    /* Open modal */
    document.querySelectorAll('.js-warn-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            selectedMentorId = parseInt(btn.dataset.mentorId, 10);
            modalName.textContent = btn.dataset.mentorName;
            modalAvatar.src = btn.dataset.mentorAvatar;
            modalAvatar.alt = btn.dataset.mentorName;

            /* Pre-select relevant message based on alert type */
            var preselect = parseInt(btn.dataset.preselect, 10);

            /* Reset selections */
            msgOptions.forEach(function (opt) { opt.classList.remove('selected'); });
            customTA.value = '';
            selectedMsgIndex = -1;

            /* Auto-select the relevant pre-selected message */
            if (preselect >= 0 && preselect < msgOptions.length) {
                msgOptions[preselect].classList.add('selected');
                selectedMsgIndex = preselect;
            }

            updateSendBtn();
            modal.classList.add('is-open');
        });
    });

    /* Close modal */
    function closeModal() {
        modal.classList.remove('is-open');
        selectedMentorId = 0;
        selectedMsgIndex = -1;
        customTA.value = '';
        msgOptions.forEach(function (opt) { opt.classList.remove('selected'); });
        updateSendBtn();
    }

    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    /* Select pre-set message */
    msgOptions.forEach(function (opt) {
        opt.addEventListener('click', function () {
            msgOptions.forEach(function (o) { o.classList.remove('selected'); });
            opt.classList.add('selected');
            selectedMsgIndex = parseInt(opt.dataset.index, 10);
            customTA.value = '';
            updateSendBtn();
        });
    });

    /* Custom message input */
    customTA.addEventListener('input', function () {
        if (customTA.value.trim() !== '') {
            msgOptions.forEach(function (o) { o.classList.remove('selected'); });
            selectedMsgIndex = -1;
        }
        updateSendBtn();
    });

    function updateSendBtn() {
        var hasMsg = selectedMsgIndex >= 0 || customTA.value.trim() !== '';
        sendBtn.disabled = !hasMsg || selectedMentorId <= 0;
    }

    /* Send warning */
    sendBtn.addEventListener('click', function () {
        if (sendBtn.disabled) return;

        var message = '';
        if (selectedMsgIndex >= 0) {
            message = warningMessages[selectedMsgIndex];
        } else {
            message = customTA.value.trim();
        }

        if (!message || selectedMentorId <= 0) return;

        sendBtn.disabled = true;
        sendBtn.textContent = 'Sending…';

        var formData = new FormData();
        formData.append('ajax_action', 'send_warning');
        formData.append('receiver_id', selectedMentorId);
        formData.append('message', '⚠️ Admin Warning: ' + message);

        fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.ok) {
                showToast('Warning sent successfully!', 'success');
                closeModal();
            } else {
                showToast(data.error || 'Failed to send.', 'error');
            }
        })
        .catch(function () {
            showToast('Network error. Try again.', 'error');
        })
        .finally(function () {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send Warning Message';
            updateSendBtn();
        });
    });

    /* ── Toast ── */
    var toastEl = document.getElementById('smToast');
    var toastTimeout;

    function showToast(msg, type) {
        clearTimeout(toastTimeout);
        toastEl.textContent = msg;
        toastEl.className = 'sm-toast sm-toast--' + (type || 'success') + ' is-visible';
        toastTimeout = setTimeout(function () {
            toastEl.classList.remove('is-visible');
        }, 3000);
    }
})();
</script>
</body>
</html>
