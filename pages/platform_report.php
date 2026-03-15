<?php
session_start();
require_once '../includes/db.php';

/* ── Guard: admin only ── */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

/* ================================================================
   DATA QUERIES
   ================================================================ */

/* ── 1. User counts by role ── */
$roleCounts = ['student' => 0, 'mentor' => 0, 'admin' => 0];
try {
    $stmt = $pdo->query("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $roleCounts[$row['role']] = (int)$row['cnt'];
    }
} catch (PDOException $e) { /* silent */ }

$totalUsers   = array_sum($roleCounts);
$studentCount = $roleCounts['student'];
$mentorCount  = $roleCounts['mentor'];
$adminCount   = $roleCounts['admin'];

$studentPct = $totalUsers ? round($studentCount / $totalUsers * 100, 1) : 0;
$mentorPct  = $totalUsers ? round($mentorCount  / $totalUsers * 100, 1) : 0;
$adminPct   = $totalUsers ? round(100 - $studentPct - $mentorPct, 1) : 0;

/* Conic-gradient for the donut chart */
$seg1End = $studentPct;
$seg2End = $seg1End + $mentorPct;
$conicGradient = "conic-gradient(var(--accent) 0% {$seg1End}%, #06b6d4 {$seg1End}% {$seg2End}%, #f59e0b {$seg2End}% 100%)";

/* ── 2. Session counts by status ── */
$sessionStatuses = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
try {
    $stmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM sessions GROUP BY status");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sessionStatuses[$row['status']] = (int)$row['cnt'];
    }
} catch (PDOException $e) { /* silent */ }
$totalSessions = array_sum($sessionStatuses);

/* ── 3. Total messages ── */
$totalMessages = 0;
try {
    $totalMessages = (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
} catch (PDOException $e) { /* silent */ }

/* ── 4. Active matches ── */
$activeMatches = 0;
try {
    $activeMatches = (int)$pdo->query("SELECT COUNT(*) FROM mentor_student_matches WHERE active = 1")->fetchColumn();
} catch (PDOException $e) { /* silent */ }

/* ── 5. Top performing mentors (by confirmed + completed sessions) ── */
$topMentors = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.profile_picture,
               COUNT(*) AS session_count
        FROM sessions s
        JOIN users u ON u.id = s.mentor_id
        WHERE s.status IN ('confirmed','completed')
        GROUP BY s.mentor_id
        ORDER BY session_count DESC
        LIMIT 5
    ");
    $topMentors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* silent */ }

$maxMentorSessions = !empty($topMentors) ? (int)$topMentors[0]['session_count'] : 1;

/* ── 6. Popular subjects (by session count) ── */
$popularSubjects = [];
try {
    $stmt = $pdo->query("
        SELECT sub.name, COUNT(*) AS cnt
        FROM sessions s
        JOIN subjects sub ON sub.id = s.subject_id
        GROUP BY s.subject_id
        ORDER BY cnt DESC
        LIMIT 6
    ");
    $popularSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* silent */ }

/* Subject dot colours (cycle) */
$subjectColors = ['#3b82f6','#06b6d4','#8b5cf6','#f59e0b','#ef4444','#10b981'];

/* ── 7. Average mentor rating ── */
$avgRating = 0;
try {
    $val = $pdo->query("SELECT AVG(rating) FROM mentor_ratings")->fetchColumn();
    $avgRating = $val ? round((float)$val, 1) : 0;
} catch (PDOException $e) { /* silent */ }

/* ── 8. Recent activity feed (last 8 events) ── */
$activities = [];
try {
    /* New users */
    $stmt = $pdo->query("
        SELECT 'user' AS type, CONCAT(first_name, ' ', last_name) AS who, role, created_at
        FROM users ORDER BY created_at DESC LIMIT 4
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $activities[] = [
            'type' => 'user',
            'text' => '<strong>' . htmlspecialchars($r['who']) . '</strong> joined as <strong>' . htmlspecialchars($r['role']) . '</strong>',
            'time' => $r['created_at'],
        ];
    }

    /* Recent sessions */
    $stmt = $pdo->query("
        SELECT s.status, s.created_at,
               u1.first_name AS s_fn, u1.last_name AS s_ln,
               u2.first_name AS m_fn, u2.last_name AS m_ln
        FROM sessions s
        JOIN users u1 ON u1.id = s.student_id
        JOIN users u2 ON u2.id = s.mentor_id
        ORDER BY s.created_at DESC
        LIMIT 4
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $student = htmlspecialchars($r['s_fn'] . ' ' . $r['s_ln']);
        $mentor  = htmlspecialchars($r['m_fn'] . ' ' . $r['m_ln']);
        $activities[] = [
            'type' => 'session',
            'text' => '<strong>' . $student . '</strong> &amp; <strong>' . $mentor . '</strong> — session <strong>' . htmlspecialchars($r['status']) . '</strong>',
            'time' => $r['created_at'],
        ];
    }

    /* Recent matches */
    $stmt = $pdo->query("
        SELECT msm.matched_at,
               u1.first_name AS s_fn, u1.last_name AS s_ln,
               u2.first_name AS m_fn, u2.last_name AS m_ln
        FROM mentor_student_matches msm
        JOIN users u1 ON u1.id = msm.student_id
        JOIN users u2 ON u2.id = msm.mentor_id
        ORDER BY msm.matched_at DESC
        LIMIT 4
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $student = htmlspecialchars($r['s_fn'] . ' ' . $r['s_ln']);
        $mentor  = htmlspecialchars($r['m_fn'] . ' ' . $r['m_ln']);
        $activities[] = [
            'type' => 'match',
            'text' => '<strong>' . $student . '</strong> matched with <strong>' . $mentor . '</strong>',
            'time' => $r['matched_at'],
        ];
    }
} catch (PDOException $e) { /* silent */ }

/* Sort by newest first, take only 8 */
usort($activities, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));
$activities = array_slice($activities, 0, 8);

