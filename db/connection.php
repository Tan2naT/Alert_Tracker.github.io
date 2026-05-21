<?php

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../config/supabase.php';

function getDatabaseConnection(): PDO
{
    if (SUPABASE_DB_HOST === SUPABASE_DB_HOST_PLACEHOLDER) {
        throw new RuntimeException('Supabase database host is not configured.');
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
        SUPABASE_DB_HOST,
        SUPABASE_DB_PORT,
        SUPABASE_DB_NAME
    );

    return new PDO($dsn, SUPABASE_DB_USER, SUPABASE_DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function getDatabaseStatus(): array
{
    try {
        $connection = getDatabaseConnection();
        $connection = null;

        return [
            'connected' => true,
            'message' => 'Supabase database connected',
        ];
    } catch (Throwable $error) {
        return [
            'connected' => false,
            'message' => 'Supabase database not connected',
            'error' => $error->getMessage(),
        ];
    }
}
