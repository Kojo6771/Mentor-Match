<?php
// Start session and load database connection
session_start();
require_once '../includes/db.php';

// Ensure user is logged in; redirect to login if not
if (!isset($_SESSION['user_id'])) {
	header('Location: login.php');
	exit;
}

// Get the logged-in user's ID and role (student or mentor)
$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['role'] ?? 'student';

// Only allow student and mentor roles on this page
if (!in_array($user_role, ['student', 'mentor'], true)) {
	header('Location: dashboard.php');
	exit;
}

// Arrays to store form validation errors and success messages
$errors = [];
$successes = [];

// CHANGE PASSWORD HANDLING (all roles) 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
	$current_password = $_POST['current_password'] ?? '';
	$new_password     = $_POST['new_password'] ?? '';
	$confirm_password = $_POST['confirm_password'] ?? '';

	if ($current_password === '') {
		$errors[] = 'Please enter your current password.';
	}

	if ($new_password === '') {
		$errors[] = 'Please enter a new password.';
	} elseif (strlen($new_password) < 8) {
		$errors[] = 'New password must be at least 8 characters.';
	} elseif (!preg_match('/[A-Z]/', $new_password)) {
		$errors[] = 'New password must contain at least one uppercase letter.';
	} elseif (!preg_match('/[0-9]/', $new_password)) {
		$errors[] = 'New password must contain at least one number.';
	}

	if ($new_password !== '' && $confirm_password !== $new_password) {
		$errors[] = 'Passwords do not match.';
	}

	if (empty($errors)) {
		try {
			$pwStmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
			$pwStmt->execute([$user_id]);
			$stored_hash = $pwStmt->fetchColumn();

			if (empty($stored_hash)) {
				$errors[] = 'No password is set on this account (you may be using OAuth sign-in).';
			} elseif (!password_verify($current_password, $stored_hash)) {
				$errors[] = 'Current password is incorrect.';
			} else {
				$new_hash = password_hash($new_password, PASSWORD_BCRYPT);
				$updatePwStmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
				$updatePwStmt->execute([$new_hash, $user_id]);
				$successes[] = 'Password changed successfully.';
			}
		} catch (PDOException $e) {
			$errors[] = 'Failed to change password. Please try again.';
		}
	}
}

