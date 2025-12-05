<?php
session_start();

// Check if seller is logged in
if (!isset($_SESSION['claim_seller_logged_in']) || $_SESSION['claim_seller_logged_in'] !== true) {
    header('Location: /claims/admin/login.php');
    exit;
}

require_once __DIR__ . '/bootstrap.php';

use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\SellerModel;

$saleModel = new SaleModel($pdo);
$sellerModel = new SellerModel($pdo);

$sellerId = $_SESSION['claim_seller_id'];
$seller = $sellerModel->find($sellerId);

// Get all sales for this seller
$allSales = $saleModel->getBySeller($sellerId);

// Check for creation success message
$createdSaleId = $_GET['created'] ?? null;
$successMessage = '';
if ($createdSaleId) {
    $successMessage = 'Sale created successfully!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sales - YFClaim</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h1 {
            color: #2c3e50;
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
            transform: translateY(-1px);
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
        
        .sales-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .sale-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .sale-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .sale-header {
            background: #34495e;
            color: white;
            padding: 1.5rem;
            position: relative;
        }
        
        .sale-header h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }
        
        .sale-meta {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .sale-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #27ae60;
            color: white;
        }
        
        .status-upcoming {
            background: #3498db;
            color: white;
        }
        
        .status-ended {
            background: #95a5a6;
            color: white;
        }
        
        .sale-body {
            padding: 1.5rem;
        }
        
        .sale-info {
            margin-bottom: 1rem;
        }
        
        .sale-info p {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .sale-info .icon {
            width: 20px;
            text-align: center;
        }
        
        .sale-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #7f8c8d;
            text-transform: uppercase;
        }
        
        .sale-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .empty-state h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            color: #666;
            margin-bottom: 2rem;
        }
        
        .filters {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #555;
        }
        
        .filter-group select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .sale-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                justify-content: space-between;
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
            <div>
                <h1>🏷️ My Sales</h1>
                <p>Manage your estate sales and track performance</p>
            </div>
            <a href="create-sale.php" class="btn btn-success">+ Create New Sale</a>
        </div>
        
        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>
        
        <div class="filters">
            <div class="filter-group">
                <label for="statusFilter">Status:</label>
                <select id="statusFilter" onchange="filterSales()">
                    <option value="all">All Sales</option>
                    <option value="active">Active</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="ended">Ended</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="sortOrder">Sort by:</label>
                <select id="sortOrder" onchange="filterSales()">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="title">Title A-Z</option>
                    <option value="status">Status</option>
                </select>
            </div>
            <div style="margin-left: auto;">
                <span id="saleCount"><?= count($allSales) ?> sales total</span>
            </div>
        </div>
        
        <?php if (empty($allSales)): ?>
            <div class="empty-state">
                <h3>No Sales Yet</h3>
                <p>You haven't created any sales yet. Get started by creating your first estate sale!</p>
                <a href="create-sale.php" class="btn btn-success">Create Your First Sale</a>
            </div>
        <?php else: ?>
            <div class="sales-grid" id="salesGrid">
                <?php foreach ($allSales as $sale): ?>
                    <?php 
                    $stats = $saleModel->getStats($sale['id']);
                    $now = time();
                    $claimStart = strtotime($sale['claim_start']);
                    $claimEnd = strtotime($sale['claim_end']);
                    
                    // Determine status
                    $status = 'ended';
                    $statusText = 'Ended';
                    if ($now < $claimStart) {
                        $status = 'upcoming';
                        $statusText = 'Upcoming';
                    } elseif ($now >= $claimStart && $now <= $claimEnd) {
                        $status = 'active';
                        $statusText = 'Active';
                    }
                    ?>
                    <div class="sale-card" data-status="<?= $status ?>" data-created="<?= strtotime($sale['created_at']) ?>" data-title="<?= htmlspecialchars($sale['title']) ?>">
                        <div class="sale-header">
                            <span class="sale-status status-<?= $status ?>"><?= $statusText ?></span>
                            <h3><?= htmlspecialchars($sale['title']) ?></h3>
                            <div class="sale-meta">
                                Created <?= date('M j, Y', strtotime($sale['created_at'])) ?>
                            </div>
                        </div>
                        <div class="sale-body">
                            <div class="sale-info">
                                <p>
                                    <span class="icon">📍</span>
                                    <?= htmlspecialchars($sale['address']) ?>, <?= htmlspecialchars($sale['city']) ?>, <?= htmlspecialchars($sale['state']) ?>
                                </p>
                                <p>
                                    <span class="icon">📅</span>
                                    <?= date('M j, Y g:i A', strtotime($sale['start_date'])) ?> - <?= date('M j, Y g:i A', strtotime($sale['end_date'])) ?>
                                </p>
                                <p>
                                    <span class="icon">🔓</span>
                                    Claims: <?= date('M j g:i A', strtotime($sale['claim_start'])) ?> - <?= date('M j g:i A', strtotime($sale['claim_end'])) ?>
                                </p>
                                <?php if (!empty($sale['access_code'])): ?>
                                <p>
                                    <span class="icon">🔑</span>
                                    Access Code: <strong><?= htmlspecialchars($sale['access_code']) ?></strong>
                                </p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="sale-stats">
                                <div class="stat">
                                    <div class="stat-value"><?= $stats['total_items'] ?></div>
                                    <div class="stat-label">Items</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?= $stats['total_offers'] ?></div>
                                    <div class="stat-label">Offers</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?= $stats['unique_buyers'] ?></div>
                                    <div class="stat-label">Buyers</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?= $stats['claimed_items'] ?></div>
                                    <div class="stat-label">Claimed</div>
                                </div>
                            </div>
                            
                            <div class="sale-actions">
                                <a href="/claims/sale.php?id=<?= $sale['id'] ?>" class="btn btn-primary btn-small">View Public Page</a>
                                <a href="manage-items.php?sale_id=<?= $sale['id'] ?>" class="btn btn-secondary btn-small">Manage Items</a>
                                <a href="view-offers.php?sale_id=<?= $sale['id'] ?>" class="btn btn-secondary btn-small">View Offers (<?= $stats['total_offers'] ?>)</a>
                                <?php if ($status === 'upcoming'): ?>
                                    <button class="btn btn-secondary btn-small" onclick="editSale(<?= $sale['id'] ?>)">Edit Sale</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function filterSales() {
            const statusFilter = document.getElementById('statusFilter').value;
            const sortOrder = document.getElementById('sortOrder').value;
            const salesGrid = document.getElementById('salesGrid');
            const saleCards = Array.from(salesGrid.querySelectorAll('.sale-card'));
            
            // Filter sales
            let visibleCount = 0;
            saleCards.forEach(card => {
                const cardStatus = card.dataset.status;
                if (statusFilter === 'all' || statusFilter === cardStatus) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Sort sales
            const visibleCards = saleCards.filter(card => card.style.display !== 'none');
            visibleCards.sort((a, b) => {
                switch (sortOrder) {
                    case 'newest':
                        return parseInt(b.dataset.created) - parseInt(a.dataset.created);
                    case 'oldest':
                        return parseInt(a.dataset.created) - parseInt(b.dataset.created);
                    case 'title':
                        return a.dataset.title.localeCompare(b.dataset.title);
                    case 'status':
                        return a.dataset.status.localeCompare(b.dataset.status);
                    default:
                        return 0;
                }
            });
            
            // Reorder in DOM
            visibleCards.forEach(card => salesGrid.appendChild(card));
            
            // Update count
            document.getElementById('saleCount').textContent = `${visibleCount} sales shown`;
        }
        
        function editSale(saleId) {
            // TODO: Implement edit sale functionality
            alert('Edit sale functionality coming soon!');
        }
    </script>
</body>
</html>