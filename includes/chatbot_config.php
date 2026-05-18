<?php
// OpenAI settings used by the chatbot API endpoint.
// server-side only file.

// My OpenAI Key
define('OPENAI_API_KEY', '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');

// Chat model used for replies.
define('OPENAI_MODEL', 'gpt-4o-mini');

// Upper bound for response length.
define('OPENAI_MAX_TOKENS', 1024);
