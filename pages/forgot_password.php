<?php
session_start();
require_once '..\includes\db.php';

// Time before code resets variable
const RESET_CODE_EXPIRY_MINUTES = 15;



// Page state used by the UI.
$errors = [];
$notice = '';
$step = 'email';
$email = trim($_SESSION['forgot_password_email'] ?? '');

// Clears the reset flow from session.
function clearForgotPasswordState(): void
{
	unset(
		$_SESSION['forgot_password_email'],
		$_SESSION['forgot_password_verified']
	);
}



function getForgotPasswordEmail(): string
{
	$postEmail = trim($_POST['email'] ?? '');
	if ($postEmail !== '') {
		return $postEmail;
	}

	return trim($_SESSION['forgot_password_email'] ?? '');
}


// Sends the reset code email. Returns false if mail() fails.
function sendResetCodeEmail(string $email, string $firstName, string $code): bool
{
	$subject = 'Mentor Match - Password Reset Code';
	$message = "Hello {$firstName},\r\n\r\n";
	$message .= "We received a request to reset your Mentor Match password.\r\n\r\n";
	$message .= "Your 6-digit verification code is: {$code}\r\n\r\n";
	$message .= 'This code expires in ' . RESET_CODE_EXPIRY_MINUTES . " minutes.\r\n\r\n";
	$message .= "If you did not request this, you can safely ignore this email.\r\n\r\n";
	$message .= "Please do not reply to this message.\r\n\r\n";
	$message .= '- Mentor Match';

	$headers = [];
	$headers[] = 'MIME-Version: 1.0';
	$headers[] = 'Content-type: text/plain; charset=UTF-8';
	$headers[] = 'From: Mentor Match (No Reply) ';
	$headers[] = 'X-Mailer: PHP/' . phpversion();

	return @mail($email, $subject, $message, implode("\r\n", $headers));
}

// Creates a fresh code and expires older active ones.
function createPasswordResetCode(PDO $pdo, array $user, string $email): bool
{
	$pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE email = ? AND used_at IS NULL')->execute([$email]);

	$code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
	$codeHash = password_hash($code, PASSWORD_DEFAULT);
	$expiresAt = date('Y-m-d H:i:s', strtotime('+' . RESET_CODE_EXPIRY_MINUTES . ' minutes'));

	$insert = $pdo->prepare('INSERT INTO password_resets (user_id, email, code_hash, expires_at) VALUES (?, ?, ?, ?)');
	$insert->execute([(int) $user['id'], $email, $codeHash, $expiresAt]);

	if (!sendResetCodeEmail($email, (string) $user['first_name'], $code)) {
		$pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE email = ? AND used_at IS NULL')->execute([$email]);
		return false;
	}

	return true;
}

if (isset($_GET['restart']) && $_GET['restart'] === '1') {
	clearForgotPasswordState();
	header('Location: forgot_password.php');
	exit;
}

