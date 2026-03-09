<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
	header('Location: ../../pages/login.php');
	exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['role'] ?? 'student';

if ($user_role !== 'student') {
	header('Location: ../../pages/dashboard.php');
	exit;
}

$errors = [];
$successes = [];
$allowed_preferences = ['Videos', 'In person sessions', 'Quizzes'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';

	if ($action === 'save_profile') {
		$first_name = trim($_POST['first_name'] ?? '');
		$last_name = trim($_POST['last_name'] ?? '');
		$phone = trim($_POST['phone'] ?? '');
		$course = trim($_POST['course'] ?? '');
		$year_of_study = trim($_POST['year_of_study'] ?? '');
		$learning_preference = $_POST['learning_preference'] ?? '';
		$bio = trim($_POST['bio'] ?? '');

		if ($first_name === '') {
			$errors[] = 'First name is required.';
		}

		if ($last_name === '') {
			$errors[] = 'Last name is required.';
		}

		if ($course === '') {
			$errors[] = 'Please choose your course.';
		}

		if ($year_of_study === '' || !ctype_digit($year_of_study) || (int)$year_of_study < 1 || (int)$year_of_study > 10) {
			$errors[] = 'Year of study must be between 1 and 10.';
		}

		if (!in_array($learning_preference, $allowed_preferences, true)) {
			$errors[] = 'Please select a valid learning preference.';
		}

		if (strlen($bio) > 1000) {
			$errors[] = 'Bio must be 1000 characters or fewer.';
		}

		if (empty($errors)) {
			try {
				$pdo->beginTransaction();

				$userStmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?');
				$userStmt->execute([$first_name, $last_name, $phone !== '' ? $phone : null, $user_id]);

				$studentCheckStmt = $pdo->prepare('SELECT student_id FROM students WHERE user_id = ? LIMIT 1');
				$studentCheckStmt->execute([$user_id]);
				$student_id = $studentCheckStmt->fetchColumn();

				if ($student_id) {
					$studentStmt = $pdo->prepare('UPDATE students SET course = ?, year_of_study = ?, learning_preference = ?, bio = ? WHERE student_id = ?');
					$studentStmt->execute([$course, (int)$year_of_study, $learning_preference, $bio !== '' ? $bio : null, $student_id]);
				} else {
					$studentStmt = $pdo->prepare('INSERT INTO students (student_id, course, year_of_study, learning_preference, bio, created_at, user_id) VALUES (?, ?, ?, ?, ?, NOW(), ?)');
					$studentStmt->execute([$user_id, $course, (int)$year_of_study, $learning_preference, $bio !== '' ? $bio : null, $user_id]);
				}

				$pdo->commit();

				$_SESSION['first_name'] = $first_name;
				$_SESSION['last_name'] = $last_name;
				$_SESSION['phone'] = $phone;
				$successes[] = 'Profile updated successfully.';
			} catch (PDOException $e) {
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}
				$errors[] = 'Failed to save your profile. Please try again.';
			}
		}
	}

	if ($action === 'upload_photo') {
		if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
			$errors[] = 'Please choose an image to upload.';
		} else {
			$file = $_FILES['profile_picture'];
			$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
			$max_size = 5 * 1024 * 1024;

			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mime_type = finfo_file($finfo, $file['tmp_name']);
			finfo_close($finfo);

			if (!in_array($mime_type, $allowed_types, true)) {
				$errors[] = 'Please upload a valid image file (JPEG, PNG, GIF, or WebP).';
			} elseif ($file['size'] > $max_size) {
				$errors[] = 'Profile picture must be smaller than 5MB.';
			} else {
				$upload_dir = __DIR__ . '/../../uploads/profile_pictures/';
				if (!is_dir($upload_dir)) {
					mkdir($upload_dir, 0755, true);
				}

				$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
				if ($extension === '') {
					$extension = 'jpg';
				}

				$filename = uniqid('profile_', true) . '_' . time() . '.' . $extension;
				$target_path = $upload_dir . $filename;
				$new_relative_path = 'uploads/profile_pictures/' . $filename;

				if (move_uploaded_file($file['tmp_name'], $target_path)) {
					try {
						$oldPicStmt = $pdo->prepare('SELECT profile_picture FROM users WHERE id = ?');
						$oldPicStmt->execute([$user_id]);
						$old_profile_picture = $oldPicStmt->fetchColumn();

						$updatePicStmt = $pdo->prepare('UPDATE users SET profile_picture = ? WHERE id = ?');
						$updatePicStmt->execute([$new_relative_path, $user_id]);

						if (!empty($old_profile_picture) && strpos($old_profile_picture, 'uploads/profile_pictures/') === 0) {
							$old_path = __DIR__ . '/../../' . $old_profile_picture;
							if (is_file($old_path)) {
								unlink($old_path);
							}
						}

						$successes[] = 'Profile picture updated.';
					} catch (PDOException $e) {
						if (is_file($target_path)) {
							unlink($target_path);
						}
						$errors[] = 'Failed to save profile picture. Please try again.';
					}
				} else {
					$errors[] = 'Failed to upload image. Please try again.';
				}
			}
		}
	}

	if ($action === 'remove_pairing') {
		try {
			$studentStmt = $pdo->prepare('SELECT student_id, mentor_id FROM students WHERE user_id = ? LIMIT 1');
			$studentStmt->execute([$user_id]);
			$studentRow = $studentStmt->fetch(PDO::FETCH_ASSOC);

			if (!$studentRow) {
				$errors[] = 'Student profile not found.';
			} elseif (empty($studentRow['mentor_id'])) {
				$successes[] = 'No mentor pairing to remove.';
			} else {
				$mentor_id = (int)$studentRow['mentor_id'];
				$student_id = (int)$studentRow['student_id'];

				$pdo->beginTransaction();

				$clearPairStmt = $pdo->prepare('UPDATE students SET mentor_id = NULL WHERE student_id = ?');
				$clearPairStmt->execute([$student_id]);

				$matchStmt = $pdo->prepare('UPDATE mentor_student_matches SET active = 0 WHERE student_id = ? AND mentor_id = ? AND active = 1');
				$matchStmt->execute([$student_id, $mentor_id]);

				$pdo->commit();
				$successes[] = 'Mentor pairing removed successfully.';
			}
		} catch (PDOException $e) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			$errors[] = 'Failed to remove pairing. Please try again.';
		}
	}
}

