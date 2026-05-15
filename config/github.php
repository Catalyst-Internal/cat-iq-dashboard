<?php

return [
    'app_id' => env('GITHUB_APP_ID'),
    'installation_id' => env('GITHUB_APP_INSTALLATION_ID'),
    'private_key' => env('GITHUB_APP_PRIVATE_KEY'),
    /** Prefer this on Laravel Cloud: PEM file contents base64-encoded (single line, no quotes). */
    'private_key_base64' => env('GITHUB_APP_PRIVATE_KEY_BASE64'),
    'org' => env('GITHUB_ORG', 'catalyst-internal'),
    'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
];
