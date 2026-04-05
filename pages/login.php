<?php 
session_start();
require_once '..\includes\db.php';

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

                <p class="auth-footer-text small">Don't have an account? <a class="link" href="./signup.php">Create one</a></p>
            </form>
        </section>
    </main>

    <script>
        document.getElementById('email')?.focus();
    </script>
</body>
</html>