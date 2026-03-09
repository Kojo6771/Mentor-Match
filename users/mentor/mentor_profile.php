<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
	header('Location: ../../pages/login.php');
	exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['role'] ?? 'mentor';

if ($user_role !== 'mentor') {
	header('Location: ../../pages/dashboard.php');
	exit;
}

$errors = [];
$successes = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';

	if ($action === 'save_profile') {
		$first_name = trim($_POST['first_name'] ?? '');
		$last_name = trim($_POST['last_name'] ?? '');
		$phone = trim($_POST['phone'] ?? '');
		$experience_years = trim($_POST['experience_years'] ?? '');
		$bio = trim($_POST['bio'] ?? '');
		$linkedin = trim($_POST['linkedin'] ?? '');
		$github = trim($_POST['github'] ?? '');

		if ($first_name === '') {
			$errors[] = 'First name is required.';
		}

		if ($last_name === '') {
			$errors[] = 'Last name is required.';
		}

		if ($experience_years === '' || !ctype_digit($experience_years) || (int)$experience_years < 0 || (int)$experience_years > 70) {
			$errors[] = 'Years of experience must be between 0 and 70.';
		}

		if (strlen($bio) > 1000) {
			$errors[] = 'Bio must be 1000 characters or fewer.';
		}

		if ($linkedin !== '' && !filter_var($linkedin, FILTER_VALIDATE_URL)) {
			$errors[] = 'Please enter a valid LinkedIn URL.';
		}

		if ($github !== '' && !filter_var($github, FILTER_VALIDATE_URL)) {
			$errors[] = 'Please enter a valid GitHub URL.';
		}

		if (empty($errors)) {
			try {
				$pdo->beginTransaction();

				$userStmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?');
				$userStmt->execute([$first_name, $last_name, $phone !== '' ? $phone : null, $user_id]);

				$mentorCheckStmt = $pdo->prepare('SELECT mentor_id FROM mentor_profiles WHERE user_id = ? LIMIT 1');
				$mentorCheckStmt->execute([$user_id]);
				$mentor_id = $mentorCheckStmt->fetchColumn();

				if ($mentor_id) {
					$mentorStmt = $pdo->prepare('UPDATE mentor_profiles SET bio = ?, linkedin = ?, github = ?, experience_years = ? WHERE mentor_id = ?');
					$mentorStmt->execute([
						$bio !== '' ? $bio : null,
						$linkedin !== '' ? $linkedin : null,
						$github !== '' ? $github : null,
						(int)$experience_years,
						(int)$mentor_id
					]);
				} else {
					$mentorStmt = $pdo->prepare('INSERT INTO mentor_profiles (mentor_id, bio, linkedin, github, experience_years, verified, user_id) VALUES (?, ?, ?, ?, ?, 1, ?)');
					$mentorStmt->execute([
						$user_id,
						$bio !== '' ? $bio : null,
						$linkedin !== '' ? $linkedin : null,
						$github !== '' ? $github : null,
						(int)$experience_years,
						$user_id
					]);
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
		$student_id = (int)($_POST['student_id'] ?? 0);

		if ($student_id <= 0) {
			$errors[] = 'Invalid student selection.';
		} else {
			try {
				$mentorIdStmt = $pdo->prepare('SELECT mentor_id FROM mentor_profiles WHERE user_id = ? LIMIT 1');
				$mentorIdStmt->execute([$user_id]);
				$current_mentor_id = (int)($mentorIdStmt->fetchColumn() ?: $user_id);

				$ownershipStmt = $pdo->prepare('SELECT student_id FROM students WHERE student_id = ? AND mentor_id = ? LIMIT 1');
				$ownershipStmt->execute([$student_id, $current_mentor_id]);
				$belongs_to_mentor = $ownershipStmt->fetchColumn();

				if (!$belongs_to_mentor) {
					$errors[] = 'You can only remove your own student pairings.';
				} else {
					$pdo->beginTransaction();

					$clearStudentStmt = $pdo->prepare('UPDATE students SET mentor_id = NULL WHERE student_id = ? AND mentor_id = ?');
					$clearStudentStmt->execute([$student_id, $current_mentor_id]);

					$deactivateMatchStmt = $pdo->prepare('UPDATE mentor_student_matches SET active = 0 WHERE student_id = ? AND mentor_id = ? AND active = 1');
					$deactivateMatchStmt->execute([$student_id, $current_mentor_id]);

					$pdo->commit();
					$successes[] = 'Student pairing removed successfully.';
				}
			} catch (PDOException $e) {
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}
				$errors[] = 'Failed to remove pairing. Please try again.';
			}
		}
	}
}

$user = null;
$mentorProfile = null;
$students = [];

try {
	$userStmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, profile_picture FROM users WHERE id = ? LIMIT 1');
	$userStmt->execute([$user_id]);
	$user = $userStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
	$user = null;
}

try {
	$mentorStmt = $pdo->prepare('SELECT mentor_id, bio, linkedin, github, experience_years, verified FROM mentor_profiles WHERE user_id = ? LIMIT 1');
	$mentorStmt->execute([$user_id]);
	$mentorProfile = $mentorStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
	$mentorProfile = null;
}

$mentor_id = $mentorProfile['mentor_id'] ?? $user_id;

try {
	$studentsStmt = $pdo->prepare('
		SELECT
			s.student_id,
			s.course,
			s.year_of_study,
			s.learning_preference,
			s.bio,
			u.first_name,
			u.last_name,
			u.email,
			u.phone,
			u.profile_picture,
			msm.matched_at
		FROM students s
		INNER JOIN users u ON u.id = s.user_id
		LEFT JOIN mentor_student_matches msm ON msm.student_id = s.student_id AND msm.mentor_id = ? AND msm.active = 1
		WHERE s.mentor_id = ?
		ORDER BY u.first_name, u.last_name
	');
	$studentsStmt->execute([(int)$mentor_id, (int)$mentor_id]);
	$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
	$students = [];
}

$first_name = $user['first_name'] ?? '';
$last_name = $user['last_name'] ?? '';
$email = $user['email'] ?? '';
$phone = $user['phone'] ?? '';
$bio = $mentorProfile['bio'] ?? '';
$linkedin = $mentorProfile['linkedin'] ?? '';
$github = $mentorProfile['github'] ?? '';
$experience_years = $mentorProfile['experience_years'] ?? '';

$fallback_name = trim($first_name . ' ' . $last_name);
if ($fallback_name === '') {
	$fallback_name = 'Mentor';
}

$fallback_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($fallback_name) . '&background=06b6d4&color=fff&size=192';
$avatar_url = !empty($user['profile_picture']) ? '../../' . $user['profile_picture'] : $fallback_avatar;
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Mentor Profile — Mentor Match</title>
	<meta name="description" content="Manage your mentor profile and current student pairings.">
	<link rel="stylesheet" href="../../assets/css/styles.css">
	<link rel="stylesheet" href="../../assets/css/student_profile.css">
	<link rel="stylesheet" href="../../assets/css/mentor_profile.css">
</head>
<body>
	<main class="container">
		<div class="profile-page">
			<div class="page-header">
				<a href="../../pages/dashboard.php" class="back-link">← Back</a>
				<h1>Mentor Profile</h1>
				<p class="lead">Update your mentor details and review your current students.</p>
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

			<section class="card pairing-card" aria-labelledby="students-heading">
				<div class="students-header-row">
					<h2 id="students-heading" class="section-title">Current Students</h2>
					<?php if (!empty($students)): ?>
						<span class="students-counter"><span id="student-position">1</span> / <?php echo count($students); ?></span>
					<?php endif; ?>
				</div>

				<?php if (empty($students)): ?>
					<p class="empty-pairing">You do not have any assigned students yet.</p>
				<?php else: ?>
					<div class="student-carousel" data-total="<?php echo count($students); ?>">
						<?php foreach ($students as $index => $student): ?>
							<?php
								$student_name = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
								if ($student_name === '') {
									$student_name = 'Student';
								}
								$student_fallback_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($student_name) . '&background=3b82f6&color=fff&size=128';
								$student_avatar = !empty($student['profile_picture']) ? '../../' . $student['profile_picture'] : $student_fallback_avatar;
							?>
							<article class="student-card <?php echo $index === 0 ? 'is-active' : 'is-hidden'; ?>" data-index="<?php echo $index; ?>">
								<div class="student-summary">
									<img src="<?php echo htmlspecialchars($student_avatar); ?>" alt="<?php echo htmlspecialchars($student_name); ?> profile" class="mentor-avatar" data-fallback="<?php echo htmlspecialchars($student_fallback_avatar); ?>" onerror="this.onerror=null;this.src=this.dataset.fallback;">
									<div class="mentor-details">
										<h3><?php echo htmlspecialchars($student_name); ?></h3>
										<p><?php echo htmlspecialchars($student['email'] ?? ''); ?></p>
										<?php if (!empty($student['matched_at'])): ?>
											<p class="student-meta"><strong>Matched:</strong> <?php echo htmlspecialchars(date('M j, Y', strtotime($student['matched_at']))); ?></p>
										<?php endif; ?>
									</div>
								</div>

								<div class="student-details-grid">
									<div>
										<span class="detail-label">Course</span>
										<span class="detail-value"><?php echo htmlspecialchars($student['course'] ?? '—'); ?></span>
									</div>
									<div>
										<span class="detail-label">Year</span>
										<span class="detail-value"><?php echo htmlspecialchars((string)($student['year_of_study'] ?? '—')); ?></span>
									</div>
									<div>
										<span class="detail-label">Preference</span>
										<span class="detail-value"><?php echo htmlspecialchars($student['learning_preference'] ?? '—'); ?></span>
									</div>
									<div>
										<span class="detail-label">Phone</span>
										<span class="detail-value"><?php echo htmlspecialchars($student['phone'] ?? 'Not provided'); ?></span>
									</div>
								</div>

								<div class="student-bio-wrap">
									<span class="detail-label">Bio</span>
									<p class="student-bio-text"><?php echo htmlspecialchars($student['bio'] ?: 'No bio added yet.'); ?></p>
								</div>

								<form method="POST" class="pairing-remove-form" onsubmit="return confirm('Remove this student pairing?');">
									<input type="hidden" name="action" value="remove_pairing">
									<input type="hidden" name="student_id" value="<?php echo (int)$student['student_id']; ?>">
									<button type="submit" class="btn danger-btn">Remove Pairing</button>
								</form>
							</article>
						<?php endforeach; ?>
					</div>

					<div class="carousel-controls">
						<button type="button" class="btn secondary-nav" id="student-prev">Previous</button>
						<button type="button" class="btn secondary-nav" id="student-next">Next</button>
					</div>
				<?php endif; ?>
			</section>

			<section class="card profile-card" aria-labelledby="profile-heading">
				<h2 id="profile-heading" class="section-title">Your Details</h2>

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

					<div>
						<label for="experience_years">Years of Experience</label>
						<input class="input" id="experience_years" name="experience_years" type="number" min="0" max="70" required value="<?php echo htmlspecialchars((string)$experience_years); ?>">
					</div>

					<div>
						<label for="bio">Bio</label>
						<textarea class="input textarea" id="bio" name="bio" maxlength="1000" rows="4" placeholder="Tell students about your teaching style and experience."><?php echo htmlspecialchars($bio); ?></textarea>
					</div>

					<div class="input-grid">
						<div>
							<label for="linkedin">LinkedIn URL</label>
							<input class="input" id="linkedin" name="linkedin" type="url" value="<?php echo htmlspecialchars($linkedin); ?>" placeholder="https://www.linkedin.com/in/your-profile">
						</div>

						<div>
							<label for="github">GitHub URL</label>
							<input class="input" id="github" name="github" type="url" value="<?php echo htmlspecialchars($github); ?>" placeholder="https://github.com/your-username">
						</div>
					</div>

					<button class="btn" type="submit">Save Changes</button>
				</form>
			</section>
		</div>
	</main>

	<?php include '../../includes/nav.php'; ?>

	<script>
		(function () {
			const cards = Array.from(document.querySelectorAll('.student-card'));
			const total = cards.length;
			if (!total) {
				return;
			}

			const prevBtn = document.getElementById('student-prev');
			const nextBtn = document.getElementById('student-next');
			const positionEl = document.getElementById('student-position');
			let currentIndex = 0;

			function renderCard(index) {
				cards.forEach((card, cardIndex) => {
					card.classList.toggle('is-active', cardIndex === index);
					card.classList.toggle('is-hidden', cardIndex !== index);
				});

				if (positionEl) {
					positionEl.textContent = String(index + 1);
				}
			}

			prevBtn.addEventListener('click', function () {
				currentIndex = (currentIndex - 1 + total) % total;
				renderCard(currentIndex);
			});

			nextBtn.addEventListener('click', function () {
				currentIndex = (currentIndex + 1) % total;
				renderCard(currentIndex);
			});

			renderCard(currentIndex);
		})();
	</script>
</body>
</html>
