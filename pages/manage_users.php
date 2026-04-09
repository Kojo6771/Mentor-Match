<?php
session_start();
require_once '../includes/db.php';

// Restrict this page to admins.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$adminId = (int)$_SESSION['user_id'];


// Process AJAX requests for role changes.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_role') {
    header('Content-Type: application/json');
    $targetId = (int)($_POST['user_id'] ?? 0);
    $newRole  = $_POST['role'] ?? '';

    if ($targetId === $adminId) {
        echo json_encode(['ok' => false, 'msg' => 'You cannot change your own role.']);
        exit;
    }
    if (!in_array($newRole, ['student', 'mentor', 'admin'], true)) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid role.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$newRole, $targetId]);
        echo json_encode(['ok' => true, 'msg' => 'Role updated to ' . ucfirst($newRole) . '.']);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => 'Database error.']);
    }
    exit;
}

// Process AJAX requests to permanently remove a user.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    header('Content-Type: application/json');
    $targetId = (int)($_POST['user_id'] ?? 0);

    if ($targetId === $adminId) {
        echo json_encode(['ok' => false, 'msg' => 'You cannot delete your own account.']);
        exit;
    }

    try {
        // Clean up related records first in case foreign key cascades do not cover every table.
        $pdo->prepare('DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?')->execute([$targetId, $targetId]);
        $pdo->prepare('DELETE FROM sessions WHERE student_id = ? OR mentor_id = ?')->execute([$targetId, $targetId]);
        $pdo->prepare('DELETE FROM mentor_ratings WHERE student_id = ? OR mentor_id = ?')->execute([$targetId, $targetId]);
        $pdo->prepare('DELETE FROM mentor_requests WHERE student_id = ? OR mentor_id = ?')->execute([$targetId, $targetId]);
        $pdo->prepare('DELETE FROM mentor_student_matches WHERE student_id = ? OR mentor_id = ?')->execute([$targetId, $targetId]);
        $pdo->prepare('DELETE FROM availability WHERE mentor_id = ?')->execute([$targetId]);
        $pdo->prepare('DELETE FROM mentor_subjects WHERE mentor_id = ?')->execute([$targetId]);
        $pdo->prepare('DELETE FROM mentor_profiles WHERE user_id = ?')->execute([$targetId]);
        $pdo->prepare('DELETE FROM students WHERE user_id = ?')->execute([$targetId]);
        $pdo->prepare('DELETE FROM mentor_applications WHERE user_id = ?')->execute([$targetId]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);

        echo json_encode(['ok' => true, 'msg' => 'User deleted.']);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Load users for the list and build quick totals for the filter bar.
$users = [];
$roleTotals = ['all' => 0, 'student' => 0, 'mentor' => 0, 'admin' => 0];
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, phone, role, created_at, profile_picture FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $roleTotals['all'] = count($users);
    foreach ($users as $u) {
        $roleTotals[$u['role']] = ($roleTotals[$u['role']] ?? 0) + 1;
    }
} catch (PDOException $e) { /* silent */ }

// Map each matched student to their current mentor name.
$studentMentorMap = [];
try {
    $stmt = $pdo->query("
        SELECT msm.student_id, u.first_name, u.last_name
        FROM mentor_student_matches msm
        JOIN users u ON u.id = msm.mentor_id
        WHERE msm.active = 1
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $studentMentorMap[(int)$row['student_id']] = $row['first_name'] . ' ' . $row['last_name'];
    }
} catch (PDOException $e) {  }

// Map each mentor to the list of students currently matched to them.
$mentorStudentsMap = [];
try {
    $stmt = $pdo->query("
        SELECT msm.mentor_id, u.first_name, u.last_name
        FROM mentor_student_matches msm
        JOIN users u ON u.id = msm.student_id
        WHERE msm.active = 1
        ORDER BY u.first_name
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mentorStudentsMap[(int)$row['mentor_id']][] = $row['first_name'] . ' ' . $row['last_name'];
    }
} catch (PDOException $e) { /* silent */ }

