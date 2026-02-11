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
    <title>Student Profile Setup — Mentor Match</title>
    <meta name="description" content="Set up your student profile.">
    <link rel="stylesheet" href="../../assets/css/styles.css">
</head>
<body>
    <main class="container">
        <section class="card" aria-labelledby="setup-heading">
            <div class="logo">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3" fill="var(--accent)"/><path d="M3 20c0-3.866 3.582-7 9-7s9 3.134 9 7" stroke="#111827" stroke-opacity=".06" stroke-width="1.5"/></svg>
                <div class="brand">Mentor Match</div>
            </div>
            <h1 id="setup-heading">Set up your Student profile</h1>
            <p class="lead">Tell us a little about your studies so we can match you with mentors.</p>

            <?php if ($success): ?>
                <div class="success" role="status">Profile saved — you can now access your dashboard.</div>
                <p><a class="btn" href="../student/student_dashboard.php">Go to dashboard</a></p>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="errors" role="alert">
                        <?php foreach ($errors as $err): ?>
                            <div><?php echo htmlspecialchars($err); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>

                    <div>
                        <label for="course">Course</label>
                        <input class="input" id="course" name="course" type="text" required value="<?php echo htmlspecialchars($_POST['course'] ?? ''); ?>" placeholder="e.g. BSc Computer Science">
                    </div>

                    <div>
                        <label for="year_of_study">Year of study</label>
                        <input class="input" id="year_of_study" name="year_of_study" type="number" min="1" max="10" required value="<?php echo htmlspecialchars($_POST['year_of_study'] ?? ''); ?>">
                    </div>

                    <div>
                        <label for="learning_preference">Learning preference</label>
                        <select class="input" id="learning_preference" name="learning_preference" required>
                            <option value="">Choose a preference</option>
                            <option value="Videos" <?php echo (isset($_POST['learning_preference']) && $_POST['learning_preference']==='Videos')?'selected':''; ?>>Videos</option>
                            <option value="In person sessions" <?php echo (isset($_POST['learning_preference']) && $_POST['learning_preference']==='In person sessions')?'selected':''; ?>>In person sessions</option>
                            <option value="Quizzes" <?php echo (isset($_POST['learning_preference']) && $_POST['learning_preference']==='Quizzes')?'selected':''; ?>>Quizzes</option>
                        </select>
                    </div>

                    <div>
                        <label for="bio">Short bio (optional)</label>
                        <textarea class="input textarea" id="bio" name="bio" maxlength="1000" placeholder="Tell mentors about your goals, background and what you'd like to learn."><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn">Save profile</button>
                    <a class="btn secondary" href="../../index.php">Skip for now</a>
                </form>
            <?php endif; ?>
        </section>
    </main>

    <style>
        .textarea{min-height:120px;resize:vertical}
        .success{background:#dcfce7;color:#166534;padding:10px;border-radius:8px;margin-bottom:12px}
    </style>
</body>
</html>
