<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/chatbot_config.php';

header('Content-Type: application/json; charset=utf-8');

// Allow only authenticated users to use the chatbot endpoint.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in to use the chatbot.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// Read the JSON payload sent from chatbot.php.
$input   = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$history = $input['history'] ?? [];

if ($message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message cannot be empty.']);
    exit;
}
if (mb_strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is too long (max 2 000 characters).']);
    exit;
}

// Basic session-based rate limit to avoid message spam.
$now = time();
if (!isset($_SESSION['cb_requests'])) $_SESSION['cb_requests'] = [];
// Keep timestamps from only the last 60 seconds.
$_SESSION['cb_requests'] = array_filter($_SESSION['cb_requests'], fn($t) => $t > $now - 60);
if (count($_SESSION['cb_requests']) >= 15) {
    http_response_code(429);
    echo json_encode(['error' => "You're sending messages too quickly. Please wait a moment."]);
    exit;
}
$_SESSION['cb_requests'][] = $now;

// Build a small user context so answers feel personalized.
$user_id    = (int)$_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'Student';
$user_role  = $_SESSION['role'] ?? 'student';

$context = "The user's name is {$first_name}.";

if ($user_role === 'student') {
    try {
        $stmt = $pdo->prepare('SELECT course, year_of_study, bio FROM students WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($student) {
            $context .= " They are a Year {$student['year_of_study']} {$student['course']} student.";
            if (!empty($student['bio'])) {
                $context .= " About them: {$student['bio']}";
            }
        }
    } catch (PDOException $e) { /* continue */ }

    // Pull subjects from the student's matched mentor when available.
    try {
        $stmt = $pdo->prepare('
            SELECT GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ", ") AS subjects
            FROM students st
            JOIN mentor_subjects ms ON ms.mentor_id = st.mentor_id
            JOIN subjects s ON s.id = ms.subject_id
            WHERE st.user_id = ?
        ');
        $stmt->execute([$user_id]);
        $subjects = $stmt->fetchColumn();
        if ($subjects) $context .= " Their mentor's subjects: {$subjects}.";
    } catch (PDOException $e) { /* continue */ }
} elseif ($user_role === 'mentor') {
    $context .= " They are a mentor on the platform.";
}

// Main assistant instructions passed as the system message.
$systemPrompt = <<<PROMPT
You are **MentorMatch AI**, a friendly and knowledgeable university academic assistant built into the MentorMatch mentoring platform.

Student context:
{$context}

Your guidelines:
• Focus on academic and educational topics relevant to the student's course and year level.
• Provide clear, well-structured explanations with examples when helpful.
• Break down complex concepts into understandable parts.
• Suggest study strategies, resources, and practical tips when appropriate.
• Be encouraging, supportive, and conversational — like a knowledgeable study buddy.
• Use markdown formatting (bold, lists, code blocks) to make responses readable.
• If the student asks about topics outside their course, still help where you can but gently relate it back to their studies.
• You can help with: course concepts, study tips, assignment guidance, exam preparation, academic writing, research methods, career advice, and university life.
• Do NOT write complete assignments, essays, or coursework for students. Instead, help them understand concepts, plan their approach, and develop their own work.
• Keep responses concise but thorough — aim for the right level of detail.
PROMPT;

// Compose the chat history to send to OpenAI.
$messages = [['role' => 'system', 'content' => $systemPrompt]];

// Keep only the most recent turns so payload stays lightweight.
$historySlice = array_slice($history, -20);
foreach ($historySlice as $msg) {
    if (isset($msg['role'], $msg['content'])) {
        $role = ($msg['role'] === 'user') ? 'user' : 'assistant';
        $messages[] = ['role' => $role, 'content' => $msg['content']];
    }
}
$messages[] = ['role' => 'user', 'content' => $message];

// Validate API key before making the network call.
$apiKey = OPENAI_API_KEY;
if ($apiKey === 'your-api-key-here' || empty($apiKey)) {
    echo json_encode([
        'error' => 'The OpenAI API key has not been configured yet. Please add your key to includes/chatbot_config.php'
    ]);
    exit;
}

// Send request to the OpenAI Chat Completions API.
$payload = json_encode([
    'model'       => OPENAI_MODEL,
    'messages'    => $messages,
    'max_tokens'  => (int)OPENAI_MAX_TOKENS,
    'temperature' => 0.7,
]);

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_TIMEOUT        => 45,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not reach the AI service. Please try again shortly.']);
    exit;
}

if ($httpCode !== 200) {
    $errData = json_decode($response, true);
    $errMsg  = $errData['error']['message'] ?? 'The AI service returned an error (HTTP ' . $httpCode . ').';
    http_response_code(502);
    echo json_encode(['error' => $errMsg]);
    exit;
}

$data  = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? '';

if ($reply === '') {
    echo json_encode(['error' => 'Received an empty response from the AI. Please try again.']);
    exit;
}

echo json_encode(['reply' => $reply]);
