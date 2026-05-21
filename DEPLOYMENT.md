# Deployment

This project uses PHP for the dashboard API and Supabase PostgreSQL for the database.

## Required Hosting Environment Variables

Set these values in your PHP hosting provider's environment/configuration dashboard:

```text
SUPABASE_DB_HOST=db.qcslgxgehsbymwqdzgba.supabase.co
SUPABASE_DB_PORT=5432
SUPABASE_DB_NAME=postgres
SUPABASE_DB_USER=postgres
SUPABASE_DB_PASSWORD=your-real-supabase-database-password
```

Do not commit the real password to GitHub.

## Hosting Requirements

Your host must support:

- PHP
- PDO
- PostgreSQL PDO driver (`pdo_pgsql`)
- HTTPS

## Database Setup

Run this SQL file in Supabase Dashboard > SQL Editor:

```text
db/supabase_database.sql
```

## Test URLs

After deployment, test the API:

```text
https://YOUR_DOMAIN/TRACKER_SYSTEM/api/coordinates.php
```

Then test inserting one coordinate:

```text
https://YOUR_DOMAIN/TRACKER_SYSTEM/api/random_test_coordinate.php?key=TAN&source=ESP32&latitude=11.563522&longitude=124.395743
```
