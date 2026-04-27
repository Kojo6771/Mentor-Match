<?php
// Base URL of the application (no trailing slash).
define('OAUTH_BASE_URL', 'http://localhost/mentor-match');

// Shared callback endpoint used by both providers.
define('OAUTH_REDIRECT_URI', OAUTH_BASE_URL . '/pages/oauth_callback.php');

// Google OAuth 
define('GOOGLE_CLIENT_ID',     '866439681331-2ggmepghcugj0ln10k40u47gnikvliib.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_AUTH_URL',      'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL',     'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL',  'https://www.googleapis.com/oauth2/v3/userinfo');

// Microsoft OAuth
define('MICROSOFT_CLIENT_ID',     '32b8f163-0c4f-449d-a6a3-5760c6e3ba1c');
define('MICROSOFT_CLIENT_SECRET', 'Ldz8Q~j1RexopXzsyKa7TryLr3x.1RDSTTNuBcs6');
define('MICROSOFT_TENANT',        'common'); // 'common' so that it supports personal + work accounts
define('MICROSOFT_AUTH_URL',      'https://login.microsoftonline.com/' . MICROSOFT_TENANT . '/oauth2/v2.0/authorize');
define('MICROSOFT_TOKEN_URL',     'https://login.microsoftonline.com/' . MICROSOFT_TENANT . '/oauth2/v2.0/token');
define('MICROSOFT_USERINFO_URL',  'https://graph.microsoft.com/v1.0/me');

?>