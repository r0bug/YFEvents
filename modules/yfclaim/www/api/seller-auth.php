<?php
// Load dependencies
require_once dirname(__DIR__) . '/bootstrap.php';

use YFEvents\Modules\YFClaim\Services\ClaimAuthService;

// Initialize auth service
$authService = new ClaimAuthService($pdo);

// Handle login request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Username and password are required']);
        exit;
    }
    
    try {
        $result = $authService->authenticateSeller($username, $password);
        
        if ($result['success']) {
            // Set session variables for claim seller
            $_SESSION['claim_seller_logged_in'] = true;
            $_SESSION['claim_seller_id'] = $result['seller']['id'];
            $_SESSION['claim_auth_user_id'] = $result['auth_user']['id'];
            $_SESSION['claim_session_id'] = $result['session_id'];
            $_SESSION['seller_email'] = $result['seller']['email'];
            $_SESSION['seller_name'] = $result['seller']['contact_name'];
            $_SESSION['company_name'] = $result['seller']['company_name'];
            $_SESSION['login_time'] = time();
            
            echo json_encode([
                'success' => true,
                'seller' => $result['seller'],
                'redirect' => '/seller/'
            ]);
        } else {
            echo json_encode($result);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'An error occurred during authentication'
        ]);
    }
    
    exit;
}

// Handle logout request
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || (isset($_GET['action']) && $_GET['action'] === 'logout')) {
    // Clear claim seller session
    unset($_SESSION['claim_seller_logged_in']);
    unset($_SESSION['claim_seller_id']);
    unset($_SESSION['claim_auth_user_id']);
    unset($_SESSION['claim_session_id']);
    unset($_SESSION['seller_email']);
    unset($_SESSION['seller_name']);
    unset($_SESSION['company_name']);
    
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Location: /claims/admin/login.php');
    }
    exit;
}

// Handle session validation (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'validate') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['claim_session_id'])) {
        echo json_encode(['valid' => false]);
        exit;
    }
    
    try {
        $session = $authService->validateSellerSession($_SESSION['claim_session_id']);
        
        if ($session) {
            echo json_encode([
                'valid' => true,
                'seller' => $session['seller'],
                'auth_user' => $session['auth_user']
            ]);
        } else {
            // Clear invalid session
            unset($_SESSION['claim_seller_logged_in']);
            unset($_SESSION['claim_seller_id']);
            unset($_SESSION['claim_session_id']);
            
            echo json_encode(['valid' => false]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['valid' => false, 'error' => $e->getMessage()]);
    }
    
    exit;
}