<?php
/**
 * Simple Events API endpoint
 */

$dbConfig = require __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    // Get date range from query params
    $startDate = $_GET['start_date'] ?? date('Y-m-d');
    $endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'));

    // Fetch approved events within date range
    $query = "SELECT id, title, description, start_datetime, end_datetime,
                     location, address, latitude, longitude, contact_info,
                     external_url, source_id, featured
              FROM events
              WHERE status = 'approved'
              AND DATE(start_datetime) >= :start_date
              AND DATE(start_datetime) <= :end_date
              ORDER BY start_datetime ASC";

    $stmt = $db->prepare($query);
    $stmt->execute([
        'start_date' => $startDate,
        'end_date' => $endDate
    ]);

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format events for calendar
    $formattedEvents = array_map(function($event) {
        return [
            'id' => (int)$event['id'],
            'title' => $event['title'],
            'description' => $event['description'] ?? '',
            'start_datetime' => $event['start_datetime'],
            'end_datetime' => $event['end_datetime'],
            'location' => $event['location'] ?? '',
            'address' => $event['address'] ?? '',
            'latitude' => $event['latitude'] ? (float)$event['latitude'] : null,
            'longitude' => $event['longitude'] ? (float)$event['longitude'] : null,
            'contact_info' => $event['contact_info'] ?? '',
            'external_url' => $event['external_url'] ?? '',
            'featured' => (bool)$event['featured']
        ];
    }, $events);

    echo json_encode([
        'success' => true,
        'events' => $formattedEvents,
        'count' => count($formattedEvents)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load events: ' . $e->getMessage()
    ]);
}
