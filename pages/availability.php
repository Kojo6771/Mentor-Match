<?php
session_start();
require_once '../includes/db.php';

/* ─── Auth & Role Guard ─*/
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

if ($user_role !== 'mentor') {
    header('Location: dashboard.php');
    exit;
}

$errors   = [];
$successes = [];

/* ─── Day-of-week helpers ── */
$day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$day_short = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/* ─── Handle POST Actions ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── Add a new time slot ── */
    if ($action === 'add_slot') {
        $is_recurring = (int)($_POST['is_recurring'] ?? 1);
        $day_of_week  = isset($_POST['day_of_week']) ? (int)$_POST['day_of_week'] : null;
        $avail_date   = trim($_POST['available_date'] ?? '');
        $start        = trim($_POST['start_time'] ?? '');
        $end          = trim($_POST['end_time'] ?? '');

        // Validate common fields
        if ($start === '' || $end === '') {
            $errors[] = 'Start and end times are required.';
        } elseif ($start >= $end) {
            $errors[] = 'End time must be after start time.';
        }

        if ($is_recurring) {
            if ($day_of_week === null || $day_of_week < 0 || $day_of_week > 6) {
                $errors[] = 'Please select a day of the week.';
            }
            // Check for overlapping recurring slot
            if (empty($errors)) {
                $stmt = $pdo->prepare('
                    SELECT COUNT(*) FROM availability
                    WHERE mentor_id = ? AND is_recurring = 1 AND day_of_week = ?
                      AND start_time < ? AND end_time > ?
                ');
                $stmt->execute([$user_id, $day_of_week, $end, $start]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = 'This overlaps with an existing slot on ' . $day_names[$day_of_week] . '.';
                }
            }
        } else {
            if ($avail_date === '' || !strtotime($avail_date)) {
                $errors[] = 'Please choose a valid date.';
            } elseif ($avail_date < date('Y-m-d')) {
                $errors[] = 'Date cannot be in the past.';
            }
            // Check for overlapping specific-date slot
            if (empty($errors)) {
                $stmt = $pdo->prepare('
                    SELECT COUNT(*) FROM availability
                    WHERE mentor_id = ? AND is_recurring = 0 AND available_date = ?
                      AND start_time < ? AND end_time > ?
                ');
                $stmt->execute([$user_id, $avail_date, $end, $start]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = 'This overlaps with an existing slot on ' . date('M j, Y', strtotime($avail_date)) . '.';
                }
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO availability (mentor_id, available_date, start_time, end_time, day_of_week, is_recurring)
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $user_id,
                    $is_recurring ? null : $avail_date,
                    $start,
                    $end,
                    $is_recurring ? $day_of_week : null,
                    $is_recurring
                ]);
                $successes[] = 'Time slot added!';
            } catch (PDOException $e) {
                $errors[] = 'Could not save the time slot. Please try again.';
            }
        }
    }

    /* ── Remove a time slot ── */
    if ($action === 'remove_slot') {
        $slot_id = (int)($_POST['slot_id'] ?? 0);
        if ($slot_id > 0) {
            $stmt = $pdo->prepare('DELETE FROM availability WHERE id = ? AND mentor_id = ?');
            $stmt->execute([$slot_id, $user_id]);
            if ($stmt->rowCount() > 0) {
                $successes[] = 'Time slot removed.';
            } else {
                $errors[] = 'Could not remove that slot.';
            }
        }
    }
}

