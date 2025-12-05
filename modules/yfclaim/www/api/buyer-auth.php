<?php
/**
 * YFClaim Buyer Authentication API
 * Handles buyer registration, verification, and authentication
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
use YFEvents\Modules\YFClaim\Models\SaleModel;

// Initialize models
$buyerModel = new BuyerModel($pdo);
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

// Handle different actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'register':
            // Register buyer for a sale
            validateInput($_POST, ['sale_id', 'name', 'contact', 'auth_method']);
            
            $saleId = intval($_POST['sale_id']);
            $name = trim($_POST['name']);
            $contact = trim($_POST['contact']);
            $authMethod = $_POST['auth_method']; // 'email' or 'phone'
            
            // Validate sale exists and is active
            $sale = $saleModel->find($saleId);
            if (!$sale || $sale['status'] !== 'active') {
                sendResponse(['success' => false, 'message' => 'Sale not found or not active'], 404);
            }
            
            // Check if claim period is active
            $now = date('Y-m-d H:i:s');
            if ($now < $sale['claim_start'] || $now > $sale['claim_end']) {
                sendResponse(['success' => false, 'message' => 'Claim period is not currently active'], 400);
            }
            
            // Validate contact format
            if ($authMethod === 'email') {
                if (!filter_var($contact, FILTER_VALIDATE_EMAIL)) {
                    sendResponse(['success' => false, 'message' => 'Invalid email address'], 400);
                }
            } else {
                // Basic phone validation
                $contact = preg_replace('/[^0-9]/', '', $contact);
                if (strlen($contact) !== 10) {
                    sendResponse(['success' => false, 'message' => 'Invalid phone number. Please enter 10 digits.'], 400);
                }
            }
            
            // Check if buyer already exists for this sale
            $existingBuyer = $buyerModel->findByContact($saleId, $contact, $authMethod);
            
            if ($existingBuyer) {
                // Resend auth code
                $authInfo = $buyerModel->resendAuthCode($existingBuyer['id']);
                sendResponse([
                    'success' => true,
                    'message' => 'Verification code resent',
                    'buyer_id' => $existingBuyer['id'],
                    'auth_method' => $authMethod,
                    'contact' => $contact
                ]);
            } else {
                // Create new buyer
                $authInfo = $buyerModel->createWithAuth($saleId, $name, $contact, $authMethod);
                sendResponse([
                    'success' => true,
                    'message' => 'Verification code sent',
                    'buyer_id' => $authInfo['buyer_id'],
                    'auth_method' => $authMethod,
                    'contact' => $contact
                ]);
            }
            break;
            
        case 'verify':
            // Verify authentication code
            validateInput($_POST, ['buyer_id', 'auth_code']);
            
            $buyerId = intval($_POST['buyer_id']);
            $authCode = trim($_POST['auth_code']);
            
            $result = $buyerModel->verifyAuthCode($buyerId, $authCode);
            
            if ($result) {
                // Start session
                session_start();
                $_SESSION['buyer_token'] = $result['session_token'];
                $_SESSION['buyer_id'] = $buyerId;
                
                sendResponse([
                    'success' => true,
                    'message' => 'Authentication successful',
                    'session_token' => $result['session_token'],
                    'buyer' => $result['buyer']
                ]);
            } else {
                sendResponse(['success' => false, 'message' => 'Invalid or expired verification code'], 400);
            }
            break;
            
        case 'resend':
            // Resend verification code
            validateInput($_POST, ['buyer_id']);
            
            $buyerId = intval($_POST['buyer_id']);
            $authInfo = $buyerModel->resendAuthCode($buyerId);
            
            if ($authInfo) {
                sendResponse([
                    'success' => true,
                    'message' => 'Verification code resent',
                    'contact' => $authInfo['contact']
                ]);
            } else {
                sendResponse(['success' => false, 'message' => 'Buyer not found'], 404);
            }
            break;
            
        case 'validate_session':
            // Validate current session
            $sessionToken = $_POST['session_token'] ?? $_SESSION['buyer_token'] ?? '';
            
            if ($sessionToken) {
                $buyer = $buyerModel->validateSession($sessionToken);
                if ($buyer) {
                    sendResponse([
                        'success' => true,
                        'buyer' => $buyer
                    ]);
                }
            }
            
            sendResponse(['success' => false, 'message' => 'Invalid session'], 401);
            break;
            
        case 'logout':
            // Logout buyer
            session_start();
            
            if (isset($_SESSION['buyer_id'])) {
                $buyerModel->logout($_SESSION['buyer_id']);
            }
            
            session_destroy();
            
            sendResponse([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
            break;
            
        default:
            sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    error_log("YFClaim Buyer Auth Error: " . $e->getMessage());
    sendResponse([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ], 500);
}