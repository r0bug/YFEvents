<?php
// YFClaim Sales Management
require_once __DIR__ . '/bootstrap.php';

// Use proper namespace imports
use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\SellerModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\OfferModel;

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
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-title">🔒 Admin Access Required</div>
            <div class="error-message">
                <p>You need to be logged in as an administrator to access the YFClaim Sales admin panel.</p>
                <p>Please log in to the main admin system first, then return to this page.</p>
            </div>
            <a href="/admin/login.php" class="login-button">Go to Admin Login</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$isAdmin = true;

// Initialize models
$saleModel = new SaleModel($pdo);
$sellerModel = new SellerModel($pdo);
$itemModel = new ItemModel($pdo);
$offerModel = new OfferModel($pdo);

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'create_sale':
                    $data = [
                        'seller_id' => $_POST['seller_id'],
                        'title' => $_POST['title'],
                        'description' => $_POST['description'],
                        'address' => $_POST['address'],
                        'city' => $_POST['city'],
                        'state' => $_POST['state'],
                        'zip' => $_POST['zip'],
                        'latitude' => $_POST['latitude'] ?? null,
                        'longitude' => $_POST['longitude'] ?? null,
                        'preview_start' => $_POST['preview_start'] ?? null,
                        'preview_end' => $_POST['preview_end'] ?? null,
                        'claim_start' => $_POST['claim_start'],
                        'claim_end' => $_POST['claim_end'],
                        'pickup_start' => $_POST['pickup_start'],
                        'pickup_end' => $_POST['pickup_end'],
                        'show_price_ranges' => isset($_POST['show_price_ranges']) ? 1 : 0,
                        'status' => 'active'
                    ];
                    $saleId = $saleModel->createSale($data);
                    $message = "Sale created successfully! Sale ID: $saleId";
                    header("Location: /claims/admin/sales.php?id=$saleId");
                    exit;
                    break;
                    
                case 'update_sale':
                    $data = [
                        'title' => $_POST['title'],
                        'description' => $_POST['description'],
                        'address' => $_POST['address'],
                        'city' => $_POST['city'],
                        'state' => $_POST['state'],
                        'zip' => $_POST['zip'],
                        'latitude' => $_POST['latitude'] ?? null,
                        'longitude' => $_POST['longitude'] ?? null,
                        'preview_start' => $_POST['preview_start'] ?? null,
                        'preview_end' => $_POST['preview_end'] ?? null,
                        'claim_start' => $_POST['claim_start'],
                        'claim_end' => $_POST['claim_end'],
                        'pickup_start' => $_POST['pickup_start'],
                        'pickup_end' => $_POST['pickup_end'],
                        'show_price_ranges' => isset($_POST['show_price_ranges']) ? 1 : 0,
                        'status' => $_POST['status']
                    ];
                    $saleModel->updateSale($_POST['sale_id'], $data);
                    $message = "Sale updated successfully!";
                    break;
                    
                case 'add_item':
                    $data = [
                        'sale_id' => $_POST['sale_id'],
                        'title' => $_POST['title'],
                        'description' => $_POST['description'],
                        'starting_price' => $_POST['starting_price'],
                        'offer_increment' => $_POST['offer_increment'] ?? 5,
                        'buy_now_price' => $_POST['buy_now_price'] ?? null,
                        'category' => $_POST['category'] ?? 'general',
                        'condition_rating' => $_POST['condition_rating'] ?? 3,
                        'dimensions' => $_POST['dimensions'] ?? null,
                        'weight' => $_POST['weight'] ?? null,
                        'status' => 'available'
                    ];
                    $itemModel->createItem($data);
                    $message = "Item added successfully!";
                    break;
                    
                case 'update_item':
                    $data = [
                        'title' => $_POST['title'],
                        'description' => $_POST['description'],
                        'starting_price' => $_POST['starting_price'],
                        'offer_increment' => $_POST['offer_increment'],
                        'buy_now_price' => $_POST['buy_now_price'] ?? null,
                        'category' => $_POST['category'],
                        'condition_rating' => $_POST['condition_rating'],
                        'dimensions' => $_POST['dimensions'] ?? null,
                        'weight' => $_POST['weight'] ?? null,
                        'status' => $_POST['item_status']
                    ];
                    $itemModel->updateItem($_POST['item_id'], $data);
                    $message = "Item updated successfully!";
                    break;
                    
                case 'delete_item':
                    $itemModel->deleteItem($_POST['item_id']);
                    $message = "Item deleted successfully!";
                    break;
                    
                case 'delete_sale':
                    // Delete all items first (which will cascade delete offers)
                    $items = $itemModel->getItemsBySale($_POST['sale_id']);
                    foreach ($items as $item) {
                        $itemModel->deleteItem($item['id']);
                    }
                    // Delete the sale
                    $saleModel->deleteSale($_POST['sale_id']);
                    $message = "Sale deleted successfully!";
                    header("Location: /claims/admin/sales.php");
                    exit;
                    break;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get specific sale if ID provided
