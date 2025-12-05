<?php
// API endpoint for shops
$dbConfig = require '../../../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    $query = "SELECT * FROM local_shops WHERE status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $shops = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse JSON fields
    foreach ($shops as &$shop) {
        if (isset($shop['hours']) && is_string($shop['hours'])) {
            $shop['hours'] = json_decode($shop['hours'], true);
        }
        if (isset($shop['operating_hours']) && is_string($shop['operating_hours'])) {
            $shop['operating_hours'] = json_decode($shop['operating_hours'], true);
        }
    }
    
    echo json_encode(['shops' => $shops]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load shops']);
}
?>