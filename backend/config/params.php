<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'openrouter' => [
        'apiKey' => getenv('OPENROUTER_API_KEY') ?: '',
        'model' => getenv('OPENROUTER_MODEL') ?: 'anthropic/claude-sonnet-4.5',
        'baseUrl' => getenv('OPENROUTER_BASE_URL') ?: 'https://openrouter.ai/api/v1',
        'referer' => getenv('APP_URL') ?: '',
        'title' => getenv('APP_NAME') ?: '',
        'temperature' => 0,
        'timeout' => 30,
    ],
];