// ==== STUDENT PROFILE HANDLING ====
if ($user_role === 'student') {
	// Define valid learning preferences for students
	$allowed_preferences = ['Videos', 'In person sessions', 'Quizzes'];

	// Handle POST requests for profile updates, photo uploads, and pairing removal
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		// Determine which action the student is performing
		$action = $_POST['action'] ?? '';

		// Save profile action: update student details (name, phone, course, etc.)
		if ($action === 'save_profile') {
			// Collect form data from the profile form
			$first_name = trim($_POST['first_name'] ?? '');
			$last_name = trim($_POST['last_name'] ?? '');
			$phone = trim($_POST['phone'] ?? '');
			$course = trim($_POST['course'] ?? '');
			$year_of_study = trim($_POST['year_of_study'] ?? '');
			$learning_preference = $_POST['learning_preference'] ?? '';
			$bio = trim($_POST['bio'] ?? '');

			// Validate all required fields
			if ($first_name === '') {
				$errors[] = 'First name is required.';
			}

			if ($last_name === '') {
				$errors[] = 'Last name is required.';
			}

			if ($course === '') {
				$errors[] = 'Please choose your course.';
			}

			// Year of study must be a valid number between 1 and 10
			if ($year_of_study === '' || !ctype_digit($year_of_study) || (int)$year_of_study < 1 || (int)$year_of_study > 10) {
				$errors[] = 'Year of study must be between 1 and 10.';
			}

			// Learning preference must be one of the predefined options
			if (!in_array($learning_preference, $allowed_preferences, true)) {
				$errors[] = 'Please select a valid learning preference.';
			}

			// Bio has a 1000 character limit for brevity
			if (strlen($bio) > 1000) {
				$errors[] = 'Bio must be 1000 characters or fewer.';
			}

			// If no validation errors, save the profile to database
			if (empty($errors)) {
				try {
					// Start transaction to ensure atomicity of updates
					$pdo->beginTransaction();

					// Update user table with basic info (name, phone)
					$userStmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?');
					$userStmt->execute([$first_name, $last_name, $phone !== '' ? $phone : null, $user_id]);

					// Check if student profile already exists
					$studentCheckStmt = $pdo->prepare('SELECT student_id FROM students WHERE user_id = ? LIMIT 1');
					$studentCheckStmt->execute([$user_id]);
					$student_id = $studentCheckStmt->fetchColumn();

					// Update existing student profile, or create new one if not exists
					if ($student_id) {
						$studentStmt = $pdo->prepare('UPDATE students SET course = ?, year_of_study = ?, learning_preference = ?, bio = ? WHERE student_id = ?');
						$studentStmt->execute([$course, (int)$year_of_study, $learning_preference, $bio !== '' ? $bio : null, $student_id]);
					} else {
						$studentStmt = $pdo->prepare('INSERT INTO students (student_id, course, year_of_study, learning_preference, bio, created_at, user_id) VALUES (?, ?, ?, ?, ?, NOW(), ?)');
						$studentStmt->execute([$user_id, $course, (int)$year_of_study, $learning_preference, $bio !== '' ? $bio : null, $user_id]);
					}

					// Commit all changes to database
					$pdo->commit();

					// Update session with new name and phone
					$_SESSION['first_name'] = $first_name;
					$_SESSION['last_name'] = $last_name;
					$_SESSION['phone'] = $phone;
					$successes[] = 'Profile updated successfully.';
				} catch (PDOException $e) {
					// Rollback transaction if any error occurs
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
					$upload_dir = __DIR__ . '/../uploads/profile_pictures/';
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
								$old_path = __DIR__ . '/../' . $old_profile_picture;
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

		// Remove pairing action: allow student to disconnect from their assigned mentor
		if ($action === 'remove_pairing') {
			try {
				// Get the student's current mentor assignment
				$studentStmt = $pdo->prepare('SELECT student_id, mentor_id FROM students WHERE user_id = ? LIMIT 1');
				$studentStmt->execute([$user_id]);
				$studentRow = $studentStmt->fetch(PDO::FETCH_ASSOC);

				if (!$studentRow) {
					$errors[] = 'Student profile not found.';
				} elseif (empty($studentRow['mentor_id'])) {
					$successes[] = 'No mentor pairing to remove.';
				} else {
					// Extract mentor and student IDs
					$mentor_id = (int)$studentRow['mentor_id'];
					$student_id = (int)$studentRow['student_id'];

					// Start transaction for atomicity
					$pdo->beginTransaction();

					// Clear the mentor assignment in the students table
					$clearPairStmt = $pdo->prepare('UPDATE students SET mentor_id = NULL WHERE student_id = ?');
					$clearPairStmt->execute([$student_id]);

					// Deactivate the match record to preserve history
					$matchStmt = $pdo->prepare('UPDATE mentor_student_matches SET active = 0 WHERE student_id = ? AND mentor_id = ? AND active = 1');
					$matchStmt->execute([$student_id, $mentor_id]);

					// Commit the transaction
					$pdo->commit();
					$successes[] = 'Mentor pairing removed successfully.';
				}
			} catch (PDOException $e) {
				// Rollback if anything fails
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}
				$errors[] = 'Failed to remove pairing. Please try again.';
			}
		}
		// Rate mentor action: allow student to submit or update their mentor rating
		if ($action === 'rate_mentor') {
			// Validate rating is between 1 and 5
			$rating_val = (int)($_POST['rating'] ?? 0);
			if ($rating_val < 1 || $rating_val > 5) {
				$errors[] = 'Please select a rating between 1 and 5.';
			} else {
				try {
					// Check if student is paired with a mentor
					$checkStmt = $pdo->prepare('SELECT student_id, mentor_id FROM students WHERE user_id = ? LIMIT 1');
					$checkStmt->execute([$user_id]);
					$checkRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
					if (!$checkRow || empty($checkRow['mentor_id'])) {
						$errors[] = 'You must be paired with a mentor to rate them.';
					} else {
						// Insert new rating or update existing one
						$rateStmt = $pdo->prepare('
							INSERT INTO mentor_ratings (student_id, mentor_id, rating)
							VALUES (?, ?, ?)
							ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = NOW()
						');
						$rateStmt->execute([(int)$checkRow['student_id'], (int)$checkRow['mentor_id'], $rating_val]);
						$successes[] = 'Rating submitted successfully!';
					}
				} catch (PDOException $e) {
					$errors[] = 'Failed to submit rating. Please try again.';
				}
			}
		}	}

	// Initialize variables for student data retrieval
	$user = null;
	$studentProfile = null;
	$subjects = [];
	$pairedMentor = null;

	// Fetch user record from database
	try {
		$userStmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, profile_picture FROM users WHERE id = ? LIMIT 1');
		$userStmt->execute([$user_id]);
		$user = $userStmt->fetch(PDO::FETCH_ASSOC);
	} catch (PDOException $e) {
		$user = null;
	}

	// Fetch list of all available subjects for course dropdown
	try {
		$subjectsStmt = $pdo->query('SELECT name FROM subjects ORDER BY name');
		$subjects = $subjectsStmt->fetchAll(PDO::FETCH_COLUMN);
	} catch (PDOException $e) {
		$subjects = [];
	}

	// Fetch student profile details
	try {
		$studentStmt = $pdo->prepare('SELECT student_id, course, year_of_study, learning_preference, bio, mentor_id FROM students WHERE user_id = ? LIMIT 1');
		$studentStmt->execute([$user_id]);
		$studentProfile = $studentStmt->fetch(PDO::FETCH_ASSOC);
	} catch (PDOException $e) {
		$studentProfile = null;
	}

	// If student is paired with a mentor, fetch the mentor's full profile
	if ($studentProfile && !empty($studentProfile['mentor_id'])) {
		try {
			// Fetch mentor details with their taught subjects
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

	// Fetch the student's own rating and the mentor's average rating
	$myRating = 0;
	$mentorAvgRating = null;
	if ($pairedMentor) {
		try {
			// Get the student's current rating for their mentor
			$sid = (int)($studentProfile['student_id'] ?? 0);
			$mid = (int)($pairedMentor['mentor_id'] ?? 0);
			$myRatingStmt = $pdo->prepare('SELECT rating FROM mentor_ratings WHERE student_id = ? AND mentor_id = ? LIMIT 1');
			$myRatingStmt->execute([$sid, $mid]);
			$myRating = (int)($myRatingStmt->fetchColumn() ?: 0);

			// Get the mentor's average rating from all students
			$avgStmt = $pdo->prepare('SELECT ROUND(AVG(rating), 1) FROM mentor_ratings WHERE mentor_id = ?');
			$avgStmt->execute([$mid]);
			$mentorAvgRating = $avgStmt->fetchColumn();
		} catch (PDOException $e) {
			$myRating = 0;
			$mentorAvgRating = null;
		}
	}

	// Extract student profile fields for use in the form
	$first_name = $user['first_name'] ?? '';
	$last_name = $user['last_name'] ?? '';
	$email = $user['email'] ?? '';
	$phone = $user['phone'] ?? '';
	$course = $studentProfile['course'] ?? '';
	$year_of_study = $studentProfile['year_of_study'] ?? '';
	$learning_preference = $studentProfile['learning_preference'] ?? '';
	$bio = $studentProfile['bio'] ?? '';

	// If student's course is not in the subjects list, add it (for custom courses)
	if ($course !== '' && !in_array($course, $subjects, true)) {
		$subjects[] = $course;
		sort($subjects);
	}

	// Generate default avatar using initials if no profile picture is set
	$fallback_name = trim($first_name . ' ' . $last_name);
	if ($fallback_name === '') {
		$fallback_name = 'Student';
	}
	$fallback_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($fallback_name) . '&background=3b82f6&color=fff&size=192';
	$avatar_url = !empty($user['profile_picture']) ? '../' . $user['profile_picture'] : $fallback_avatar;

	// Generate default avatar for paired mentor
	$mentor_fallback_name = $pairedMentor ? trim(($pairedMentor['first_name'] ?? '') . ' ' . ($pairedMentor['last_name'] ?? '')) : 'Mentor';
	$mentor_fallback_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($mentor_fallback_name) . '&background=06b6d4&color=fff&size=192';
	$mentor_avatar_url = ($pairedMentor && !empty($pairedMentor['profile_picture']))
		? '../' . $pairedMentor['profile_picture']
		: $mentor_fallback_avatar;
}
// End of student profile handling

// ==== MENTOR PROFILE HANDLING ====
if ($user_role === 'mentor') {
	// Handle POST requests for mentor profile updates, photo uploads, and student pairing removal
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		// Determine which action the mentor is performing
		$action = $_POST['action'] ?? '';

		// Save profile action: update mentor details (name, phone, bio, experience, social links)
		if ($action === 'save_profile') {
			// Collect all form data for mentor profile
			$first_name = trim($_POST['first_name'] ?? '');
			$last_name = trim($_POST['last_name'] ?? '');
			$phone = trim($_POST['phone'] ?? '');
			$experience_years = trim($_POST['experience_years'] ?? '');
			$bio = trim($_POST['bio'] ?? '');
			$linkedin = trim($_POST['linkedin'] ?? '');
			$github = trim($_POST['github'] ?? '');

			// Validate all required fields
			if ($first_name === '') {
				$errors[] = 'First name is required.';
			}

			if ($last_name === '') {
				$errors[] = 'Last name is required.';
			}

			// Years of experience must be a valid number between 0 and 70
			if ($experience_years === '' || !ctype_digit($experience_years) || (int)$experience_years < 0 || (int)$experience_years > 70) {
				$errors[] = 'Years of experience must be between 0 and 70.';
			}

			// Bio has a 1000 character limit for brevity
			if (strlen($bio) > 1000) {
				$errors[] = 'Bio must be 1000 characters or fewer.';
			}

			// If LinkedIn URL provided, it must be valid
			if ($linkedin !== '' && !filter_var($linkedin, FILTER_VALIDATE_URL)) {
				$errors[] = 'Please enter a valid LinkedIn URL.';
			}

			// If GitHub URL provided, it must be valid
			if ($github !== '' && !filter_var($github, FILTER_VALIDATE_URL)) {
				$errors[] = 'Please enter a valid GitHub URL.';
			}

			// If no validation errors, save the mentor profile to database
			if (empty($errors)) {
				try {
					// Start transaction to ensure atomicity of updates
					$pdo->beginTransaction();

					// Update user table with basic info (name, phone)
					$userStmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?');
					$userStmt->execute([$first_name, $last_name, $phone !== '' ? $phone : null, $user_id]);

					// Check if mentor profile already exists
					$mentorCheckStmt = $pdo->prepare('SELECT mentor_id FROM mentor_profiles WHERE user_id = ? LIMIT 1');
					$mentorCheckStmt->execute([$user_id]);
					$mentor_id = $mentorCheckStmt->fetchColumn();

					// Update existing mentor profile, or create new one if not exists
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

					// Commit all changes to database
					$pdo->commit();

					// Update session with new name and phone
					$_SESSION['first_name'] = $first_name;
					$_SESSION['last_name'] = $last_name;
					$_SESSION['phone'] = $phone;
					$successes[] = 'Profile updated successfully.';
				} catch (PDOException $e) {
					// Rollback transaction if any error occurs
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
					$upload_dir = __DIR__ . '/../uploads/profile_pictures/';
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
								$old_path = __DIR__ . '/../' . $old_profile_picture;
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

		// Remove pairing action: allow mentor to disconnect from a student
		if ($action === 'remove_pairing') {
			// Get the student ID from the form
			$student_id = (int)($_POST['student_id'] ?? 0);

			if ($student_id <= 0) {
				$errors[] = 'Invalid student selection.';
			} else {
				try {
					// Get the current mentor's ID
					$mentorIdStmt = $pdo->prepare('SELECT mentor_id FROM mentor_profiles WHERE user_id = ? LIMIT 1');
					$mentorIdStmt->execute([$user_id]);
					$current_mentor_id = (int)($mentorIdStmt->fetchColumn() ?: $user_id);

					// Verify that the student belongs to this mentor (security check)
					$ownershipStmt = $pdo->prepare('SELECT student_id FROM students WHERE student_id = ? AND mentor_id = ? LIMIT 1');
					$ownershipStmt->execute([$student_id, $current_mentor_id]);
					$belongs_to_mentor = $ownershipStmt->fetchColumn();

					if (!$belongs_to_mentor) {
						$errors[] = 'You can only remove your own student pairings.';
					} else {
						// Start transaction for atomicity
						$pdo->beginTransaction();

						// Clear the mentor assignment in the students table
						$clearStudentStmt = $pdo->prepare('UPDATE students SET mentor_id = NULL WHERE student_id = ? AND mentor_id = ?');
						$clearStudentStmt->execute([$student_id, $current_mentor_id]);

						// Deactivate the match record to preserve history
						$deactivateMatchStmt = $pdo->prepare('UPDATE mentor_student_matches SET active = 0 WHERE student_id = ? AND mentor_id = ? AND active = 1');
						$deactivateMatchStmt->execute([$student_id, $current_mentor_id]);

						// Commit the transaction
						$pdo->commit();
						$successes[] = 'Student pairing removed successfully.';
					}
				} catch (PDOException $e) {
					// Rollback if anything fails
					if ($pdo->inTransaction()) {
						$pdo->rollBack();
					}
					$errors[] = 'Failed to remove pairing. Please try again.';
				}
			}
		}
	}

	// Initialize variables for mentor data retrieval
	$user = null;
	$mentorProfile = null;
	$students = [];

	// Fetch user record from database
	try {
		$userStmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, profile_picture FROM users WHERE id = ? LIMIT 1');
		$userStmt->execute([$user_id]);
		$user = $userStmt->fetch(PDO::FETCH_ASSOC);
	} catch (PDOException $e) {
		$user = null;
	}

	// Fetch mentor profile details
	try {
		$mentorStmt = $pdo->prepare('SELECT mentor_id, bio, linkedin, github, experience_years, verified FROM mentor_profiles WHERE user_id = ? LIMIT 1');
		$mentorStmt->execute([$user_id]);
		$mentorProfile = $mentorStmt->fetch(PDO::FETCH_ASSOC);
	} catch (PDOException $e) {
		$mentorProfile = null;
	}

	// Get the mentor's ID from profile, or use user_id if profile doesn't exist yet
	$mentor_id = $mentorProfile['mentor_id'] ?? $user_id;

	// Fetch all students assigned to this mentor
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

	// Extract mentor profile fields for use in the form
	$first_name = $user['first_name'] ?? '';
	$last_name = $user['last_name'] ?? '';
	$email = $user['email'] ?? '';
	$phone = $user['phone'] ?? '';
	$bio = $mentorProfile['bio'] ?? '';
	$linkedin = $mentorProfile['linkedin'] ?? '';
	$github = $mentorProfile['github'] ?? '';
	$experience_years = $mentorProfile['experience_years'] ?? '';

	// Generate default avatar using initials if no profile picture is set
	$fallback_name = trim($first_name . ' ' . $last_name);
	if ($fallback_name === '') {
		$fallback_name = 'Mentor';
	}

	$fallback_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($fallback_name) . '&background=06b6d4&color=fff&size=192';
	$avatar_url = !empty($user['profile_picture']) ? '../' . $user['profile_picture'] : $fallback_avatar;
}
// End of mentor profile handling
 ?>

<!-- HTML -->
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Profile | Mentor Match</title>
	<meta name="description" content="Manage your profile on Mentor Match.">
	<link rel="stylesheet" href="../assets/css/student_profile.css">
	<?php if ($user_role === 'mentor'): ?>
		<link rel="stylesheet" href="../assets/css/mentor_profile.css">
	<?php endif; ?>
</head>
<body>

<!-- Sticky Header -->
<header class="profile-header">
	<div class="profile-header-inner">
		<h1>Your Profile</h1>
	</div>
</header>

<main>
	<div class="profile-page">
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

			<?php if ($user_role === 'student'): ?>
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

						<button type="button" class="btn change-password-btn" id="open-password-modal">Change Password</button>
						<button class="btn" type="submit">Save Changes</button>
						<a href="logout.php" class="btn danger-btn logout-btn">Log Out</a>
					</form>
				</section>

				<div class="mentor-modal" id="mentor-modal" aria-hidden="true">
					<div class="mentor-modal__backdrop" data-close-mentor></div>
					<section class="card pairing-card mentor-modal__panel" aria-labelledby="pairing-heading" role="dialog" aria-modal="true">
						<div class="mentor-modal__top">
							<h2 id="pairing-heading" class="section-title">Current Mentor Pairing</h2>
							<button type="button" class="mentor-close-btn" id="close-mentor-modal" aria-label="Close mentor view">&times;</button>
						</div>

						<?php if ($pairedMentor): ?>
							<div class="mentor-summary">
								<img src="<?php echo htmlspecialchars($mentor_avatar_url); ?>" alt="Mentor profile" class="mentor-avatar" data-fallback="<?php echo htmlspecialchars($mentor_fallback_avatar); ?>" onerror="this.onerror=null;this.src=this.dataset.fallback;">
								<div class="mentor-details">
									<h3>
										<?php echo htmlspecialchars(($pairedMentor['first_name'] ?? '') . ' ' . ($pairedMentor['last_name'] ?? '')); ?>
										<?php if ($mentorAvgRating !== null && (float)$mentorAvgRating > 0): ?>
											<span class="mentor-avg-rating" style="font-size:0.82rem;font-weight:600;vertical-align:middle;margin-left:6px">&#9733; <?php echo htmlspecialchars(number_format((float)$mentorAvgRating, 1)); ?></span>
										<?php endif; ?>
									</h3>
									<p><?php echo htmlspecialchars($pairedMentor['email'] ?? ''); ?></p>
									<?php if (!empty($pairedMentor['subjects'])): ?>
										<p class="mentor-meta"><strong>Subjects:</strong> <?php echo htmlspecialchars($pairedMentor['subjects']); ?></p>
									<?php endif; ?>
								</div>
							</div>

							<div class="mentor-rating-section">
								<div class="mentor-rating-header">
									<span class="mentor-rating-label">Rate your mentor</span>
								</div>
								<form method="POST" class="rating-form" id="rating-form">
									<input type="hidden" name="action" value="rate_mentor">
									<div class="star-rating" role="group" aria-label="Rate your mentor">
										<?php for ($i = 5; $i >= 1; $i--): ?>
											<input type="radio" class="star-input" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" <?php echo ($myRating === $i) ? 'checked' : ''; ?>>
											<label class="star-label" for="star<?php echo $i; ?>" title="<?php echo $i; ?> star<?php echo $i > 1 ? 's' : ''; ?>">&#9733;</label>
										<?php endfor; ?>
									</div>
									<button type="submit" class="btn rating-submit-btn">Submit Rating</button>
								</form>
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
			<?php endif; ?>

			// Mentor profile view with student list and profile editing
			<?php if ($user_role === 'mentor'): ?>
				<section class="card profile-card" aria-labelledby="profile-heading">
					<div class="profile-section-head">
						<h2 id="profile-heading" class="section-title">Your Details</h2>
						<button type="button" class="btn view-students-btn" id="open-students-modal">
							<?php echo count($students) === 1 ? 'View Student' : 'View Students'; ?><?php echo count($students) > 1 ? ' (' . count($students) . ')' : ''; ?>
						</button>
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

						<button type="button" class="btn change-password-btn" id="open-password-modal">Change Password</button>
						<button class="btn" type="submit">Save Changes</button>
						<a href="logout.php" class="btn danger-btn logout-btn">Log Out</a>
					</form>
				</section>

				<div class="students-modal" id="students-modal" aria-hidden="true">
					<div class="students-modal__backdrop" data-close-students></div>
					<section class="card pairing-card students-modal__panel" aria-labelledby="students-heading" role="dialog" aria-modal="true">
						<div class="students-modal__top">
							<div class="students-header-row">
								<h2 id="students-heading" class="section-title">Current Students</h2>
								<?php if (!empty($students)): ?>
									<span class="students-counter"><span id="student-position">1</span> / <?php echo count($students); ?></span>
								<?php endif; ?>
							</div>
							<button type="button" class="students-close-btn" id="close-students-modal" aria-label="Close student view">&times;</button>
						</div>

						// If no students are assigned, show a message. Otherwise, display student cards in a carousel format.
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
										$student_avatar = !empty($student['profile_picture']) ? '../' . $student['profile_picture'] : $student_fallback_avatar;
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
												<span class="detail-value"><?php echo htmlspecialchars($student['course'] ?? '-'); ?></span>
											</div>
											<div>
												<span class="detail-label">Year</span>
												<span class="detail-value"><?php echo htmlspecialchars((string)($student['year_of_study'] ?? '-')); ?></span>
											</div>
											<div>
												<span class="detail-label">Preference</span>
												<span class="detail-value"><?php echo htmlspecialchars($student['learning_preference'] ?? '-'); ?></span>
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

							<?php if (count($students) > 1): ?>
								<div class="carousel-controls">
									<button type="button" class="btn secondary-nav" id="student-prev">Previous</button>
									<button type="button" class="btn secondary-nav" id="student-next">Next</button>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</section>
				</div>
			<?php endif; ?>

		<!-- Change Password Modal -->
		<div class="password-modal" id="password-modal" aria-hidden="true">
			<div class="password-modal__backdrop" data-close-password></div>
			<section class="card password-modal__panel" aria-labelledby="password-heading" role="dialog" aria-modal="true">
				<div class="password-modal__top">
					<h2 id="password-heading" class="section-title">Change Password</h2>
					<button type="button" class="password-close-btn" id="close-password-modal" aria-label="Close">&times;</button>
				</div>
				<form method="POST" class="profile-form" novalidate>
					<input type="hidden" name="action" value="change_password">
					<div>
						<label for="current_password">Current Password</label>
						<input class="input" id="current_password" name="current_password" type="password" required autocomplete="current-password">
					</div>
					<div>
						<label for="new_password">New Password</label>
						<input class="input" id="new_password" name="new_password" type="password" required autocomplete="new-password">
						<p class="input-hint">At least 8 characters, 1 uppercase letter, and 1 number.</p>
					</div>
					<div>
						<label for="confirm_password">Confirm New Password</label>
						<input class="input" id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password">
					</div>
					<button class="btn" type="submit">Update Password</button>
				</form>
			</section>
		</div>
		</div>
</main>

	<?php include '../includes/nav.php'; ?>

	<script>
		// Initialize page interactivity for mentor/student modals and carousels
		(function () {
			// STUDENT MODAL FUNCTIONALITY: Handle mentor profile viewing modal
			<?php if ($user_role === 'student'): ?>
			// Get references to modal element and trigger buttons
			const mentorModal = document.getElementById('mentor-modal');
			const openMentorBtn = document.getElementById('open-mentor-modal');
			const closeMentorBtn = document.getElementById('close-mentor-modal');
			const closeMentorBackdrop = mentorModal ? mentorModal.querySelector('[data-close-mentor]') : null;

			// Open the mentor modal and update accessibility attributes
			function openMentorModal() {
				if (!mentorModal) {
					return;
				}
				mentorModal.classList.add('is-open');
				mentorModal.setAttribute('aria-hidden', 'false');
				document.body.classList.add('mentor-modal-open');
			}

			// Close the mentor modal and restore accessibility attributes
			function closeMentorModal() {
				if (!mentorModal) {
					return;
				}
				mentorModal.classList.remove('is-open');
				mentorModal.setAttribute('aria-hidden', 'true');
				document.body.classList.remove('mentor-modal-open');
			}

			// Bind open button to open the modal
			if (openMentorBtn) {
				openMentorBtn.addEventListener('click', openMentorModal);
			}

			// Bind close button to close the modal
			if (closeMentorBtn) {
				closeMentorBtn.addEventListener('click', closeMentorModal);
			}

			// Allow closing by clicking on the backdrop
			if (closeMentorBackdrop) {
				closeMentorBackdrop.addEventListener('click', closeMentorModal);
			}
			<?php endif; ?>

			<?php if ($user_role === 'mentor'): ?>
			// Get references to students modal and buttons
			const studentsModal = document.getElementById('students-modal');
			const openStudentsBtn = document.getElementById('open-students-modal');
			const closeStudentsBtn = document.getElementById('close-students-modal');
			const closeStudentsBackdrop = studentsModal ? studentsModal.querySelector('[data-close-students]') : null;

			// Open the students modal and update accessibility attributes
			function openStudentsModal() {
				if (!studentsModal) {
					return;
				}
				studentsModal.classList.add('is-open');
				studentsModal.setAttribute('aria-hidden', 'false');
				document.body.classList.add('students-modal-open');
			}

			// Close the students modal and restore accessibility attributes
			function closeStudentsModal() {
				if (!studentsModal) {
					return;
				}
				studentsModal.classList.remove('is-open');
				studentsModal.setAttribute('aria-hidden', 'true');
				document.body.classList.remove('students-modal-open');
			}

			// Bind open button to open the modal
			if (openStudentsBtn) {
				openStudentsBtn.addEventListener('click', openStudentsModal);
			}

			// Bind close button to close the modal
			if (closeStudentsBtn) {
				closeStudentsBtn.addEventListener('click', closeStudentsModal);
			}

			// Allow closing by clicking on the backdrop
			if (closeStudentsBackdrop) {
				closeStudentsBackdrop.addEventListener('click', closeStudentsModal);
			}

			// Get all student cards and navigation elements
			const cards = Array.from(document.querySelectorAll('.student-card'));
			const total = cards.length;
			const prevBtn = document.getElementById('student-prev');
			const nextBtn = document.getElementById('student-next');
			const positionEl = document.getElementById('student-position');
			let currentIndex = 0;

			// Display the student card at the given index and update position counter
			function renderCard(index) {
				cards.forEach((card, cardIndex) => {
					card.classList.toggle('is-active', cardIndex === index);
					card.classList.toggle('is-hidden', cardIndex !== index);
				});

				// Update the position indicator (e.g., "1 / 5")
				if (positionEl) {
					positionEl.textContent = String(index + 1);
				}
			}

			// Set up carousel navigation if there are multiple students
			if (total > 0 && prevBtn && nextBtn) {
				// Previous button cycles backward through students
				prevBtn.addEventListener('click', function () {
					currentIndex = (currentIndex - 1 + total) % total;
					renderCard(currentIndex);
				});

				// Next button cycles forward through students
				nextBtn.addEventListener('click', function () {
					currentIndex = (currentIndex + 1) % total;
					renderCard(currentIndex);
				});

				// Render the first student on load
				renderCard(currentIndex);
			}
			<?php endif; ?>

			// PASSWORD MODAL FUNCTIONALITY
			const passwordModal = document.getElementById('password-modal');
			const openPasswordBtn = document.getElementById('open-password-modal');
			const closePasswordBtn = document.getElementById('close-password-modal');
			const closePasswordBackdrop = passwordModal ? passwordModal.querySelector('[data-close-password]') : null;

			function openPasswordModal() {
				if (!passwordModal) { return; }
				passwordModal.classList.add('is-open');
				passwordModal.setAttribute('aria-hidden', 'false');
				document.body.classList.add('password-modal-open');
			}

			function closePasswordModal() {
				if (!passwordModal) { return; }
				passwordModal.classList.remove('is-open');
				passwordModal.setAttribute('aria-hidden', 'true');
				document.body.classList.remove('password-modal-open');
			}

			if (openPasswordBtn) { openPasswordBtn.addEventListener('click', openPasswordModal); }
			if (closePasswordBtn) { closePasswordBtn.addEventListener('click', closePasswordModal); }
			if (closePasswordBackdrop) { closePasswordBackdrop.addEventListener('click', closePasswordModal); }

			// Auto-open the modal if a password change error occurred
<?php if (!empty($errors) && ($_POST['action'] ?? '') === 'change_password'): ?>
			if (passwordModal) { openPasswordModal(); }
<?php endif; ?>

			// Global keyboard handler: allow Escape key to close any open modal
			document.addEventListener('keydown', function (event) {
				if (event.key === 'Escape') {
					// Close the student's mentor modal if open
					const mentorModal = document.getElementById('mentor-modal');
					if (mentorModal && mentorModal.classList.contains('is-open')) {
						mentorModal.classList.remove('is-open');
						mentorModal.setAttribute('aria-hidden', 'true');
						document.body.classList.remove('mentor-modal-open');
					}

					// Close the mentor's students modal if open
					const studentsModal = document.getElementById('students-modal');
					if (studentsModal && studentsModal.classList.contains('is-open')) {
						studentsModal.classList.remove('is-open');
						studentsModal.setAttribute('aria-hidden', 'true');
						document.body.classList.remove('students-modal-open');
					}

				// Close the password modal if open
				const pwModal = document.getElementById('password-modal');
				if (pwModal && pwModal.classList.contains('is-open')) {
					pwModal.classList.remove('is-open');
					pwModal.setAttribute('aria-hidden', 'true');
					document.body.classList.remove('password-modal-open');
				}
				}
			});
		})();
	</script>
</body>
</html>
