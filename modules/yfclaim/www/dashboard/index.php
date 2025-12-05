<?php
session_start();

// Check if seller is logged in
if (!isset($_SESSION['claim_seller_logged_in']) || $_SESSION['claim_seller_logged_in'] !== true) {
    header('Location: /claims/admin/login.php');
    exit;
}

// Load dependencies
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../src/Models/SellerModel.php';
require_once __DIR__ . '/../../src/Models/SaleModel.php';

use YFEvents\Modules\YFClaim\Models\SellerModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;

$sellerModel = new SellerModel($db);
$saleModel = new SaleModel($db);

$sellerId = $_SESSION['claim_seller_id'];
$seller = $sellerModel->find($sellerId);

if (!$seller) {
    header('Location: /modules/yfclaim/admin/login.php');
    exit;
}

// Get seller statistics
$stats = $sellerModel->getStats($sellerId);

// Get recent sales
$recentSales = $saleModel->getBySeller($sellerId);

// Get current/active sales
$activeSales = array_filter($recentSales, function($sale) {
    return $sale['status'] === 'active';
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - YFClaim</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
        }
        
        .header {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-name {
            font-weight: 600;
            color: #333;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .welcome-section h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .welcome-section p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 {
            color: #333;
            font-size: 1.2rem;
            margin: 0;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .sale-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sale-item:last-child {
            border-bottom: none;
        }
        
        .sale-info h4 {
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .sale-meta {
            color: #666;
            font-size: 0.85rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-draft {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-closed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .empty-state {
            text-align: center;
            color: #666;
            padding: 2rem;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .action-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            border: 2px solid transparent;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .action-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .action-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .action-card h4 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .action-card p {
            color: #666;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">YFClaim Seller Portal</div>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($seller['contact_name']) ?></span>
                <a href="/claims/api/seller-auth.php?action=logout" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome-section">
            <h1>Welcome back, <?= htmlspecialchars($seller['contact_name']) ?>!</h1>
            <p>Manage your claim sales for <?= htmlspecialchars($seller['company_name']) ?></p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_sales'] ?></div>
                <div class="stat-label">Total Sales</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['active_sales'] ?></div>
                <div class="stat-label">Active Sales</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_items'] ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_offers'] ?></div>
                <div class="stat-label">Total Offers</div>
            </div>
        </div>
        
        <div class="quick-actions">
            <div class="action-card" onclick="window.location.href='create-sale.php'">
                <div class="action-icon">🎯</div>
                <h4>Create New Sale</h4>
                <p>Set up a new claim sale with items and scheduling</p>
            </div>
            <div class="action-card" onclick="window.location.href='manage-items.php'">
                <div class="action-icon">📦</div>
                <h4>Manage Items</h4>
                <p>Add, edit, and organize items across your sales</p>
            </div>
            <div class="action-card" onclick="window.location.href='view-offers.php'">
                <div class="action-icon">💰</div>
                <h4>Review Offers</h4>
                <p>View and accept offers from buyers</p>
            </div>
        </div>
        
        <div class="main-content">
            <div class="card">
                <div class="card-header">
                    <h3>Active Sales</h3>
                    <?php if (count($activeSales) > 0): ?>
                        <a href="sales.php" class="btn">View All</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($activeSales)): ?>
                        <div class="empty-state">
                            <p>No active sales</p>
                            <a href="create-sale.php" class="btn btn-success">Create Your First Sale</a>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($activeSales, 0, 3) as $sale): ?>
                            <div class="sale-item">
                                <div class="sale-info">
                                    <h4><?= htmlspecialchars($sale['title']) ?></h4>
                                    <div class="sale-meta">
                                        Ends: <?= date('M j, Y g:i A', strtotime($sale['claim_end'])) ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?= $sale['status'] ?>">
                                    <?= ucfirst($sale['status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Recent Sales</h3>
                    <?php if (count($recentSales) > 3): ?>
                        <a href="sales.php" class="btn">View All</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($recentSales)): ?>
                        <div class="empty-state">
                            <p>No sales yet</p>
                            <a href="create-sale.php" class="btn btn-success">Create Your First Sale</a>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($recentSales, 0, 5) as $sale): ?>
                            <div class="sale-item">
                                <div class="sale-info">
                                    <h4><?= htmlspecialchars($sale['title']) ?></h4>
                                    <div class="sale-meta">
                                        Created: <?= date('M j, Y', strtotime($sale['created_at'])) ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?= $sale['status'] ?>">
                                    <?= ucfirst($sale['status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            fetch('/claims/api/seller-stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelector('.stat-card:nth-child(1) .stat-number').textContent = data.stats.total_sales;
                        document.querySelector('.stat-card:nth-child(2) .stat-number').textContent = data.stats.active_sales;
                        document.querySelector('.stat-card:nth-child(3) .stat-number').textContent = data.stats.total_items;
                        document.querySelector('.stat-card:nth-child(4) .stat-number').textContent = data.stats.total_offers;
                    }
                })
                .catch(console.error);
        }, 30000);
    </script>
</body>
</html>