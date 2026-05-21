ESP32CoordinateUploader.exe

This executable sends five browser-style test coordinates to random_test_coordinate.php:

https://pnkalrt.page.gd/TRACKER_SYSTEM/api/random_test_coordinate.php?key=TAN&source=Browser-Test-1&latitude=11.562275&longitude=124.399615

Default five-point Naval/BiPSU route upload:
dist\ESP32CoordinateUploader.exe

Route points:
1. 11.562275, 124.399615
2. 11.562385, 124.399865
3. 11.562500, 124.400115
4. 11.562610, 124.400345
5. 11.562720, 124.400575

Continuous route upload every 5 seconds:
dist\ESP32CoordinateUploader.exe --count 0

The executable automatically handles InfinityFree's __test browser-validation cookie before uploading.

Custom random-test endpoint:
dist\ESP32CoordinateUploader.exe --url https://YOUR_DOMAIN/TRACKER_SYSTEM/api/random_test_coordinate.php --key TAN

Upload 5 slightly different sample coordinates:
dist\ESP32CoordinateUploader.exe --count 5 --random

Custom coordinate:
dist\ESP32CoordinateUploader.exe --lat 11.562720 --lng 124.400575

Simulate a second device:
dist\ESP32CoordinateUploader.exe --source ESP32-2 --count 5

Use --help to see all options.