/* ─── Fetch All Recurring Slots ──────── */
$recurring_slots = [];
try {
    $stmt = $pdo->prepare('
        SELECT * FROM availability
        WHERE mentor_id = ? AND is_recurring = 1
        ORDER BY day_of_week ASC, start_time ASC
    ');
    $stmt->execute([$user_id]);
    $recurring_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recurring_slots = [];
}

// Group by day
$slots_by_day = [];
for ($i = 0; $i < 7; $i++) {
    $slots_by_day[$i] = [];
}
foreach ($recurring_slots as $slot) {
    $slots_by_day[(int)$slot['day_of_week']][] = $slot;
}

// Total recurring count per day (for overview)
$day_counts = [];
for ($i = 0; $i < 7; $i++) {
    $day_counts[$i] = count($slots_by_day[$i]);
}
$has_any_recurring = array_sum($day_counts) > 0;

/* ─── Fetch Specific Date Slots ──────────────────────────────────── */
$date_slots = [];
try {
    $stmt = $pdo->prepare('
        SELECT * FROM availability
        WHERE mentor_id = ? AND is_recurring = 0
          AND (available_date >= CURDATE() OR available_date IS NULL)
        ORDER BY available_date ASC, start_time ASC
    ');
    $stmt->execute([$user_id]);
    $date_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $date_slots = [];
}

// Group specific date slots by date
$date_slots_grouped = [];
foreach ($date_slots as $ds) {
    $date_slots_grouped[$ds['available_date']][] = $ds;
}

/* ─── Order days starting from Monday ────────────────────────────── */
$day_order = [1, 2, 3, 4, 5, 6, 0]; // Mon – Sun

/* ─── Helper: format time ────────────────────────────────────────── */
function fmt_time(string $time): string
{
    return date('g:i A', strtotime($time));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Availability — Mentor Match</title>
    <meta name="description" content="Set your availability for mentoring sessions.">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/availability.css">
</head>
<body>
    <main class="avail-container">
        <div class="avail-wrapper">

            <!-- ── Back Link ── -->
            <a href="calendar.php" class="avail-back">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                Back to Calendar
            </a>

            <!-- ── Page Header ── -->
            <div class="avail-header">
                <div>
                    <h1>My Availability</h1>
                    <p>Set when students can book sessions</p>
                </div>
                <button type="button" class="btn-add-slot" id="openModalBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Slot
                </button>
            </div>

            <!-- ── Alerts ── -->
            <?php foreach ($errors as $e): ?>
                <div class="avail-alert avail-alert--error"><?php echo htmlspecialchars($e); ?></div>
            <?php endforeach; ?>
            <?php foreach ($successes as $s): ?>
                <div class="avail-alert avail-alert--success"><?php echo htmlspecialchars($s); ?></div>
            <?php endforeach; ?>

            <!-- ── Week Overview Strip ── -->
            <div class="avail-overview">
                <p class="avail-overview__title">Weekly Overview</p>
                <div class="avail-overview__days">
                    <?php foreach ($day_order as $dow): ?>
                        <?php $active = $day_counts[$dow] > 0; ?>
                        <div class="avail-overview__day <?php echo $active ? 'avail-overview__day--active' : ''; ?>">
                            <span class="avail-overview__day-label"><?php echo $day_short[$dow]; ?></span>
                            <span class="avail-overview__dot"></span>
                            <?php if ($active): ?>
                                <span class="avail-overview__day-count"><?php echo $day_counts[$dow]; ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Weekly Schedule ── -->
            <div class="avail-card">
                <h2 class="avail-card__title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Weekly Schedule
                </h2>

                <?php if (!$has_any_recurring): ?>
                    <div class="avail-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <p>No weekly availability set</p>
                        <span>Add your regular available hours so students know when to book</span>
                        <button type="button" class="btn-add-slot" onclick="document.getElementById('openModalBtn').click()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Your First Slot
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($day_order as $dow): ?>
                        <div class="avail-day">
                            <div class="avail-day__header">
                                <h3 class="avail-day__name"><?php echo $day_names[$dow]; ?></h3>
                                <div class="avail-day__header-actions">
                                    <?php if ($day_counts[$dow] > 0): ?>
                                        <span class="avail-day__count"><?php echo $day_counts[$dow]; ?> slot<?php echo $day_counts[$dow] !== 1 ? 's' : ''; ?></span>
                                    <?php endif; ?>
                                    <button type="button" class="btn-add-day-slot" data-day="<?php echo $dow; ?>" aria-label="Add slot for <?php echo $day_names[$dow]; ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                        Add Slot
                                    </button>
                                </div>
                            </div>

                            <?php if (empty($slots_by_day[$dow])): ?>
                                <p class="avail-day__empty">Not available</p>
                            <?php else: ?>
                                <div class="avail-day__slots">
                                    <?php foreach ($slots_by_day[$dow] as $slot): ?>
                                        <div class="avail-slot">
                                            <svg class="avail-slot__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            <span class="avail-slot__time">
                                                <?php echo fmt_time($slot['start_time']); ?> – <?php echo fmt_time($slot['end_time']); ?>
                                            </span>
                                            <form method="POST" style="display:inline;margin:0;padding:0" onsubmit="return confirm('Remove this time slot?')">
                                                <input type="hidden" name="action" value="remove_slot">
                                                <input type="hidden" name="slot_id" value="<?php echo (int)$slot['id']; ?>">
                                                <button type="submit" class="avail-slot__remove" aria-label="Remove slot">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- ── Specific Date Slots ── -->
            <?php if (!empty($date_slots_grouped)): ?>
                <h3 class="avail-section-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    Specific Date Availability
                </h3>

                <?php foreach ($date_slots_grouped as $date => $slots): ?>
                    <div class="avail-date-group">
                        <p class="avail-date-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <?php echo date('l, F j, Y', strtotime($date)); ?>
                        </p>
                        <div class="avail-day__slots">
                            <?php foreach ($slots as $slot): ?>
                                <div class="avail-slot">
                                    <svg class="avail-slot__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <span class="avail-slot__time">
                                        <?php echo fmt_time($slot['start_time']); ?> – <?php echo fmt_time($slot['end_time']); ?>
                                    </span>
                                    <form method="POST" style="display:inline;margin:0;padding:0" onsubmit="return confirm('Remove this time slot?')">
                                        <input type="hidden" name="action" value="remove_slot">
                                        <input type="hidden" name="slot_id" value="<?php echo (int)$slot['id']; ?>">
                                        <button type="submit" class="avail-slot__remove" aria-label="Remove slot">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </main>

    <!-- ── Add Time Slot Modal ── -->
    <div class="avail-modal-backdrop" id="slotModal" style="display:none">
        <div class="avail-modal">
            <div class="avail-modal__header">
                <h2>Add Available Time</h2>
                <button type="button" class="avail-modal__close" id="closeModalBtn">&times;</button>
            </div>
            <form method="POST" class="avail-modal__body" id="slotForm" novalidate>
                <input type="hidden" name="action" value="add_slot">
                <input type="hidden" name="is_recurring" id="isRecurring" value="1">

                <!-- Type Toggle -->
                <div>
                    <label>Slot Type</label>
                    <div class="avail-type-toggle" id="typeToggle">
                        <label class="avail-type-option selected" data-value="1">
                            <input type="radio" name="slot_type_radio" value="1" checked>
                            <svg class="avail-type-option__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                            Every Week
                        </label>
                        <label class="avail-type-option" data-value="0">
                            <input type="radio" name="slot_type_radio" value="0">
                            <svg class="avail-type-option__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Specific Date
                        </label>
                    </div>
                </div>

                <!-- Day of Week (for recurring) -->
                <div id="fieldDayOfWeek">
                    <label for="day_of_week">Day of Week</label>
                    <select name="day_of_week" id="day_of_week" class="input">
                        <option value="">Select a day</option>
                        <?php foreach ($day_order as $dow): ?>
                            <option value="<?php echo $dow; ?>"><?php echo $day_names[$dow]; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Specific Date (for one-off) -->
                <div id="fieldDate" class="avail-field-hidden">
                    <label for="available_date">Date</label>
                    <input type="date" name="available_date" id="available_date" class="input" min="<?php echo date('Y-m-d'); ?>">
                </div>

                <!-- Times -->
                <div class="avail-time-row">
                    <div>
                        <label for="start_time">Start Time</label>
                        <input type="time" name="start_time" id="start_time" class="input" required>
                    </div>
                    <div>
                        <label for="end_time">End Time</label>
                        <input type="time" name="end_time" id="end_time" class="input" required>
                    </div>
                </div>

                <div class="avail-modal__footer">
                    <button type="button" class="btn btn-outline" id="cancelModalBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Slot</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/nav.php'; ?>

    <script>
    (function () {
        /* ── Modal open / close ── */
        const modal     = document.getElementById('slotModal');
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

        /* ── Type Toggle (Weekly / Specific Date) ── */
        const typeToggle    = document.getElementById('typeToggle');
        const isRecurring   = document.getElementById('isRecurring');
        const fieldDay      = document.getElementById('fieldDayOfWeek');
        const fieldDate     = document.getElementById('fieldDate');

        if (typeToggle) {
            typeToggle.querySelectorAll('.avail-type-option').forEach(function (opt) {
                opt.addEventListener('click', function () {
                    typeToggle.querySelectorAll('.avail-type-option').forEach(function (o) {
                        o.classList.remove('selected');
                    });
                    opt.classList.add('selected');

                    var val = opt.getAttribute('data-value');
                    isRecurring.value = val;

                    if (val === '1') {
                        fieldDay.classList.remove('avail-field-hidden');
                        fieldDate.classList.add('avail-field-hidden');
                    } else {
                        fieldDay.classList.add('avail-field-hidden');
                        fieldDate.classList.remove('avail-field-hidden');
                    }
                });
            });
        }

        /* ── Per-day Add Slot buttons ── */
        document.querySelectorAll('.btn-add-day-slot').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var day = btn.getAttribute('data-day');

                // Switch to "Every Week" mode
                if (typeToggle) {
                    typeToggle.querySelectorAll('.avail-type-option').forEach(function (o) {
                        o.classList.remove('selected');
                    });
                    var weeklyOpt = typeToggle.querySelector('[data-value="1"]');
                    if (weeklyOpt) weeklyOpt.classList.add('selected');
                }
                if (isRecurring) isRecurring.value = '1';
                if (fieldDay)  fieldDay.classList.remove('avail-field-hidden');
                if (fieldDate) fieldDate.classList.add('avail-field-hidden');

                // Pre-select the day
                var daySelect = document.getElementById('day_of_week');
                if (daySelect && day !== null) daySelect.value = day;

                openModal();
            });
        });

        /* ── Auto-dismiss alerts after 4s ── */
        document.querySelectorAll('.avail-alert').forEach(function (el) {
            setTimeout(function () {
                el.style.transition = 'opacity 0.3s, transform 0.3s';
                el.style.opacity = '0';
                el.style.transform = 'translateY(-8px)';
                setTimeout(function () { el.remove(); }, 300);
            }, 4000);
        });
    })();
    </script>
</body>
</html>
