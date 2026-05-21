<?php
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../config/developer.php';

date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

class CoordinateNotFoundException extends RuntimeException
{
}

function readCoordinates(PDO $connection): array
{
    $result = $connection->query(
        'SELECT id, latitude, longitude, added_at, source, status
         FROM coordinates
         ORDER BY id DESC'
    );

    $coordinates = [];

    while ($row = $result->fetch()) {
        $coordinates[] = [
            'id' => (int) $row['id'],
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
            'addedAt' => $row['added_at'],
            'source' => $row['source'],
            'status' => $row['status'],
        ];
    }

    return addDevicePlottingData($coordinates);
}

function sameCoordinate(array $first, array $second): bool
{
    return abs((float) $first['latitude'] - (float) $second['latitude']) < 0.000001
        && abs((float) $first['longitude'] - (float) $second['longitude']) < 0.000001;
}

function addDevicePlottingData(array $coordinates): array
{
    $sourceLabels = [];
    $sourceTrails = [];

    foreach ($coordinates as $coordinate) {
        $source = trim((string) ($coordinate['source'] ?? ''));

        if ($source === '') {
            $source = 'Unknown Device';
        }

        if (!isset($sourceLabels[$source])) {
            $sourceLabels[$source] = 'Device ' . (count($sourceLabels) + 1);
        }

        if (!isset($sourceTrails[$source])) {
            $sourceTrails[$source] = [];
        }

        array_unshift($sourceTrails[$source], [
            'id' => $coordinate['id'],
            'latitude' => (float) $coordinate['latitude'],
            'longitude' => (float) $coordinate['longitude'],
            'addedAt' => $coordinate['addedAt'],
        ]);
    }

    $previousBySource = [];

    for ($index = count($coordinates) - 1; $index >= 0; $index--) {
        $source = trim((string) ($coordinates[$index]['source'] ?? ''));

        if ($source === '') {
            $source = 'Unknown Device';
        }

        $isMoving = isset($previousBySource[$source]) && !sameCoordinate($coordinates[$index], $previousBySource[$source]);

        $coordinates[$index]['source'] = $source;
        $coordinates[$index]['deviceLabel'] = $sourceLabels[$source];
        $coordinates[$index]['deviceSource'] = $source;
        $coordinates[$index]['deviceStatus'] = $isMoving ? 'Moving' : 'Idle';
        $coordinates[$index]['status'] = $coordinates[$index]['deviceStatus'];
        $coordinates[$index]['deviceTrail'] = $sourceTrails[$source];

        $previousBySource[$source] = $coordinates[$index];
    }

    return $coordinates;
}

function saveCoordinate(PDO $connection, float $latitude, float $longitude, string $addedAt, string $source): array
{
    $connection->beginTransaction();

    try {
        $statement = $connection->prepare(
            'INSERT INTO coordinates (latitude, longitude, added_at, source, status)
             VALUES (:latitude, :longitude, :added_at, :source, :status)
             RETURNING id'
        );

        $status = 'Idle';
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

        return $newCoordinate;
    } catch (Throwable $error) {
        $connection->rollBack();
        throw $error;
    }
}

function resetStoredMovementStatus(PDO $connection): void
{
    $connection->query("UPDATE coordinates SET status = 'Idle'");
}

function updateCoordinate(
    PDO $connection,
    int $id,
    float $latitude,
    float $longitude,
    string $addedAt,
    string $source,
    string $status
): array {
    $connection->beginTransaction();

    try {
        $statement = $connection->prepare(
            'UPDATE coordinates
             SET latitude = :latitude,
                 longitude = :longitude,
                 added_at = :added_at,
                 source = :source,
                 status = :status
             WHERE id = :id'
        );
        $statement->bindValue(':latitude', $latitude);
        $statement->bindValue(':longitude', $longitude);
        $statement->bindValue(':added_at', $addedAt);
        $statement->bindValue(':source', $source);
        $statement->bindValue(':status', $status);
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        if ($statement->rowCount() === 0) {
            $checkStatement = $connection->prepare('SELECT id FROM coordinates WHERE id = :id');
            $checkStatement->bindValue(':id', $id, PDO::PARAM_INT);
            $checkStatement->execute();
            $exists = $checkStatement->fetch();

            if (!$exists) {
                throw new CoordinateNotFoundException('Coordinate not found.');
            }
        }

        $connection->commit();

        return [
            'id' => $id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'addedAt' => $addedAt,
            'source' => $source,
            'status' => $status,
        ];
    } catch (Throwable $error) {
        $connection->rollBack();
        throw $error;
    }
}

