<?php
/**
 * Webcat Authentication Endpoint
 *
 * This API allows Webcat to authenticate users against the YFEvents user table.
 * Only users with is_webcat_user=1 (staff, vendors, or explicitly granted) can log in.
 *
 * POST /api/auth/webcat-login.php
 * Body: { "email": "...", "password": "..." }
 *
 * Returns: { "success": true, "user": {...}, "token": "..." }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['email']) || empty($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email and password are required']);
    exit;
}

$email = trim($input['email']);
$password = $input['password'];

try {
    // Load database config
    $dbConfig = require __DIR__ . '/../../../../config/database.php';

    // Connect to database
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    // Find user by email with webcat access
    $stmt = $pdo->prepare("
        SELECT id, username, email, password_hash, first_name, last_name,
               is_seller, is_yf_vendor, is_yf_staff, is_yf_associate, is_webcat_user,
               seller_id, shop_id, status
        FROM users
        WHERE email = :email
        AND status = 'active'
        AND (is_webcat_user = 1 OR is_yf_staff = 1 OR is_yf_vendor = 1)
        LIMIT 1
    ");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials or no Webcat access']);
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        exit;
    }

    // Determine Webcat role based on YFEvents flags
    $webcatRole = 'Vendor'; // Default
    if ($user['is_yf_staff']) {
        $webcatRole = 'Admin';
    } elseif ($user['is_yf_associate']) {
        $webcatRole = 'Staff';
    }

    // Generate a simple auth token (in production, use JWT)
    $token = bin2hex(random_bytes(32));
    $tokenExpiry = date('Y-m-d H:i:s', strtotime('+7 days'));

    // Store token in database (optional - for token validation)
    $stmt = $pdo->prepare("
        UPDATE users
        SET verification_token = :token,
            last_login = NOW()
        WHERE id = :id
    ");
    $stmt->execute(['token' => $token, 'id' => $user['id']]);

    // Return user data for Webcat
    $response = [
        'success' => true,
        'user' => [
            'id' => (int)$user['id'],
            'yfevents_id' => (int)$user['id'], // For reference
            'name' => trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['username'],
            'email' => $user['email'],
            'user_type' => $webcatRole,
            'is_active' => true,
            'is_yf_staff' => (bool)$user['is_yf_staff'],
            'is_yf_vendor' => (bool)$user['is_yf_vendor'],
            'seller_id' => $user['seller_id'],
            'shop_id' => $user['shop_id'],
        ],
        'token' => $token,
        'expires_at' => $tokenExpiry
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    error_log('Webcat auth error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
