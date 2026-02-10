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
    <title>Apply as Mentor — Mentor Match</title>
    <meta name="description" content="Apply to become a mentor on Mentor Match — share your expertise and help students grow.">
    <link rel="stylesheet" href="../../assets/css/styles.css">
</head>
<body>
    <main class="container">
        <section class="card" aria-labelledby="application-heading">
            <div class="logo">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3" fill="var(--accent)"/><path d="M3 20c0-3.866 3.582-7 9-7s9 3.134 9 7" stroke="#111827" stroke-opacity=".06" stroke-width="1.5"/></svg>
                <div class="brand">Mentor Match</div>
            </div>
            <h1 id="application-heading">Become a Mentor</h1>
            <p class="lead">Share your expertise and help students achieve their goals.</p>

            <?php if ($success): ?>
                <div class="success" role="alert">
                    <strong>Application submitted successfully!</strong> We'll review your application and get back to you soon.
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="errors" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div>
                    <label for="motivation">Why do you want to become a mentor?</label>
                    <textarea class="input textarea" id="motivation" name="motivation" required placeholder="Tell us about your passion for mentoring and teaching others..." maxlength="2000"><?php echo htmlspecialchars($_POST['motivation'] ?? ''); ?></textarea>
                    <small class="char-count"><span id="char-count">0</span>/2000</small>
                </div>

                <div>
                    <label for="subject_id">Subject</label>
                    <select class="input" id="subject_id" name="subject_id" required>
                        <option value="">Select a subject</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo (isset($_POST['subject_id']) && $_POST['subject_id'] == $s['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="experience_years">Years of experience</label>
                    <input class="input" id="experience_years" name="experience_years" type="number" inputmode="numeric" min="0" max="70" step="1" required placeholder="5" value="<?php echo htmlspecialchars($_POST['experience_years'] ?? ''); ?>">
                    <small class="help-text">How many years of professional or practical experience do you have?</small>
                </div>

                <button type="submit" class="btn">Submit Application</button>
                <a href="../index.php" class="btn secondary">Cancel</a>
            </form>
        </section>
    </main>

    <style>
        .textarea {
            min-height: 140px;
            padding: 12px 14px;
            resize: vertical;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;
            font-size: 1rem;
        }

        .char-count {
            display: block;
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 4px;
        }

        .help-text {
            display: block;
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 4px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 12px;
        }

        .secondary {
            margin-top: 6px;
            display: block;
            text-align: center;
            text-decoration: none;
        }
    </style>

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
