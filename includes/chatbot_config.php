<?php
// OpenAI settings used by the chatbot API endpoint.
// server-side only file.

// My OpenAI Key
define('OPENAI_API_KEY', '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');

// Chat model used for replies.
define('OPENAI_MODEL', 'gpt-4o-mini');

// Upper bound for response length.
define('OPENAI_MAX_TOKENS', 1024);
