<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$first_name = htmlspecialchars($_SESSION['first_name'] ?? 'there');
$user_role  = $_SESSION['role'] ?? 'student';

// Load course details so the page can suggest relevant prompts.
$student_course = '';
$student_year   = '';

if ($user_role === 'student') {
    try {
        $stmt = $pdo->prepare('SELECT course, year_of_study FROM students WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($student) {
            $student_course = htmlspecialchars($student['course']);
            $student_year   = (int)$student['year_of_study'];
        }
    } catch (PDOException $e) { /* continue */ }
}

// Keep an avatar fallback ready if the user has no profile picture.
$fallback_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($first_name) . '&background=3b82f6&color=fff&size=128';
try {
    $stmt = $pdo->prepare('SELECT profile_picture FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $pp = $stmt->fetchColumn();
    $avatar_url = !empty($pp) ? '../' . htmlspecialchars($pp) : $fallback_avatar;
} catch (PDOException $e) {
    $avatar_url = $fallback_avatar;
}

// Starter question chips tailored to the detected course.
$suggestions = [];
$cl = strtolower($student_course);
if (strpos($cl, 'computer') !== false || strpos($cl, 'software') !== false || strpos($cl, 'computing') !== false) {
    $suggestions = [
        'Explain object-oriented programming',
        'How do sorting algorithms compare?',
        'Help me understand databases',
        'What are design patterns?',
        'Tips for coding assignments',
    ];
} elseif (strpos($cl, 'engineer') !== false) {
    $suggestions = [
        'Explain thermodynamics basics',
        'Help with calculus problems',
        'Circuit analysis methods',
        'Engineering project tips',
        'Exam revision strategies',
    ];
} elseif (strpos($cl, 'math') !== false || strpos($cl, 'statistic') !== false) {
    $suggestions = [
        'Explain linear algebra concepts',
        'Help with probability theory',
        'Calculus integration techniques',
        'Statistical analysis methods',
        'Proof writing strategies',
    ];
} elseif (strpos($cl, 'business') !== false || strpos($cl, 'account') !== false || strpos($cl, 'econom') !== false) {
    $suggestions = [
        'Explain supply and demand',
        'Financial statement analysis',
        'Marketing strategy basics',
        'Business plan structure',
        'Exam preparation tips',
    ];
} elseif (strpos($cl, 'psycholog') !== false || strpos($cl, 'sociolog') !== false) {
    $suggestions = [
        'Key psychological theories',
        'Research methods in social science',
        'Help with case studies',
        'Essay structure tips',
        'Statistical tests explained',
    ];
} elseif (strpos($cl, 'law') !== false) {
    $suggestions = [
        'How to write a legal essay',
        'Contract law basics',
        'Case analysis method',
        'Tort law principles',
        'Legal research strategies',
    ];
} elseif (strpos($cl, 'physic') !== false || strpos($cl, 'chemist') !== false || strpos($cl, 'biolog') !== false) {
    $suggestions = [
        'Explain core principles',
        'Lab report writing tips',
        'Help with equations',
        'Study methods for science',
        'Exam preparation strategies',
    ];
} elseif (strpos($cl, 'english') !== false || strpos($cl, 'histor') !== false) {
    $suggestions = [
        'Essay structuring tips',
        'Critical analysis techniques',
        'How to cite sources properly',
        'Revision strategies',
        'Research methodology help',
    ];
} else {
    $suggestions = [
        'Help with key course concepts',
        'Study tips for my exams',
        'How to write a great essay',
        'Research methodology explained',
        'Career paths in my field',
    ];
}

// Friendly intro text shown before the first message.
$welcomeText = "I'm your AI study assistant on MentorMatch.";
if ($student_course && $student_year) {
    $welcomeText .= " I know you're studying <strong>{$student_course}</strong> in <strong>Year&nbsp;{$student_year}</strong> — ask me anything about your course!";
} else {
    $welcomeText .= " Ask me anything about your studies — concepts, exam prep, essay help, and more.";
}

// Reusable inline SVG icons used by the UI.
$sparkleIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/></svg>';
$sendIcon    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
$refreshIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>';
?>

<!-- HTML -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content">
    <title>AI Chatbot — MentorMatch</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/chatbot.css">
</head>
<body>

<div class="cb-page" id="cbPage">

    <!-- Top bar -->
    <header class="cb-header">
        <div class="cb-header__bot">
            <div class="cb-avatar"><?= $sparkleIcon ?></div>
            <div>
                <h1 class="cb-header__title">MentorMatch AI</h1>
                <p class="cb-header__subtitle">
                    <?= $student_course ? htmlspecialchars($student_course) . ' Assistant' : 'Academic Assistant' ?>
                </p>
            </div>
        </div>
        <div class="cb-header__actions">
            <button class="cb-header__btn" id="cbClear" title="New conversation" aria-label="Start new conversation">
                <?= $refreshIcon ?>
            </button>
        </div>
    </header>

    <!-- Conversation area -->
    <main class="cb-messages" id="cbMessages" role="log" aria-live="polite" aria-label="Chat messages">

        <!-- Intro shown before the first message -->
        <div class="cb-welcome" id="cbWelcome">
            <div class="cb-avatar cb-avatar--lg"><?= $sparkleIcon ?></div>
            <h2>Hi <?= $first_name ?>! 👋</h2>
            <p><?= $welcomeText ?></p>
        </div>

        <!-- Quick-start prompt chips -->
        <div class="cb-suggestions" id="cbSuggestions">
            <?php foreach ($suggestions as $s): ?>
            <button class="cb-chip" type="button"><?= htmlspecialchars($s) ?></button>
            <?php endforeach; ?>
        </div>

        <!-- Bot typing indicator (hidden by default) -->
        <div class="cb-typing" id="cbTyping" role="status" aria-label="AI is thinking">
            <div class="cb-row__avatar">
                <div class="cb-avatar"><?= $sparkleIcon ?></div>
            </div>
            <div class="cb-typing__bubble">
                <span class="cb-typing__dot"></span>
                <span class="cb-typing__dot"></span>
                <span class="cb-typing__dot"></span>
            </div>
        </div>

    </main>

    <!-- Message composer -->
    <div class="cb-composer">
        <textarea id="cbInput"
                  class="cb-input"
                  placeholder="Ask me anything…"
                  rows="1"
                  aria-label="Type your message"></textarea>
        <button id="cbSend" class="cb-send" aria-label="Send message">
            <?= $sendIcon ?>
        </button>
    </div>

</div>

<?php include '../includes/nav.php'; ?>

<script>
(() => {
    // Cache DOM references used throughout the script.
    const messagesEl    = document.getElementById('cbMessages');
    const inputEl       = document.getElementById('cbInput');
    const sendBtn       = document.getElementById('cbSend');
    const clearBtn      = document.getElementById('cbClear');
    const welcomeEl     = document.getElementById('cbWelcome');
    const suggestionsEl = document.getElementById('cbSuggestions');
    const typingEl      = document.getElementById('cbTyping');

    // Local client-side chat state.
    let history   = [];
    let sending   = false;

    // Keep page height in sync with mobile viewport/keyboard changes.
    function setVH() {
        const h = window.visualViewport
            ? window.visualViewport.height
            : window.innerHeight;
        document.documentElement.style.setProperty('--cb-vh', h + 'px');
    }
    setVH();
    window.addEventListener('resize', setVH);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', setVH);
        window.visualViewport.addEventListener('scroll', setVH);
    }

    // Expand the textarea as the user types, up to a max height.
    inputEl.addEventListener('input', () => {
        inputEl.style.height = 'auto';
        inputEl.style.height = Math.min(inputEl.scrollHeight, 120) + 'px';
    });

    // Press Enter to send; Shift+Enter inserts a new line.
    inputEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    });

    // Send current message from the button.
    sendBtn.addEventListener('click', handleSend);

    // Clicking a suggestion submits it as a message.
    suggestionsEl.addEventListener('click', (e) => {
        const chip = e.target.closest('.cb-chip');
        if (!chip) return;
        inputEl.value = chip.textContent;
        handleSend();
    });

    // Reset chat UI and local history.
    clearBtn.addEventListener('click', () => {
        history = [];
        // Remove rendered chat rows.
        messagesEl.querySelectorAll('.cb-row').forEach(r => r.remove());
        // Bring back the intro and suggestion chips.
        welcomeEl.classList.remove('cb-welcome--hidden');
        suggestionsEl.classList.remove('cb-suggestions--hidden');
        inputEl.value = '';
        inputEl.style.height = 'auto';
        inputEl.focus();
    });

    // Main send flow: render user message, call API, then render bot response.
    async function handleSend() {
        const text = inputEl.value.trim();
        if (!text || sending) return;

        sending = true;
        sendBtn.disabled = true;

        // Hide the intro once conversation begins.
        if (history.length === 0) {
            welcomeEl.classList.add('cb-welcome--hidden');
            suggestionsEl.classList.add('cb-suggestions--hidden');
        }

        // Render the outgoing user message immediately.
        appendMessage('user', text);
        history.push({ role: 'user', content: text });

        // Reset input after sending.
        inputEl.value = '';
        inputEl.style.height = 'auto';

        // Show typing state while waiting for the API response.
        showTyping();

        try {
            const res = await fetch('chatbot_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text, history: history.slice(0, -1) }),
            });

            const raw = await res.text();
            let data = null;

            try {
                data = raw ? JSON.parse(raw) : null;
            } catch (parseErr) {
                throw new Error('INVALID_JSON_RESPONSE');
            }

            hideTyping();

            if (!res.ok) {
                appendMessage('error', data?.error || `Chatbot request failed (HTTP ${res.status}).`);
            } else if (data?.error) {
                appendMessage('error', data.error);
            } else if (!data?.reply) {
                appendMessage('error', 'Received an empty response from the chatbot.');
            } else {
                appendMessage('bot', data.reply);
                history.push({ role: 'assistant', content: data.reply });
            }
        } catch (err) {
            hideTyping();
            if (err?.message === 'INVALID_JSON_RESPONSE') {
                appendMessage('error', 'Chatbot server returned an invalid response. Please contact admin.');
            } else {
                appendMessage('error', 'Something went wrong. Please check your connection and try again.');
            }
        }

        sending = false;
        sendBtn.disabled = false;
        inputEl.focus();
    }

    // Render one message bubble row in the conversation.
    function appendMessage(type, content) {
        const row = document.createElement('div');
        row.className = 'cb-row cb-row--' + (type === 'user' ? 'user' : 'bot');

        const now = new Date();
        const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        if (type === 'user') {
            row.innerHTML = `
                <div class="cb-bubble cb-bubble--user">
                    ${escapeHtml(content)}
                    <span class="cb-bubble__time">${time}</span>
                </div>`;
        } else if (type === 'error') {
            row.innerHTML = `
                <div class="cb-row__avatar">
                    <div class="cb-avatar">${SPARKLE_SVG}</div>
                </div>
                <div class="cb-bubble cb-bubble--error">
                    ${escapeHtml(content)}
                    <span class="cb-bubble__time">${time}</span>
                </div>`;
        } else {
            row.innerHTML = `
                <div class="cb-row__avatar">
                    <div class="cb-avatar">${SPARKLE_SVG}</div>
                </div>
                <div class="cb-bubble cb-bubble--bot">
                    ${renderMarkdown(content)}
                    <span class="cb-bubble__time">${time}</span>
                </div>`;
        }

        // Keep typing indicator pinned at the bottom by inserting above it.
        messagesEl.insertBefore(row, typingEl);
        scrollToBottom();
    }

    // Toggle typing indicator state.
    function showTyping() {
        typingEl.classList.add('is-visible');
        scrollToBottom();
    }
    function hideTyping() {
        typingEl.classList.remove('is-visible');
    }

    // Smoothly move the chat to the latest message.
    function scrollToBottom() {
        requestAnimationFrame(() => {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        });
    }

    // Escape raw text before injecting into the DOM.
    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // SVG used for avatars created dynamically in JavaScript.
    const SPARKLE_SVG = `<?= str_replace(["\n", "\r"], '', $sparkleIcon) ?>`;

    // Lightweight markdown-to-HTML formatter for bot replies.
    function renderMarkdown(text) {
        // Escape HTML first
        text = escapeHtml(text);

        // Code blocks  ```lang\ncode\n```
        text = text.replace(/```(\w*)\n([\s\S]*?)```/g, (_, lang, code) => {
            return `<pre><code>${code.trim()}</code></pre>`;
        });
        // Remaining triple backticks without newline
        text = text.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');

        // Inline code
        text = text.replace(/`([^`]+)`/g, '<code>$1</code>');

        // Bold **text**
        text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

        // Italic *text*
        text = text.replace(/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');

        // Headers (### and ####)
        text = text.replace(/^#### (.+)$/gm, '<h4>$1</h4>');
        text = text.replace(/^### (.+)$/gm, '<h3>$1</h3>');

        // Unordered lists
        text = text.replace(/^[\-\*] (.+)$/gm, '⌊li⌉$1⌊/li⌉');
        text = text.replace(/(⌊li⌉[\s\S]*?⌊\/li⌉\n?)+/g, (m) => '<ul>' + m + '</ul>');
        text = text.replace(/⌊li⌉/g, '<li>').replace(/⌊\/li⌉/g, '</li>');

        // Ordered lists
        text = text.replace(/^\d+\.\s(.+)$/gm, '⌊oli⌉$1⌊/oli⌉');
        text = text.replace(/(⌊oli⌉[\s\S]*?⌊\/oli⌉\n?)+/g, (m) => '<ol>' + m + '</ol>');
        text = text.replace(/⌊oli⌉/g, '<li>').replace(/⌊\/oli⌉/g, '</li>');

        // Blockquotes
        text = text.replace(/^&gt; (.+)$/gm, '<blockquote>$1</blockquote>');

        // Horizontal rules
        text = text.replace(/^---$/gm, '<hr>');

        // Links [text](url)
        text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');

        // Paragraphs: double newlines
        text = text.replace(/\n\n/g, '</p><p>');

        // Single newlines → <br> (but not inside pre/code)
        text = text.replace(/\n/g, '<br>');

        // Wrap in paragraph
        text = '<p>' + text + '</p>';

        // Clean up empty/nested paragraphs around block elements
        const blocks = 'pre|ul|ol|h3|h4|blockquote|hr';
        const re1 = new RegExp(`<p>\\s*(<(?:${blocks})(?:>|\\s))`, 'g');
        const re2 = new RegExp(`(<\/(?:${blocks})>)\\s*<\/p>`, 'g');
        text = text.replace(re1, '$1');
        text = text.replace(re2, '$1');
        text = text.replace(/<p><\/p>/g, '');
        text = text.replace(/<p><br>/g, '<p>');

        return text;
    }
})();
</script>

</body>
</html>