$user = null;
$studentProfile = null;
$subjects = [];
$pairedMentor = null;

try {
	$userStmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, profile_picture FROM users WHERE id = ? LIMIT 1');
	$userStmt->execute([$user_id]);
	$user = $userStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
	$user = null;
}

try {
	$subjectsStmt = $pdo->query('SELECT name FROM subjects ORDER BY name');
	$subjects = $subjectsStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
	$subjects = [];
}

try {
	$studentStmt = $pdo->prepare('SELECT student_id, course, year_of_study, learning_preference, bio, mentor_id FROM students WHERE user_id = ? LIMIT 1');
	$studentStmt->execute([$user_id]);
	$studentProfile = $studentStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
	$studentProfile = null;
}

if ($studentProfile && !empty($studentProfile['mentor_id'])) {
	try {
		$mentorStmt = $pdo->prepare('
			SELECT 
				mp.mentor_id,
				mp.bio,
				mp.experience_years,
				u.first_name,
				u.last_name,
				u.email,
				u.profile_picture,
				GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ", ") AS subjects
			FROM mentor_profiles mp
			JOIN users u ON u.id = mp.user_id
			LEFT JOIN mentor_subjects ms ON ms.mentor_id = mp.mentor_id
			LEFT JOIN subjects s ON s.id = ms.subject_id
			WHERE mp.mentor_id = ?
			GROUP BY mp.mentor_id
		');
		$mentorStmt->execute([(int)$studentProfile['mentor_id']]);
		$pairedMentor = $mentorStmt->fetch(PDO::FETCH_ASSOC);
	} catch (PDOException $e) {
		$pairedMentor = null;
	}
}

$first_name = $user['first_name'] ?? '';
$last_name = $user['last_name'] ?? '';
$email = $user['email'] ?? '';
$phone = $user['phone'] ?? '';
$course = $studentProfile['course'] ?? '';
$year_of_study = $studentProfile['year_of_study'] ?? '';
$learning_preference = $studentProfile['learning_preference'] ?? '';
$bio = $studentProfile['bio'] ?? '';

if ($course !== '' && !in_array($course, $subjects, true)) {
	$subjects[] = $course;
	sort($subjects);
}