if (!empty($_SESSION['forgot_password_verified']) && $email !== '') {
	$step = 'reset';
} elseif ($email !== '') {
	$step = 'verify';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';

	// Step 1: Request code.
	if ($action === 'request_code') {
		$email = trim($_POST['email'] ?? '');
		$step = 'email';

		if ($email === '') {
			$errors[] = 'Please enter your email address.';
		} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$errors[] = 'Please enter a valid email address.';
		} else {
			try {
				$stmt = $pdo->prepare('SELECT id, first_name, email FROM users WHERE email = ? LIMIT 1');
				$stmt->execute([$email]);
				$user = $stmt->fetch(PDO::FETCH_ASSOC);

				if (!$user) {
					$errors[] = 'No account was found with that email address.';
				} elseif (!createPasswordResetCode($pdo, $user, $email)) {
					$errors[] = 'We could not send the email right now. Please try again later.';
				} else {
					$_SESSION['forgot_password_email'] = $email;
					unset($_SESSION['forgot_password_verified']);
					$step = 'verify';
					$notice = 'A 6-digit verification code has been sent to your email address.';
				}
			} catch (PDOException $e) {
				$errors[] = 'Something went wrong while preparing your password reset.';
			}
		}
	}

	//  Resend a fresh code.
	if ($action === 'resend_code') {
		$email = getForgotPasswordEmail();
		$step = 'verify';

		if ($email === '') {
			$step = 'email';
			$errors[] = 'Please start again and enter your email address.';
		} else {
			try {
				$stmt = $pdo->prepare('SELECT id, first_name, email FROM users WHERE email = ? LIMIT 1');
				$stmt->execute([$email]);
				$user = $stmt->fetch(PDO::FETCH_ASSOC);

				if (!$user) {
					clearForgotPasswordState();
					$step = 'email';
					$errors[] = 'No account was found with that email address.';
				} elseif (!createPasswordResetCode($pdo, $user, $email)) {
					$errors[] = 'We could not resend the email right now. Please try again later.';
				} else {
					$_SESSION['forgot_password_email'] = $email;
					$notice = 'A new code has been sent to your email.';
				}
			} catch (PDOException $e) {
				$errors[] = 'Something went wrong while resending your code.';
			}
		}
	}

	// Verify code.
	if ($action === 'verify_code') {
		$email = getForgotPasswordEmail();
		$code = preg_replace('/\D/', '', $_POST['verification_code'] ?? '');
		$step = 'verify';

		if ($email === '') {
			clearForgotPasswordState();
			$step = 'email';
			$errors[] = 'Please start again and enter your email address.';
		} elseif (strlen($code) !== 6) {
			$errors[] = 'Please enter the full 6-digit verification code.';
		} else {
			try {
				$stmt = $pdo->prepare('SELECT id, user_id, code_hash, expires_at FROM password_resets WHERE email = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1');
				$stmt->execute([$email]);
				$resetRow = $stmt->fetch(PDO::FETCH_ASSOC);

				if (!$resetRow) {
					$errors[] = 'No active reset code was found. Please request a new code.';
				} elseif (strtotime((string) $resetRow['expires_at']) < time()) {
					$pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([(int) $resetRow['id']]);
					$errors[] = 'That code has expired. Please request a new code.';
				} elseif (!password_verify($code, (string) $resetRow['code_hash'])) {
					$errors[] = 'The code you entered is incorrect.';
				} else {
					$pdo->prepare('UPDATE password_resets SET verified_at = NOW() WHERE id = ?')->execute([(int) $resetRow['id']]);
					$_SESSION['forgot_password_verified'] = true;
					$step = 'reset';
					$notice = 'Code verified. You can now choose a new password.';
				}
			} catch (PDOException $e) {
				$errors[] = 'Something went wrong while verifying your code.';
			}
		}
	}

	// Save the new password.
	if ($action === 'reset_password') {
		$email = getForgotPasswordEmail();
		$password = $_POST['password'] ?? '';
		$confirmPassword = $_POST['confirm_password'] ?? '';
		$step = 'reset';

		if ($email === '') {
			clearForgotPasswordState();
			$step = 'email';
			$errors[] = 'Please start again and enter your email address.';
		}

		if (strlen($password) < 8) {
			$errors[] = 'Your new password must be at least 8 characters long.';
		}

		if ($password !== $confirmPassword) {
			$errors[] = 'The passwords do not match.';
		}

		if (empty($errors)) {
			try {
				$stmt = $pdo->prepare('SELECT id, user_id, email, expires_at FROM password_resets WHERE email = ? AND verified_at IS NOT NULL AND used_at IS NULL ORDER BY id DESC LIMIT 1');
				$stmt->execute([$email]);
				$resetRow = $stmt->fetch(PDO::FETCH_ASSOC);

				if (!$resetRow) {
					clearForgotPasswordState();
					$step = 'email';
					$errors[] = 'Your reset session is no longer valid. Please start again.';
				} elseif (strtotime((string) $resetRow['expires_at']) < time()) {
					$pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE email = ? AND used_at IS NULL')->execute([$email]);
					clearForgotPasswordState();
					$step = 'email';
					$errors[] = 'That code has expired. Please request a new one.';
				} else {
					$passwordHash = password_hash($password, PASSWORD_DEFAULT);

					$pdo->beginTransaction();
					$pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$passwordHash, (int) $resetRow['user_id']]);
					$pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([(int) $resetRow['id']]);
					$pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE email = ? AND used_at IS NULL')->execute([$email]);
					$pdo->commit();

					clearForgotPasswordState();
					$email = '';
					$step = 'success';
					$notice = 'Your password has been updated successfully. You can now sign in.';
				}
			} catch (PDOException $e) {
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}
				$errors[] = 'Something went wrong while saving your new password.';
			}
		}
	}

	// Keep email in session for the next step.
	if ($step !== 'success' && $email !== '') {
		$_SESSION['forgot_password_email'] = $email;
	}
}

