<?php
session_start();

// Seller Statistics API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if seller is logged in
if (!isset($_SESSION['claim_seller_logged_in']) || $_SESSION['claim_seller_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once dirname(__DIR__) . '/bootstrap.php';

use YFEvents\Modules\YFClaim\Models\SellerModel;

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

try {
    $sellerModel = new SellerModel($pdo);
    $sellerId = $_SESSION['claim_seller_id'];
    
    // Get seller statistics
    $stats = $sellerModel->getStats($sellerId);
    
    sendResponse([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    error_log("Seller stats error: " . $e->getMessage());
    sendResponse(['error' => 'Failed to get statistics'], 500);
}
?>