/* ── Helper: human time-ago ── */
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)   return 'just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Report | MentorMatch</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/platform_report.css">
</head>
<body>
<div class="report-container">
    <div class="report-wrapper">

        <!-- ═══════ Header ═══════ -->
        <div class="report-header">
            <div>
                <h1>Platform Report</h1>
                <p>At-a-glance platform health &amp; insights</p>
            </div>
            <div class="report-header__right">
                <?php echo date('M j, Y'); ?><br>
                <span style="font-size:0.72rem;color:var(--muted);"><?php echo date('g:i A'); ?></span>
            </div>
        </div>

        <!-- ═══════ Quick Stats ═══════ -->
        <div class="report-stat-row">
            <div class="report-stat report-stat--accent">
                <div class="report-stat__value"><?php echo $totalUsers; ?></div>
                <div class="report-stat__label">Users</div>
            </div>
            <div class="report-stat report-stat--green">
                <div class="report-stat__value"><?php echo $totalSessions; ?></div>
                <div class="report-stat__label">Sessions</div>
            </div>
            <div class="report-stat report-stat--amber">
                <div class="report-stat__value"><?php echo $totalMessages; ?></div>
                <div class="report-stat__label">Messages</div>
            </div>
        </div>

        <!-- ═══════ Pie Chart – User Distribution ═══════ -->
        <div class="report-card">
            <div class="report-card__title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                User Distribution
            </div>

            <?php if ($totalUsers > 0): ?>
            <div class="pie-wrapper">
                <!-- Donut -->
                <div class="pie-chart" style="background: <?php echo $conicGradient; ?>;">
                    <div class="pie-chart__center" style="background: var(--card); width: 60%; height: 60%; border-radius: 50%; position: absolute; top: 20%; left: 20%;">
                        <span class="pie-chart__center-value"><?php echo $totalUsers; ?></span>
                        <span class="pie-chart__center-label">total</span>
                    </div>
                </div>

                <!-- Legend -->
                <div class="pie-legend">
                    <div class="pie-legend__item">
                        <span class="pie-legend__dot pie-legend__dot--students"></span>
                        <span class="pie-legend__text">
                            Students &nbsp;<span class="pie-legend__count"><?php echo $studentCount; ?></span>
                            <span class="pie-legend__pct">(<?php echo $studentPct; ?>%)</span>
                        </span>
                    </div>
                    <div class="pie-legend__item">
                        <span class="pie-legend__dot pie-legend__dot--mentors"></span>
                        <span class="pie-legend__text">
                            Mentors &nbsp;<span class="pie-legend__count"><?php echo $mentorCount; ?></span>
                            <span class="pie-legend__pct">(<?php echo $mentorPct; ?>%)</span>
                        </span>
                    </div>
                    <div class="pie-legend__item">
                        <span class="pie-legend__dot pie-legend__dot--admins"></span>
                        <span class="pie-legend__text">
                            Admins &nbsp;<span class="pie-legend__count"><?php echo $adminCount; ?></span>
                            <span class="pie-legend__pct">(<?php echo $adminPct; ?>%)</span>
                        </span>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <div class="report-empty">No users registered yet.</div>
            <?php endif; ?>
        </div>

        <!-- ═══════ Top Performing Mentors ═══════ -->
        <div class="report-card">
            <div class="report-card__title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                Top Performing Mentors
            </div>

            <?php if (!empty($topMentors)): ?>
            <div class="bar-chart">
                <?php foreach ($topMentors as $i => $m):
                    $rank    = $i + 1;
                    $name    = htmlspecialchars($m['first_name'] . ' ' . $m['last_name']);
                    $count   = (int)$m['session_count'];
                    $pct     = $maxMentorSessions ? round($count / $maxMentorSessions * 100) : 0;
                    $rankCls = $rank <= 3 ? " bar-rank--{$rank}" : '';
                    $fillCls = $rank === 1 ? ' bar-fill--gold' : '';
                ?>
                <div class="bar-row">
                    <span class="bar-rank<?php echo $rankCls; ?>"><?php echo $rank; ?></span>
                    <div class="bar-info">
                        <div class="bar-info__row">
                            <span class="bar-name"><?php echo $name; ?></span>
                            <span class="bar-count"><?php echo $count; ?> session<?php echo $count !== 1 ? 's' : ''; ?></span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill<?php echo $fillCls; ?>" style="width:<?php echo $pct; ?>%;"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div class="report-empty">No confirmed or completed sessions yet.</div>
            <?php endif; ?>
        </div>

        <!-- ═══════ Session Status Breakdown ═══════ -->
        <div class="report-card">
            <div class="report-card__title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Session Breakdown
            </div>

            <div class="status-grid">
                <div class="status-card status-card--pending">
                    <div class="status-card__value"><?php echo $sessionStatuses['pending']; ?></div>
                    <div class="status-card__label">Pending</div>
                </div>
                <div class="status-card status-card--confirmed">
                    <div class="status-card__value"><?php echo $sessionStatuses['confirmed']; ?></div>
                    <div class="status-card__label">Confirmed</div>
                </div>
                <div class="status-card status-card--completed">
                    <div class="status-card__value"><?php echo $sessionStatuses['completed']; ?></div>
                    <div class="status-card__label">Completed</div>
                </div>
                <div class="status-card status-card--cancelled">
                    <div class="status-card__value"><?php echo $sessionStatuses['cancelled']; ?></div>
                    <div class="status-card__label">Cancelled</div>
                </div>
            </div>
        </div>

        <!-- ═══════ Extra Stats Row ═══════ -->
        <div class="report-stat-row">
            <div class="report-stat">
                <div class="report-stat__value" style="color:#8b5cf6;"><?php echo $activeMatches; ?></div>
                <div class="report-stat__label">Matches</div>
            </div>
            <div class="report-stat">
                <div class="report-stat__value" style="color:#f59e0b;">
                    <?php echo $avgRating > 0 ? $avgRating : '—'; ?>
                </div>
                <div class="report-stat__label">Avg Rating</div>
            </div>
            <div class="report-stat">
                <div class="report-stat__value" style="color:#059669;"><?php echo $sessionStatuses['completed']; ?></div>
                <div class="report-stat__label">Completed</div>
            </div>
        </div>

        <!-- ═══════ Popular Subjects ═══════ -->
        <?php if (!empty($popularSubjects)): ?>
        <div class="report-card">
            <div class="report-card__title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                Popular Subjects
            </div>

            <div class="subject-list">
                <?php foreach ($popularSubjects as $si => $sub): ?>
                <div class="subject-row">
                    <span class="subject-dot" style="background:<?php echo $subjectColors[$si % count($subjectColors)]; ?>;"></span>
                    <span class="subject-name"><?php echo htmlspecialchars($sub['name']); ?></span>
                    <span class="subject-count"><?php echo (int)$sub['cnt']; ?> session<?php echo (int)$sub['cnt'] !== 1 ? 's' : ''; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══════ Recent Activity ═══════ -->
        <?php if (!empty($activities)): ?>
        <div class="report-card">
            <div class="report-card__title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Recent Activity
            </div>

            <div class="activity-list">
                <?php foreach ($activities as $act): ?>
                <div class="activity-item">
                    <?php if ($act['type'] === 'user'): ?>
                        <span class="activity-icon activity-icon--user">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                    <?php elseif ($act['type'] === 'session'): ?>
                        <span class="activity-icon activity-icon--session">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </span>
                    <?php else: ?>
                        <span class="activity-icon activity-icon--match">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </span>
                    <?php endif; ?>
                    <div class="activity-body">
                        <p class="activity-text"><?php echo $act['text']; ?></p>
                        <div class="activity-time"><?php echo timeAgo($act['time']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.report-wrapper -->
</div><!-- /.report-container -->

<?php include '../includes/nav.php'; ?>
</body>
</html>
