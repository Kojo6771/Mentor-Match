<?php 
session_start();
require_once '..\includes\db.php';
require_once '..\includes\oauth_config.php';
$errors = [];
$profile_picture_path = null;

// ── OAuth initiation (preserves chosen role) ──
if (isset($_GET['oauth'])) {
    $provider = $_GET['oauth'];
    $role = isset($_GET['role']) && $_GET['role'] === 'mentor' ? 'mentor' : 'student';
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    $_SESSION['oauth_action'] = 'signup';
    $_SESSION['oauth_role'] = $role;

    if ($provider === 'google') {
        $params = http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'redirect_uri'  => OAUTH_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => 'google-' . $state,
            'prompt'        => 'select_account',
        ]);
        header('Location: ' . GOOGLE_AUTH_URL . '?' . $params);
        exit;
    }

    if ($provider === 'microsoft') {
        // PKCE avoids relying on client secrets during browser-based auth flows.
        $code_verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $code_challenge = rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');
        $_SESSION['ms_oauth_code_verifier'] = $code_verifier;

        $params = http_build_query([
            'client_id'     => MICROSOFT_CLIENT_ID,
            'redirect_uri'  => OAUTH_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'openid profile User.Read offline_access',
            'state'         => 'microsoft-' . $state,
            'prompt'        => 'select_account',
            'code_challenge' => $code_challenge,
            'code_challenge_method' => 'S256',
        ]);
        header('Location: ' . MICROSOFT_AUTH_URL . '?' . $params);
        exit;
    }
}