// Map current step to the progress tracker.
$progressStep = ['email' => 1, 'verify' => 2, 'reset' => 3, 'success' => 3][$step] ?? 1;
?>


<!-- HTML -->
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Forgot Password | Mentor Match</title>
	<meta name="description" content="Reset your Mentor Match password with a 6-digit email verification code.">
	<link rel="stylesheet" href="../assets/css/styles.css">
	<link rel="stylesheet" href="../assets/css/forgot_password.css">
</head>
<body>
	<main class="container">
		<section class="card forgot-card" aria-labelledby="forgot-heading">
			<a class="back-link" href="./login.php" aria-label="Back to sign in">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
				Back to sign in
			</a>

			<div class="logo">
				<svg width="36" height="36" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3" fill="var(--accent)"/><path d="M3 20c0-3.866 3.582-7 9-7s9 3.134 9 7" stroke="#111827" stroke-opacity=".06" stroke-width="1.5"/></svg>
				<div class="brand">Mentor Match</div>
			</div>

			<div class="forgot-steps" aria-hidden="true">
				<div class="forgot-step-dot <?php echo $progressStep > 1 ? 'done' : 'active'; ?>"><?php echo $progressStep > 1 ? '✓' : '1'; ?></div>
				<div class="forgot-step-line <?php echo $progressStep > 1 ? 'done' : ''; ?>"></div>
				<div class="forgot-step-dot <?php echo $progressStep === 2 ? 'active' : ($progressStep > 2 ? 'done' : ''); ?>"><?php echo $progressStep > 2 ? '✓' : '2'; ?></div>
				<div class="forgot-step-line <?php echo $progressStep > 2 ? 'done' : ''; ?>"></div>
				<div class="forgot-step-dot <?php echo $progressStep >= 3 ? 'active' : ''; ?>"><?php echo $step === 'success' ? '✓' : '3'; ?></div>
			</div>

			<div class="forgot-hero <?php echo $step === 'success' ? 'success' : ''; ?>" aria-hidden="true">
				<?php if ($step === 'verify'): ?>
					<svg width="30" height="30" viewBox="0 0 24 24" fill="none"><path d="M4 6h16v12H4z" stroke="currentColor" stroke-width="2"/><path d="M4 7l8 6 8-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
				<?php elseif ($step === 'reset' || $step === 'success'): ?>
					<svg width="30" height="30" viewBox="0 0 24 24" fill="none"><path d="M12 17a2 2 0 100-4 2 2 0 000 4z" stroke="currentColor" stroke-width="2"/><path d="M6 10V8a6 6 0 1112 0v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><rect x="4" y="10" width="16" height="10" rx="2" stroke="currentColor" stroke-width="2"/></svg>
				<?php else: ?>
					<svg width="30" height="30" viewBox="0 0 24 24" fill="none"><path d="M4 6h16v12H4z" stroke="currentColor" stroke-width="2"/><path d="M4 7l8 6 8-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
				<?php endif; ?>
			</div>

			<?php if ($step === 'email'): ?>
				<div class="forgot-center">
					<h1 id="forgot-heading">Forgot your password?</h1>
					<p class="forgot-lead">Enter the email address linked to your account and we'll send you a secure 6-digit code to reset your password.</p>
				</div>
			<?php elseif ($step === 'verify'): ?>
				<div class="forgot-center">
					<h1 id="forgot-heading">Check your email</h1>
					<p class="forgot-lead">We sent a 6-digit verification code to <strong><?php echo htmlspecialchars($email); ?></strong>. Enter it below to continue.</p>
				</div>
			<?php elseif ($step === 'reset'): ?>
				<div class="forgot-center">
					<h1 id="forgot-heading">Create a new password</h1>
					<p class="forgot-lead">Your identity has been verified. Choose a strong password and confirm it to finish resetting your account.</p>
				</div>
			<?php else: ?>
				<div class="forgot-center">
					<h1 id="forgot-heading">Password updated</h1>
					<p class="forgot-lead">Your password has been reset successfully. You can now sign in using your new password.</p>
				</div>
			<?php endif; ?>

			<?php if (!empty($notice)): ?>
				<div class="notice" role="status"><?php echo htmlspecialchars($notice); ?></div>
			<?php endif; ?>

			<?php if (!empty($errors)): ?>
				<div class="errors" role="alert">
					<?php foreach ($errors as $error): ?>
						<div><?php echo htmlspecialchars($error); ?></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ($step === 'email'): ?>
				<form method="POST" novalidate>
					<input type="hidden" name="action" value="request_code">
					<div>
						<label for="email">Email address</label>
						<input class="input" id="email" name="email" type="email" required value="<?php echo htmlspecialchars($email); ?>" placeholder="you@example.com" autocomplete="email">
					</div>
					<button class="btn send-code-btn" type="submit">Send 6-digit code</button>
					<p class="small">Remembered it? <a class="link" href="./login.php">Sign in</a></p>
				</form>
		<?php elseif ($step === 'verify'): ?>
			<form method="POST" id="verify-form" novalidate>
				<input type="hidden" name="action" value="verify_code">
				<input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
				<input type="hidden" name="verification_code" id="verification_code" value="">
				<div>
					<label for="code-1">Verification code</label>
					<div class="code-grid" id="code-grid">
						<input class="code-box" id="code-1" type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="Digit 1">
						<input class="code-box" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 2">
						<input class="code-box" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 3">
						<input class="code-box" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 4">
						<input class="code-box" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 5">
						<input class="code-box" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 6">
					</div>
					<div class="code-help">
						<span class="meta-note">Code expires in <?php echo RESET_CODE_EXPIRY_MINUTES; ?> minutes.</span>
						<span class="meta-note">Didn't get it? Use resend below.</span>
					</div>
				</div>
				<button class="btn" type="submit">Verify code</button>
			</form>
			<form method="POST" novalidate>
				<input type="hidden" name="action" value="resend_code">
				<input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
				<button class="resend-btn" type="submit">Resend code</button>
			</form>
			<p class="small">Entered the wrong email? <a class="link" href="./forgot_password.php?restart=1">Start again</a></p>
		<?php elseif ($step === 'reset'): ?>
			<form method="POST" id="reset-form" novalidate>
				<input type="hidden" name="action" value="reset_password">
				<input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
				<div class="password-shell">
					<div>
						<label for="password">New password</label>
						<div class="password-wrap">
							<input class="input" id="password" name="password" type="password" required placeholder="At least 8 characters" autocomplete="new-password">
							<button class="toggle-pass" type="button" data-target="password" aria-label="Show password">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
							</button>
						</div>
						<div class="password-meter" aria-hidden="true">
							<div class="password-meter-track"><div class="password-meter-fill" id="password-meter-fill"></div></div>
							<div class="password-meter-label" id="password-meter-label">Use 8+ characters with a mix of letters and numbers.</div>
						</div>
					</div>
					<div>
						<label for="confirm_password">Confirm password</label>
						<div class="password-wrap">
							<input class="input" id="confirm_password" name="confirm_password" type="password" required placeholder="Re-enter your password" autocomplete="new-password">
							<button class="toggle-pass" type="button" data-target="confirm_password" aria-label="Show password">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
							</button>
						</div>
						<span class="help-text" id="password-match-hint">Re-enter the same password to confirm.</span>
					</div>
				</div>
				<button class="btn" type="submit">Save new password</button>
			</form>
			<?php else: ?>
				<div class="success-actions">
					<a class="btn linkish" href="./login.php">Go to sign in</a>
					<p class="small">Your password has been changed and your account is ready to use.</p>
				</div>
			<?php endif; ?>
		</section>
	</main>

	<script>
		(function () {
			// Remove restart query param after reload.
			var params = new URLSearchParams(window.location.search);
			if (params.get('restart') === '1') {
				window.history.replaceState({}, document.title, window.location.pathname);
			}

			var codeBoxes = Array.prototype.slice.call(document.querySelectorAll('.code-box'));
			var hiddenCode = document.getElementById('verification_code');

			// Handles 6-digit code input UX.
			if (codeBoxes.length && hiddenCode) {
				var syncCode = function () {
					hiddenCode.value = codeBoxes.map(function (box) { return box.value; }).join('');
				};

				codeBoxes.forEach(function (box, index) {
					box.addEventListener('input', function () {
						box.value = box.value.replace(/\D/g, '').slice(0, 1);
						syncCode();
						if (box.value && index < codeBoxes.length - 1) {
							codeBoxes[index + 1].focus();
						}
					});

					box.addEventListener('keydown', function (event) {
						if (event.key === 'Backspace' && box.value === '' && index > 0) {
							codeBoxes[index - 1].focus();
						}
						if (event.key === 'ArrowLeft' && index > 0) {
							codeBoxes[index - 1].focus();
						}
						if (event.key === 'ArrowRight' && index < codeBoxes.length - 1) {
							codeBoxes[index + 1].focus();
						}
					});

					box.addEventListener('paste', function (event) {
						var pasted = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
						if (!pasted) return;
						event.preventDefault();
						pasted.split('').forEach(function (digit, pastedIndex) {
							if (codeBoxes[pastedIndex]) {
								codeBoxes[pastedIndex].value = digit;
							}
						});
						syncCode();
						codeBoxes[Math.min(pasted.length, codeBoxes.length - 1)].focus();
					});
				});
			}

			// Show/hide password fields.
			document.querySelectorAll('.toggle-pass').forEach(function (button) {
				button.addEventListener('click', function () {
					var input = document.getElementById(button.getAttribute('data-target'));
					if (!input) return;
					input.type = input.type === 'password' ? 'text' : 'password';
				});
			});

			var passwordInput = document.getElementById('password');
			var confirmInput = document.getElementById('confirm_password');
			var meterFill = document.getElementById('password-meter-fill');
			var meterLabel = document.getElementById('password-meter-label');
			var matchHint = document.getElementById('password-match-hint');

			// Live strength + match feedback.
			if (passwordInput && meterFill && meterLabel) {
				var updateStrength = function () {
					var value = passwordInput.value;
					var score = 0;
					if (value.length >= 8) score++;
					if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
					if (/\d/.test(value)) score++;
					if (/[^A-Za-z0-9]/.test(value)) score++;

					var width = ['0%', '25%', '50%', '75%', '100%'][score] || '0%';
					var label = 'Use 8+ characters with a mix of letters and numbers.';
					var color = '#e5e7eb';

					if (score === 1) { label = 'Weak password'; color = '#ef4444'; }
					if (score === 2) { label = 'Fair password'; color = '#f59e0b'; }
					if (score === 3) { label = 'Good password'; color = '#3b82f6'; }
					if (score >= 4) { label = 'Strong password'; color = '#10b981'; }

					meterFill.style.width = width;
					meterFill.style.background = color;
					meterLabel.textContent = value ? label : 'Use 8+ characters with a mix of letters and numbers.';
				};

				var updateMatch = function () {
					if (!confirmInput || !matchHint) return;
					if (confirmInput.value === '') {
						matchHint.textContent = 'Re-enter the same password to confirm.';
						matchHint.style.color = 'var(--muted)';
						return;
					}
					if (passwordInput.value === confirmInput.value) {
						matchHint.textContent = 'Passwords match.';
						matchHint.style.color = '#059669';
					} else {
						matchHint.textContent = 'Passwords do not match.';
						matchHint.style.color = '#dc2626';
					}
				};

				passwordInput.addEventListener('input', function () {
					updateStrength();
					updateMatch();
				});

				if (confirmInput) {
					confirmInput.addEventListener('input', updateMatch);
				}
			}

			// Focus first visible field on load.
			var firstInput = document.querySelector('input:not([type="hidden"])');
			if (firstInput) {
				firstInput.focus();
			}
		})();
	</script>
</body>
</html>
