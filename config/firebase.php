<?php

function getFirebaseConfig(): array
{
    return [
        'project_id' => 'your-firebase-project-id',
        'database_url' => 'https://your-firebase-project-id-default-rtdb.firebaseio.com',
        'credentials_file' => __DIR__ . '/firebase-service-account.json',
        'ready' => false,
    ];
}

function getFirebaseConnectionStatus(): array
{
    $config = getFirebaseConfig();

    return [
        'connected' => false,
        'message' => $config['ready']
            ? 'Firebase configured'
            : 'Firebase placeholder',
    ];
}
