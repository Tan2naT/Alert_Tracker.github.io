<?php

function supabaseEnv(string $name, string $default = ''): string
{
    $value = getenv($name);

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

/*
  Supabase database settings.

  Replace SUPABASE_DB_HOST with your actual Supabase database host:
  db.YOUR_PROJECT_REF.supabase.co

  You can also set these as hosting environment variables instead of editing
  this file:
  - SUPABASE_DB_HOST
  - SUPABASE_DB_PORT
  - SUPABASE_DB_NAME
  - SUPABASE_DB_USER
  - SUPABASE_DB_PASSWORD
*/
const SUPABASE_DB_HOST_PLACEHOLDER = 'db.YOUR_PROJECT_REF.supabase.co';

define('SUPABASE_DB_HOST', supabaseEnv('SUPABASE_DB_HOST', 'db.qcslgxgehsbymwqdzgba.supabase.co'));
define('SUPABASE_DB_PORT', supabaseEnv('SUPABASE_DB_PORT', '5432'));
define('SUPABASE_DB_NAME', supabaseEnv('SUPABASE_DB_NAME', 'postgres'));
define('SUPABASE_DB_USER', supabaseEnv('SUPABASE_DB_USER', 'postgres'));
define('SUPABASE_DB_PASSWORD', supabaseEnv('SUPABASE_DB_PASSWORD'));