// Ensure uploads directory exists
$uploads_dir = '../uploads/profile_pictures/';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'] === 'mentor' ? 'mentor' : 'student';

    if ($password !== $confirm_password) {
        $errors[] = "The passwords do not match, please try again.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must include at least one uppercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must include at least one number.";
    }

    // Profile picture validation and upload
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Please upload a profile picture.";
    } else {
        $file = $_FILES['profile_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            $errors[] = "Please upload a valid image file (JPEG, PNG, GIF, or WebP).";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "Profile picture must be smaller than 5MB.";
        } else {
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('profile_') . '_' . time() . '.' . $ext;
            $file_path = $uploads_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $profile_picture_path = 'uploads/profile_pictures/' . $filename;
            } else {
                $errors[] = "Failed to upload profile picture. Please try again.";
            }
        }
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        if (empty($errors)) {
            $sql = "INSERT INTO users (first_name, last_name, email, phone, password, role, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$first_name, $last_name, $email, $phone, $password_hash, $role, $profile_picture_path]);

            $user_id = $pdo->lastInsertId();

            $_SESSION['user_id'] = $user_id;
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
            $_SESSION['phone'] = $phone;
            $_SESSION['role'] = $role;

            if ($role === 'mentor') {
                header("Location: ../users/mentor/mentor_application.php");
                exit;
            }
            
            if ($role === 'student') {
                header("Location: ../users/student/student_profile_setup.php");
                exit;
            }
        }
    } catch (PDOException $e) {
        // Clean up uploaded file if database operation fails
        if ($profile_picture_path && file_exists($uploads_dir . basename($profile_picture_path))) {
            unlink($uploads_dir . basename($profile_picture_path));
        }
        $errors[] = "An error occurred, please try again later.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Up | Mentor Match</title>
    <meta name="description" content="Sign up for Mentor Match — mobile friendly mentor/Students matching.">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <main class="auth-page">
        <a href="../index.php" class="auth-brand">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="8" r="3" fill="#3b82f6"/>
                <path d="M3 20c0-3.866 3.582-7 9-7s9 3.134 9 7" stroke="#111827" stroke-opacity=".06" stroke-width="1.5"/>
            </svg>
            <span class="auth-brand-text">Mentor Match</span>
        </a>

        <section class="auth-card" aria-labelledby="signup-heading">
            <h1 id="signup-heading">Create your account</h1>
            <p class="auth-lead">Quick and easy sign up to find mentors or students.</p>

            <?php if (!empty($errors)): ?>
                <div class="errors" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" novalidate>
                <div class="row">
                    <div>
                        <label for="first_name">First name</label>
                        <input class="input" id="first_name" name="first_name" type="text" required value="<?php echo htmlspecialchars($first_name ?? ''); ?>" placeholder="Jane">
                    </div>
                    <div>
                        <label for="last_name">Last name</label>
                        <input class="input" id="last_name" name="last_name" type="text" required value="<?php echo htmlspecialchars($last_name ?? ''); ?>" placeholder="Doe">
                    </div>
                </div>

                <div>
                    <label for="phone">Phone</label>
                    <input class="input" id="phone" name="phone" type="tel" inputmode="tel" pattern="[0-9+\- ()]*" value="<?php echo htmlspecialchars($phone ?? ''); ?>" placeholder="(+44) 7123 456 789">
                </div>

                <div>
                    <label for="email">Email</label>
                    <input class="input" id="email" name="email" type="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>" placeholder="you@example.com">
                </div>

                <div>
                    <label for="profile_picture">Profile Picture</label>
                    <input class="input" id="profile_picture" name="profile_picture" type="file" accept="image/jpeg,image/png,image/gif,image/webp" required>
                    <small class="help-text">Upload a JPG, PNG, GIF, or WebP image (max 5MB)</small>
                </div>

                <div class="row">
                    <div>
                        <label for="password">Password</label>
                        <input class="input" id="password" name="password" type="password" required placeholder="Create a password" autocomplete="new-password">
                    </div>
                    <div>
                        <label for="confirm_password">Confirm</label>
                        <input class="input" id="confirm_password" name="confirm_password" type="password" required placeholder="Re-enter password" autocomplete="new-password">
                    </div>
                </div>

                <fieldset style="border:0;padding:0;margin:0">
                    <legend class="small">I am a</legend>
                    <div class="roles" role="radiogroup" aria-label="Account type">
                        <label class="role" id="role-mentor">
                            <input type="radio" name="role" value="mentor" required>
                            Mentor
                        </label>
                        <label class="role" id="role-student">
                            <input type="radio" name="role" value="student" required checked>
                            Student
                        </label>
                    </div>
                </fieldset>

                <div class="auth-terms">
                    <input id="terms" name="terms" type="checkbox" required>
                    <label for="terms" class="small" style="margin-bottom:0">I agree to the <a class="link" href="terms.php">Terms</a> and <a class="link" href="privacy.php">Privacy Policy</a>.</label>
                </div>

                <button class="btn" type="submit">Get started</button>
            </form>

            <div class="oauth-divider"><span>or sign up with</span></div>

            <div class="oauth-buttons">
                <a href="#" class="btn-oauth btn-google" id="oauth-google">
                    <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59a14.5 14.5 0 0 1 0-9.18l-7.98-6.19a24.0 24.0 0 0 0 0 21.56l7.98-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                    Google
                </a>
                <a href="#" class="btn-oauth btn-microsoft" id="oauth-microsoft">
                    <svg width="20" height="20" viewBox="0 0 21 21"><rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/><rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg>
                    Microsoft
                </a>
            </div>

            <p class="auth-footer-text small">Already have an account? <a class="link" href="./login.php">Sign in</a></p>
        </section>
    </main>

    <script>
        document.querySelectorAll('.role').forEach(label => {
            const input = label.querySelector('input');
            label.addEventListener('click', ()=>{
                document.querySelectorAll('.role').forEach(l=>l.classList.remove('selected'));
                label.classList.add('selected');
                input.checked = true;
            });
            if (label.querySelector('input').checked) label.classList.add('selected');
        });

        // Pass selected role to OAuth sign-up links
        document.getElementById('oauth-google').addEventListener('click', function(e) {
            e.preventDefault();
            var role = document.querySelector('input[name="role"]:checked')?.value || 'student';
            window.location.href = 'signup.php?oauth=google&role=' + role;
        });
        document.getElementById('oauth-microsoft').addEventListener('click', function(e) {
            e.preventDefault();
            var role = document.querySelector('input[name="role"]:checked')?.value || 'student';
            window.location.href = 'signup.php?oauth=microsoft&role=' + role;
        });
    </script>

</body>

</html>
