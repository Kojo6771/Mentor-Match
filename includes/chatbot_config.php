<?php
// OpenAI settings used by the chatbot API endpoint.
// Keep this file server-side only.

define('OPENAI_API_KEY', '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');

// Chat model used for replies.
define('OPENAI_MODEL', 'gpt-4o-mini');

// Upper bound for response length.
define('OPENAI_MAX_TOKENS', 1024);
