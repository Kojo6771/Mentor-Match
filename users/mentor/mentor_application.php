<?php 
session_start();
require_once '../../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../pages/login.php');
    exit;
}

$errors = [];
$success = false;
// Load available subjects for the dropdown
$subjects = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM subjects ORDER BY name");
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // ignore - form will show without subjects
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $motivation = trim($_POST['motivation']);
    $experience_years = trim($_POST['experience_years']);
    $subject_id = $_POST['subject_id'] ?? null;

    // Validation
    if (empty($motivation)) {
        $errors[] = "Please tell us about your motivation to become a mentor.";
    } elseif (strlen($motivation) < 20) {
        $errors[] = "Motivation should be at least 20 characters long.";
    } elseif (strlen($motivation) > 2000) {
        $errors[] = "Motivation should not exceed 2000 characters.";
    }

    if (empty($experience_years)) {
        $errors[] = "Please enter your years of experience.";
    } elseif (!is_numeric($experience_years) || $experience_years < 0 || $experience_years > 70) {
        $errors[] = "Please enter a valid number of years (0-70).";
    }

    // Subject validation
    if (empty($subject_id) || !is_numeric($subject_id)) {
        $errors[] = "Please select a subject.";
    } else {
        // ensure subject exists
        try {
            $checkSub = $pdo->prepare("SELECT id FROM subjects WHERE id = ?");
            $checkSub->execute([(int)$subject_id]);
            if ($checkSub->rowCount() === 0) {
                $errors[] = "Selected subject is invalid.";
            }
        } catch (PDOException $e) {
            $errors[] = "An error occurred while validating the subject.";
        }
    }

    // Check if user already has a pending or approved application
    if (empty($errors)) {
        try {
            $check_sql = "SELECT id FROM mentor_applications WHERE user_id = ? AND status IN ('pending', 'approved')";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$user_id]);

            if ($check_stmt->rowCount() > 0) {
                $errors[] = "You already have a pending or approved mentor application.";
            }
        } catch (PDOException $e) {
            $errors[] = "An error occurred while checking your application status.";
        }
    }

    // Insert into database if no errors (application + subject mapping)
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $sql = "INSERT INTO mentor_applications (user_id, motivation, experience_years, status) VALUES (?, ?, ?, 'pending')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $motivation, (int)$experience_years]);

            $application_id = $pdo->lastInsertId();

            $sub_sql = "INSERT INTO mentor_application_subjects (application_id, subject_id) VALUES (?, ?)";
            $sub_stmt = $pdo->prepare($sub_sql);
            $sub_stmt->execute([$application_id, (int)$subject_id]);

            $pdo->commit();

            $success = true;
            $_POST = [];
            header("Refresh:3; url=../../pages/dashboard.php");
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "An error occurred while submitting your application. Please try again.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Apply as Mentor | Mentor Match</title>
    <meta name="description" content="Apply to become a mentor on Mentor Match — share your expertise and help students grow.">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="../../assets/css/mentor_application.css">
</head>
<body class="mentor-app-page">
    <main class="mentor-app-container">
        <section class="mentor-app-card" aria-labelledby="application-heading">
            <!-- Decorative gradient bar rendered via CSS ::before -->

            <div class="mentor-app-header">
                <div class="mentor-app-icon-wrap">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" fill="url(#starGrad)" stroke="none"/>
                        <defs><linearGradient id="starGrad" x1="2" y1="2" x2="22" y2="21"><stop stop-color="#3b82f6"/><stop offset="1" stop-color="#06b6d4"/></linearGradient></defs>
                    </svg>
                </div>
                <div class="mentor-app-brand">Mentor Match</div>
                <h1 id="application-heading">Become a Mentor</h1>
                <p class="mentor-app-lead">Share your expertise and help students achieve their goals.</p>
            </div>

            <?php if ($success): ?>
                <div class="mentor-app-success" role="alert">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="#dcfce7"/><path d="M8 12l3 3 5-5" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <div><strong>Application submitted successfully!</strong> We'll review your application and get back to you soon.</div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="mentor-app-errors" role="alert">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="#fee2e2"/><path d="M12 8v4m0 4h.01" stroke="#dc2626" stroke-width="2" stroke-linecap="round"/></svg>
                    <div>
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="form-group">
                    <label for="motivation">
                        <svg class="field-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4L16.5 3.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Why do you want to become a mentor?
                    </label>
                    <textarea class="input textarea" id="motivation" name="motivation" required placeholder="Tell us about your passion for mentoring and teaching others..." maxlength="2000"><?php echo htmlspecialchars($_POST['motivation'] ?? ''); ?></textarea>
                    <small class="char-count"><span id="char-count">0</span>/2000</small>
                </div>

                <div class="form-group">
                    <label for="subject_id">
                        <svg class="field-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 016.5 17H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Subject
                    </label>
                    <div class="select-wrap">
                        <select class="input" id="subject_id" name="subject_id" required>
                            <option value="">Select a subject</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo (isset($_POST['subject_id']) && $_POST['subject_id'] == $s['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="select-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                </div>

                <div class="form-group">
                    <label for="experience_years">
                        <svg class="field-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M22 12h-4l-3 9L9 3l-3 9H2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Years of experience
                    </label>
                    <input class="input" id="experience_years" name="experience_years" type="number" inputmode="numeric" min="0" max="70" step="1" required placeholder="5" value="<?php echo htmlspecialchars($_POST['experience_years'] ?? ''); ?>">
                    <small class="help-text">How many years of professional or practical experience do you have?</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn mentor-app-submit">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M22 2L11 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Submit Application
                    </button>
                    <a href="../../index.php" class="btn mentor-app-cancel">Cancel</a>
                </div>
            </form>
        </section>
    </main>


    <script>
        const textarea = document.getElementById('motivation');
        const charCount = document.getElementById('char-count');

        textarea.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });

        // Update character count on page load
        charCount.textContent = textarea.value.length;
    </script>
</body>
</html>