$fallback_name = trim($first_name . ' ' . $last_name);
if ($fallback_name === '') {
	$fallback_name = 'Student';
}
$fallback_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($fallback_name) . '&background=3b82f6&color=fff&size=192';
$avatar_url = !empty($user['profile_picture']) ? '../../' . $user['profile_picture'] : $fallback_avatar;

$mentor_fallback_name = $pairedMentor ? trim(($pairedMentor['first_name'] ?? '') . ' ' . ($pairedMentor['last_name'] ?? '')) : 'Mentor';
$mentor_fallback_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($mentor_fallback_name) . '&background=06b6d4&color=fff&size=192';
$mentor_avatar_url = ($pairedMentor && !empty($pairedMentor['profile_picture']))
	? '../../' . $pairedMentor['profile_picture']
	: $mentor_fallback_avatar;
?>

<!-- HTML -->
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Profile Settings — Mentor Match</title>
	<meta name="description" content="Manage your student profile and mentor pairing.">
	<link rel="stylesheet" href="../../assets/css/styles.css">
	<link rel="stylesheet" href="../../assets/css/student_profile.css">
</head>
<body>
	<main class="container">
		<div class="profile-page">
			<div class="page-header">
				<a href="../../pages/dashboard.php" class="back-link">← Back</a>
				<h1>Profile Settings</h1>
				<p class="lead">Manage your account information and mentor pairing.</p>
			</div>

			<?php if (!empty($errors)): ?>
				<div class="errors" role="alert">
					<?php foreach ($errors as $error): ?>
						<div><?php echo htmlspecialchars($error); ?></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if (!empty($successes)): ?>
				<div class="success" role="status">
					<?php foreach ($successes as $success): ?>
						<div><?php echo htmlspecialchars($success); ?></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<section class="card profile-card" aria-labelledby="profile-heading">
				<div class="profile-section-head">
					<h2 id="profile-heading" class="section-title">Your Profile</h2>
					<button type="button" class="btn view-mentor-btn" id="open-mentor-modal">View Mentor</button>
				</div>

				<form method="POST" enctype="multipart/form-data" class="avatar-row">
					<input type="hidden" name="action" value="upload_photo">

					<img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Your profile" class="avatar" data-fallback="<?php echo htmlspecialchars($fallback_avatar); ?>" onerror="this.onerror=null;this.src=this.dataset.fallback;">

					<div class="avatar-controls">
						<h3>Profile Picture</h3>
						<p>Upload JPG, PNG, GIF, or WebP (max 5MB)</p>
						<label class="upload-label" for="profile_picture">Choose Photo</label>
						<input id="profile_picture" name="profile_picture" type="file" accept="image/jpeg,image/png,image/gif,image/webp" required>
						<button class="btn avatar-btn" type="submit">Change Photo</button>
					</div>
				</form>

				<form method="POST" class="profile-form" novalidate>
					<input type="hidden" name="action" value="save_profile">

					<div class="input-grid">
						<div>
							<label for="first_name">First Name</label>
							<input class="input" id="first_name" name="first_name" type="text" required value="<?php echo htmlspecialchars($first_name); ?>">
						</div>

						<div>
							<label for="last_name">Last Name</label>
							<input class="input" id="last_name" name="last_name" type="text" required value="<?php echo htmlspecialchars($last_name); ?>">
						</div>
					</div>

					<div class="input-grid">
						<div>
							<label for="email">Email</label>
							<input class="input readonly" id="email" type="email" value="<?php echo htmlspecialchars($email); ?>" disabled>
						</div>

						<div>
							<label for="phone">Phone</label>
							<input class="input" id="phone" name="phone" type="tel" inputmode="tel" pattern="[0-9+\- ()]*" value="<?php echo htmlspecialchars($phone); ?>" placeholder="(+44) 7123 456 789">
						</div>
					</div>

					<div class="input-grid">
						<div>
							<label for="course">Course</label>
							<select class="input" id="course" name="course" required>
								<option value="">Choose your course</option>
								<?php foreach ($subjects as $subject): ?>
									<option value="<?php echo htmlspecialchars($subject); ?>" <?php echo ($course === $subject) ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($subject); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div>
							<label for="year_of_study">Year of Study</label>
							<input class="input" id="year_of_study" name="year_of_study" type="number" min="1" max="10" required value="<?php echo htmlspecialchars((string)$year_of_study); ?>">
						</div>
					</div>

					<div>
						<label for="learning_preference">Learning Preference</label>
						<select class="input" id="learning_preference" name="learning_preference" required>
							<option value="">Choose a preference</option>
							<option value="Videos" <?php echo ($learning_preference === 'Videos') ? 'selected' : ''; ?>>Videos</option>
							<option value="In person sessions" <?php echo ($learning_preference === 'In person sessions') ? 'selected' : ''; ?>>In person sessions</option>
							<option value="Quizzes" <?php echo ($learning_preference === 'Quizzes') ? 'selected' : ''; ?>>Quizzes</option>
						</select>
					</div>

					<div>
						<label for="bio">Bio</label>
						<textarea class="input textarea" id="bio" name="bio" maxlength="1000" rows="4" placeholder="Tell mentors about your goals and what you want to learn."><?php echo htmlspecialchars($bio); ?></textarea>
					</div>

					<button class="btn" type="submit">Save Changes</button>
				</form>
			</section>

			<div class="mentor-modal" id="mentor-modal" aria-hidden="true">
				<div class="mentor-modal__backdrop" data-close-mentor></div>
				<section class="card pairing-card mentor-modal__panel" aria-labelledby="pairing-heading" role="dialog" aria-modal="true">
					<div class="mentor-modal__top">
						<h2 id="pairing-heading" class="section-title">Current Mentor Pairing</h2>
						<button type="button" class="mentor-close-btn" id="close-mentor-modal" aria-label="Close mentor view">✕</button>
					</div>

					<?php if ($pairedMentor): ?>
						<div class="mentor-summary">
							<img src="<?php echo htmlspecialchars($mentor_avatar_url); ?>" alt="Mentor profile" class="mentor-avatar" data-fallback="<?php echo htmlspecialchars($mentor_fallback_avatar); ?>" onerror="this.onerror=null;this.src=this.dataset.fallback;">
							<div class="mentor-details">
								<h3><?php echo htmlspecialchars(($pairedMentor['first_name'] ?? '') . ' ' . ($pairedMentor['last_name'] ?? '')); ?></h3>
								<p><?php echo htmlspecialchars($pairedMentor['email'] ?? ''); ?></p>
								<?php if (!empty($pairedMentor['subjects'])): ?>
									<p class="mentor-meta"><strong>Subjects:</strong> <?php echo htmlspecialchars($pairedMentor['subjects']); ?></p>
								<?php endif; ?>
							</div>
						</div>

						<form method="POST" class="remove-form" onsubmit="return confirm('Remove your pairing with this mentor?');">
							<input type="hidden" name="action" value="remove_pairing">
							<button type="submit" class="btn danger-btn">Remove Pairing</button>
						</form>
					<?php else: ?>
						<p class="empty-pairing">You are not currently paired with a mentor.</p>
					<?php endif; ?>
				</section>
			</div>
		</div>
	</main>

	<?php include '../../includes/nav.php'; ?>

	<script>
		(function () {
			const modal = document.getElementById('mentor-modal');
			const openMentorBtn = document.getElementById('open-mentor-modal');
			const closeMentorBtn = document.getElementById('close-mentor-modal');
			const closeBackdrop = modal ? modal.querySelector('[data-close-mentor]') : null;

			function openMentorModal() {
				if (!modal) {
					return;
				}
				modal.classList.add('is-open');
				modal.setAttribute('aria-hidden', 'false');
				document.body.classList.add('mentor-modal-open');
			}

			function closeMentorModal() {
				if (!modal) {
					return;
				}
				modal.classList.remove('is-open');
				modal.setAttribute('aria-hidden', 'true');
				document.body.classList.remove('mentor-modal-open');
			}

			if (openMentorBtn) {
				openMentorBtn.addEventListener('click', openMentorModal);
			}

			if (closeMentorBtn) {
				closeMentorBtn.addEventListener('click', closeMentorModal);
			}

			if (closeBackdrop) {
				closeBackdrop.addEventListener('click', closeMentorModal);
			}

			document.addEventListener('keydown', function (event) {
				if (event.key === 'Escape') {
					closeMentorModal();
				}
			});
		})();
	</script>
</body>
</html>
