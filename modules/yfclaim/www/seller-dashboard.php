<?php
/**
 * YFClaim Enhanced Seller Dashboard
 * Modern interface for estate sale companies to manage their sales
 */

require_once __DIR__ . '/bootstrap.php';

// Check authentication
if (!isset($_SESSION['claim_seller_logged_in']) || $_SESSION['claim_seller_logged_in'] !== true) {
    header('Location: /claims/seller-login.php');
    exit;
}

use YFEvents\Modules\YFClaim\Models\SellerModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\OfferModel;

// Initialize models
$sellerModel = new SellerModel($pdo);
$saleModel = new SaleModel($pdo);
$itemModel = new ItemModel($pdo);
$offerModel = new OfferModel($pdo);

$sellerId = $_SESSION['claim_seller_id'];
$seller = $sellerModel->find($sellerId);

if (!$seller) {
    session_destroy();
    header('Location: /claims/seller-login.php');
    exit;
}

// Get comprehensive statistics
$stats = $sellerModel->getStats($sellerId);
$sales = $saleModel->getBySeller($sellerId);

// Categorize sales
$activeSales = [];
$upcomingSales = [];
$completedSales = [];

foreach ($sales as $sale) {
    if ($sale['status'] === 'active') {
        $now = date('Y-m-d H:i:s');
        if ($now >= $sale['claim_start'] && $now <= $sale['claim_end']) {
            $activeSales[] = $sale;
        } elseif ($now < $sale['claim_start']) {
            $upcomingSales[] = $sale;
        } else {
            $completedSales[] = $sale;
        }
    } else {
        $completedSales[] = $sale;
    }
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_sale_stats':
            $saleId = intval($_GET['sale_id']);
            $saleStats = $saleModel->getStats($saleId);
            echo json_encode(['success' => true, 'stats' => $saleStats]);
            exit;
            
        case 'get_recent_offers':
            $limit = intval($_GET['limit'] ?? 10);
            $sql = "
                SELECT o.*, i.title as item_title, s.title as sale_title, b.name as buyer_name
                FROM yfc_offers o
                JOIN yfc_items i ON o.item_id = i.id
                JOIN yfc_sales s ON i.sale_id = s.id
                JOIN yfc_buyers b ON o.buyer_id = b.id
                WHERE s.seller_id = ?
                ORDER BY o.created_at DESC
                LIMIT ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$sellerId, $limit]);
            $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'offers' => $offers]);
            exit;
    }
}

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
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .seller-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .nav-menu {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }
        
        .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            gap: 2rem;
        }
        
        .nav-item {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .nav-item:hover,
        .nav-item.active {
            background: #667eea;
            color: white;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .card-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        .btn-success {
            background: #10b981;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-warning {
            background: #f59e0b;
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
        .btn-danger {
            background: #ef4444;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .sales-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .sale-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .sale-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .sale-header {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            padding: 1rem;
        }
        
        .sale-header.upcoming {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
        }
        
        .sale-header.completed {
            background: linear-gradient(135deg, #6b7280 0%, #9ca3af 100%);
        }
        
        .sale-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .sale-dates {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .sale-content {
            padding: 1rem;
        }
        
        .sale-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .sale-stat {
            text-align: center;
        }
        
        .sale-stat-value {
            font-size: 1.3rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .sale-stat-label {
            font-size: 0.8rem;
            color: #666;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-winning {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-outbid {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(102, 126, 234, 0.3);
            border-radius: 50%;
            border-top-color: #667eea;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .nav-content {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .dashboard-grid,
            .sales-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                flex-direction: column;
            }
            
            .sale-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">
                🏷️ YFClaim Seller Portal
            </div>
            <div class="seller-info">
                <span>Welcome, <?= htmlspecialchars($seller['company_name']) ?></span>
                <a href="/claims/api/seller-auth.php?action=logout" class="btn btn-sm">Logout</a>
            </div>
        </div>
    </header>

    <nav class="nav-menu">
        <div class="nav-content">
            <a href="#dashboard" class="nav-item active" onclick="showSection('dashboard')">📊 Dashboard</a>
            <a href="#sales" class="nav-item" onclick="showSection('sales')">🏪 My Sales</a>
            <a href="#offers" class="nav-item" onclick="showSection('offers')">💰 Recent Offers</a>
            <a href="#analytics" class="nav-item" onclick="showSection('analytics')">📈 Analytics</a>
            <a href="/claims/admin/" class="nav-item">⚙️ Full Admin</a>
        </div>
    </nav>

    <div class="container">
        <!-- Dashboard Section -->
        <div id="dashboard-section" class="section">
            <div class="quick-actions">
                <a href="/claims/admin/sales.php?action=create" class="btn btn-success">➕ Create New Sale</a>
                <a href="/claims/admin/sales.php" class="btn">📋 Manage Sales</a>
                <a href="/claims/admin/reports.php" class="btn">📊 View Reports</a>
            </div>

            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_sales'] ?></div>
                    <div class="stat-label">Total Sales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['active_sales'] ?></div>
                    <div class="stat-label">Active Sales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_items'] ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_offers'] ?></div>
                    <div class="stat-label">Total Offers</div>
                </div>
            </div>

            <!-- Active Sales -->
            <?php if (!empty($activeSales)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">🔥 Active Sales</h3>
                    </div>
                    <div class="sales-grid">
                        <?php foreach ($activeSales as $sale): ?>
                            <div class="sale-card">
                                <div class="sale-header">
                                    <div class="sale-title"><?= htmlspecialchars($sale['title']) ?></div>
                                    <div class="sale-dates">
                                        Claims: <?= date('M j, g:i A', strtotime($sale['claim_start'])) ?> - 
                                        <?= date('M j, g:i A', strtotime($sale['claim_end'])) ?>
                                    </div>
                                </div>
                                <div class="sale-content">
                                    <div class="sale-stats" id="sale-stats-<?= $sale['id'] ?>">
                                        <div class="loading"></div>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                                        <a href="/claims/admin/sales.php?id=<?= $sale['id'] ?>" class="btn btn-sm">Manage</a>
                                        <a href="/claims/sale.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-success">View Public</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Offers -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">💰 Recent Offers</h3>
                    <button class="btn btn-sm" onclick="loadRecentOffers()">Refresh</button>
                </div>
                <div id="recent-offers">
                    <div style="text-align: center; padding: 2rem;">
                        <div class="loading"></div>
                        <p>Loading recent offers...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Section -->
        <div id="sales-section" class="section" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🏪 All My Sales</h3>
                    <a href="/claims/admin/sales.php?action=create" class="btn">Create New Sale</a>
                </div>
                
                <?php if (!empty($sales)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Sale Title</th>
                                <th>Status</th>
                                <th>Claim Period</th>
                                <th>Items</th>
                                <th>Offers</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($sale['title']) ?></strong><br>
                                        <small><?= htmlspecialchars($sale['city']) ?>, <?= htmlspecialchars($sale['state']) ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $sale['status'] ?>">
                                            <?= ucfirst($sale['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= date('M j, Y', strtotime($sale['claim_start'])) ?><br>
                                        <small>to <?= date('M j, Y', strtotime($sale['claim_end'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="stat-value" style="font-size: 1rem;"><?= $itemModel->count(['sale_id' => $sale['id']]) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $offerCount = $pdo->prepare("
                                            SELECT COUNT(*) FROM yfc_offers o
                                            JOIN yfc_items i ON o.item_id = i.id
                                            WHERE i.sale_id = ?
                                        ");
                                        $offerCount->execute([$sale['id']]);
                                        ?>
                                        <span class="stat-value" style="font-size: 1rem;"><?= $offerCount->fetchColumn() ?></span>
                                    </td>
                                    <td>
                                        <a href="/claims/admin/sales.php?id=<?= $sale['id'] ?>" class="btn btn-sm">Manage</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🏪</div>
                        <h3>No Sales Yet</h3>
                        <p>Create your first estate sale to get started.</p>
                        <a href="/claims/admin/sales.php?action=create" class="btn">Create New Sale</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Other sections (offers, analytics) would be implemented similarly -->
    </div>

    <script>
        // Load sale statistics for active sales
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($activeSales as $sale): ?>
                loadSaleStats(<?= $sale['id'] ?>);
            <?php endforeach; ?>
            
            loadRecentOffers();
        });

        async function loadSaleStats(saleId) {
            try {
                const response = await fetch(`?action=get_sale_stats&sale_id=${saleId}`);
                const result = await response.json();
                
                if (result.success) {
                    const container = document.getElementById(`sale-stats-${saleId}`);
                    container.innerHTML = `
                        <div class="sale-stat">
                            <div class="sale-stat-value">${result.stats.total_items}</div>
                            <div class="sale-stat-label">Items</div>
                        </div>
                        <div class="sale-stat">
                            <div class="sale-stat-value">${result.stats.total_offers}</div>
                            <div class="sale-stat-label">Offers</div>
                        </div>
                        <div class="sale-stat">
                            <div class="sale-stat-value">${result.stats.items_with_offers}</div>
                            <div class="sale-stat-label">With Offers</div>
                        </div>
                        <div class="sale-stat">
                            <div class="sale-stat-value">${result.stats.unique_buyers}</div>
                            <div class="sale-stat-label">Buyers</div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading sale stats:', error);
            }
        }

        async function loadRecentOffers() {
            try {
                const response = await fetch('?action=get_recent_offers&limit=10');
                const result = await response.json();
                
                if (result.success && result.offers.length > 0) {
                    const container = document.getElementById('recent-offers');
                    const offersHtml = result.offers.map(offer => `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid #eee;">
                            <div>
                                <strong>${offer.item_title}</strong> from ${offer.sale_title}<br>
                                <small>Buyer: ${offer.buyer_name}</small>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.2rem; font-weight: bold; color: #10b981;">$${parseFloat(offer.offer_amount).toFixed(2)}</div>
                                <small>${new Date(offer.created_at).toLocaleDateString()}</small>
                            </div>
                        </div>
                    `).join('');
                    
                    container.innerHTML = offersHtml;
                } else {
                    document.getElementById('recent-offers').innerHTML = `
                        <div class="empty-state">
                            <div class="empty-state-icon">💰</div>
                            <h3>No Recent Offers</h3>
                            <p>Offers will appear here when buyers start bidding on your items.</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading recent offers:', error);
            }
        }

        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            document.getElementById(sectionName + '-section').style.display = 'block';
            
            // Update navigation
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            event.target.classList.add('active');
        }
    </script>
</body>
</html>