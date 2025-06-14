<?php
// Simple events API endpoint for testing
require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Get date range from query parameters (support both formats)
    $startDate = $_GET['start_date'] ?? $_GET['start'] ?? date('Y-m-01'); // Default to start of current month
    $endDate = $_GET['end_date'] ?? $_GET['end'] ?? date('Y-m-t'); // Default to end of current month
    
    // Check if this is an admin request (keep past events for admin)
    $isAdmin = isset($_GET['admin']) && $_GET['admin'] === 'true';
    
    // Simple query to get approved events with source information
    $sql = "SELECT 
                e.id,
                e.title,
                e.description,
                e.start_datetime,
                e.end_datetime,
                e.location,
                e.address,
                e.latitude,
                e.longitude,
                e.status,
                e.external_url,
                cs.name as source_name,
                cs.url as source_url
            FROM events e
            LEFT JOIN calendar_sources cs ON e.source_id = cs.id
            WHERE e.status = 'approved' 
            AND e.start_datetime >= :start_date 
            AND e.start_datetime <= :end_date";
    
    // Only filter past events if explicitly requested
    if (isset($_GET['future_only']) && $_GET['future_only'] === 'true') {
        $sql .= " AND e.start_datetime >= :current_date";
    }
    
    $sql .= " ORDER BY e.start_datetime ASC";
    
    $stmt = $pdo->prepare($sql);
    $params = [
        'start_date' => $startDate . ' 00:00:00',
        'end_date' => $endDate . ' 23:59:59'
    ];
    
    // Add current date filter if future_only is requested
    if (isset($_GET['future_only']) && $_GET['future_only'] === 'true') {
        $params['current_date'] = date('Y-m-d H:i:s');
    }
    
    $stmt->execute($params);
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dates for JavaScript
    foreach ($events as &$event) {
        $event['start'] = $event['start_datetime'];
        $event['end'] = $event['end_datetime'] ?: $event['start_datetime'];
    }
    
    echo json_encode([
        'success' => true,
        'events' => $events,
        'count' => count($events)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load events',
        'message' => $e->getMessage()
    ]);
}
?>