This website is a GPS Tracking System Dashboard for monitoring coordinates sent by an ESP32-based tracking device.

It receives latitude and longitude data from an ESP32 through a PHP API, stores the records in a Supabase PostgreSQL database, and displays the saved locations on a web dashboard. The dashboard shows each device’s coordinates, time added, movement status, and an embedded Google Map view of the selected location.

The project includes:

A regular dashboard for viewing tracked locations
A developer dashboard for editing and deleting coordinate records
PHP API endpoints for receiving ESP32/browser test coordinates
Supabase database integration
Google Maps embedding for location visualization
ESP32 upload instructions/code support


(in short)
ESP32 GPS tracker sends coordinates
↓
PHP API receives them
↓
Supabase stores them
↓
Website dashboard displays location history and map view
