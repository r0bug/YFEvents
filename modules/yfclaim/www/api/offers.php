<?php
/**
 * YFClaim Offers API
 * Handles buyer offers on items
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once dirname(__DIR__) . '/bootstrap.php';

use YFEvents\Modules\YFClaim\Models\BuyerModel;
use YFEvents\Modules\YFClaim\Models\OfferModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;

// Initialize models
$buyerModel = new BuyerModel($pdo);
$offerModel = new OfferModel($pdo);
$itemModel = new ItemModel($pdo);
$saleModel = new SaleModel($pdo);

// Helper function to send JSON response
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Helper function to validate input
function validateInput($data, $required = []) {
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            sendResponse(['success' => false, 'message' => "Field '$field' is required"], 400);
        }
    }
}

// Helper function to get authenticated buyer
function getAuthenticatedBuyer($buyerModel) {
    session_start();
    $sessionToken = $_SESSION['buyer_token'] ?? '';
    
    if (!$sessionToken) {
        sendResponse(['success' => false, 'message' => 'Authentication required'], 401);
    }
    
    $buyer = $buyerModel->validateSession($sessionToken);
    if (!$buyer) {
        sendResponse(['success' => false, 'message' => 'Invalid session'], 401);
    }
    
    return $buyer;
}

// Handle different actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            // Create new offer
            $buyer = getAuthenticatedBuyer($buyerModel);
            validateInput($_POST, ['item_id', 'offer_amount']);
            
            $itemId = intval($_POST['item_id']);
            $offerAmount = floatval($_POST['offer_amount']);
            $maxOffer = floatval($_POST['max_offer'] ?? 0);
            
            // Validate offer amount
            if ($offerAmount <= 0) {
                sendResponse(['success' => false, 'message' => 'Offer amount must be greater than zero'], 400);
            }
            
            // Get item and validate
            $item = $itemModel->find($itemId);
            if (!$item || $item['status'] !== 'available') {
                sendResponse(['success' => false, 'message' => 'Item not available for offers'], 400);
            }
            
            // Validate sale is active
            $sale = $saleModel->find($item['sale_id']);
            if (!$sale || !$saleModel->isActive($item['sale_id'])) {
                sendResponse(['success' => false, 'message' => 'Sale is not currently active'], 400);
            }
            
            // Validate buyer is for this sale
            if ($buyer['sale_id'] != $item['sale_id']) {
                sendResponse(['success' => false, 'message' => 'You are not registered for this sale'], 400);
            }
            
            // Check if buyer already has an offer on this item
            $existingOffer = $offerModel->getBuyerOffer($itemId, $buyer['id']);
            
            if ($existingOffer) {
                // Update existing offer if new amount is higher
                if ($offerAmount > $existingOffer['offer_amount']) {
                    $offerModel->updateAmount($existingOffer['id'], $offerAmount);
                    sendResponse([
                        'success' => true,
                        'message' => 'Offer updated successfully',
                        'offer_id' => $existingOffer['id'],
                        'offer_amount' => $offerAmount
                    ]);
                } else {
                    sendResponse(['success' => false, 'message' => 'New offer must be higher than your current offer'], 400);
                }
            } else {
                // Create new offer
                $offerData = [
                    'item_id' => $itemId,
                    'buyer_id' => $buyer['id'],
                    'offer_amount' => $offerAmount,
                    'max_offer' => $maxOffer,
                    'status' => 'active',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ];
                
                $offerId = $offerModel->createOffer($offerData);
                
                sendResponse([
                    'success' => true,
                    'message' => 'Offer submitted successfully',
                    'offer_id' => $offerId,
                    'offer_amount' => $offerAmount
                ]);
            }
            break;
            
        case 'get_item_offers':
            // Get offers for an item (public view)
            validateInput($_GET, ['item_id']);
            
            $itemId = intval($_GET['item_id']);
            $stats = $offerModel->getItemStats($itemId);
            $highest = $offerModel->getHighest($itemId);
            
            sendResponse([
                'success' => true,
                'stats' => $stats,
                'highest_offer' => $highest
            ]);
            break;
            
        case 'get_my_offers':
            // Get buyer's offers
            $buyer = getAuthenticatedBuyer($buyerModel);
            $status = $_GET['status'] ?? null;
            
            $offers = $buyerModel->getOffers($buyer['id'], $status);
            $stats = $buyerModel->getStats($buyer['id']);
            
            sendResponse([
                'success' => true,
                'offers' => $offers,
                'stats' => $stats
            ]);
            break;
            
        case 'get_offer':
            // Get specific offer details
            $buyer = getAuthenticatedBuyer($buyerModel);
            validateInput($_GET, ['offer_id']);
            
            $offerId = intval($_GET['offer_id']);
            $offer = $offerModel->find($offerId);
            
            if (!$offer || $offer['buyer_id'] != $buyer['id']) {
                sendResponse(['success' => false, 'message' => 'Offer not found'], 404);
            }
            
            // Get item details
            $item = $itemModel->find($offer['item_id']);
            $offer['item'] = $item;
            
            sendResponse([
                'success' => true,
                'offer' => $offer
            ]);
            break;
            
        case 'update':
            // Update offer amount
            $buyer = getAuthenticatedBuyer($buyerModel);
            validateInput($_POST, ['offer_id', 'offer_amount']);
            
            $offerId = intval($_POST['offer_id']);
            $newAmount = floatval($_POST['offer_amount']);
            
            // Validate offer belongs to buyer
            $offer = $offerModel->find($offerId);
            if (!$offer || $offer['buyer_id'] != $buyer['id']) {
                sendResponse(['success' => false, 'message' => 'Offer not found'], 404);
            }
            
            // Validate new amount is higher
            if ($newAmount <= $offer['offer_amount']) {
                sendResponse(['success' => false, 'message' => 'New offer must be higher than current offer'], 400);
            }
            
            // Validate item is still available
            $item = $itemModel->find($offer['item_id']);
            if (!$item || $item['status'] !== 'available') {
                sendResponse(['success' => false, 'message' => 'Item is no longer available'], 400);
            }
            
            // Update offer
            $offerModel->updateAmount($offerId, $newAmount);
            
            sendResponse([
                'success' => true,
                'message' => 'Offer updated successfully',
                'offer_amount' => $newAmount
            ]);
            break;
            
        case 'cancel':
            // Cancel offer (mark as cancelled)
            $buyer = getAuthenticatedBuyer($buyerModel);
            validateInput($_POST, ['offer_id']);
            
            $offerId = intval($_POST['offer_id']);
            
            // Validate offer belongs to buyer
            $offer = $offerModel->find($offerId);
            if (!$offer || $offer['buyer_id'] != $buyer['id']) {
                sendResponse(['success' => false, 'message' => 'Offer not found'], 404);
            }
            
            // Can only cancel active offers
            if ($offer['status'] !== 'active') {
                sendResponse(['success' => false, 'message' => 'Can only cancel active offers'], 400);
            }
            
            // Update offer status
            $offerModel->update($offerId, ['status' => 'cancelled']);
            
            sendResponse([
                'success' => true,
                'message' => 'Offer cancelled successfully'
            ]);
            break;
            
        default:
            sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    error_log("YFClaim Offers API Error: " . $e->getMessage());
    sendResponse([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ], 500);
}