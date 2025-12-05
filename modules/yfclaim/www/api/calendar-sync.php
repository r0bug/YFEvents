<?php
/**
 * Calendar Sync API
 * Syncs estate sales with the main events calendar
 */

require_once __DIR__ . '/../bootstrap.php';

use YFEvents\Modules\YFClaim\Services\CalendarIntegration;

header('Content-Type: application/json');

// Only allow POST for sync operations
$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

$calendarSync = new CalendarIntegration($pdo);

switch ($action) {
    case 'sync':
        // Sync a specific sale
        $saleId = (int)($_POST['sale_id'] ?? 0);
        if (!$saleId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'sale_id required']);
            exit;
        }

        $eventId = $calendarSync->syncSaleToCalendar($saleId);
        echo json_encode([
            'success' => true,
            'sale_id' => $saleId,
            'event_id' => $eventId
        ]);
        break;

    case 'sync_all':
        // Sync all active sales
        $results = $calendarSync->syncAllActiveSales();
        echo json_encode([
            'success' => true,
            'synced' => count($results),
            'results' => $results
        ]);
        break;

    case 'status':
    default:
        // Get sync status
        $stmt = $pdo->query("
            SELECT
                COUNT(*) as total_active_sales,
                SUM(CASE WHEN event_id IS NOT NULL THEN 1 ELSE 0 END) as synced_sales
            FROM yfc_sales
            WHERE status = 'active'
        ");
        $status = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'status' => $status
        ]);
        break;
}