$sale = null;
$items = [];
$offers = [];
if (isset($_GET['id'])) {
    $sale = $saleModel->getSaleById($_GET['id']);
    if ($sale) {
        // Get seller info
        $sale['seller'] = $sellerModel->getSellerById($sale['seller_id']);
        
        // Get items
        $items = $itemModel->getItemsBySale($sale['id']);
        
        // Get offers for each item
        foreach ($items as &$item) {
            $item['offers'] = $itemModel->getOffers($item['id']);
            // Get highest offer
            $item['highest_offer'] = $itemModel->getHighestOffer($item['id']);
            // Get price range for display
            $item['price_range'] = $itemModel->getPriceRange($item['id']);
        }
        
        // Get sale statistics
        $sale['stats'] = $saleModel->getStats($sale['id']);
    }
} else {
    // Get list of sales
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Get all sales with seller info
    $sales = $saleModel->getAllSales(100, 0);
    
    // Filter by status and search if needed
    if ($status || $search) {
        $sales = array_filter($sales, function($sale) use ($status, $search) {
            if ($status && $sale['status'] !== $status) {
                return false;
            }
            if ($search) {
                $searchLower = strtolower($search);
                return stripos($sale['title'], $searchLower) !== false ||
                       stripos($sale['description'] ?? '', $searchLower) !== false ||
                       stripos($sale['company_name'] ?? '', $searchLower) !== false ||
                       stripos($sale['contact_name'] ?? '', $searchLower) !== false;
            }
            return true;
        });
    }
    
    // Add statistics to each sale
    foreach ($sales as &$sale) {
        $stats = $saleModel->getStats($sale['id']);
        $sale['item_count'] = $stats['total_items'];
        $sale['offer_count'] = $stats['total_offers'];
    }
}