// Format join dates into short labels for the UI.
function joinedLabel(string $dt): string {
    $ts = strtotime($dt);
    $diff = time() - $ts;
    if ($diff < 86400) return 'Today';
    if ($diff < 172800) return 'Yesterday';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | MentorMatch</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/manage_users.css">
</head>
<body>

<div class="mu-container">
    <div class="mu-wrapper">

        <!-- Page header -->
        <div class="mu-header">
            <div>
                <h1>Manage Users</h1>
                <p>View, edit roles &amp; remove accounts</p>
            </div>
            <span class="mu-user-count"><strong><?php echo $roleTotals['all']; ?></strong> users</span>
        </div>

        <!-- Search and role filters -->
        <div class="mu-toolbar">
            <div class="mu-search">
                <span class="mu-search__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </span>
                <input type="text" class="mu-search__input" id="searchInput" placeholder="Search by name or email…" autocomplete="off">
            </div>
            <div class="mu-filters" id="filterBar">
                <button class="mu-filter-btn active" data-filter="all">All <span class="mu-filter-count"><?php echo $roleTotals['all']; ?></span></button>
                <button class="mu-filter-btn" data-filter="student">Students <span class="mu-filter-count"><?php echo $roleTotals['student']; ?></span></button>
                <button class="mu-filter-btn" data-filter="mentor">Mentors <span class="mu-filter-count"><?php echo $roleTotals['mentor']; ?></span></button>
                <button class="mu-filter-btn" data-filter="admin">Admins <span class="mu-filter-count"><?php echo $roleTotals['admin']; ?></span></button>
            </div>
        </div>

        <!-- User cards -->
        <div class="mu-list" id="userList">
            <?php if (empty($users)): ?>
                <div class="mu-empty">
                    <div class="mu-empty__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    </div>
                    <p class="mu-empty__title">No users found</p>
                    <p class="mu-empty__text">Users will appear here once they sign up.</p>
                </div>
            <?php else: ?>
                <?php foreach ($users as $u):
                    $uid   = (int)$u['id'];
                    $name  = htmlspecialchars($u['first_name'] . ' ' . $u['last_name']);
                    $email = htmlspecialchars($u['email']);
                    $role  = htmlspecialchars($u['role']);
                    $joinedStr = joinedLabel($u['created_at']);
                    $isSelf = ($uid === $adminId);

                    // Use the uploaded avatar when available, otherwise fall back to initials.
                    $fallbackAvatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($u['first_name'] . ' ' . $u['last_name']) . '&background=3b82f6&color=fff&size=88';
                    if (!empty($u['profile_picture'])) {
                        $avatarUrl = '../' . ltrim($u['profile_picture'], '/');
                    } else {
                        $avatarUrl = $fallbackAvatarUrl;
                    }
                ?>

                <!-- Data attributes are used by the search and filter controls. -->
                <div class="mu-card" data-uid="<?php echo $uid; ?>" data-role="<?php echo $role; ?>" data-name="<?php echo strtolower($name); ?>" data-email="<?php echo strtolower($email); ?>">
                    <img class="mu-avatar" src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="<?php echo $name; ?>" loading="lazy" data-fallback="<?php echo htmlspecialchars($fallbackAvatarUrl); ?>" onerror="this.onerror=null;this.src=this.dataset.fallback;">
                    <div class="mu-info">
                        <p class="mu-name"><?php echo $name; ?><?php if ($isSelf): ?> <span style="font-size:0.72rem;color:var(--accent);font-weight:700;">(you)</span><?php endif; ?></p>
                        <p class="mu-email"><?php echo $email; ?></p>
                        <div class="mu-meta">
                            <span class="mu-role mu-role--<?php echo $role; ?>" data-role-badge><?php echo ucfirst($role); ?></span>
                            <span class="mu-joined">Joined <?php echo $joinedStr; ?></span>
                        </div>
                        <?php if ($role === 'student'): ?>
                        <div class="mu-mentor-match">
                            <?php if (!empty($studentMentorMap[$uid])): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                <span>Mentor: <strong><?php echo htmlspecialchars($studentMentorMap[$uid]); ?></strong></span>
                            <?php else: ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                <span>No mentor matched</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Show the mentor's matched students -->
                        <?php if ($role === 'mentor'): ?>
                        <div class="mu-mentor-match mu-mentor-match--mentor mu-mentor-match--list">
                            <?php if (!empty($mentorStudentsMap[$uid])): ?>
                                <div class="mu-students-header">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    <span>Students (<?php echo count($mentorStudentsMap[$uid]); ?>)</span>
                                </div>
                                <ul class="mu-students-list">
                                    <?php foreach ($mentorStudentsMap[$uid] as $studentName): ?>
                                    <li><?php echo htmlspecialchars($studentName); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                <span>No students matched</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="mu-actions">
                        <?php if (!$isSelf): ?>
                        <button class="mu-btn-icon mu-btn-icon--edit" title="Change role" onclick="openEditModal(<?php echo $uid; ?>, '<?php echo $role; ?>', '<?php echo addslashes($name); ?>')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="mu-btn-icon mu-btn-icon--delete" title="Delete user" onclick="openDeleteModal(<?php echo $uid; ?>, '<?php echo addslashes($name); ?>')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Empty state shown when the current search/filter returns nothing -->
        <div class="mu-empty" id="noResults" style="display:none;">
            <div class="mu-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
            <p class="mu-empty__title">No users match</p>
            <p class="mu-empty__text">Try a different search or filter.</p>
        </div>

    </div><!-- /.mu-wrapper -->
</div><!-- /.mu-container -->

<!-- Modal for changing a user's role -->
<div class="mu-overlay" id="editOverlay">
    <div class="mu-modal">
        <div class="mu-modal__header">
            <div class="mu-modal__icon mu-modal__icon--edit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </div>
            <div>
                <p class="mu-modal__title">Change Role</p>
                <p class="mu-modal__subtitle" id="editUserName"></p>
            </div>
        </div>
        <div class="mu-modal__body">
            <span class="mu-modal__label">Select new role</span>
            <div class="mu-role-options" id="roleOptions">
                <div class="mu-role-option" data-value="student" onclick="selectRole(this)">
                    <span class="mu-role-option__icon">📚</span>
                    <span class="mu-role-option__label">Student</span>
                </div>
                <div class="mu-role-option" data-value="mentor" onclick="selectRole(this)">
                    <span class="mu-role-option__icon">🎓</span>
                    <span class="mu-role-option__label">Mentor</span>
                </div>
                <div class="mu-role-option" data-value="admin" onclick="selectRole(this)">
                    <span class="mu-role-option__icon">🛡️</span>
                    <span class="mu-role-option__label">Admin</span>
                </div>
            </div>
        </div>
        <div class="mu-modal__actions">
            <button class="mu-modal__btn mu-modal__btn--cancel" onclick="closeModals()">Cancel</button>
            <button class="mu-modal__btn mu-modal__btn--save" id="saveRoleBtn" onclick="saveRole()" disabled>Save</button>
        </div>
    </div>
</div>

<!-- Modal for confirming account deletion -->
<div class="mu-overlay" id="deleteOverlay">
    <div class="mu-modal">
        <div class="mu-modal__header">
            <div class="mu-modal__icon mu-modal__icon--delete">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </div>
            <div>
                <p class="mu-modal__title">Delete User</p>
                <p class="mu-modal__subtitle" id="deleteUserName"></p>
            </div>
        </div>
        <div class="mu-modal__warning">
            This will <strong>permanently</strong> remove the user and all their data (messages, sessions, matches). This action cannot be undone.
        </div>
        <div class="mu-modal__actions">
            <button class="mu-modal__btn mu-modal__btn--cancel" onclick="closeModals()">Cancel</button>
            <button class="mu-modal__btn mu-modal__btn--delete" id="confirmDeleteBtn" onclick="confirmDelete()">Delete</button>
        </div>
    </div>
</div>

<!-- Toast message area -->
<div class="mu-toast" id="toast"></div>

<?php include '../includes/nav.php'; ?>

<script>
// State used by the modals and search/filter controls.
let editUid = null, editOrigRole = null, editNewRole = null;
let deleteUid = null;
const currentFilter = { role: 'all', search: '' };

// Search input and role filter buttons.
const searchInput = document.getElementById('searchInput');
const filterBar   = document.getElementById('filterBar');
const userList    = document.getElementById('userList');
const noResults   = document.getElementById('noResults');

searchInput.addEventListener('input', () => {
    currentFilter.search = searchInput.value.trim().toLowerCase();
    applyFilters();
});

filterBar.addEventListener('click', (e) => {
    const btn = e.target.closest('.mu-filter-btn');
    if (!btn) return;
    filterBar.querySelectorAll('.mu-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentFilter.role = btn.dataset.filter;
    applyFilters();
});

function applyFilters() {
    const cards = userList.querySelectorAll('.mu-card');
    let visible = 0;
    cards.forEach(card => {
        const matchRole = currentFilter.role === 'all' || card.dataset.role === currentFilter.role;
        const matchSearch = !currentFilter.search ||
            card.dataset.name.includes(currentFilter.search) ||
            card.dataset.email.includes(currentFilter.search);
        const show = matchRole && matchSearch;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    noResults.style.display = visible === 0 && cards.length > 0 ? '' : 'none';
}

// Open the role editor and preload the current role.
function openEditModal(uid, currentRole, name) {
    editUid = uid;
    editOrigRole = currentRole;
    editNewRole = currentRole;
    document.getElementById('editUserName').textContent = name;
    document.querySelectorAll('.mu-role-option').forEach(opt => {
        opt.classList.toggle('selected', opt.dataset.value === currentRole);
    });
    document.getElementById('saveRoleBtn').disabled = true;
    document.getElementById('editOverlay').classList.add('visible');
}

function selectRole(el) {
    document.querySelectorAll('.mu-role-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    editNewRole = el.dataset.value;
    document.getElementById('saveRoleBtn').disabled = (editNewRole === editOrigRole);
}

// Save the selected role and update the card without reloading the page.
function saveRole() {
    const btn = document.getElementById('saveRoleBtn');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const fd = new FormData();
    fd.append('action', 'update_role');
    fd.append('user_id', editUid);
    fd.append('role', editNewRole);

    fetch('manage_users.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                // Refresh the role badge and filter data on the existing card.
                const card = userList.querySelector(`.mu-card[data-uid="${editUid}"]`);
                if (card) {
                    card.dataset.role = editNewRole;
                    const badge = card.querySelector('[data-role-badge]');
                    if (badge) {
                        badge.className = 'mu-role mu-role--' + editNewRole;
                        badge.textContent = editNewRole.charAt(0).toUpperCase() + editNewRole.slice(1);
                    }
                }
                updateFilterCounts();
                closeModals();
                showToast(res.msg, 'success');
            } else {
                showToast(res.msg, 'error');
            }
        })
        .catch(() => showToast('Network error.', 'error'))
        .finally(() => { btn.disabled = false; btn.textContent = 'Save'; });
}

// Open the delete confirmation modal.
function openDeleteModal(uid, name) {
    deleteUid = uid;
    document.getElementById('deleteUserName').textContent = name;
    document.getElementById('deleteOverlay').classList.add('visible');
}

// Delete the selected user and remove their card from the UI.
function confirmDelete() {
    const btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = true;
    btn.textContent = 'Deleting…';

    const fd = new FormData();
    fd.append('action', 'delete_user');
    fd.append('user_id', deleteUid);

    fetch('manage_users.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                const card = userList.querySelector(`.mu-card[data-uid="${deleteUid}"]`);
                if (card) {
                    card.classList.add('mu-card--deleting');
                    setTimeout(() => { card.remove(); updateFilterCounts(); applyFilters(); }, 380);
                }
                closeModals();
                showToast(res.msg, 'success');
                // Keep the total user count in the header in sync.
                const countEl = document.querySelector('.mu-user-count strong');
                if (countEl) countEl.textContent = parseInt(countEl.textContent) - 1;
            } else {
                showToast(res.msg, 'error');
            }
        })
        .catch(() => showToast('Network error.', 'error'))
        .finally(() => { btn.disabled = false; btn.textContent = 'Delete'; });
}

// Close any open modal overlay.
function closeModals() {
    document.querySelectorAll('.mu-overlay').forEach(o => o.classList.remove('visible'));
}

// Close the modal when the dimmed backdrop is clicked.
document.querySelectorAll('.mu-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeModals();
    });
});

// Let the Escape key dismiss any open modal.
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModals();
});

// Show a short-lived status message.
function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'mu-toast mu-toast--' + type + ' visible';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('visible'), 2800);
}

// Recalculate counts after a role change or deletion.
function updateFilterCounts() {
    const cards = userList.querySelectorAll('.mu-card');
    const counts = { all: 0, student: 0, mentor: 0, admin: 0 };
    cards.forEach(c => { counts.all++; counts[c.dataset.role]++; });
    filterBar.querySelectorAll('.mu-filter-btn').forEach(btn => {
        const f = btn.dataset.filter;
        const span = btn.querySelector('.mu-filter-count');
        if (span) span.textContent = counts[f] ?? 0;
    });
}
</script>

</body>
</html>