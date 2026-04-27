<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/oauth_config.php';

//Validate the incoming request 
$code     = $_GET['code']     ?? '';
$state    = $_GET['state']    ?? '';

// The state is formatted as "provider-token" 
$provider = '';
$state_token = '';
if (strpos($state, '-') !== false) {
    $provider    = substr($state, 0, strpos($state, '-'));
    $state_token = substr($state, strpos($state, '-') + 1);
}

if (!in_array($provider, ['google', 'microsoft'], true)) {
    header('Location: login.php?error=invalid_provider');
    exit;
}

// Verify state parameter to prevent CSRF attacks.
if (empty($state_token) || !isset($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state_token)) {
    header('Location: login.php?error=invalid_state');
    exit;
}

// Clean up one-time state tokens.
unset($_SESSION['oauth_state']);

if (empty($code)) {
    header('Location: login.php?error=no_code');
    exit;
}

// Determine the intended role (only used if we create a new account).
$intended_role = $_SESSION['oauth_role'] ?? 'student';
unset($_SESSION['oauth_role']);
$ms_code_verifier = $_SESSION['ms_oauth_code_verifier'] ?? '';
unset($_SESSION['ms_oauth_code_verifier']);

//Exchange authorization code for access token 
if ($provider === 'google') {
    $token_url = GOOGLE_TOKEN_URL;
    $post_fields = [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => OAUTH_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ];
    $userinfo_url = GOOGLE_USERINFO_URL;
} else {
    $token_url = MICROSOFT_TOKEN_URL;
    $post_fields = [
        'code'          => $code,
        'client_id'     => MICROSOFT_CLIENT_ID,
        'client_secret' => MICROSOFT_CLIENT_SECRET,
        'redirect_uri'  => OAUTH_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ];

    // Include PKCE code_verifier if available (works alongside client_secret).
    if (!empty($ms_code_verifier)) {
        $post_fields['code_verifier'] = $ms_code_verifier;
    }

    $userinfo_url = MICROSOFT_USERINFO_URL;
}

$token_response = http_post($token_url, $post_fields);

if (!$token_response || !isset($token_response['access_token'])) {
    $oauth_error = isset($token_response['error']) ? $token_response['error'] : 'unknown';
    header('Location: login.php?error=token_failed&provider=' . urlencode($provider) . '&oauth_error=' . urlencode($oauth_error));
    exit;
}

$access_token = $token_response['access_token'];

// Fetch user profile from provider
$profile = http_get($userinfo_url, $access_token);

if (!$profile) {
    header('Location: login.php?error=profile_failed');
    exit;
}

// Normalise the profile fields across providers.
if ($provider === 'google') {
    $oauth_id    = $profile['sub']            ?? '';
    $email       = $profile['email']          ?? '';
    $first_name  = $profile['given_name']     ?? '';
    $last_name   = $profile['family_name']    ?? '';
    $picture_url = $profile['picture']        ?? '';
    $phone       = $profile['phoneNumber']    ?? '';
} else {
    $oauth_id    = $profile['id']             ?? '';
    $email       = $profile['mail'] ?? ($profile['userPrincipalName'] ?? '');
    $first_name  = $profile['givenName']      ?? '';
    $last_name   = $profile['surname']        ?? '';
    $picture_url = '';  // MS Graph photo requires a separate call; skip for now.
    $phone       = $profile['mobilePhone'] ?? (!empty($profile['businessPhones'][0]) ? $profile['businessPhones'][0] : '');
}

if (empty($email)) {
    header('Location: login.php?error=no_email');
    exit;
}

// Check whether the user already exists 
try {
    $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, role, oauth_provider FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header('Location: login.php?error=db');
    exit;
}

if ($existing) {
    // Link the OAuth provider if not already linked.
    if (empty($existing['oauth_provider'])) {
        $pdo->prepare('UPDATE users SET oauth_provider = ?, oauth_id = ? WHERE id = ?')
            ->execute([$provider, $oauth_id, $existing['id']]);
    }

    // Log the user in.
    $_SESSION['user_id']    = $existing['id'];
    $_SESSION['first_name'] = $existing['first_name'];
    $_SESSION['last_name']  = $existing['last_name'];
    $_SESSION['email']      = $existing['email'];
    $_SESSION['phone']      = $existing['phone'] ?? '';
    $_SESSION['role']       = $existing['role'];

    header('Location: dashboard.php');
    exit;
}

// Create a new account
$random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
$role = in_array($intended_role, ['student', 'mentor'], true) ? $intended_role : 'student';

// Download and save profile picture from the OAuth provider.
$profile_picture_path = null;
if (!empty($picture_url)) {
    $uploads_dir = '../uploads/profile_pictures/';
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }
    $img_data = @file_get_contents($picture_url);
    if ($img_data) {
        $filename = 'profile_oauth_' . uniqid() . '_' . time() . '.jpg';
        if (file_put_contents($uploads_dir . $filename, $img_data)) {
            $profile_picture_path = 'uploads/profile_pictures/' . $filename;
        }
    }
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO users (first_name, last_name, email, phone, password, role, profile_picture, oauth_provider, oauth_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$first_name, $last_name, $email, $phone, $random_password, $role, $profile_picture_path, $provider, $oauth_id]);
    $new_user_id = $pdo->lastInsertId();
} catch (PDOException $e) {
    header('Location: login.php?error=create_failed');
    exit;
}

$_SESSION['user_id']    = $new_user_id;
$_SESSION['first_name'] = $first_name;
$_SESSION['last_name']  = $last_name;
$_SESSION['email']      = $email;
$_SESSION['phone']      = $phone;
$_SESSION['role']       = $role;

// Redirect new users to role-specific onboarding.
if ($role === 'mentor') {
    header('Location: ../users/mentor/mentor_application.php');
} else {
    header('Location: ../users/student/student_profile_setup.php');
}
exit;

// HTTP helpers
function http_post(string $url, array $fields): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response ? json_decode($response, true) : null;
}

function http_get(string $url, string $bearer_token): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $bearer_token],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response ? json_decode($response, true) : null;
}
