<?php
require_once __DIR__ . '/../../db/connection.php';
require_once __DIR__ . '/../../config/developer.php';

$requestKey = $_GET['key'] ?? '';

if (!hash_equals(DEVELOPER_DASHBOARD_KEY, $requestKey)) {
    http_response_code(404);
    exit('Page not found.');
}

$databaseStatus = getDatabaseStatus();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Developer Tracking System Dashboard</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <main class="dashboard">
    <header class="header">
      <div class="logo-area">
        <img src="../img/Panic%20Tracker.png" alt="Panic Tracker logo">
      </div>

      <div class="header-text">
        <h1>Developer Dashboard</h1>
        <p>View, edit, and delete saved coordinates for testing.</p>
      </div>

      <div class="connection-status">
        <?php echo htmlspecialchars($databaseStatus['message'], ENT_QUOTES, 'UTF-8'); ?>
      </div>
    </header>

    <section class="card">
      <div class="card-header">
        <h2>Coordinate Records</h2>
        <span class="selected-location" id="latestLocation">Device location loading...</span>
      </div>

      <div class="table-wrapper">
        <table class="developer-table">
          <thead>
            <tr>
              <th>No.</th>
              <th>Device</th>
              <th>Coordinates</th>
              <th>Time Added</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>

          <tbody id="coordinateTable"></tbody>
        </table>
      </div>
    </section>

    <section class="card">
      <div class="card-header">
        <h2>Google Map Location</h2>
        <span class="selected-location" id="selectedCoordinates"></span>
      </div>

      <div class="map-container">
        <iframe
          id="mapFrame"
          title="Google Map Location"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade">
        </iframe>
      </div>
    </section>
  </main>

  <script>
    window.DEVELOPER_DASHBOARD_KEY = <?php echo json_encode(DEVELOPER_DASHBOARD_KEY); ?>;
  </script>
  <script src="../js/dev-dashboard.js?v=20260512-manual-map-selection"></script>
</body>
</html>