// Get active sellers for dropdown
$activeSellers = $sellerModel->getActive();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $sale ? 'Sale Details' : 'Manage Sales' ?> - YFClaim Admin</title>
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
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
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
        .status.active {
            background: #28a745;
            color: white;
        }
        .status.closed {
            background: #6c757d;
            color: white;
        }
        .status.available {
            background: #28a745;
            color: white;
        }
        .status.sold {
            background: #dc3545;
            color: white;
        }
        .status.pending {
            background: #ffc107;
            color: #000;
        }
        .status.accepted {
            background: #28a745;
            color: white;
        }
        .status.rejected {
            background: #dc3545;
            color: white;
        }
        .actions {
            display: flex;
            gap: 0.5rem;
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
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 5px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .modal-header h3 {
            margin: 0;
        }
        .close {
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
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .badge {
            background: #6c757d;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        .price {
            font-weight: bold;
            color: #28a745;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .info-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 3px;
        }
        .info-label {
            font-size: 0.875rem;
            color: #666;
            margin-bottom: 0.25rem;
        }
        .info-value {
            font-weight: bold;
            color: #333;
        }
        .item-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        .item-offers {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        .offer-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 3px;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= $sale ? 'Sale Details' : 'Manage Sales' ?></h1>
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
            <a href="/claims/admin/sales.php" class="active">Manage Sales</a>
            <a href="/claims/admin/offers.php">Manage Offers</a>
            <a href="/claims/admin/buyers.php">Manage Buyers</a>
            <a href="/claims/admin/reports.php">Reports</a>
        </div>
        
        <?php if ($sale): ?>
            <!-- Sale Details View -->
            <div class="section">
                <div class="section-header">
                    <h2><?= htmlspecialchars($sale['title']) ?></h2>
                    <div class="actions">
                        <button onclick="showEditSaleModal()" class="btn btn-primary">Edit Sale</button>
                        <a href="qr-codes.php?sale_id=<?= $sale['id'] ?>" class="btn btn-success">QR Codes</a>
                        <form method="post" style="margin: 0;">
                            <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
                            <button type="submit" name="action" value="delete_sale" class="btn btn-danger" 
                                    onclick="return confirm('Delete this sale and all its items? This cannot be undone.')">Delete Sale</button>
                        </form>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Sale ID</div>
                        <div class="info-value">#<?= $sale['id'] ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Seller</div>
                        <div class="info-value"><?= htmlspecialchars($sale['seller']['company_name'] ?? $sale['seller']['contact_name'] ?? 'Unknown') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status <?= $sale['status'] ?>"><?= ucfirst($sale['status']) ?></span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Created</div>
                        <div class="info-value"><?= date('M d, Y g:i A', strtotime($sale['created_at'])) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Location</div>
                        <div class="info-value">
                            <?= htmlspecialchars($sale['address']) ?><br>
                            <?= htmlspecialchars($sale['city']) ?>, <?= htmlspecialchars($sale['state']) ?> <?= htmlspecialchars($sale['zip']) ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Preview Period</div>
                        <div class="info-value">
                            <?= $sale['preview_start'] ? date('M d, Y g:i A', strtotime($sale['preview_start'])) : 'Not set' ?> -<br>
                            <?= $sale['preview_end'] ? date('M d, Y g:i A', strtotime($sale['preview_end'])) : 'Not set' ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Claim Period</div>
                        <div class="info-value">
                            <?= date('M d, Y g:i A', strtotime($sale['claim_start'])) ?> -<br>
                            <?= date('M d, Y g:i A', strtotime($sale['claim_end'])) ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Pickup Period</div>
                        <div class="info-value">
                            <?= date('M d, Y g:i A', strtotime($sale['pickup_start'])) ?> -<br>
                            <?= date('M d, Y g:i A', strtotime($sale['pickup_end'])) ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Access Code</div>
                        <div class="info-value"><?= htmlspecialchars($sale['access_code']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">QR Code</div>
                        <div class="info-value"><?= htmlspecialchars($sale['qr_code']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Statistics</div>
                        <div class="info-value">
                            Items: <?= $sale['stats']['total_items'] ?><br>
                            Offers: <?= $sale['stats']['total_offers'] ?><br>
                            Claimed: <?= $sale['stats']['claimed_items'] ?><br>
                            Buyers: <?= $sale['stats']['unique_buyers'] ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($sale['description']): ?>
                    <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($sale['description'])) ?></p>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <div class="section-header">
                    <h2>Items</h2>
                    <button onclick="showAddItemModal()" class="btn btn-primary">+ Add Item</button>
                </div>
                
                <?php if (empty($items)): ?>
                    <p style="text-align: center; color: #666;">No items added yet</p>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <div class="item-card">
                            <div class="item-header">
                                <div>
                                    <h3 style="margin: 0 0 0.5rem 0;">
                                        #<?= htmlspecialchars($item['item_number']) ?> - <?= htmlspecialchars($item['title']) ?>
                                    </h3>
                                    <div class="price">
                                        Starting: $<?= number_format($item['starting_price'], 2) ?>
                                        <?php if ($item['buy_now_price']): ?>
                                            | Buy Now: $<?= number_format($item['buy_now_price'], 2) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div style="margin-top: 0.5rem;">
                                        <span class="status <?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span>
                                        <span class="badge">Category: <?= ucfirst($item['category']) ?></span>
                                        <span class="badge">Condition: <?= $item['condition_rating'] ?>/5</span>
                                        <?php if ($item['highest_offer']): ?>
                                            <span class="badge" style="background: #28a745;">High: $<?= number_format($item['highest_offer']['offer_amount'], 2) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="actions">
                                    <button onclick='editItem(<?= json_encode($item) ?>)' class="btn btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">Edit</button>
                                    <form method="post" style="margin: 0;">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
                                        <button type="submit" name="action" value="delete_item" class="btn btn-danger" 
                                                style="padding: 0.25rem 0.75rem; font-size: 0.875rem;"
                                                onclick="return confirm('Delete this item?')">Delete</button>
                                    </form>
                                </div>
                            </div>
                            
                            <?php if ($item['description']): ?>
                                <p style="margin: 1rem 0;"><?= nl2br(htmlspecialchars($item['description'])) ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['offers'])): ?>
                                <div class="item-offers">
                                    <h4 style="margin: 0 0 0.5rem 0;">Offers (<?= count($item['offers']) ?>)</h4>
                                    <?php foreach ($item['offers'] as $offer): ?>
                                        <div class="offer-item">
                                            <div>
                                                <strong><?= htmlspecialchars($offer['buyer_name']) ?></strong>
                                                <span style="color: #666; font-size: 0.875rem;">
                                                    <?= date('M d g:i A', strtotime($offer['created_at'])) ?>
                                                </span>
                                            </div>
                                            <div>
                                                <span class="price">$<?= number_format($offer['offer_amount'], 2) ?></span>
                                                <span class="status <?= $offer['status'] ?>" style="margin-left: 0.5rem;">
                                                    <?= ucfirst($offer['status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center;">
                <a href="/claims/admin/sales.php" class="btn">← Back to Sales List</a>
            </div>
            
        <?php else: ?>
            <!-- Sales List View -->
            <div class="section">
                <div class="section-header">
                    <h2>Sales</h2>
                    <button class="btn btn-primary" onclick="showCreateSaleModal()">+ Create New Sale</button>
                </div>
                
                <form method="get" class="filters">
                    <input type="text" name="search" placeholder="Search sales..." value="<?= htmlspecialchars($search) ?>">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="/claims/admin/sales.php" class="btn">Clear</a>
                </form>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Seller</th>
                            <th>Items</th>
                            <th>Offers</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td><?= $sale['id'] ?></td>
                            <td><?= htmlspecialchars($sale['title']) ?></td>
                            <td><?= htmlspecialchars($sale['company_name'] ?? $sale['contact_name'] ?? 'Unknown') ?></td>
                            <td>
                                <?= $sale['item_count'] ?>
                                <?php if ($sale['item_count'] > 0): ?>
                                    <span class="badge">items</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $sale['offer_count'] ?>
                                <?php if ($sale['offer_count'] > 0): ?>
                                    <span class="badge">offers</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($sale['city'] . ', ' . $sale['state']) ?></td>
                            <td>
                                <span class="status <?= $sale['status'] ?>">
                                    <?= ucfirst($sale['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($sale['created_at'])) ?></td>
                            <td>
                                <div class="actions">
                                    <a href="?id=<?= $sale['id'] ?>" class="btn btn-primary" 
                                       style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">View</a>
                                    <a href="qr-codes.php?sale_id=<?= $sale['id'] ?>" class="btn btn-success" 
                                       style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">QR Codes</a>
                                    <form method="post" style="margin: 0;">
                                        <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
                                        <button type="submit" name="action" value="delete_sale" class="btn btn-danger" 
                                                style="padding: 0.25rem 0.75rem; font-size: 0.875rem;"
                                                onclick="return confirm('Delete this sale and all its items?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Create/Edit Sale Modal -->
    <div id="saleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="saleModalTitle">Create New Sale</h3>
                <span class="close" onclick="closeSaleModal()">&times;</span>
            </div>
            <form method="post" id="saleForm">
                <input type="hidden" name="action" id="saleAction" value="create_sale">
                <input type="hidden" name="sale_id" id="saleId" value="">
                
                <?php if (!$sale): ?>
                <div class="form-group">
                    <label>Seller *</label>
                    <select name="seller_id" required>
                        <option value="">Select a seller...</option>
                        <?php foreach ($activeSellers as $seller): ?>
                            <option value="<?= $seller['id'] ?>"><?= htmlspecialchars($seller['company_name'] ?? $seller['contact_name']) ?> (<?= htmlspecialchars($seller['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" id="saleTitle" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="saleDescription"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Address *</label>
                    <input type="text" name="address" id="saleAddress" required>
                </div>
                
                <div class="form-group">
                    <label>City *</label>
                    <input type="text" name="city" id="saleCity" required>
                </div>
                
                <div class="form-group">
                    <label>State *</label>
                    <input type="text" name="state" id="saleState" maxlength="2" required>
                </div>
                
                <div class="form-group">
                    <label>ZIP Code *</label>
                    <input type="text" name="zip" id="saleZip" required>
                </div>
                
                <div class="form-group">
                    <label>Preview Start</label>
                    <input type="datetime-local" name="preview_start" id="salePreviewStart">
                </div>
                
                <div class="form-group">
                    <label>Preview End</label>
                    <input type="datetime-local" name="preview_end" id="salePreviewEnd">
                </div>
                
                <div class="form-group">
                    <label>Claim Start *</label>
                    <input type="datetime-local" name="claim_start" id="saleClaimStart" required>
                </div>
                
                <div class="form-group">
                    <label>Claim End *</label>
                    <input type="datetime-local" name="claim_end" id="saleClaimEnd" required>
                </div>
                
                <div class="form-group">
                    <label>Pickup Start *</label>
                    <input type="datetime-local" name="pickup_start" id="salePickupStart" required>
                </div>
                
                <div class="form-group">
                    <label>Pickup End *</label>
                    <input type="datetime-local" name="pickup_end" id="salePickupEnd" required>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="show_price_ranges" id="saleShowPriceRanges" value="1">
                        Show price ranges on items
                    </label>
                </div>
                
                <input type="hidden" name="latitude" id="saleLatitude">
                <input type="hidden" name="longitude" id="saleLongitude">
                
                <?php if ($sale): ?>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="saleStatus">
                        <option value="active">Active</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span id="saleSubmitText">Create Sale</span>
                    </button>
                    <button type="button" class="btn" onclick="closeSaleModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add/Edit Item Modal -->
    <div id="itemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="itemModalTitle">Add Item</h3>
                <span class="close" onclick="closeItemModal()">&times;</span>
            </div>
            <form method="post" id="itemForm">
                <input type="hidden" name="action" id="itemAction" value="add_item">
                <input type="hidden" name="sale_id" value="<?= $sale['id'] ?? '' ?>">
                <input type="hidden" name="item_id" id="itemId" value="">
                
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" id="itemTitle" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="itemDescription"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Starting Price *</label>
                    <input type="number" name="starting_price" id="itemStartingPrice" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>Offer Increment</label>
                    <input type="number" name="offer_increment" id="itemOfferIncrement" step="0.01" min="1" value="5">
                </div>
                
                <div class="form-group">
                    <label>Buy Now Price (optional)</label>
                    <input type="number" name="buy_now_price" id="itemBuyNowPrice" step="0.01" min="0">
                </div>
                
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="itemCategory">
                        <option value="general">General</option>
                        <option value="furniture">Furniture</option>
                        <option value="electronics">Electronics</option>
                        <option value="clothing">Clothing</option>
                        <option value="books">Books</option>
                        <option value="toys">Toys</option>
                        <option value="household">Household</option>
                        <option value="outdoor">Outdoor</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Condition Rating</label>
                    <select name="condition_rating" id="itemConditionRating">
                        <option value="5">5 - Excellent/New</option>
                        <option value="4">4 - Very Good</option>
                        <option value="3" selected>3 - Good</option>
                        <option value="2">2 - Fair</option>
                        <option value="1">1 - Poor</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Dimensions (optional)</label>
                    <input type="text" name="dimensions" id="itemDimensions" placeholder="e.g., 24" x 36" x 12"">
                </div>
                
                <div class="form-group">
                    <label>Weight (optional)</label>
                    <input type="text" name="weight" id="itemWeight" placeholder="e.g., 25 lbs">
                </div>
                
                <div class="form-group" id="itemStatusGroup" style="display: none;">
                    <label>Status</label>
                    <select name="item_status" id="itemStatus">
                        <option value="available">Available</option>
                        <option value="sold">Sold</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span id="itemSubmitText">Add Item</span>
                    </button>
                    <button type="button" class="btn" onclick="closeItemModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        <?php if ($sale): ?>
        const saleData = <?= json_encode($sale) ?>;
        
        function showEditSaleModal() {
            document.getElementById('saleModalTitle').textContent = 'Edit Sale';
            document.getElementById('saleAction').value = 'update_sale';
            document.getElementById('saleId').value = saleData.id;
            document.getElementById('saleTitle').value = saleData.title;
            document.getElementById('saleDescription').value = saleData.description || '';
            document.getElementById('saleAddress').value = saleData.address;
            document.getElementById('saleCity').value = saleData.city;
            document.getElementById('saleState').value = saleData.state;
            document.getElementById('saleZip').value = saleData.zip;
            document.getElementById('saleLatitude').value = saleData.latitude || '';
            document.getElementById('saleLongitude').value = saleData.longitude || '';
            
            // Format datetime-local inputs
            if (saleData.preview_start) {
                document.getElementById('salePreviewStart').value = saleData.preview_start.slice(0, 16);
            }
            if (saleData.preview_end) {
                document.getElementById('salePreviewEnd').value = saleData.preview_end.slice(0, 16);
            }
            document.getElementById('saleClaimStart').value = saleData.claim_start.slice(0, 16);
            document.getElementById('saleClaimEnd').value = saleData.claim_end.slice(0, 16);
            document.getElementById('salePickupStart').value = saleData.pickup_start.slice(0, 16);
            document.getElementById('salePickupEnd').value = saleData.pickup_end.slice(0, 16);
            
            document.getElementById('saleShowPriceRanges').checked = saleData.show_price_ranges == 1;
            document.getElementById('saleStatus').value = saleData.status;
            document.getElementById('saleSubmitText').textContent = 'Update Sale';
            document.getElementById('saleModal').classList.add('active');
        }
        
        function showAddItemModal() {
            document.getElementById('itemModalTitle').textContent = 'Add Item';
            document.getElementById('itemForm').reset();
            document.getElementById('itemAction').value = 'add_item';
            document.getElementById('itemId').value = '';
            document.getElementById('itemStatusGroup').style.display = 'none';
            document.getElementById('itemSubmitText').textContent = 'Add Item';
            document.getElementById('itemModal').classList.add('active');
        }
        
        function editItem(item) {
            document.getElementById('itemModalTitle').textContent = 'Edit Item';
            document.getElementById('itemAction').value = 'update_item';
            document.getElementById('itemId').value = item.id;
            document.getElementById('itemTitle').value = item.title;
            document.getElementById('itemDescription').value = item.description || '';
            document.getElementById('itemStartingPrice').value = item.starting_price;
            document.getElementById('itemOfferIncrement').value = item.offer_increment || 5;
            document.getElementById('itemBuyNowPrice').value = item.buy_now_price || '';
            document.getElementById('itemCategory').value = item.category || 'general';
            document.getElementById('itemConditionRating').value = item.condition_rating || 3;
            document.getElementById('itemDimensions').value = item.dimensions || '';
            document.getElementById('itemWeight').value = item.weight || '';
            document.getElementById('itemStatus').value = item.status;
            document.getElementById('itemStatusGroup').style.display = 'block';
            document.getElementById('itemSubmitText').textContent = 'Update Item';
            document.getElementById('itemModal').classList.add('active');
        }
        <?php else: ?>
        function showCreateSaleModal() {
            document.getElementById('saleModalTitle').textContent = 'Create New Sale';
            document.getElementById('saleForm').reset();
            document.getElementById('saleAction').value = 'create_sale';
            document.getElementById('saleSubmitText').textContent = 'Create Sale';
            document.getElementById('saleModal').classList.add('active');
        }
        <?php endif; ?>
        
        function closeSaleModal() {
            document.getElementById('saleModal').classList.remove('active');
        }
        
        function closeItemModal() {
            document.getElementById('itemModal').classList.remove('active');
        }
        
        // Close modals on outside click
        document.getElementById('saleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSaleModal();
            }
        });
        
        document.getElementById('itemModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeItemModal();
            }
        });
    </script>
</body>
</html>