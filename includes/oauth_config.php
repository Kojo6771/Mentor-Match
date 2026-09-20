<?php
require_once __DIR__ . '/env.php';
// Base URL of the application (no trailing slash).
define('OAUTH_BASE_URL', required_env('OAUTH_BASE_URL'));

// Shared callback endpoint used by both providers.
define('OAUTH_REDIRECT_URI', OAUTH_BASE_URL . '/pages/oauth_callback.php');

// Google OAuth 
define('GOOGLE_CLIENT_ID',     required_env('GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET', required_env('GOOGLE_CLIENT_SECRET'));
define('GOOGLE_AUTH_URL',      'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL',     'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL',  'https://www.googleapis.com/oauth2/v3/userinfo');

// Microsoft OAuth
define('MICROSOFT_CLIENT_ID',     required_env('MICROSOFT_CLIENT_ID'));
define('MICROSOFT_CLIENT_SECRET', required_env('MICROSOFT_CLIENT_SECRET'));
define('MICROSOFT_TENANT',        env('MICROSOFT_TENANT', 'common')); // 'common' so that it supports personal + work accounts
define('MICROSOFT_AUTH_URL',      'https://login.microsoftonline.com/' . MICROSOFT_TENANT . '/oauth2/v2.0/authorize');
define('MICROSOFT_TOKEN_URL',     'https://login.microsoftonline.com/' . MICROSOFT_TENANT . '/oauth2/v2.0/token');
define('MICROSOFT_USERINFO_URL',  'https://graph.microsoft.com/v1.0/me');

?>