function deleteCoordinate(PDO $connection, int $id): void
{
    $connection->beginTransaction();

    try {
        $statement = $connection->prepare('DELETE FROM coordinates WHERE id = :id');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        if ($statement->rowCount() === 0) {
            throw new CoordinateNotFoundException('Coordinate not found.');
        }

        resetStoredMovementStatus($connection);
        $connection->commit();
    } catch (Throwable $error) {
        $connection->rollBack();
        throw $error;
    }
}

function getRequestData(): array
{
    $jsonInput = json_decode(file_get_contents('php://input'), true);

    if (is_array($jsonInput)) {
        return $jsonInput;
    }

    return $_POST;
}

function getRequestId(array $requestData): ?int
{
    $id = $requestData['id'] ?? $_GET['id'] ?? null;

    if (!is_numeric($id)) {
        return null;
    }

    return (int) $id;
}

function validateDeveloperKey(array $requestData): void
{
    $requestKey = $requestData['devKey'] ?? $_GET['devKey'] ?? '';

    if (!hash_equals(DEVELOPER_DASHBOARD_KEY, (string) $requestKey)) {
        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Developer access key is required.',
        ]);
        exit;
    }
}

$databaseStatus = getDatabaseStatus();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestData = getRequestData();
    $latitude = $requestData['latitude'] ?? null;
    $longitude = $requestData['longitude'] ?? null;

    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'Latitude and longitude are required numeric values.',
        ]);
        exit;
    }

    try {
        $connection = getDatabaseConnection();
        $source = trim((string) ($requestData['source'] ?? 'ESP32'));

        if ($source === '') {
            $source = 'ESP32';
        }

        $newCoordinate = saveCoordinate(
            $connection,
            (float) $latitude,
            (float) $longitude,
            $requestData['addedAt'] ?? date('Y-m-d h:i A'),
            $source
        );
        $connection = null;
    } catch (Throwable $error) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Database error while saving coordinate.',
            'error' => $error->getMessage(),
        ]);
        exit;
    }

    http_response_code(201);

    echo json_encode([
        'success' => true,
        'message' => 'Coordinate received from ESP32.',
        'coordinate' => $newCoordinate,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $requestData = getRequestData();
    validateDeveloperKey($requestData);

    $id = getRequestId($requestData);
    $latitude = $requestData['latitude'] ?? null;
    $longitude = $requestData['longitude'] ?? null;
    $addedAt = $requestData['addedAt'] ?? null;
    $source = trim((string) ($requestData['source'] ?? 'ESP32'));
    $status = $requestData['status'] ?? 'Idle';

    if (!$id || !is_numeric($latitude) || !is_numeric($longitude) || !is_string($addedAt) || trim($addedAt) === '' || $source === '') {
        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'ID, latitude, longitude, device source, and time added are required.',
        ]);
        exit;
    }

    if (!in_array($status, ['Moving', 'Idle'], true)) {
        $status = 'Idle';
    }

    try {
        $connection = getDatabaseConnection();
        $updatedCoordinate = updateCoordinate(
            $connection,
            $id,
            (float) $latitude,
            (float) $longitude,
            trim($addedAt),
            $source,
            $status
        );
        $connection = null;
    } catch (CoordinateNotFoundException $error) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => $error->getMessage(),
        ]);
        exit;
    } catch (Throwable $error) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Database error while updating coordinate.',
            'error' => $error->getMessage(),
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Coordinate updated.',
        'coordinate' => $updatedCoordinate,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $requestData = getRequestData();
    validateDeveloperKey($requestData);

    $id = getRequestId($requestData);

    if (!$id) {
        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'Coordinate ID is required.',
        ]);
        exit;
    }

    try {
        $connection = getDatabaseConnection();
        deleteCoordinate($connection, $id);
        $connection = null;
    } catch (CoordinateNotFoundException $error) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => $error->getMessage(),
        ]);
        exit;
    } catch (Throwable $error) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Database error while deleting coordinate.',
            'error' => $error->getMessage(),
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Coordinate deleted.',
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Only GET, POST, PUT, PATCH, and DELETE requests are allowed.',
    ]);
    exit;
}

try {
    $connection = getDatabaseConnection();
    $coordinates = readCoordinates($connection);
    $connection = null;

    echo json_encode([
        'database' => $databaseStatus,
        'coordinates' => $coordinates,
    ]);
} catch (Throwable $error) {
    http_response_code(500);

    echo json_encode([
        'database' => $databaseStatus,
        'coordinates' => [],
        'success' => false,
        'message' => 'Database error while loading coordinates.',
        'error' => $error->getMessage(),
    ]);
}
