<?php 
session_start();
require_once '..\includes\db.php';
require_once '..\includes\oauth_config.php';

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    header('Location: ../index.php');
    exit;
}

// ── OAuth initiation ──
if (isset($_GET['oauth'])) {
    $provider = $_GET['oauth'];
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    $_SESSION['oauth_action'] = 'login';

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

// Collect friendly errors for the UI.
$errors = []; 

// Handle login form submit.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Basic required-field check.
    if (empty($email) || empty($password)) {
        $errors[] = "Please enter both email and password.";
    } else {
        try {
            // Look up the user by email.
            $sql = "SELECT id, first_name, last_name, email, phone, password, role FROM users WHERE email = ? LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check password and start the user session.
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['phone'] = $user['phone'];
                $_SESSION['role'] = $user['role'];

                // Send user to their main page.
                header("Location: dashboard.php");
                exit;
            } else {
                $errors[] = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            // Keep database details hidden from users.
            $errors[] = "An error occurred, please try again later.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In | Mentor Match</title>
    <meta name="description" content="Sign in to Mentor Match — find mentors or Students.">
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

        <section class="auth-card login-card" aria-labelledby="login-heading">
            <h1 id="login-heading">Sign in to your account</h1>
            <p class="auth-lead">Welcome back — sign in to continue to Mentor Match.</p>

            <?php if (!empty($errors)): ?>
                <div class="errors" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div>
                    <label for="email">Email</label>
                    <input class="input" id="email" name="email" type="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>" placeholder="you@example.com">
                </div>

                <div>
                    <label for="password">Password</label>
                    <input class="input" id="password" name="password" type="password" required placeholder="Your password" autocomplete="current-password">
                </div>

                <div class="auth-options-row">
                    <label class="auth-remember"><input type="checkbox" name="remember"> <span class="small">Remember me</span></label>
                    <a class="link small" href="./forgot_password.php">Forgot password?</a>
                </div>

                <button class="btn" type="submit">Sign in</button>
            </form>

            <div class="oauth-divider"><span>or continue with</span></div>

            <div class="oauth-buttons">
                <a href="login.php?oauth=google" class="btn-oauth btn-google">
                    <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59a14.5 14.5 0 0 1 0-9.18l-7.98-6.19a24.0 24.0 0 0 0 0 21.56l7.98-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                    Google
                </a>
                <a href="login.php?oauth=microsoft" class="btn-oauth btn-microsoft">
                    <svg width="20" height="20" viewBox="0 0 21 21"><rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/><rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg>
                    Microsoft
                </a>
            </div>

            <p class="auth-footer-text small">Don't have an account? <a class="link" href="./signup.php">Create one</a></p>
        </section>
    </main>

    <script>
        document.getElementById('email')?.focus();
    </script>
</body>
</html>