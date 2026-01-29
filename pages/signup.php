<?php 
session_start();
require_once '..\includes\db.php';
$errors = [];   

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

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $sql = "INSERT INTO users (first_name, last_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$first_name, $last_name, $email, $phone, $password_hash, $role]);

        $user_id = $pdo->lastInsertId();

        $_SESSION['user_id'] = $user_id;
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name;
        $_SESSION['email'] = $email;
        $_SESSION['phone'] = $phone;
        $_SESSION['role'] = $role;

        if ($role === 'mentor') {
            header("Location: ./mentor_profile_setup.php");
            exit;
        }
        
        if ($role === 'student') {
            header("Location: ./student_profile_setup.php");
            exit;
        }

    } catch (PDOException $e) {
        echo "An error occurred, please try again later: " . $e->getMessage();
        exit;
    }
}




?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Up — Mentor Match</title>
    <meta name="description" content="Sign up for Mentor Match — mobile friendly mentor/Students matching.">
    <link rel="stylesheet" href="../assets/css/signup.css">
</head>
<body>
    <main class="container">
        <section class="card" aria-labelledby="signup-heading">
            <div class="logo">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3" fill="var(--accent)"/><path d="M3 20c0-3.866 3.582-7 9-7s9 3.134 9 7" stroke="#111827" stroke-opacity=".06" stroke-width="1.5"/></svg>
                <div class="brand">Mentor Match</div>
            </div>
            <h1 id="signup-heading">Create your account</h1>
            <p class="lead">Quick and easy sign up to find mentors or Studentss. Designed for mobile devices.</p>

            <?php if (!empty($errors)): ?>
                <div class="errors" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
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

                <div style="display:flex;gap:8px;align-items:center;margin-top:6px">
                    <input id="terms" name="terms" type="checkbox" required>
                    <label for="terms" class="small">I agree to the <a class="link" href="#">Terms</a> and <a class="link" href="#">Privacy Policy</a>.</label>
                </div>

                <button class="btn" type="submit">Get started</button>
                <button type="button" class="btn secondary" onclick="location.href='/pages/login.php'">Sign in</button>

                <p class="small">Already have an account? <a class="link" href="./login.php">Sign in</a></p>
            </form>
        </section>
    </main>

    <script>
        // Simple UI: role selection styling + basic client-side validation hint
        document.querySelectorAll('.role').forEach(label => {
            const input = label.querySelector('input');
            label.addEventListener('click', ()=>{
                document.querySelectorAll('.role').forEach(l=>l.classList.remove('selected'));
                label.classList.add('selected');
                input.checked = true;
            });
            // initialize selected from checked state (useful if server-side preserved it)
            if (label.querySelector('input').checked) label.classList.add('selected');
        });
    </script>
</body>
</html>
