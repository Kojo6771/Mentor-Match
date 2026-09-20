<?php
require_once __DIR__ . '/env.php';
// OpenAI settings used by the chatbot API endpoint.
// server-side only file.

// My OpenAI Key
define('OPENAI_API_KEY', required_env('OPENAI_API_KEY'));

// Chat model used for replies.
define('OPENAI_MODEL', env('OPENAI_MODEL', 'gpt-4o-mini'));

// Upper bound for response length.
define('OPENAI_MAX_TOKENS', (int) env('OPENAI_MAX_TOKENS', '1024'));
