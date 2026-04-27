<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../pages/login.php');
    exit;
}
$errors = [];
$success = false;

// If student profile already exists, redirect to dashboard
try {
    $check = $pdo->prepare('SELECT student_id FROM students WHERE student_id = ?');
    $check->execute([$_SESSION['user_id']]);
    if ($check->rowCount() > 0) {
        header('Location: ../../pages/dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    
}

// Fetch subjects from database
$subjects = [];
try {
    $stmt = $pdo->query('SELECT id, name FROM subjects ORDER BY name');
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Unable to load subjects.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course = trim($_POST['course'] ?? '');
    $year_of_study = trim($_POST['year_of_study'] ?? '');
    $learning_preference = $_POST['learning_preference'] ?? '';
    $bio = trim($_POST['bio'] ?? '');

    if ($course === '') {
        $errors[] = 'Please enter your course.';
    }

    if ($year_of_study === '' || !is_numeric($year_of_study) || (int)$year_of_study < 1) {
        $errors[] = 'Please enter a valid year of study.';
    }

    $allowed_prefs = ['Videos', 'In person sessions', 'Quizzes'];
    if (!in_array($learning_preference, $allowed_prefs)) {
        $errors[] = 'Please select a learning preference.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('INSERT INTO students (student_id, course, year_of_study, learning_preference, bio, created_at, user_id) VALUES (?, ?, ?, ?, ?, NOW(), ?)');
            $stmt->execute([$_SESSION['user_id'], $course, (int)$year_of_study, $learning_preference, $bio, $_SESSION['user_id']]);
            $success = true;
        } catch (PDOException $e) {
            $errors[] = 'An error occurred while saving your profile: ' . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Profile Setup | Mentor Match</title>
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="../../assets/css/student_profile_setup.css">
    <meta name="description" content="Set up your student profile.">
</head>
<body class="setup-page">
    <main class="setup-container">
        <section class="setup-card" aria-labelledby="setup-heading">
            <!-- Decorative header -->
            <div class="setup-header">
                <div class="setup-icon-wrap">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4z" fill="#fff"/><path d="M12 14c-4.42 0-8 1.79-8 4v2h16v-2c0-2.21-3.58-4-8-4z" fill="#fff"/></svg>
                </div>
                <h1 id="setup-heading">Set up your Student profile</h1>
                <p class="setup-lead">Tell us a little about your studies so we can match you with mentors.</p>
            </div>

            <!-- Progress indicator -->
            <div class="setup-progress" aria-hidden="true">
                <div class="progress-step active">
                    <div class="progress-dot">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4-4v2" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><circle cx="10" cy="7" r="3" stroke="currentColor" stroke-width="2.5"/></svg>
                    </div>
                    <span>Profile</span>
                </div>
                <div class="progress-line"></div>
                <div class="progress-step">
                    <div class="progress-dot">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M17 18a2 2 0 00-2-2H9a2 2 0 00-2 2" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><circle cx="12" cy="11" r="3" stroke="currentColor" stroke-width="2.5"/><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2.5"/></svg>
                    </div>
                    <span>Match</span>
                </div>
                <div class="progress-line"></div>
                <div class="progress-step">
                    <div class="progress-dot">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2V3zM22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7V3z" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <span>Learn</span>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="setup-success" role="status">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="#22c55e" opacity="0.15"/><path d="M9 12l2 2 4-4" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span>Profile saved — you can now access your dashboard.</span>
                </div>
                <p><a class="btn setup-btn-primary" href="../../pages/dashboard.php">Go to dashboard</a></p>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="setup-errors" role="alert">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="#ef4444" opacity="0.12"/><path d="M12 8v4m0 4h.01" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round"/></svg>
                        <div>
                            <?php foreach ($errors as $err): ?>
                                <div><?php echo htmlspecialchars($err); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate class="setup-form">

                    <div class="form-group">
                        <label for="course">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 016.5 17H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Course
                        </label>
                        <div class="select-wrap">
                            <select class="input" id="course" name="course" required>
                                <option value="">Choose a course</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo htmlspecialchars($subject['name']); ?>" <?php echo (isset($_POST['course']) && $_POST['course'] === $subject['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="select-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="year_of_study">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            Year of study
                        </label>
                        <input class="input" id="year_of_study" name="year_of_study" type="number" min="1" max="10" required value="<?php echo htmlspecialchars($_POST['year_of_study'] ?? ''); ?>" placeholder="e.g. 2">
                    </div>

                    <div class="form-group">
                        <label for="learning_preference">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.27 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Learning preference
                        </label>
                        <div class="select-wrap">
                            <select class="input" id="learning_preference" name="learning_preference" required>
                                <option value="">Choose a preference</option>
                                <option value="Videos" <?php echo (isset($_POST['learning_preference']) && $_POST['learning_preference']==='Videos')?'selected':''; ?>>Videos</option>
                                <option value="In person sessions" <?php echo (isset($_POST['learning_preference']) && $_POST['learning_preference']==='In person sessions')?'selected':''; ?>>In person sessions</option>
                                <option value="Quizzes" <?php echo (isset($_POST['learning_preference']) && $_POST['learning_preference']==='Quizzes')?'selected':''; ?>>Quizzes</option>
                            </select>
                            <svg class="select-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bio">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Short bio <span class="label-optional">(optional)</span>
                        </label>
                        <textarea class="input textarea" id="bio" name="bio" maxlength="1000" placeholder="Tell mentors about your goals, background and what you'd like to learn."><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
                        <span class="form-hint">Max 1000 characters</span>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn setup-btn-primary">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Save profile
                        </button>
                        <a class="btn setup-btn-secondary" href="../../index.php">Skip for now</a>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>


</body>
</html>
