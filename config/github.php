<?php

return [
    'app_id' => env('GITHUB_APP_ID'),
    'installation_id' => env('GITHUB_APP_INSTALLATION_ID'),
    'private_key' => env('GITHUB_APP_PRIVATE_KEY'),
    'org' => env('GITHUB_ORG', 'catalyst-internal'),
    'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
];
