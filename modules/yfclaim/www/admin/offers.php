<?php
// YFClaim Offers Management
require_once __DIR__ . '/bootstrap.php';

// Use proper namespaces
use YFEvents\Modules\YFClaim\Models\OfferModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\BuyerModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\SellerModel;

// Authentication check
session_start();

// Check if logged in through main admin OR temporary bypass
$isLoggedIn = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) ||
              (isset($_GET['admin_bypass']) && $_GET['admin_bypass'] === 'YakFind2025');

if (!$isLoggedIn) {
    // Instead of redirecting to a potentially wrong path, show a helpful message
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - YFClaim Admin</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
            .error-container { background: white; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .error-title { color: #dc3545; font-size: 24px; margin-bottom: 20px; }
            .error-message { color: #666; line-height: 1.6; margin-bottom: 20px; }
            .login-button { background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
            .login-button:hover { background: #0056b3; }
            .help-text { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-title">🔒 Admin Access Required</div>
            <div class="error-message">
                <p>You need to be logged in as an administrator to access the YFClaim admin panel.</p>
                <p>Please log in to the main admin system first, then return to this page.</p>
            </div>
            
            <a href="../../../www/html/admin/login.php" class="login-button">Go to Main Admin Login</a>
            
            <div class="help-text">
                <strong>Login Credentials:</strong><br>
                Username: <code>YakFind</code><br>
                Password: <code>MapTime</code>
            </div>
            
            <div class="help-text">
                <strong>Troubleshooting:</strong><br>
                If the login link above doesn't work, try these alternatives:
                <ul>
                    <li><a href="/admin/login.php">Alternative login path 1</a></li>
                    <li><a href="/www/html/admin/login.php">Alternative login path 2</a></li>
                </ul>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$isAdmin = true;

// Initialize models
$offerModel = new OfferModel($pdo);
$itemModel = new ItemModel($pdo);
$buyerModel = new BuyerModel($pdo);
$saleModel = new SaleModel($pdo);
$sellerModel = new SellerModel($pdo);

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'accept_offer':
                    $offerId = $_POST['offer_id'];
                    $sellerNotes = $_POST['seller_notes'] ?? null;
                    $offerModel->acceptOffer($offerId, $sellerNotes);
                    $message = "Offer accepted successfully!";
                    break;
                    
                case 'reject_offer':
                    $offerId = $_POST['offer_id'];
                    $sellerNotes = $_POST['seller_notes'] ?? null;
                    $offerModel->rejectOffer($offerId, $sellerNotes);
                    $message = "Offer rejected!";
                    break;
                    
                case 'update_offer_status':
                    $offerId = $_POST['offer_id'];
                    $status = $_POST['status'];
                    $sellerNotes = $_POST['seller_notes'] ?? null;
                    
                    if ($status === 'winning') {
                        $offerModel->acceptOffer($offerId, $sellerNotes);
                    } elseif ($status === 'rejected') {
                        $offerModel->rejectOffer($offerId, $sellerNotes);
                    } else {
                        $updateData = ['status' => $status];
                        if ($sellerNotes) {
                            $updateData['seller_notes'] = $sellerNotes;
                        }
                        $offerModel->updateOffer($offerId, $updateData);
                    }
                    $message = "Offer status updated!";
                    break;
                    
                case 'delete_offer':
                    $offerModel->deleteOffer($_POST['offer_id']);
                    $message = "Offer deleted!";
                    break;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Build query for filtered offers
$query = "SELECT o.*, 
          i.title as item_title, i.price as item_price, i.status as item_status, i.item_number,
          s.title as sale_title, s.id as sale_id,
          b.name as buyer_name, b.email as buyer_email, b.phone as buyer_phone,
          sel.name as seller_name, sel.id as seller_id
          FROM yfc_offers o
          LEFT JOIN yfc_items i ON o.item_id = i.id
          LEFT JOIN yfc_sales s ON i.sale_id = s.id
          LEFT JOIN yfc_buyers b ON o.buyer_id = b.id
          LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
          WHERE 1=1";
$params = [];

if ($status) {
    $query .= " AND o.status = ?";
    $params[] = $status;
}

if ($search) {
    $query .= " AND (i.title LIKE ? OR b.name LIKE ? OR s.title LIKE ? OR i.item_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Count total for pagination
$countQuery = str_replace("SELECT o.*, i.title as item_title, i.price as item_price, i.status as item_status, i.item_number, s.title as sale_title, s.id as sale_id, b.name as buyer_name, b.email as buyer_email, b.phone as buyer_phone, sel.name as seller_name, sel.id as seller_id", "SELECT COUNT(*)", $query);
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalOffers = $stmt->fetchColumn();
$totalPages = ceil($totalOffers / $perPage);

// Get paginated results
$query .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$offers = $stmt->fetchAll();

// Get statistics using model methods
$stats = [];
$stats['total_offers'] = $offerModel->count();
$stats['pending_offers'] = $offerModel->count(['status' => 'active']);
$stats['accepted_offers'] = $offerModel->count(['status' => 'winning']);
$stats['rejected_offers'] = $offerModel->count(['status' => 'rejected']);

// Get offer details if viewing single offer
$viewOffer = null;
$offerHistory = [];
if (isset($_GET['view']) && $_GET['view']) {
    $viewOffer = $offerModel->getOfferById($_GET['view']);
    if ($viewOffer) {
        // Get full details
        $viewOffer['item'] = $itemModel->find($viewOffer['item_id']);
        $viewOffer['buyer'] = $buyerModel->find($viewOffer['buyer_id']);
        if ($viewOffer['item']) {
            $viewOffer['sale'] = $saleModel->find($viewOffer['item']['sale_id']);
            if ($viewOffer['sale']) {
                $viewOffer['seller'] = $sellerModel->find($viewOffer['sale']['seller_id']);
            }
            // Get offer history for this item
            $offerHistory = $offerModel->getHistory($viewOffer['item_id']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Offers - YFClaim Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }
        .header {
            background: #333;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
        }
        .header-nav {
            display: flex;
            gap: 1rem;
        }
        .header-nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
        }
        .header-nav a:hover {
            background: rgba(255,255,255,0.2);
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        .nav {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .nav a {
            color: #007bff;
            text-decoration: none;
            padding: 0.5rem 1rem;
            background: white;
            border-radius: 3px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .nav a:hover, .nav a.active {
            background: #007bff;
            color: white;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: white;
            padding: 1.5rem;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            color: #666;
            font-size: 0.875rem;
            text-transform: uppercase;
        }
        .section {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        .section h2 {
            margin: 0;
            color: #333;
        }
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .filters input, .filters select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 1rem;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        .btn:hover {
            opacity: 0.8;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #666;
            text-transform: uppercase;
            font-size: 0.875rem;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 3px;
            font-size: 0.875rem;
            font-weight: bold;
            display: inline-block;
        }
        .status.active, .status.pending {
            background: #17a2b8;
            color: white;
        }
        .status.winning, .status.accepted {
            background: #28a745;
            color: white;
        }
        .status.rejected {
            background: #dc3545;
            color: white;
        }
        .status.outbid {
            background: #ffc107;
            color: #000;
        }
        .status.expired {
            background: #6c757d;
            color: white;
        }
        .actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
        }
        .price {
            font-weight: bold;
            color: #28a745;
        }
        .offer-info {
            font-size: 0.875rem;
            color: #666;
        }
        .offer-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .detail-box {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 5px;
        }
        .detail-box h3 {
            margin-top: 0;
            color: #333;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-label {
            font-weight: bold;
            color: #666;
        }
        .pagination {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-top: 2rem;
        }
        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            color: #007bff;
            text-decoration: none;
            border-radius: 3px;
        }
        .pagination a:hover {
            background: #007bff;
            color: white;
        }
        .pagination .current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background: white;
            max-width: 500px;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 5px;
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #333;
        }
        .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 3px;
            resize: vertical;
            min-height: 100px;
        }
        .history-timeline {
            position: relative;
            padding-left: 2rem;
        }
        .history-item {
            position: relative;
            padding-bottom: 1.5rem;
            border-left: 2px solid #ddd;
            padding-left: 1.5rem;
        }
        .history-item:last-child {
            border-left: none;
        }
        .history-dot {
            position: absolute;
            left: -0.5rem;
            top: 0;
            width: 1rem;
            height: 1rem;
            background: #007bff;
            border-radius: 50%;
            border: 2px solid white;
        }
        .history-time {
            font-size: 0.875rem;
            color: #666;
            margin-bottom: 0.25rem;
        }
        .history-content {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Manage Offers</h1>
        <div class="header-nav">
            <a href="/claims/admin/">Dashboard</a>
            <a href="/admin/">YFEvents Admin</a>
            <a href="/admin/logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="nav">
            <a href="/claims/admin/index.php">Dashboard</a>
            <a href="/claims/admin/sellers.php">Manage Sellers</a>
            <a href="/claims/admin/sales.php">Manage Sales</a>
            <a href="/claims/admin/offers.php" class="active">Manage Offers</a>
            <a href="/claims/admin/buyers.php">Manage Buyers</a>
            <a href="/claims/admin/reports.php">Reports</a>
        </div>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_offers'] ?></div>
                <div class="stat-label">Total Offers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['pending_offers'] ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['accepted_offers'] ?></div>
                <div class="stat-label">Winning</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['rejected_offers'] ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>
        
        <?php if ($viewOffer): ?>
            <div class="section">
                <div class="section-header">
                    <h2>Offer Details #<?= $viewOffer['id'] ?></h2>
                    <a href="/claims/admin/offers.php" class="btn btn-primary">Back to List</a>
                </div>
                
                <div class="offer-details">
                    <div class="detail-box">
                        <h3>Offer Information</h3>
                        <div class="detail-row">
                            <span class="detail-label">Offer ID:</span>
                            <span><?= $viewOffer['id'] ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Amount:</span>
                            <span class="price">$<?= number_format($viewOffer['offer_amount'], 2) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Max Offer:</span>
                            <span><?= $viewOffer['max_offer'] ? '$' . number_format($viewOffer['max_offer'], 2) : 'N/A' ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Status:</span>
                            <span class="status <?= $viewOffer['status'] ?>"><?= ucfirst($viewOffer['status']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Created:</span>
                            <span><?= date('M d, Y g:i A', strtotime($viewOffer['created_at'])) ?></span>
                        </div>
                        <?php if ($viewOffer['seller_notes']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Seller Notes:</span>
                                <span><?= htmlspecialchars($viewOffer['seller_notes']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="detail-box">
                        <h3>Item Information</h3>
                        <?php if ($viewOffer['item']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Item:</span>
                                <span><?= htmlspecialchars($viewOffer['item']['title']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Item #:</span>
                                <span><?= htmlspecialchars($viewOffer['item']['item_number']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">List Price:</span>
                                <span>$<?= number_format($viewOffer['item']['price'], 2) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Item Status:</span>
                                <span class="status <?= $viewOffer['item']['status'] ?>"><?= ucfirst($viewOffer['item']['status']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="detail-box">
                        <h3>Buyer Information</h3>
                        <?php if ($viewOffer['buyer']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Name:</span>
                                <span><?= htmlspecialchars($viewOffer['buyer']['name']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Email:</span>
                                <span><?= htmlspecialchars($viewOffer['buyer']['email']) ?></span>
                            </div>
                            <?php if ($viewOffer['buyer']['phone']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Phone:</span>
                                    <span><?= htmlspecialchars($viewOffer['buyer']['phone']) ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="detail-box">
                        <h3>Sale Information</h3>
                        <?php if ($viewOffer['sale']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Sale:</span>
                                <span>
                                    <a href="/claims/admin/sales.php?id=<?= $viewOffer['sale']['id'] ?>" style="color: #007bff;">
                                        <?= htmlspecialchars($viewOffer['sale']['title']) ?>
                                    </a>
                                </span>
                            </div>
                            <?php if ($viewOffer['seller']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Seller:</span>
                                    <span><?= htmlspecialchars($viewOffer['seller']['name']) ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($offerHistory)): ?>
                    <h3>Offer History for This Item</h3>
                    <div class="history-timeline">
                        <?php foreach ($offerHistory as $history): ?>
                            <div class="history-item">
                                <div class="history-dot"></div>
                                <div class="history-time">
                                    <?= date('M d, Y g:i A', strtotime($history['created_at'])) ?>
                                </div>
                                <div class="history-content">
                                    <strong><?= htmlspecialchars($history['buyer_name']) ?></strong> 
                                    <?= $history['action'] ?> offer of 
                                    <span class="price">$<?= number_format($history['offer_amount'], 2) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($viewOffer['status'] === 'active'): ?>
                    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                        <button class="btn btn-success" onclick="showActionModal('accept', <?= $viewOffer['id'] ?>)">Accept Offer</button>
                        <button class="btn btn-danger" onclick="showActionModal('reject', <?= $viewOffer['id'] ?>)">Reject Offer</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="section">
                <div class="section-header">
                    <h2>Offers</h2>
                </div>
                
                <form method="get" class="filters">
                    <input type="text" name="search" placeholder="Search by item, buyer, sale, or item#..." value="<?= htmlspecialchars($search) ?>" style="width: 300px;">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="winning" <?= $status === 'winning' ? 'selected' : '' ?>>Winning</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="outbid" <?= $status === 'outbid' ? 'selected' : '' ?>>Outbid</option>
                        <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="/claims/admin/offers.php" class="btn">Clear</a>
                </form>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item</th>
                            <th>Sale</th>
                            <th>Buyer</th>
                            <th>Offer Amount</th>
                            <th>Item Price</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($offers as $offer): ?>
                        <tr>
                            <td><?= $offer['id'] ?></td>
                            <td>
                                <?= htmlspecialchars($offer['item_title']) ?>
                                <?php if ($offer['item_number']): ?>
                                    <div class="offer-info">#<?= htmlspecialchars($offer['item_number']) ?></div>
                                <?php endif; ?>
                                <?php if ($offer['item_status'] === 'claimed'): ?>
                                    <span class="status rejected" style="font-size: 0.75rem; margin-left: 0.5rem;">CLAIMED</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/claims/admin/sales.php?id=<?= $offer['sale_id'] ?>" style="color: #007bff;">
                                    <?= htmlspecialchars($offer['sale_title']) ?>
                                </a>
                                <div class="offer-info">by <?= htmlspecialchars($offer['seller_name']) ?></div>
                            </td>
                            <td>
                                <?= htmlspecialchars($offer['buyer_name']) ?>
                                <div class="offer-info"><?= htmlspecialchars($offer['buyer_email']) ?></div>
                                <?php if ($offer['buyer_phone']): ?>
                                    <div class="offer-info"><?= htmlspecialchars($offer['buyer_phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="price">$<?= number_format($offer['offer_amount'], 2) ?></td>
                            <td>$<?= number_format($offer['item_price'], 2) ?></td>
                            <td>
                                <span class="status <?= $offer['status'] ?>">
                                    <?= ucfirst($offer['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y g:i A', strtotime($offer['created_at'])) ?></td>
                            <td>
                                <div class="actions">
                                    <a href="?view=<?= $offer['id'] ?>" class="btn btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">View</a>
                                    <?php if ($offer['status'] === 'active'): ?>
                                        <button class="btn btn-success" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;" 
                                                onclick="showActionModal('accept', <?= $offer['id'] ?>)">Accept</button>
                                        <button class="btn btn-danger" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;"
                                                onclick="showActionModal('reject', <?= $offer['id'] ?>)">Reject</button>
                                    <?php endif; ?>
                                    <form method="post" style="margin: 0; display: inline;">
                                        <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                        <button type="submit" name="action" value="delete_offer" class="btn btn-danger" 
                                                style="padding: 0.25rem 0.75rem; font-size: 0.875rem;"
                                                onclick="return confirm('Delete this offer?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal for accepting/rejecting offers -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Offer Action</h2>
            <form method="post" id="actionForm">
                <input type="hidden" name="offer_id" id="modalOfferId">
                <input type="hidden" name="action" id="modalAction">
                
                <div class="form-group">
                    <label for="seller_notes">Seller Notes (optional):</label>
                    <textarea name="seller_notes" id="seller_notes" 
                              placeholder="Add notes for the buyer..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn" id="modalSubmit">Submit</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function showActionModal(action, offerId) {
        const modal = document.getElementById('actionModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalAction = document.getElementById('modalAction');
        const modalOfferId = document.getElementById('modalOfferId');
        const modalSubmit = document.getElementById('modalSubmit');
        
        modalOfferId.value = offerId;
        
        if (action === 'accept') {
            modalTitle.textContent = 'Accept Offer';
            modalAction.value = 'accept_offer';
            modalSubmit.className = 'btn btn-success';
            modalSubmit.textContent = 'Accept Offer';
        } else {
            modalTitle.textContent = 'Reject Offer';
            modalAction.value = 'reject_offer';
            modalSubmit.className = 'btn btn-danger';
            modalSubmit.textContent = 'Reject Offer';
        }
        
        modal.style.display = 'block';
    }
    
    function closeModal() {
        document.getElementById('actionModal').style.display = 'none';
        document.getElementById('seller_notes').value = '';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('actionModal');
        if (event.target === modal) {
            closeModal();
        }
    }
    </script>
</body>
</html>