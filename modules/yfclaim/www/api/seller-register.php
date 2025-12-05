<?php
// Seller Registration API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
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
    
    // Get form data
    $contactName = $_POST['contact_name'] ?? '';
    $companyName = $_POST['company_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $website = $_POST['website'] ?? '';
    $address = $_POST['address'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validate required fields
    if (empty($contactName) || empty($companyName) || empty($email) || empty($phone) || empty($username) || empty($password)) {
        sendResponse(['error' => 'All required fields must be filled'], 400);
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(['error' => 'Invalid email address'], 400);
    }
    
    // Validate password length
    if (strlen($password) < 6) {
        sendResponse(['error' => 'Password must be at least 6 characters'], 400);
    }
    
    // Check if email already exists
    $existing = $sellerModel->findByEmail($email);
    if ($existing) {
        sendResponse(['error' => 'Email address already registered'], 400);
    }
    
    // Check if username already exists
    $existing = $sellerModel->findByUsername($username);
    if ($existing) {
        sendResponse(['error' => 'Username already taken'], 400);
    }
    
    // Create seller account
    $sellerData = [
        'contact_name' => $contactName,
        'company_name' => $companyName,
        'email' => $email,
        'phone' => $phone,
        'website' => $website ?: null,
        'address' => $address ?: null,
        'username' => $username,
        'password' => $password, // createSeller will hash this
        'status' => 'active',
        'email_verified' => 0,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $sellerId = $sellerModel->createSeller($sellerData);
    
    if ($sellerId) {
        sendResponse([
            'success' => true,
            'message' => 'Account created successfully',
            'seller_id' => $sellerId
        ]);
    } else {
        sendResponse(['error' => 'Failed to create account'], 500);
    }
    
} catch (Exception $e) {
    error_log("Seller registration error: " . $e->getMessage());
    sendResponse(['error' => 'Registration failed. Please try again.'], 500);
}
?>