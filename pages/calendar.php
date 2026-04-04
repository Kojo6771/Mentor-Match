<?php
session_start();
require_once '../includes/db.php';

// Only students and mentors can access the calendar page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'student';

if (!in_array($user_role, ['student', 'mentor'], true)) {
    header('Location: dashboard.php');
    exit;
}

// Helper functions for session time checks.
function get_session_end_datetime(array $session): ?DateTimeImmutable
{
    $sessionDate = trim((string)($session['session_date'] ?? ''));
    $endTime = trim((string)($session['end_time'] ?? ''));

    if ($sessionDate === '' || $endTime === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($sessionDate . ' ' . $endTime);
    } catch (Exception $e) {
        return null;
    }
}

// Check if a session has ended based on its date and end time.
function session_has_ended(array $session): bool
{
    $sessionEnd = get_session_end_datetime($session);
    if ($sessionEnd === null) {
        return false;
    }

    return $sessionEnd <= new DateTimeImmutable();
}

// AJAX endpoint: return mentor availability for a specific date.
if (isset($_GET['get_availability']) && isset($_GET['date'])) {
    header('Content-Type: application/json');
    $req_date   = trim($_GET['date']);
    $req_mentor = (int)($_GET['mentor_id'] ?? 0);

    if (!$req_mentor || !strtotime($req_date)) {
        echo json_encode(['slots' => []]);
        exit;
    }

    $dow = (int)date('w', strtotime($req_date));
    $slots = [];

    // Weekly recurring slots for that weekday.
    $stmt = $pdo->prepare('
        SELECT start_time, end_time FROM availability
        WHERE mentor_id = ? AND is_recurring = 1 AND day_of_week = ?
        ORDER BY start_time
    ');
    $stmt->execute([$req_mentor, $dow]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $slots[] = [
            'start' => date('g:i A', strtotime($r['start_time'])),
            'end'   => date('g:i A', strtotime($r['end_time'])),
            'start_raw' => $r['start_time'],
            'end_raw'   => $r['end_time'],
        ];
    }

    // One-off slots created for this exact date.
    $stmt = $pdo->prepare('
        SELECT start_time, end_time FROM availability
        WHERE mentor_id = ? AND is_recurring = 0 AND available_date = ?
        ORDER BY start_time
    ');
    $stmt->execute([$req_mentor, $req_date]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $slots[] = [
            'start' => date('g:i A', strtotime($r['start_time'])),
            'end'   => date('g:i A', strtotime($r['end_time'])),
            'start_raw' => $r['start_time'],
            'end_raw'   => $r['end_time'],
        ];
    }

    echo json_encode(['slots' => $slots]);
    exit;
}

$errors   = [];
$successes = [];

// Work out who the current user is paired with.
$partner       = null;   // assoc: user_id, first_name, last_name, profile_picture, subjects
$mentor_db_id  = null;   // mentor_profiles.mentor_id  (FK used in sessions)
$student_db_id = null;   // students.student_id        (FK used in sessions)
$subjects      = [];     // mentor's subjects for the booking form

if ($user_role === 'student') {
    // Load the student record and the assigned mentor.
    $stmt = $pdo->prepare('SELECT student_id, mentor_id FROM students WHERE user_id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    $studentRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($studentRow) {
        $student_db_id = (int)$studentRow['student_id'];
        if (!empty($studentRow['mentor_id'])) {
            $mentor_db_id = (int)$studentRow['mentor_id'];

            $stmt = $pdo->prepare('
                SELECT u.id AS user_id, u.first_name, u.last_name, u.profile_picture,
                       GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ", ") AS subjects
                FROM mentor_profiles mp
                JOIN users u ON u.id = mp.user_id
                LEFT JOIN mentor_subjects ms ON ms.mentor_id = mp.mentor_id
                LEFT JOIN subjects s ON s.id = ms.subject_id
                WHERE mp.mentor_id = ?
                GROUP BY u.id
            ');
            $stmt->execute([$mentor_db_id]);
            $partner = $stmt->fetch(PDO::FETCH_ASSOC);

            // Subjects available for the booking form.
            $stmt = $pdo->prepare('
                SELECT s.id, s.name
                FROM mentor_subjects ms
                JOIN subjects s ON s.id = ms.subject_id
                WHERE ms.mentor_id = ?
                ORDER BY s.name
            ');
            $stmt->execute([$mentor_db_id]);
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} else {
    // Mentors need their mentor profile id; student partners are loaded separately below.
    $stmt = $pdo->prepare('SELECT mentor_id FROM mentor_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    $mentor_db_id = (int)($stmt->fetchColumn() ?: 0);

    // Load the mentor's subjects for the session form.
    if ($mentor_db_id > 0) {
        $stmt = $pdo->prepare('
            SELECT s.id, s.name
            FROM mentor_subjects ms
            JOIN subjects s ON s.id = ms.subject_id
            WHERE ms.mentor_id = ?
            ORDER BY s.name
        ');
        $stmt->execute([$mentor_db_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Handle form submissions for booking and updating sessions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create a new proposed session.
    if ($action === 'book_session') {
        $subject_id  = (int)($_POST['subject_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $date        = trim($_POST['session_date'] ?? '');
        $start       = trim($_POST['start_time'] ?? '');
        $end         = trim($_POST['end_time'] ?? '');
        $location    = trim($_POST['location'] ?? '');

        if ($title === '') {
            $errors[] = 'Session title is required.';
        }
        if ($subject_id <= 0) {
            $errors[] = 'Please choose a subject.';
        }
        if ($date === '' || !strtotime($date)) {
            $errors[] = 'Please choose a valid date.';
        }
        if ($start === '' || $end === '') {
            $errors[] = 'Start and end times are required.';
        } elseif ($start >= $end) {
            $errors[] = 'End time must be after start time.';
        }
        if (!$mentor_db_id || !$student_db_id) {
            // Mentors choose which paired student this session is for.
            if ($user_role === 'mentor') {
                $target_student = (int)($_POST['student_id'] ?? 0);
                if ($target_student > 0) {
                    $student_db_id = $target_student;
                } else {
                    $errors[] = 'Please select a student.';
                }
            } else {
                $errors[] = 'You must be paired with a mentor first.';
            }
        }

        // Check that the chosen time fits within the mentor's availability.
        if (empty($errors) && $mentor_db_id && $date && $start && $end) {
            $dow = (int)date('w', strtotime($date));
            $within_avail = false;

            // First look at recurring weekly availability.
            $stmt = $pdo->prepare('
                SELECT COUNT(*) FROM availability
                WHERE mentor_id = ? AND is_recurring = 1 AND day_of_week = ?
                  AND start_time <= ? AND end_time >= ?
            ');
            $stmt->execute([$mentor_db_id, $dow, $start, $end]);
            if ($stmt->fetchColumn() > 0) $within_avail = true;

            // Then check one-off date slots.
            if (!$within_avail) {
                $stmt = $pdo->prepare('
                    SELECT COUNT(*) FROM availability
                    WHERE mentor_id = ? AND is_recurring = 0 AND available_date = ?
                      AND start_time <= ? AND end_time >= ?
                ');
                $stmt->execute([$mentor_db_id, $date, $start, $end]);
                if ($stmt->fetchColumn() > 0) $within_avail = true;
            }

            // If availability exists but this time does not fit, show a helpful error.
            if (!$within_avail) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM availability WHERE mentor_id = ?');
                $stmt->execute([$mentor_db_id]);
                $has_any_avail = $stmt->fetchColumn() > 0;
                if ($has_any_avail) {
                    $errors[] = 'The selected time is outside the mentor\'s available hours. Please check their availability and try again.';
                }
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO sessions (student_id, mentor_id, subject_id, title, description, location, session_date, start_time, end_time, status, proposed_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", ?, NOW())
                ');
                $stmt->execute([
                    $student_db_id,
                    $mentor_db_id,
                    $subject_id,
                    $title,
                    $description ?: null,
                    $location ?: null,
                    $date,
                    $start,
                    $end,
                    $user_id
                ]);
                $successes[] = 'Session proposed successfully!';
            } catch (PDOException $e) {
                $errors[] = 'Could not create session. Please try again.';
            }
        }
    }

    // Update an existing session status.
    if ($action === 'update_session') {
        $session_id = (int)($_POST['session_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';

        if (!in_array($new_status, ['confirmed', 'completed', 'cancelled'], true)) {
            $errors[] = 'Invalid status.';
        } elseif ($session_id <= 0) {
            $errors[] = 'Invalid session.';
        } else {
            // Make sure this user is allowed to act on the session.
            $stmt = $pdo->prepare('SELECT * FROM sessions WHERE id = ? LIMIT 1');
            $stmt->execute([$session_id]);
            $sess = $stmt->fetch(PDO::FETCH_ASSOC);

            $allowed = false;
            if ($sess) {
                if ($user_role === 'student' && (int)$sess['student_id'] === $student_db_id) $allowed = true;
                if ($user_role === 'mentor' && (int)$sess['mentor_id'] === $mentor_db_id) $allowed = true;
            }

            if (!$allowed) {
                $errors[] = 'You do not have permission to update this session.';
            } elseif ($new_status === 'completed') {
                if ($user_role !== 'mentor' || (int)$sess['mentor_id'] !== $mentor_db_id) {
                    $errors[] = 'Only the assigned mentor can complete this session.';
                } elseif (($sess['status'] ?? '') !== 'confirmed') {
                    $errors[] = 'Only confirmed sessions can be marked as completed.';
                } elseif (!session_has_ended($sess)) {
                    $errors[] = 'This session can only be completed after its end time has passed.';
                }
            }

            if (empty($errors)) {
                $stmt = $pdo->prepare('UPDATE sessions SET status = ? WHERE id = ?');
                $stmt->execute([$new_status, $session_id]);

                if ($new_status === 'confirmed') {
                    $successes[] = 'Session accepted.';
                } elseif ($new_status === 'completed') {
                    $successes[] = 'Session marked as completed.';
                } else {
                    $successes[] = 'Session cancelled.';
                }
            } else {
                // Leave errors in place for the page to render normally.
            }
        }
    }
}

// Work out which month the calendar should show.
$cal_month = (int)($_GET['month'] ?? date('n'));
$cal_year  = (int)($_GET['year'] ?? date('Y'));
$selected_day = ($_GET['day'] ?? null);

if ($cal_month < 1)  { $cal_month = 12; $cal_year--; }
if ($cal_month > 12) { $cal_month = 1;  $cal_year++; }

$first_of_month = mktime(0, 0, 0, $cal_month, 1, $cal_year);
$month_name     = date('F Y', $first_of_month);
$days_in_month  = (int)date('t', $first_of_month);
$start_weekday  = ((int)date('w', $first_of_month) + 6) % 7; // 0=Mon, 6=Sun

$prev_month = $cal_month - 1;
$prev_year  = $cal_year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }

$next_month = $cal_month + 1;
$next_year  = $cal_year;
if ($next_month > 12) { $next_month = 1; $next_year++; }

$today_str = date('Y-m-d');

// Load all sessions for the current month.
$month_start = sprintf('%04d-%02d-01', $cal_year, $cal_month);
$month_end   = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $days_in_month);

$sessions = [];
try {
    if ($user_role === 'student' && $student_db_id) {
        $stmt = $pdo->prepare('
            SELECT se.*, sub.name AS subject_name,
                   u.first_name, u.last_name, u.profile_picture
            FROM sessions se
            LEFT JOIN subjects sub ON sub.id = se.subject_id
            LEFT JOIN users u ON u.id = (SELECT user_id FROM mentor_profiles WHERE mentor_id = se.mentor_id LIMIT 1)
            WHERE se.student_id = ?
              AND se.session_date BETWEEN ? AND ?
            ORDER BY se.session_date ASC, se.start_time ASC
        ');
        $stmt->execute([$student_db_id, $month_start, $month_end]);
    } elseif ($user_role === 'mentor' && $mentor_db_id) {
        $stmt = $pdo->prepare('
            SELECT se.*, sub.name AS subject_name,
                   u.first_name, u.last_name, u.profile_picture
            FROM sessions se
            LEFT JOIN subjects sub ON sub.id = se.subject_id
            LEFT JOIN users u ON u.id = (SELECT user_id FROM students WHERE student_id = se.student_id LIMIT 1)
            WHERE se.mentor_id = ?
              AND se.session_date BETWEEN ? AND ?
            ORDER BY se.session_date ASC, se.start_time ASC
        ');
        $stmt->execute([$mentor_db_id, $month_start, $month_end]);
    }

    if (isset($stmt)) {
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $sessions = [];
}

// Group sessions by date for the calendar grid.
$sessions_by_date = [];
foreach ($sessions as $s) {
    $sessions_by_date[$s['session_date']][] = $s;
}

// Sessions for the currently selected date.
$selected_date_sessions = [];
if ($selected_day !== null) {
    $sel_date_str = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, (int)$selected_day);
    $selected_date_sessions = $sessions_by_date[$sel_date_str] ?? [];
}

// Upcoming confirmed sessions, excluding the selected day to avoid duplicates.
$sel_date_for_filter = $selected_day !== null ? sprintf('%04d-%02d-%02d', $cal_year, $cal_month, (int)$selected_day) : null;
$upcoming = array_filter($sessions, function ($s) use ($today_str, $sel_date_for_filter) {
    return $s['session_date'] >= $today_str
        && $s['status'] === 'confirmed'
        && $s['session_date'] !== $sel_date_for_filter;
});
usort($upcoming, fn($a, $b) => strcmp($a['session_date'] . $a['start_time'], $b['session_date'] . $b['start_time']));
$upcoming = array_slice($upcoming, 0, 5);

// Pending proposals waiting on the current user.
$pending = array_filter($sessions, function ($s) use ($user_id) {
    return $s['status'] === 'pending' && (int)($s['proposed_by'] ?? 0) !== $user_id;
});

// Mentors need a list of paired students for the booking form.
$paired_students = [];
if ($user_role === 'mentor' && $mentor_db_id > 0) {
    try {
        $stmt = $pdo->prepare('
            SELECT st.student_id, u.first_name, u.last_name
            FROM students st
            JOIN users u ON u.id = st.user_id
            WHERE st.mentor_id = ?
            ORDER BY u.first_name, u.last_name
        ');
        $stmt->execute([$mentor_db_id]);
        $paired_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $paired_students = [];
    }
}

// Render a session card shared by the selected-day, upcoming, and pending lists.
function render_session_card(array $s, int $user_id, string $user_role): string
{
    $name = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
    if ($name === '') $name = 'User';

    $fallback = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=3b82f6&color=fff&size=96';
    $avatar   = !empty($s['profile_picture']) ? '../' . ltrim($s['profile_picture'], '/') : $fallback;

    $badge_class = 'badge--' . $s['status'];
    $status_label = ucfirst($s['status']);

    $title = htmlspecialchars($s['title'] ?? 'Session');
    $with  = htmlspecialchars($name);
    $desc  = !empty($s['description']) ? '<p class="session-desc">' . htmlspecialchars($s['description']) . '</p>' : '';

    $date_fmt  = date('M j', strtotime($s['session_date']));
    $start_fmt = date('g:i A', strtotime($s['start_time']));
    $end_fmt   = !empty($s['end_time']) ? ' - ' . date('g:i A', strtotime($s['end_time'])) : '';
    $subject   = !empty($s['subject_name']) ? htmlspecialchars($s['subject_name']) : '';
    $location  = !empty($s['location']) ? htmlspecialchars($s['location']) : '';

    $is_proposer  = (int)($s['proposed_by'] ?? 0) === $user_id;
    $is_pending   = $s['status'] === 'pending';
    $is_confirmed = $s['status'] === 'confirmed';
    $is_future    = $s['session_date'] >= date('Y-m-d');
    $has_ended    = session_has_ended($s);

    $actions = '';

    // Show accept/decline only to the person who did not propose the session.
    if ($is_pending && !$is_proposer) {
        $actions .= '
        <div class="session-actions">
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="update_session">
                <input type="hidden" name="session_id" value="' . (int)$s['id'] . '">
                <input type="hidden" name="new_status" value="confirmed">
                <button type="submit" class="btn-sm btn-accept">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Accept
                </button>
            </form>
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="update_session">
                <input type="hidden" name="session_id" value="' . (int)$s['id'] . '">
                <input type="hidden" name="new_status" value="cancelled">
                <button type="submit" class="btn-sm btn-decline">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    Decline
                </button>
            </form>
        </div>';
    }

    // Confirmed future sessions can still be cancelled.
    if ($is_confirmed && $is_future) {
        $actions .= '
        <div class="session-actions">
            <form method="POST" style="display:inline" onsubmit="return confirm(\'Cancel this session?\')">
                <input type="hidden" name="action" value="update_session">
                <input type="hidden" name="session_id" value="' . (int)$s['id'] . '">
                <input type="hidden" name="new_status" value="cancelled">
                <button type="submit" class="btn-sm btn-cancel">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Cancel Session
                </button>
            </form>
        </div>';
    }

    if ($user_role === 'mentor' && $is_confirmed && $has_ended) {
        $actions .= '
        <div class="session-actions">
            <form method="POST" style="display:inline" onsubmit="return confirm(\'Mark this session as completed?\')">
                <input type="hidden" name="action" value="update_session">
                <input type="hidden" name="session_id" value="' . (int)$s['id'] . '">
                <input type="hidden" name="new_status" value="completed">
                <button type="submit" class="btn-sm btn-complete">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    Mark Completed
                </button>
            </form>
        </div>';
    }

    // "Add to Calendar" dropdown for confirmed or completed sessions.
    $export_btn = '';
    if (in_array($s['status'], ['confirmed', 'completed'], true)) {
        $export_btn = '
        <div class="cal-export-wrap">
            <button type="button" class="btn-sm btn-export" onclick="toggleCalExport(this)" title="Add to external calendar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Add to Calendar
            </button>
            <div class="cal-export-menu" style="display:none">
                <button type="button" onclick="exportToGoogle(this)">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Google Calendar
                </button>
                <button type="button" onclick="exportToOutlook(this)">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Outlook / Office 365
                </button>
                <button type="button" onclick="downloadICS(this)">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download .ics
                </button>
            </div>
        </div>';
    }

    $avatar_html = '<img src="' . htmlspecialchars($avatar) . '" alt="' . $with . '" class="session-avatar" onerror="this.onerror=null;this.src=\'' . htmlspecialchars($fallback) . '\'">';

    $meta_items = '<span class="session-meta-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        ' . $date_fmt . ' ' . $start_fmt . $end_fmt . '
    </span>';

    if ($subject !== '') {
        $meta_items .= '<span class="session-meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            ' . $subject . '
        </span>';
    }

    if ($location !== '') {
        $meta_items .= '<span class="session-meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            ' . $location . '
        </span>';
    }

    return '
    <div class="session-card"
         data-cal-title="' . $title . '"
         data-cal-date="' . htmlspecialchars($s['session_date']) . '"
         data-cal-start="' . htmlspecialchars($s['start_time']) . '"
         data-cal-end="' . htmlspecialchars($s['end_time'] ?? '') . '"
         data-cal-desc="' . htmlspecialchars(($s['description'] ?? '') . ' — with ' . $name) . '"
         data-cal-location="' . $location . '"
         data-cal-subject="' . $subject . '">
        ' . $avatar_html . '
        <div class="session-body">
            <div class="session-top">
                <div>
                    <p class="session-title">' . $title . '</p>
                    <p class="session-with">with ' . $with . '</p>
                </div>
                <span class="badge ' . $badge_class . '">' . $status_label . '</span>
            </div>
            ' . $desc . '
            <div class="session-meta">' . $meta_items . '</div>
            ' . $actions . '
            ' . $export_btn . '
        </div>
    </div>';
}

// Small helper for calendar navigation links.
function cal_url(array $params): string
{
    return 'calendar.php?' . http_build_query($params);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Calendar — Mentor Match</title>
    <meta name="description" content="Schedule mentoring sessions on Mentor Match.">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/calendar.css">
</head>
<body>
    <main class="cal-container">
        <div class="cal-wrapper">

            <!-- Page header -->
            <div class="cal-header">
                <div>
                    <h1>Calendar</h1>
                    <p>Schedule &amp; manage sessions</p>
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex-shrink:0">
                    <?php if ($user_role === 'mentor'): ?>
                        <a href="availability.php" class="btn-new-session" style="background:linear-gradient(135deg,#10b981 0%,#06b6d4 100%);text-decoration:none;font-size:0.82rem;padding:9px 14px">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Availability
                        </a>
                    <?php endif; ?>
                    <?php if (($user_role === 'student' && $partner) || ($user_role === 'mentor' && !empty($paired_students))): ?>
                        <button type="button" class="btn-new-session" id="openModalBtn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            New Session
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Success and error messages -->
            <?php foreach ($errors as $e): ?>
                <div class="cal-alert cal-alert--error"><?php echo htmlspecialchars($e); ?></div>
            <?php endforeach; ?>
            <?php foreach ($successes as $s): ?>
                <div class="cal-alert cal-alert--success"><?php echo htmlspecialchars($s); ?></div>
            <?php endforeach; ?>

            <!-- Monthly calendar grid -->
            <div class="cal-card">
                <div class="cal-nav">
                    <a href="<?php echo cal_url(['month' => $prev_month, 'year' => $prev_year]); ?>" class="cal-nav-btn" aria-label="Previous month">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    </a>
                    <h2><?php echo $month_name; ?></h2>
                    <a href="<?php echo cal_url(['month' => $next_month, 'year' => $next_year]); ?>" class="cal-nav-btn" aria-label="Next month">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                </div>

                <div class="cal-grid">
                    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $wd): ?>
                        <div class="cal-weekday"><?php echo $wd; ?></div>
                    <?php endforeach; ?>

                    <?php for ($i = 0; $i < $start_weekday; $i++): ?>
                        <div class="cal-day cal-day--empty"></div>
                    <?php endfor; ?>

                    <?php for ($d = 1; $d <= $days_in_month; $d++):
                        $date_str = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $d);
                        $is_today    = ($date_str === $today_str);
                        $is_selected = ($selected_day !== null && (int)$selected_day === $d);
                        $day_sessions = $sessions_by_date[$date_str] ?? [];
                        $has_sessions = count($day_sessions) > 0;

                        $classes = 'cal-day';
                        if ($is_selected)  $classes .= ' cal-day--selected';
                        elseif ($is_today) $classes .= ' cal-day--today';
                        if ($has_sessions && !$is_selected && !$is_today) $classes .= ' cal-day--has-session';

                        $url = cal_url(['month' => $cal_month, 'year' => $cal_year, 'day' => $d]);
                    ?>
                        <a href="<?php echo $url; ?>" class="<?php echo $classes; ?>" style="text-decoration:none;color:inherit">
                            <span><?php echo $d; ?></span>
                            <?php if ($has_sessions): ?>
                                <div class="cal-dots">
                                    <?php for ($dot = 0; $dot < min(count($day_sessions), 3); $dot++): ?>
                                        <span class="cal-dot"></span>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Sessions for the selected date -->
            <?php if ($selected_day !== null): ?>
                <?php
                    $sel_ts = mktime(0, 0, 0, $cal_month, (int)$selected_day, $cal_year);
                    $sel_label = date('l, F j', $sel_ts);
                ?>
                <h3 class="cal-section-title">Sessions on <?php echo $sel_label; ?></h3>

                <?php if (empty($selected_date_sessions)): ?>
                    <div class="cal-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <p>No sessions scheduled for this day</p>
                        <?php if (($user_role === 'student' && $partner) || ($user_role === 'mentor' && !empty($paired_students))): ?>
                            <button type="button" class="btn-new-session" onclick="document.getElementById('openModalBtn').click()">Schedule Session</button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($selected_date_sessions as $sess): ?>
                        <?php echo render_session_card($sess, $user_id, $user_role); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Upcoming confirmed sessions -->
            <?php if ($selected_date_sessions == null): ?>
                <h3 class="cal-section-title">Upcoming Sessions</h3>
                <?php if (empty($upcoming)): ?>
                    <div class="cal-empty">
                        <p>No upcoming confirmed sessions</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($upcoming as $sess): ?>
                        <?php echo render_session_card($sess, $user_id, $user_role); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Pending proposals awaiting a response -->
            <?php if (!empty($pending)): ?>
                <h3 class="cal-section-title">Pending Proposals</h3>
                <?php foreach ($pending as $sess): ?>
                    <?php echo render_session_card($sess, $user_id, $user_role); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal for proposing a new session -->
    <div class="session-modal-backdrop" id="sessionModal" style="display:none">
        <div class="session-modal">
            <div class="session-modal__header">
                <h2>Propose Session</h2>
                <button type="button" class="session-modal__close" id="closeModalBtn">&times;</button>
            </div>
            <form method="POST" class="session-modal__body" novalidate>
                <input type="hidden" name="action" value="book_session">

                <?php if ($user_role === 'mentor' && !empty($paired_students)): ?>
                    <div>
                        <label for="student_id">With</label>
                        <select name="student_id" id="student_id" class="input" required>
                            <option value="">Select a student</option>
                            <?php foreach ($paired_students as $ps): ?>
                                <option value="<?php echo (int)$ps['student_id']; ?>">
                                    <?php echo htmlspecialchars(trim($ps['first_name'] . ' ' . $ps['last_name'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div>
                    <label for="title">Session Title</label>
                    <input type="text" name="title" id="title" class="input" placeholder="e.g., Web Development Discussion" required>
                </div>

                <div>
                    <label for="description">Description</label>
                    <textarea name="description" id="description" placeholder="What will you discuss?" rows="3"></textarea>
                </div>

                <div>
                    <label for="subject_id">Subject</label>
                    <select name="subject_id" id="subject_id" class="input" required>
                        <option value="">Select a subject</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?php echo (int)$sub['id']; ?>"><?php echo htmlspecialchars($sub['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="session_date">Date</label>
                    <input type="date" name="session_date" id="session_date" class="input" min="<?php echo date('Y-m-d'); ?>" required>
                    <div id="availabilityHint" style="margin-top:6px;font-size:0.82rem;color:var(--muted);display:none">
                        <div id="availabilitySlots"></div>
                    </div>
                </div>

                <div class="time-row">
                    <div>
                        <label for="start_time">Start Time</label>
                        <input type="time" name="start_time" id="start_time" class="input" required>
                    </div>
                    <div>
                        <label for="end_time">End Time</label>
                        <input type="time" name="end_time" id="end_time" class="input" required>
                    </div>
                </div>

                <div id="timeValidationMsg" style="display:none;margin-top:4px;font-size:0.82rem;color:#b45309;background:#fef3c7;padding:7px 10px;border-radius:8px;border:1px solid #fcd34d;font-weight:500"></div>

                <div>
                    <label for="location">Location / Meeting Link</label>
                    <input type="text" name="location" id="location" class="input" placeholder="e.g., Library Room 3 or Zoom link">
                </div>

                <div class="session-modal__footer">
                    <button type="button" class="btn btn-outline" id="cancelModalBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Propose Session</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/nav.php'; ?>

    <script>
    /* ── Add to External Calendar helpers ── */
    function getCardData(btn) {
        var card = btn.closest('.session-card');
        return {
            title:    card.getAttribute('data-cal-title') || 'Mentor Match Session',
            date:     card.getAttribute('data-cal-date')  || '',
            start:    card.getAttribute('data-cal-start') || '',
            end:      card.getAttribute('data-cal-end')   || '',
            desc:     card.getAttribute('data-cal-desc')  || '',
            location: card.getAttribute('data-cal-location') || '',
            subject:  card.getAttribute('data-cal-subject')  || ''
        };
    }

    // Convert "2026-04-10" + "14:00" to "20260410T140000" (local time, no Z)
    function toCalDT(date, time) {
        return date.replace(/-/g, '') + 'T' + (time || '000000').replace(/:/g, '') + '00';
    }

    // Google Calendar uses UTC-style stamps but we keep local for simplicity
    function exportToGoogle(btn) {
        var d = getCardData(btn);
        var start = toCalDT(d.date, d.start);
        var end   = toCalDT(d.date, d.end || d.start);
        var desc  = d.desc + (d.subject ? '\nSubject: ' + d.subject : '');
        var url = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            + '&text='    + encodeURIComponent(d.title)
            + '&dates='   + encodeURIComponent(start + '/' + end)
            + '&details=' + encodeURIComponent(desc)
            + '&location='+ encodeURIComponent(d.location);
        window.open(url, '_blank', 'noopener');
        closeAllExportMenus();
    }

    function exportToOutlook(btn) {
        var d = getCardData(btn);
        var iso = function(date, time) {
            return date + 'T' + (time || '00:00') + ':00';
        };
        var desc = d.desc + (d.subject ? '\nSubject: ' + d.subject : '');
        var url = 'https://outlook.live.com/calendar/0/action/compose?rru=addevent'
            + '&subject='  + encodeURIComponent(d.title)
            + '&startdt='  + encodeURIComponent(iso(d.date, d.start))
            + '&enddt='    + encodeURIComponent(iso(d.date, d.end || d.start))
            + '&body='     + encodeURIComponent(desc)
            + '&location=' + encodeURIComponent(d.location);
        window.open(url, '_blank', 'noopener');
        closeAllExportMenus();
    }

    function downloadICS(btn) {
        var d = getCardData(btn);
        var start = toCalDT(d.date, d.start);
        var end   = toCalDT(d.date, d.end || d.start);
        var desc  = d.desc + (d.subject ? '\\nSubject: ' + d.subject : '');
        var uid   = d.date + '-' + (d.start||'').replace(/:/g,'') + '@mentormatch';
        var ics = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//MentorMatch//Calendar//EN',
            'BEGIN:VEVENT',
            'UID:' + uid,
            'DTSTART:' + start,
            'DTEND:' + end,
            'SUMMARY:' + d.title,
            'DESCRIPTION:' + desc.replace(/\n/g, '\\n'),
            'LOCATION:' + d.location,
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR'
        ].join('\r\n');

        var blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = (d.title || 'session').replace(/[^a-zA-Z0-9 ]/g, '').trim().replace(/\s+/g, '_') + '.ics';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
        closeAllExportMenus();
    }

    function toggleCalExport(btn) {
        var menu = btn.nextElementSibling;
        var isOpen = menu.style.display !== 'none';
        closeAllExportMenus();
        if (!isOpen) menu.style.display = 'block';
    }

    function closeAllExportMenus() {
        document.querySelectorAll('.cal-export-menu').forEach(function(m) { m.style.display = 'none'; });
    }

    // Close menus when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.cal-export-wrap')) closeAllExportMenus();
    });

    (function () {
        // Open and close the new-session modal.
        const modal     = document.getElementById('sessionModal');
        const openBtn   = document.getElementById('openModalBtn');
        const closeBtn  = document.getElementById('closeModalBtn');
        const cancelBtn = document.getElementById('cancelModalBtn');

        function openModal()  { if (modal) modal.style.display = 'flex'; }
        function closeModal() { if (modal) modal.style.display = 'none'; }

        if (openBtn)   openBtn.addEventListener('click', openModal);
        if (closeBtn)  closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });

        // Student bookings must stay inside the mentor's available time windows.
        var mentorId       = <?php echo json_encode($mentor_db_id ?: 0); ?>;
        var isStudent      = <?php echo json_encode($user_role === 'student'); ?>;
        var availableSlots = [];
        var dateInput      = document.getElementById('session_date');
        var startInput     = document.getElementById('start_time');
        var endInput       = document.getElementById('end_time');
        var submitBtn      = document.getElementById('submitBtn');

        // Students cannot pick times until availability is loaded for a date.
        if (isStudent && mentorId) {
            if (startInput) startInput.disabled = true;
            if (endInput)   endInput.disabled   = true;
            if (submitBtn)  submitBtn.disabled  = true;
        }

        // Pre-fill the modal date when a day is selected in the calendar.
        var params   = new URLSearchParams(window.location.search);
        var urlDay   = params.get('day');
        var urlMonth = params.get('month');
        var urlYear  = params.get('year');
        if (urlDay && urlMonth && urlYear && dateInput) {
            var m = String(urlMonth).padStart(2, '0');
            var d = String(urlDay).padStart(2, '0');
            dateInput.value = urlYear + '-' + m + '-' + d;
            fetchAvailability(dateInput.value);
        }

        if (dateInput) {
            dateInput.addEventListener('change', function () { fetchAvailability(this.value); });
        }
        if (startInput) {
            startInput.addEventListener('change', validateTimes);
            startInput.addEventListener('input',  validateTimes);
        }
        if (endInput) {
            endInput.addEventListener('change', validateTimes);
            endInput.addEventListener('input',  validateTimes);
        }

        // Load available slots for the chosen date and show them as clickable chips.
        function fetchAvailability(dateVal) {
            var hint    = document.getElementById('availabilityHint');
            var slotsEl = document.getElementById('availabilitySlots');
            if (!hint || !slotsEl) return;

            // Reset time inputs and validation whenever the date changes.
            if (startInput) startInput.value = '';
            if (endInput)   endInput.value   = '';
            clearTimeValidation();

            if (!mentorId || !dateVal) { hint.style.display = 'none'; return; }

            fetch('calendar.php?get_availability=1&date=' + encodeURIComponent(dateVal) + '&mentor_id=' + mentorId)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    availableSlots = data.slots || [];

                    if (availableSlots.length > 0) {
                        var label = isStudent ? 'Mentor available:' : 'Your time slots:';
                        var html = '<div class="avail-label">' + label + '</div><div class="avail-chips">';
                        html += availableSlots.map(function (s, i) {
                            return '<button type="button" class="avail-slot-chip" data-idx="' + i + '">'
                                 + s.start + ' \u2013 ' + s.end + '</button>';
                        }).join('');
                        html += '</div>';
                        if (isStudent) {
                            html += '<div class="avail-hint-note">Click a slot to auto-fill, or enter custom times within a window.</div>';
                        }
                        slotsEl.innerHTML = html;
                        hint.style.display = 'block';

                        slotsEl.querySelectorAll('.avail-slot-chip').forEach(function (chip) {
                            chip.addEventListener('click', function () {
                                var idx = parseInt(this.getAttribute('data-idx'), 10);
                                var s = availableSlots[idx];
                                if (startInput) startInput.value = s.start_raw;
                                if (endInput)   endInput.value   = s.end_raw;
                                slotsEl.querySelectorAll('.avail-slot-chip').forEach(function (c) { c.classList.remove('active'); });
                                this.classList.add('active');
                                if (isStudent) validateTimes();
                            });
                        });

                        if (isStudent) {
                            if (startInput) startInput.disabled = false;
                            if (endInput)   endInput.disabled   = false;
                        }
                    } else {
                        availableSlots = [];
                        if (isStudent) {
                            slotsEl.innerHTML = '<span class="avail-unavail">\u26a0 Your mentor is not available on this day \u2014 please choose a different date.</span>';
                            hint.style.display = 'block';
                            if (startInput) { startInput.value = ''; startInput.disabled = true; }
                            if (endInput)   { endInput.value   = ''; endInput.disabled   = true; }
                            if (submitBtn)  submitBtn.disabled = true;
                        } else {
                            hint.style.display = 'none';
                        }
                    }
                })
                .catch(function () { hint.style.display = 'none'; availableSlots = []; });
        }

            // Check that the chosen times fit inside one of the available windows.
        function validateTimes() {
            if (!isStudent || availableSlots.length === 0) return;
            var warnEl = document.getElementById('timeValidationMsg');
            if (!warnEl) return;
            var start = startInput ? startInput.value : '';
            var end   = endInput   ? endInput.value   : '';
            if (!start || !end) {
                warnEl.style.display = 'none';
                if (submitBtn) submitBtn.disabled = true;
                return;
            }
            var within = availableSlots.some(function (s) {
                return start >= s.start_raw && end <= s.end_raw && start < end;
            });
            if (within) {
                warnEl.style.display = 'none';
                if (submitBtn) submitBtn.disabled = false;
            } else {
                warnEl.textContent = '\u26a0 These times fall outside your mentor\u2019s available windows for this day.';
                warnEl.style.display = 'block';
                if (submitBtn) submitBtn.disabled = true;
            }
        }

        // Reset validation state when no valid availability has been selected yet.
        function clearTimeValidation() {
            var warnEl = document.getElementById('timeValidationMsg');
            if (warnEl) warnEl.style.display = 'none';
            if (isStudent) {
                if (startInput) startInput.disabled = true;
                if (endInput)   endInput.disabled   = true;
                if (submitBtn)  submitBtn.disabled  = true;
            }
        }
    })();
    </script>
</body>
</html>
