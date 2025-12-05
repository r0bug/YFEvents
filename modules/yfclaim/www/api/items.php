<?php
/**
 * YFClaim Items API
 * Provides item data for public viewing
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once dirname(__DIR__) . '/bootstrap.php';

use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\OfferModel;

// Initialize models
$itemModel = new ItemModel($pdo);
$saleModel = new SaleModel($pdo);
$offerModel = new OfferModel($pdo);

// Helper function to send JSON response
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Handle different actions
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_sale_items':
            // Get all items for a sale
            $saleId = intval($_GET['sale_id'] ?? 0);
            
            if (!$saleId) {
                sendResponse(['success' => false, 'message' => 'Sale ID required'], 400);
            }
            
            // Validate sale exists
            $sale = $saleModel->find($saleId);
            if (!$sale) {
                sendResponse(['success' => false, 'message' => 'Sale not found'], 404);
            }
            
            // Get items with primary images
            $items = $itemModel->getWithPrimaryImages($saleId);
            
            // Add offer statistics to each item
            foreach ($items as &$item) {
                $offerStats = $offerModel->getItemStats($item['id']);
                $item['offer_count'] = $offerStats['total_offers'] ?? 0;
                $item['current_high_offer'] = $offerStats['max_offer'] ?? null;
                $item['unique_bidders'] = $offerStats['unique_buyers'] ?? 0;
            }
            
            sendResponse([
                'success' => true,
                'items' => $items,
                'sale' => $sale
            ]);
            break;
            
        case 'get_item':
            // Get detailed item information
            $itemId = intval($_GET['item_id'] ?? 0);
            
            if (!$itemId) {
                sendResponse(['success' => false, 'message' => 'Item ID required'], 400);
            }
            
            // Get item with images
            $item = $itemModel->getWithImages($itemId);
            if (!$item) {
                sendResponse(['success' => false, 'message' => 'Item not found'], 404);
            }
            
            // Get offer statistics
            $offerStats = $offerModel->getItemStats($itemId);
            $highestOffer = $offerModel->getHighest($itemId);
            
            // Get sale information
            $sale = $saleModel->find($item['sale_id']);
            
            sendResponse([
                'success' => true,
                'item' => $item,
                'offer_stats' => $offerStats,
                'highest_offer' => $highestOffer,
                'sale' => $sale
            ]);
            break;
            
        case 'search':
            // Search items
            $query = $_GET['query'] ?? '';
            $saleId = intval($_GET['sale_id'] ?? 0);
            
            if (strlen($query) < 2) {
                sendResponse(['success' => false, 'message' => 'Search query must be at least 2 characters'], 400);
            }
            
            $items = $itemModel->search($query, $saleId ?: null);
            
            // Add offer statistics
            foreach ($items as &$item) {
                $offerStats = $offerModel->getItemStats($item['id']);
                $item['offer_count'] = $offerStats['total_offers'] ?? 0;
                $item['current_high_offer'] = $offerStats['max_offer'] ?? null;
            }
            
            sendResponse([
                'success' => true,
                'items' => $items,
                'query' => $query
            ]);
            break;
            
        case 'get_categories':
            // Get items by category
            $category = $_GET['category'] ?? '';
            $saleId = intval($_GET['sale_id'] ?? 0);
            
            if (!$category) {
                sendResponse(['success' => false, 'message' => 'Category required'], 400);
            }
            
            $items = $itemModel->getByCategory($category, $saleId ?: null);
            
            // Add offer statistics
            foreach ($items as &$item) {
                $offerStats = $offerModel->getItemStats($item['id']);
                $item['offer_count'] = $offerStats['total_offers'] ?? 0;
                $item['current_high_offer'] = $offerStats['max_offer'] ?? null;
            }
            
            sendResponse([
                'success' => true,
                'items' => $items,
                'category' => $category
            ]);
            break;
            
        case 'get_featured':
            // Get featured items
            $saleId = intval($_GET['sale_id'] ?? 0);
            
            $conditions = ['featured' => 1, 'status' => 'available'];
            if ($saleId) {
                $conditions['sale_id'] = $saleId;
            }
            
            $items = $itemModel->all($conditions, 'sort_order ASC, created_at DESC', 20);
            
            // Add offer statistics
            foreach ($items as &$item) {
                $offerStats = $offerModel->getItemStats($item['id']);
                $item['offer_count'] = $offerStats['total_offers'] ?? 0;
                $item['current_high_offer'] = $offerStats['max_offer'] ?? null;
            }
            
            sendResponse([
                'success' => true,
                'items' => $items
            ]);
            break;
            
        case 'get_offer_history':
            // Get offer history for an item (public view - anonymized)
            $itemId = intval($_GET['item_id'] ?? 0);
            
            if (!$itemId) {
                sendResponse(['success' => false, 'message' => 'Item ID required'], 400);
            }
            
            // Get anonymized offer history
            $sql = "
                SELECT 
                    offer_amount,
                    action,
                    created_at,
                    CONCAT('Bidder ', 
                        CHAR(65 + (buyer_id % 26))
                    ) as anonymous_buyer
                FROM yfc_offer_history 
                WHERE item_id = ? 
                ORDER BY created_at DESC 
                LIMIT 50
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$itemId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendResponse([
                'success' => true,
                'history' => $history
            ]);
            break;
            
        default:
            sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    error_log("YFClaim Items API Error: " . $e->getMessage());
    sendResponse([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ], 500);
}