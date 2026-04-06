<?php
/**
 * OAuth 2.0 Configuration for Google and Microsoft sign-in.
 *
 * Instructions:
 * 1. GOOGLE  – Create credentials at https://console.cloud.google.com/apis/credentials
 *              Set the redirect URI to: http://localhost/mentor-match/pages/oauth_callback.php
 *
 * 2. MICROSOFT – Register an app at https://portal.azure.com → App registrations
 *                Set the redirect URI to: http://localhost/mentor-match/pages/oauth_callback.php
 *                Under "Authentication" choose "Web" platform.
 *
 * Replace the placeholder values below with your own client IDs and secrets.
 */

// Base URL of the application (no trailing slash).
define('OAUTH_BASE_URL', 'http://localhost/mentor-match');

// Shared callback endpoint used by both providers.
define('OAUTH_REDIRECT_URI', OAUTH_BASE_URL . '/pages/oauth_callback.php');

// ── Google OAuth ──
define('GOOGLE_CLIENT_ID',     '866439681331-2ggmepghcugj0ln10k40u47gnikvliib.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_AUTH_URL',      'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL',     'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL',  'https://www.googleapis.com/oauth2/v3/userinfo');

// ── Microsoft OAuth ──
define('MICROSOFT_CLIENT_ID',     '32b8f163-0c4f-449d-a6a3-5760c6e3ba1c');
define('MICROSOFT_CLIENT_SECRET', 'b4d60902-b115-48f5-89eb-1718170a8022');
define('MICROSOFT_TENANT',        'common'); // 'common' supports personal + work accounts
define('MICROSOFT_AUTH_URL',      'https://login.microsoftonline.com/' . MICROSOFT_TENANT . '/oauth2/v2.0/authorize');
define('MICROSOFT_TOKEN_URL',     'https://login.microsoftonline.com/' . MICROSOFT_TENANT . '/oauth2/v2.0/token');
define('MICROSOFT_USERINFO_URL',  'https://graph.microsoft.com/v1.0/me');
