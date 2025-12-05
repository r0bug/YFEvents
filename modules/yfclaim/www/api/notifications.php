<?php
/**
 * Notifications API
 */

require_once __DIR__ . '/../bootstrap.php';

use YFEvents\Modules\YFClaim\Services\NotificationService;

header('Content-Type: application/json');

$notificationService = new NotificationService($pdo);

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

// Determine user type and ID from session
$userType = null;
$userId = null;

if (isset($_SESSION['claim_seller_id'])) {
    $userType = 'seller';
    $userId = $_SESSION['claim_seller_id'];
} elseif (isset($_SESSION['buyer_token'])) {
    // Get buyer from token
    $stmt = $pdo->prepare("SELECT id FROM yfc_buyers WHERE session_token = ?");
    $stmt->execute([$_SESSION['buyer_token']]);
    $buyer = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($buyer) {
        $userType = 'buyer';
        $userId = $buyer['id'];
    }
}

if (!$userType || !$userId) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

switch ($action) {
    case 'get':
        $limit = (int)($_GET['limit'] ?? 10);
        $notifications = $notificationService->getUnread($userType, $userId, $limit);
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'count' => count($notifications)
        ]);
        break;

    case 'mark_read':
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        if ($notificationId) {
            $notificationService->markRead($notificationId);
        }
        echo json_encode(['success' => true]);
        break;

    case 'mark_all_read':
        $notificationService->markAllRead($userType, $userId);
        echo json_encode(['success' => true]);
        break;

    case 'preferences':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $emailOffers = isset($_POST['email_offers']) ? 1 : 0;
            $emailUpdates = isset($_POST['email_updates']) ? 1 : 0;

            $stmt = $pdo->prepare("
                INSERT INTO yfc_notification_preferences (user_type, user_id, email_offers, email_updates)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE email_offers = VALUES(email_offers), email_updates = VALUES(email_updates)
            ");
            $stmt->execute([$userType, $userId, $emailOffers, $emailUpdates]);

            echo json_encode(['success' => true]);
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM yfc_notification_preferences WHERE user_type = ? AND user_id = ?
            ");
            $stmt->execute([$userType, $userId]);
            $prefs = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'email_offers' => true,
                'email_updates' => true
            ];
            echo json_encode(['success' => true, 'preferences' => $prefs]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
