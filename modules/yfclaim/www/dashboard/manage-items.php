<?php
session_start();

// Check if seller is logged in
if (!isset($_SESSION['claim_seller_logged_in']) || $_SESSION['claim_seller_logged_in'] !== true) {
    header('Location: /claims/admin/login.php');
    exit;
}

require_once __DIR__ . '/bootstrap.php';

use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\SellerModel;

$saleModel = new SaleModel($pdo);
$itemModel = new ItemModel($pdo);
$sellerModel = new SellerModel($pdo);

$sellerId = $_SESSION['claim_seller_id'];
$seller = $sellerModel->find($sellerId);

// Get sale ID from URL
$saleId = $_GET['sale_id'] ?? null;
$sale = null;
$items = [];

if ($saleId) {
    $sale = $saleModel->find($saleId);
    // Verify seller owns this sale
    if (!$sale || $sale['seller_id'] != $sellerId) {
        header('Location: sales.php');
        exit;
    }
    $items = $saleModel->getItems($saleId);
}

$success = false;
$error = '';

// Handle form submission for adding new item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_item') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $itemNumber = trim($_POST['item_number'] ?? '');
    $startingPrice = $_POST['starting_price'] ?? 0;
    
    if (empty($title) || empty($description)) {
        $error = 'Title and description are required.';
    } else {
        try {
            $itemData = [
                'sale_id' => $saleId,
                'title' => $title,
                'description' => $description,
                'category' => $category ?: 'General',
                'item_number' => $itemNumber ?: null,
                'starting_price' => floatval($startingPrice),
                'status' => 'available'
            ];
            
            $itemId = $itemModel->create($itemData);
            
            if ($itemId) {
                $success = true;
                $items = $saleModel->getItems($saleId); // Refresh items list
            } else {
                $error = 'Failed to add item.';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Items - YFClaim</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            margin-left: 2rem;
            transition: opacity 0.3s;
        }
        
        .nav-links a:hover {
            opacity: 0.8;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .breadcrumb {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .breadcrumb a {
            color: #3498db;
            text-decoration: none;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #219a52;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }
        
        .main-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .sidebar {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 2rem;
            height: fit-content;
        }
        
        .items-header {
            padding: 2rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .items-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .item-card {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s;
        }
        
        .item-card:hover {
            background: #f8f9fa;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .item-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .item-meta {
            font-size: 0.9rem;
            color: #666;
        }
        
        .item-status {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-claimed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .item-description {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .item-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        textarea {
            resize: vertical;
            height: 100px;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }
        
        .empty-state h3 {
            margin-bottom: 1rem;
        }
        
        .no-sale-selected {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .sale-info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 2rem;
        }
        
        .sale-info h4 {
            color: #1976d2;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                order: -1;
            }
            
            .items-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">YFClaim Seller Portal</div>
            <nav class="nav-links">
                <a href="index.php">Dashboard</a>
                <a href="sales.php">My Sales</a>
                <a href="/claims/api/seller-auth.php?action=logout">Logout</a>
            </nav>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <div class="breadcrumb">
                <a href="index.php">Dashboard</a> > <a href="sales.php">My Sales</a> > Manage Items
            </div>
            <h1>📦 Manage Items</h1>
            <?php if ($sale): ?>
                <p>Managing items for: <strong><?= htmlspecialchars($sale['title']) ?></strong></p>
            <?php endif; ?>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                Item added successfully!
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$sale): ?>
            <div class="no-sale-selected">
                <h3>No Sale Selected</h3>
                <p>Please select a sale to manage its items.</p>
                <a href="sales.php" class="btn btn-primary">View My Sales</a>
            </div>
        <?php else: ?>
            <div class="content-grid">
                <div class="main-content">
                    <div class="items-header">
                        <div>
                            <h2>Items (<?= count($items) ?>)</h2>
                            <p>Add and manage items for this sale</p>
                        </div>
                        <button class="btn btn-success" onclick="toggleAddForm()">+ Add Item</button>
                    </div>
                    
                    <div class="items-list">
                        <?php if (empty($items)): ?>
                            <div class="empty-state">
                                <h3>No Items Yet</h3>
                                <p>Start adding items to your sale using the form on the right.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <div class="item-card">
                                    <div class="item-header">
                                        <div>
                                            <div class="item-title"><?= htmlspecialchars($item['title']) ?></div>
                                            <div class="item-meta">
                                                <?php if ($item['item_number']): ?>
                                                    Item #<?= htmlspecialchars($item['item_number']) ?> • 
                                                <?php endif; ?>
                                                <?= htmlspecialchars($item['category']) ?>
                                                <?php if ($item['starting_price'] > 0): ?>
                                                    • Starting at $<?= number_format($item['starting_price'], 2) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="item-status status-<?= htmlspecialchars($item['status']) ?>">
                                            <?= ucfirst($item['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="item-description">
                                        <?= htmlspecialchars($item['description']) ?>
                                    </div>
                                    
                                    <div class="item-actions">
                                        <button class="btn btn-secondary btn-small" onclick="editItem(<?= $item['id'] ?>)">Edit</button>
                                        <button class="btn btn-secondary btn-small" onclick="deleteItem(<?= $item['id'] ?>)">Delete</button>
                                        <?php if ($item['status'] === 'available'): ?>
                                            <a href="/claims/item.php?id=<?= $item['id'] ?>" class="btn btn-primary btn-small" target="_blank">View Public</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="sidebar">
                    <div class="sale-info">
                        <h4>Sale Information</h4>
                        <p><strong><?= htmlspecialchars($sale['title']) ?></strong></p>
                        <p>Claims: <?= date('M j', strtotime($sale['claim_start'])) ?> - <?= date('M j', strtotime($sale['claim_end'])) ?></p>
                        <p>Access Code: <strong><?= htmlspecialchars($sale['access_code']) ?></strong></p>
                    </div>
                    
                    <div id="addItemForm" style="display: none;">
                        <h3>Add New Item</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_item">
                            
                            <div class="form-group">
                                <label for="title">Item Title *</label>
                                <input type="text" id="title" name="title" required 
                                       placeholder="e.g., Antique Oak Dining Table">
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description *</label>
                                <textarea id="description" name="description" required 
                                          placeholder="Detailed description of the item..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select id="category" name="category">
                                    <option value="General">General</option>
                                    <option value="Furniture">Furniture</option>
                                    <option value="Antiques">Antiques</option>
                                    <option value="Jewelry">Jewelry</option>
                                    <option value="Art">Art</option>
                                    <option value="Books">Books</option>
                                    <option value="Electronics">Electronics</option>
                                    <option value="Collectibles">Collectibles</option>
                                    <option value="Tools">Tools</option>
                                    <option value="Kitchenware">Kitchenware</option>
                                    <option value="Clothing">Clothing</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="item_number">Item Number</label>
                                <input type="text" id="item_number" name="item_number" 
                                       placeholder="Optional item number">
                            </div>
                            
                            <div class="form-group">
                                <label for="starting_price">Starting Price</label>
                                <input type="number" id="starting_price" name="starting_price" 
                                       step="0.01" min="0" placeholder="0.00">
                            </div>
                            
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="submit" class="btn btn-success">Add Item</button>
                                <button type="button" class="btn btn-secondary" onclick="toggleAddForm()">Cancel</button>
                            </div>
                        </form>
                    </div>
                    
                    <div id="quickActions">
                        <h3>Quick Actions</h3>
                        <a href="/claims/sale.php?id=<?= $sale['id'] ?>" class="btn btn-primary" target="_blank" style="width: 100%; margin-bottom: 0.5rem;">View Public Sale Page</a>
                        <a href="view-offers.php?sale_id=<?= $sale['id'] ?>" class="btn btn-secondary" style="width: 100%; margin-bottom: 0.5rem;">View Offers</a>
                        <a href="sales.php" class="btn btn-secondary" style="width: 100%;">Back to Sales</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function toggleAddForm() {
            const form = document.getElementById('addItemForm');
            const actions = document.getElementById('quickActions');
            
            if (form.style.display === 'none') {
                form.style.display = 'block';
                actions.style.display = 'none';
            } else {
                form.style.display = 'none';
                actions.style.display = 'block';
                // Clear form
                form.querySelector('form').reset();
            }
        }
        
        function editItem(itemId) {
            // TODO: Implement edit item functionality
            alert('Edit item functionality coming soon!');
        }
        
        function deleteItem(itemId) {
            if (confirm('Are you sure you want to delete this item?')) {
                // TODO: Implement delete item functionality
                alert('Delete item functionality coming soon!');
            }
        }
    </script>
</body>
</html>