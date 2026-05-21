<?php
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../config/developer.php';

date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

$requestKey = $_GET['key'] ?? '';

if (!hash_equals(DEVELOPER_DASHBOARD_KEY, (string) $requestKey)) {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Developer access key is required.',
    ]);
    exit;
}

function randomCoordinate(float $center, float $spread): float
{
    $offset = (mt_rand() / mt_getrandmax() * $spread * 2) - $spread;

    return round($center + $offset, 6);
}

$requestLatitude = $_GET['latitude'] ?? null;
$requestLongitude = $_GET['longitude'] ?? null;
$latitude = is_numeric($requestLatitude) ? round((float) $requestLatitude, 6) : randomCoordinate(14.599512, 0.03);
$longitude = is_numeric($requestLongitude) ? round((float) $requestLongitude, 6) : randomCoordinate(120.984222, 0.03);
$addedAt = date('Y-m-d h:i A');
$source = trim((string) ($_GET['source'] ?? 'Browser Test'));
$status = 'Idle';

if ($source === '') {
    $source = 'Browser Test';
}

try {
    $connection = getDatabaseConnection();
    $connection->beginTransaction();

    $statement = $connection->prepare(
        'INSERT INTO coordinates (latitude, longitude, added_at, source, status)
         VALUES (:latitude, :longitude, :added_at, :source, :status)
         RETURNING id'
    );
    $statement->bindValue(':latitude', $latitude);
    $statement->bindValue(':longitude', $longitude);
    $statement->bindValue(':added_at', $addedAt);
    $statement->bindValue(':source', $source);
    $statement->bindValue(':status', $status);
    $statement->execute();
    $id = (int) $statement->fetchColumn();

    $newCoordinate = [
        'id' => $id,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'addedAt' => $addedAt,
        'source' => $source,
        'status' => $status,
    ];

    $connection->commit();
    $connection = null;

    echo json_encode([
        'success' => true,
        'message' => 'Random test coordinate saved.',
        'coordinate' => $newCoordinate,
    ]);
} catch (Throwable $error) {
    if (isset($connection) && $connection instanceof PDO) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        $connection = null;
    }

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database error while saving random test coordinate.',
        'error' => $error->getMessage(),
    ]);
}
