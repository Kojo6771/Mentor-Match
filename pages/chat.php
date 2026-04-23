<?php
session_start();
require_once '../includes/db.php';

// Only students and mentors can access the chat page.
if (!isset($_SESSION['user_id'])) {
	header('Location: login.php');
	exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['role'] ?? 'student';

if (!in_array($user_role, ['student', 'mentor'], true)) {
	header('Location: dashboard.php');
	exit;
}

if ($user_role === 'student') {
	// Students should not keep unread counters for messages already opened here.
	try {
		$markRead = $pdo->prepare('UPDATE messages SET read_at = NOW() WHERE receiver_id = ? AND read_at IS NULL');
		$markRead->execute([$user_id]);
	} catch (PDOException $e) {
		// Ignore read-status failures and keep the page usable.
	}
}

$errors = [];
$successes = [];
$conversation_messages = [];
$chat_partner = null;
$chat_partner_user_id = null;
$student_options = [];
$selected_student_user_id = null;

if (($_GET['sent'] ?? '') === '1') {
	$successes[] = 'Message sent.';
}

if ($user_role === 'student') {
	$studentProfile = null;

	try {
		$studentStmt = $pdo->prepare('SELECT student_id, mentor_id FROM students WHERE user_id = ? LIMIT 1');
		$studentStmt->execute([$user_id]);
		$studentProfile = $studentStmt->fetch(PDO::FETCH_ASSOC);
	} catch (PDOException $e) {
		$studentProfile = null;
	}

	if ($studentProfile && !empty($studentProfile['mentor_id'])) {
		try {
			$mentorStmt = $pdo->prepare('
				SELECT
					u.id AS user_id,
					u.first_name,
					u.last_name,
					u.email,
					u.profile_picture,
					mp.experience_years,
					GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ", ") AS subjects
				FROM mentor_profiles mp
				JOIN users u ON u.id = mp.user_id
				LEFT JOIN mentor_subjects ms ON ms.mentor_id = mp.mentor_id
				LEFT JOIN subjects s ON s.id = ms.subject_id
				WHERE mp.mentor_id = ?
				GROUP BY u.id
			');
			$mentorStmt->execute([(int)$studentProfile['mentor_id']]);
			$chat_partner = $mentorStmt->fetch(PDO::FETCH_ASSOC);
			$chat_partner_user_id = $chat_partner ? (int)$chat_partner['user_id'] : null;
		} catch (PDOException $e) {
			$chat_partner = null;
			$chat_partner_user_id = null;
		}
	}
}

// Mentors can have multiple student conversations.
if ($user_role === 'mentor') {
	$mentor_id = null;

	try {
		$mentorStmt = $pdo->prepare('SELECT mentor_id FROM mentor_profiles WHERE user_id = ? LIMIT 1');
		$mentorStmt->execute([$user_id]);
		$mentor_id = (int)($mentorStmt->fetchColumn() ?: 0);
	} catch (PDOException $e) {
		$mentor_id = 0;
	}

	if ($mentor_id > 0) {
		try {
			$studentsStmt = $pdo->prepare('
				SELECT
					u.id AS user_id,
					u.first_name,
					u.last_name,
					u.email,
					u.profile_picture,
					st.course,
					st.year_of_study
				FROM students st
				JOIN users u ON u.id = st.user_id
				WHERE st.mentor_id = ?
				ORDER BY u.first_name, u.last_name
			');
			$studentsStmt->execute([$mentor_id]);
			$student_options = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

			// Add the latest message preview for each student conversation.
			foreach ($student_options as &$student) {
				try {
					$lastMsgStmt = $pdo->prepare('
						SELECT message, sent_at, sender_id
						FROM messages
						WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
						ORDER BY sent_at DESC, id DESC
						LIMIT 1
					');
					$lastMsgStmt->execute([$user_id, $student['user_id'], $student['user_id'], $user_id]);
					$lastMsg = $lastMsgStmt->fetch(PDO::FETCH_ASSOC);
					
					if ($lastMsg) {
						$student['last_message'] = $lastMsg['message'];
						$student['last_message_time'] = $lastMsg['sent_at'];
						$student['last_sender_id'] = $lastMsg['sender_id'];
					} else {
						$student['last_message'] = null;
						$student['last_message_time'] = null;
						$student['last_sender_id'] = null;
					}
				} catch (PDOException $e) {
					$student['last_message'] = null;
					$student['last_message_time'] = null;
					$student['last_sender_id'] = null;
				}
			}
			unset($student);

			usort($student_options, static function (array $a, array $b): int {
				$aTime = $a['last_message_time'] ?? null;
				$bTime = $b['last_message_time'] ?? null;

				if (!empty($aTime) && !empty($bTime)) {
					$aTimestamp = strtotime($aTime) ?: 0;
					$bTimestamp = strtotime($bTime) ?: 0;

					if ($aTimestamp !== $bTimestamp) {
						return $bTimestamp <=> $aTimestamp;
					}
				} elseif (!empty($aTime)) {
					return -1;
				} elseif (!empty($bTime)) {
					return 1;
				}

				$aName = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
				$bName = trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''));

				return strcasecmp($aName, $bName);
			});
		} catch (PDOException $e) {
			$student_options = [];
		}
	}

	// Open the requested student conversation when the student id is in the query string.
	$requested_student = (int)($_GET['student'] ?? 0);
	if (!empty($student_options) && $requested_student > 0) {
		$allowed_user_ids = array_map(static function ($student) {
			return (int)$student['user_id'];
		}, $student_options);

		if (in_array($requested_student, $allowed_user_ids, true)) {
			$selected_student_user_id = $requested_student;
			
			foreach ($student_options as $student) {
				if ((int)$student['user_id'] === $selected_student_user_id) {
					$chat_partner = $student;
					$chat_partner_user_id = $selected_student_user_id;
					break;
				}
			}
		}
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';

	if ($action === 'send_message') {
		$receiver_id = (int)($_POST['receiver_id'] ?? 0);
		$message = trim($_POST['message'] ?? '');

		$allowed_receiver = false;

		if ($user_role === 'student' && $chat_partner_user_id && $receiver_id === $chat_partner_user_id) {
			$allowed_receiver = true;
		}

		if ($user_role === 'mentor') {
			foreach ($student_options as $student) {
				if ((int)$student['user_id'] === $receiver_id) {
					$allowed_receiver = true;
					break;
				}
			}
		}

		if (!$allowed_receiver) {
			$errors[] = 'Invalid chat recipient.';
		} elseif ($message === '') {
			$errors[] = 'Please enter a message.';
		} elseif (strlen($message) > 2000) {
			$errors[] = 'Message must be 2000 characters or fewer.';
		} else {
			try {
				$insertStmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message, sent_at) VALUES (?, ?, ?, NOW())');
				$insertStmt->execute([$user_id, $receiver_id, $message]);

				header('Location: chat.php?sent=1');
				exit;
			} catch (PDOException $e) {
				$errors[] = 'Failed to send message. Please try again.';
			}
		}
	}
}

if ($chat_partner_user_id) {
	try {
		$messagesStmt = $pdo->prepare('
			SELECT sender_id, receiver_id, message, sent_at
			FROM messages
			WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
			ORDER BY sent_at ASC, id ASC
		');
		$messagesStmt->execute([$user_id, $chat_partner_user_id, $chat_partner_user_id, $user_id]);
		$conversation_messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);
	} catch (PDOException $e) {
		$conversation_messages = [];
	}
}

// Mentors also receive admin warning messages, shown as a pinned conversation.
$admin_notices = [];
$admin_user_ids = [];
if ($user_role === 'mentor') {
	try {
		$adminStmt = $pdo->prepare('
			SELECT m.id, m.sender_id, m.message, m.sent_at, m.read_at,
			       CONCAT(u.first_name, \' \', u.last_name) AS admin_name
			FROM messages m
			JOIN users u ON u.id = m.sender_id AND u.role = \'admin\'
			WHERE m.receiver_id = ?
			ORDER BY m.sent_at DESC
			LIMIT 10
		');
		$adminStmt->execute([$user_id]);
		$admin_notices = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

		// Keep track of which senders are admins.
		foreach ($admin_notices as $an) {
			$admin_user_ids[(int)$an['sender_id']] = true;
		}

		// Mark admin notices as read once they have been loaded here.
		if (!empty($admin_notices)) {
			$pdo->prepare('UPDATE messages SET read_at = NOW() WHERE receiver_id = ? AND read_at IS NULL AND sender_id IN (SELECT id FROM users WHERE role = \'admin\')')
				->execute([$user_id]);
		}
	} catch (PDOException $e) {
		$admin_notices = [];
	}
}

$partner_name = '';
$partner_email = '';
$partner_meta = '';
$partner_avatar = '';
$partner_avatar_fallback = '';

// Prepare chat partner details for display in the header of the conversation.
if ($chat_partner) {
	$partner_name = trim(($chat_partner['first_name'] ?? '') . ' ' . ($chat_partner['last_name'] ?? ''));
	$partner_email = $chat_partner['email'] ?? '';

	if ($user_role === 'student') {
		$meta_parts = [];
		if (!empty($chat_partner['subjects'])) {
			$meta_parts[] = 'Subjects: ' . $chat_partner['subjects'];
		}
		if (!empty($chat_partner['experience_years'])) {
			$meta_parts[] = 'Experience: ' . ((int)$chat_partner['experience_years']) . ' yrs';
		}
		$partner_meta = implode(' • ', $meta_parts);
	}

	if ($user_role === 'mentor') {
		$meta_parts = [];
		if (!empty($chat_partner['course'])) {
			$meta_parts[] = $chat_partner['course'];
		}
		if (!empty($chat_partner['year_of_study'])) {
			$meta_parts[] = 'Year ' . (int)$chat_partner['year_of_study'];
		}
		$partner_meta = implode(' • ', $meta_parts);
	}

	if ($partner_name === '') {
		$partner_name = $user_role === 'student' ? 'Your Mentor' : 'Student';
	}

	$partner_avatar_fallback = 'https://ui-avatars.com/api/?name=' . urlencode($partner_name) . '&background=3b82f6&color=fff&size=128';
	$partner_avatar = !empty($chat_partner['profile_picture']) ? '../' . ltrim($chat_partner['profile_picture'], '/') : $partner_avatar_fallback;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Chatroom | Mentor Match</title>
	<meta name="description" content="Chat with your mentor or students on Mentor Match.">
	<link rel="stylesheet" href="../assets/css/styles.css">
	<link rel="stylesheet" href="../assets/css/chat.css">
</head>
<body>
	<main class="container">
		<?php if ($user_role === 'student'): ?>
			<!-- Student view: a direct chat with the assigned mentor -->
			<section class="chat-page chat-page--student card" aria-labelledby="chat-heading">
				<h1 id="chat-heading">Chatroom</h1>
				<p class="lead">Chat directly with your mentor.</p>

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

				<?php if (!$chat_partner_user_id): ?>
					<p class="empty-state">You are not currently paired with a mentor.</p>
				<?php else: ?>
					<div class="chat-partner">
						<img src="<?php echo htmlspecialchars($partner_avatar); ?>" alt="Chat partner" class="partner-avatar" data-fallback="<?php echo htmlspecialchars($partner_avatar_fallback); ?>" onerror="this.onerror=null;this.src=this.dataset.fallback;">
						<div class="chat-partner__details">
							<h2><?php echo htmlspecialchars($partner_name); ?></h2>
							<?php if ($partner_email !== ''): ?>
								<p><?php echo htmlspecialchars($partner_email); ?></p>
							<?php endif; ?>
							<?php if ($partner_meta !== ''): ?>
								<p class="partner-meta"><?php echo htmlspecialchars($partner_meta); ?></p>
							<?php endif; ?>
						</div>
					</div>

					<div class="messages" id="messages-container">
						<?php if (empty($conversation_messages)): ?>
							<p class="empty-conversation">No messages yet. Start the conversation below.</p>
						<?php else: ?>
							<?php foreach ($conversation_messages as $message): ?>
								<?php $is_own = ((int)$message['sender_id'] === $user_id); ?>
								<article class="message-bubble <?php echo $is_own ? 'is-own' : 'is-other'; ?>">
									<p><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
									<time datetime="<?php echo htmlspecialchars($message['sent_at']); ?>">
										<?php echo htmlspecialchars(date('M j, g:i a', strtotime($message['sent_at']))); ?>
									</time>
								</article>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<form method="POST" class="composer" novalidate>
						<input type="hidden" name="action" value="send_message">
						<input type="hidden" name="receiver_id" value="<?php echo (int)$chat_partner_user_id; ?>">
						<label for="message" class="sr-only">Message</label>
						<textarea class="input message-input" id="message" name="message" maxlength="2000" rows="3" placeholder="Type your message..." required></textarea>
						<button type="submit" class="btn">Send</button>
					</form>
				<?php endif; ?>
			</section>

		<?php else: ?>
			<!-- Mentor view: conversation list with modal chat windows -->
			<section class="chat-page card" aria-labelledby="chat-heading">
				<h1 id="chat-heading">Messages</h1>

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

				<?php if (empty($student_options)): ?>
					<p class="empty-state">You do not have any matched students yet.</p>
				<?php else: ?>
					<!-- Conversation list -->
					<div class="conversations-list">
						<?php if (!empty($admin_notices)): ?>
							<?php
								$latestNotice = $admin_notices[0];
								$adminPreview = $latestNotice['message'];
								if (strlen($adminPreview) > 50) $adminPreview = substr($adminPreview, 0, 50) . '...';
								$adminLastTime = strtotime($latestNotice['sent_at']);
								$adminDiff = time() - $adminLastTime;
								if ($adminDiff < 60) { $adminTimeStr = 'Just now'; }
								elseif ($adminDiff < 3600) { $adminTimeStr = floor($adminDiff / 60) . 'm'; }
								elseif ($adminDiff < 86400) { $adminTimeStr = floor($adminDiff / 3600) . 'h'; }
								elseif ($adminDiff < 604800) { $adminTimeStr = floor($adminDiff / 86400) . 'd'; }
								else { $adminTimeStr = date('M j', $adminLastTime); }
								$hasUnread = false;
								foreach ($admin_notices as $an) { if (empty($an['read_at'])) { $hasUnread = true; break; } }
							?>
							<a href="#" class="conversation-item conversation-item--admin" id="adminConvoBtn">
								<div class="conversation-avatar conversation-avatar--admin">
									<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
								</div>
								<div class="conversation-details">
									<div class="conversation-header">
										<h3 class="conversation-name conversation-name--admin">Admin Messages <?php if ($hasUnread): ?><span class="admin-unread-dot"></span><?php endif; ?></h3>
										<span class="conversation-time"><?php echo htmlspecialchars($adminTimeStr); ?></span>
									</div>
									<p class="conversation-preview conversation-preview--admin"><?php echo htmlspecialchars($adminPreview); ?></p>
								</div>
							</a>
						<?php endif; ?>
						<?php foreach ($student_options as $student): ?>
							<?php
								$student_name = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
								$avatar_fallback = 'https://ui-avatars.com/api/?name=' . urlencode($student_name) . '&background=3b82f6&color=fff&size=128';
								$student_avatar = !empty($student['profile_picture']) ? '../' . $student['profile_picture'] : $avatar_fallback;
								
								$last_message = $student['last_message'] ?? null;
								$last_time = $student['last_message_time'] ?? null;
								$is_sent_by_me = $student['last_sender_id'] == $user_id;
								
								$preview_text = 'No messages yet';
								if ($last_message) {
									$preview = strlen($last_message) > 50 ? substr($last_message, 0, 50) . '...' : $last_message;
									$preview_text = ($is_sent_by_me ? 'You: ' : '') . $preview;
								}
								
								$time_display = '';
								if ($last_time) {
									$timestamp = strtotime($last_time);
									$now = time();
									$diff = $now - $timestamp;
									
									if ($diff < 60) {
										$time_display = 'Just now';
									} elseif ($diff < 3600) {
										$time_display = floor($diff / 60) . 'm';
									} elseif ($diff < 86400) {
										$time_display = floor($diff / 3600) . 'h';
									} elseif ($diff < 604800) {
										$time_display = floor($diff / 86400) . 'd';
									} else {
										$time_display = date('M j', $timestamp);
									}
								}
							?>
							<a href="?student=<?php echo (int)$student['user_id']; ?>" class="conversation-item" data-student-id="<?php echo (int)$student['user_id']; ?>">
								<img src="<?php echo htmlspecialchars($student_avatar); ?>" alt="<?php echo htmlspecialchars($student_name); ?>" class="conversation-avatar">
								<div class="conversation-details">
									<div class="conversation-header">
										<h3 class="conversation-name"><?php echo htmlspecialchars($student_name); ?></h3>
										<?php if ($time_display): ?>
											<span class="conversation-time"><?php echo htmlspecialchars($time_display); ?></span>
										<?php endif; ?>
									</div>
									<p class="conversation-preview"><?php echo htmlspecialchars($preview_text); ?></p>
								</div>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>

			<!-- Modal showing admin messages -->
			<?php if (!empty($admin_notices)): ?>
				<div class="chat-modal" id="adminModal">
					<div class="chat-modal__backdrop"></div>
					<div class="chat-modal__panel">
						<div class="chat-modal__header">
							<div class="chat-modal__partner">
								<div class="conversation-avatar conversation-avatar--admin" style="width:48px;height:48px;">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
								</div>
								<div>
									<h2 class="chat-modal__name">Admin Messages</h2>
									<p class="chat-modal__meta">Warnings &amp; notifications from platform admin</p>
								</div>
							</div>
							<button type="button" class="chat-modal__close" id="adminModalClose" aria-label="Close">&times;</button>
						</div>

						<div class="chat-modal__messages">
							<?php foreach (array_reverse($admin_notices) as $notice): ?>
								<?php $isRecent = (time() - strtotime($notice['sent_at'])) < 86400 * 3; ?>
								<article class="message-bubble is-other is-admin-msg <?php echo $isRecent ? 'is-admin-msg--recent' : ''; ?>">
									<p><?php echo nl2br(htmlspecialchars($notice['message'])); ?></p>
									<time datetime="<?php echo htmlspecialchars($notice['sent_at']); ?>">
										<?php echo htmlspecialchars($notice['admin_name']); ?> &middot; <?php echo htmlspecialchars(date('M j, g:i a', strtotime($notice['sent_at']))); ?>
									</time>
								</article>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<!-- Modal showing the active student conversation -->
			<?php if ($chat_partner_user_id): ?>
				<div class="chat-modal is-open" id="chatModal">
					<div class="chat-modal__backdrop"></div>
					<div class="chat-modal__panel">
						<div class="chat-modal__header">
							<div class="chat-modal__partner">
								<img src="<?php echo htmlspecialchars($partner_avatar); ?>" alt="Partner" class="chat-modal__avatar">
								<div>
									<h2 class="chat-modal__name"><?php echo htmlspecialchars($partner_name); ?></h2>
									<?php if ($partner_meta !== ''): ?>
										<p class="chat-modal__meta"><?php echo htmlspecialchars($partner_meta); ?></p>
									<?php endif; ?>
								</div>
							</div>
							<button type="button" class="chat-modal__close" aria-label="Close chat">&times;</button>
						</div>

						<div class="chat-modal__messages" id="messages-container">
							<?php if (empty($conversation_messages)): ?>
								<p class="empty-conversation">No messages yet. Start the conversation below.</p>
							<?php else: ?>
								<?php foreach ($conversation_messages as $message): ?>
									<?php $is_own = ((int)$message['sender_id'] === $user_id); ?>
									<article class="message-bubble <?php echo $is_own ? 'is-own is-mentor-outgoing' : 'is-other is-mentor-incoming'; ?>">
										<p><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
										<time datetime="<?php echo htmlspecialchars($message['sent_at']); ?>">
											<?php echo htmlspecialchars(date('M j, g:i a', strtotime($message['sent_at']))); ?>
										</time>
									</article>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>

						<form method="POST" class="chat-modal__composer" novalidate>
							<input type="hidden" name="action" value="send_message">
							<input type="hidden" name="receiver_id" value="<?php echo (int)$chat_partner_user_id; ?>">
							<label for="message" class="sr-only">Message</label>
							<textarea class="input message-input" id="message" name="message" maxlength="2000" rows="3" placeholder="Type your message..." required></textarea>
							<button type="submit" class="btn">Send</button>
						</form>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</main>

	<?php include '../includes/nav.php'; ?>

	<script>
		(function () {
			const container = document.getElementById('messages-container');
			if (container) {
				container.scrollTop = container.scrollHeight;
			}

			// Close the open chat modal by returning to the conversation list.
			const chatModal = document.getElementById('chatModal');
			const closeBtn = chatModal ? chatModal.querySelector('.chat-modal__close') : null;
			const backdrop = chatModal ? chatModal.querySelector('.chat-modal__backdrop') : null;

			if (closeBtn && chatModal) {
				closeBtn.addEventListener('click', function () {
					window.history.back();
				});
			}

			if (backdrop && chatModal) {
				backdrop.addEventListener('click', function () {
					window.history.back();
				});
			}

			// Let Escape close the active chat modal.
			if (chatModal) {
				document.addEventListener('keydown', function (e) {
					if (e.key === 'Escape' && chatModal.classList.contains('is-open')) {
						window.history.back();
					}
				});
			}

			// Separate modal for admin warning messages.
			var adminBtn = document.getElementById('adminConvoBtn');
			var adminModal = document.getElementById('adminModal');
			var adminClose = document.getElementById('adminModalClose');

			function closeAdminModal() {
				if (adminModal) adminModal.classList.remove('is-open');
			}

			if (adminBtn && adminModal) {
				adminBtn.addEventListener('click', function (e) {
					e.preventDefault();
					adminModal.classList.add('is-open');
					var msgs = adminModal.querySelector('.chat-modal__messages');
					if (msgs) msgs.scrollTop = msgs.scrollHeight;
				});
			}

			if (adminClose) {
				adminClose.addEventListener('click', closeAdminModal);
			}

			if (adminModal) {
				var adminBackdrop = adminModal.querySelector('.chat-modal__backdrop');
				if (adminBackdrop) {
					adminBackdrop.addEventListener('click', closeAdminModal);
				}
				document.addEventListener('keydown', function (e) {
					if (e.key === 'Escape' && adminModal.classList.contains('is-open')) {
						closeAdminModal();
					}
				});
			}
		})();
	</script>
</body>
</html>
