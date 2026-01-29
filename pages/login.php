<?php 
session_start();
require_once '..\includes\db.php';
$errors = []; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $errors[] = "Please enter both email and password.";
    } else {
        try {
            $sql = "SELECT id, first_name, last_name, email, phone, password, role FROM users WHERE email = ? LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['phone'] = $user['phone'];
                $_SESSION['role'] = $user['role'];

                header("Location: ./dashboard.php");
                exit;
            } else {
                $errors[] = "Invalid email or password.";
            }
        } catch (PDOException $e) {
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
    <title>Sign In — Mentor Match</title>
    <meta name="description" content="Sign in to Mentor Match — find mentors or Students.">
    <link rel="stylesheet" href="../assets/css/signup.css">
</head>
<body>
    <main class="container">
        <section class="card" aria-labelledby="login-heading">
            <div class="logo">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3" fill="var(--accent)"/><path d="M3 20c0-3.866 3.582-7 9-7s9 3.134 9 7" stroke="#111827" stroke-opacity=".06" stroke-width="1.5"/></svg>
                <div class="brand">Mentor Match</div>
            </div>
            <h1 id="login-heading">Sign in to your account</h1>
            <p class="lead">Welcome back — sign in to continue to Mentor Match.</p>

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

                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:4px">
                    <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="remember"> <span class="small">Remember me</span></label>
                    <a class="link small" href="#">Forgot password?</a>
                </div>

                <button class="btn" type="submit">Sign in</button>
                <button type="button" class="btn secondary" onclick="location.href='./signup.php'">Create account</button>

                <p class="small">Don't have an account? <a class="link" href="./signup.php">Create one</a></p>
            </form>
        </section>
    </main>

    <script>
        // Basic client-side convenience: focus first field
        document.getElementById('email')?.focus();
    </script>
</body>
</html>