<?php
// YFClaim Reports
require_once __DIR__ . '/bootstrap.php';

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

// Get date range
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Basic statistics
$stats = [];

// Total counts
$stats['total_sellers'] = $pdo->query("SELECT COUNT(*) FROM yfc_sellers")->fetchColumn();
$stats['active_sellers'] = $pdo->query("SELECT COUNT(*) FROM yfc_sellers WHERE status = 'active'")->fetchColumn();
$stats['total_buyers'] = $pdo->query("SELECT COUNT(*) FROM yfc_buyers")->fetchColumn();
$stats['total_sales'] = $pdo->query("SELECT COUNT(*) FROM yfc_sales")->fetchColumn();
$stats['active_sales'] = $pdo->query("SELECT COUNT(*) FROM yfc_sales WHERE status = 'active'")->fetchColumn();
$stats['total_items'] = $pdo->query("SELECT COUNT(*) FROM yfc_items")->fetchColumn();
$stats['available_items'] = $pdo->query("SELECT COUNT(*) FROM yfc_items WHERE status = 'available'")->fetchColumn();
$stats['sold_items'] = $pdo->query("SELECT COUNT(*) FROM yfc_items WHERE status = 'sold'")->fetchColumn();
$stats['total_offers'] = $pdo->query("SELECT COUNT(*) FROM yfc_offers")->fetchColumn();
$stats['pending_offers'] = $pdo->query("SELECT COUNT(*) FROM yfc_offers WHERE status = 'pending'")->fetchColumn();
$stats['accepted_offers'] = $pdo->query("SELECT COUNT(*) FROM yfc_offers WHERE status = 'accepted'")->fetchColumn();

// Date range statistics
$dateStats = [];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM yfc_sales WHERE created_at BETWEEN ? AND ? + INTERVAL 1 DAY");
$stmt->execute([$startDate, $endDate]);
$dateStats['new_sales'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM yfc_sellers WHERE created_at BETWEEN ? AND ? + INTERVAL 1 DAY");
$stmt->execute([$startDate, $endDate]);
$dateStats['new_sellers'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM yfc_buyers WHERE created_at BETWEEN ? AND ? + INTERVAL 1 DAY");
$stmt->execute([$startDate, $endDate]);
$dateStats['new_buyers'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM yfc_offers WHERE created_at BETWEEN ? AND ? + INTERVAL 1 DAY");
$stmt->execute([$startDate, $endDate]);
$dateStats['new_offers'] = $stmt->fetchColumn();

// Top sellers by sales count
$topSellers = $pdo->query("
    SELECT s.company_name as name, s.contact_name as fb_name, COUNT(sa.id) as sale_count
    FROM yfc_sellers s
    LEFT JOIN yfc_sales sa ON s.id = sa.seller_id
    GROUP BY s.id
    ORDER BY sale_count DESC
    LIMIT 10
")->fetchAll();

// Top items by offer count
$topItems = $pdo->query("
    SELECT i.title, i.starting_price as price, COUNT(o.id) as offer_count,
           s.title as sale_title, sel.company_name as seller_name
    FROM yfc_items i
    LEFT JOIN yfc_offers o ON i.id = o.item_id
    LEFT JOIN yfc_sales s ON i.sale_id = s.id
    LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
    GROUP BY i.id
    HAVING offer_count > 0
    ORDER BY offer_count DESC
    LIMIT 10
")->fetchAll();

// Recent activity
$recentActivity = $pdo->query("
    SELECT 'offer' as type, o.created_at, i.title as item_title, b.name as buyer_name, o.offer_amount as amount
    FROM yfc_offers o
    LEFT JOIN yfc_items i ON o.item_id = i.id
    LEFT JOIN yfc_buyers b ON o.buyer_id = b.id
    UNION ALL
    SELECT 'sale' as type, s.created_at, s.title as item_title, sel.company_name as buyer_name, NULL as amount
    FROM yfc_sales s
    LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
    UNION ALL
    SELECT 'seller' as type, s.created_at, CONCAT('New seller: ', s.company_name) as item_title, NULL as buyer_name, NULL as amount
    FROM yfc_sellers s
    ORDER BY created_at DESC
    LIMIT 20
")->fetchAll();

// Price analytics
$priceStats = $pdo->query("
    SELECT 
        AVG(starting_price) as avg_price,
        MIN(starting_price) as min_price,
        MAX(starting_price) as max_price,
        COUNT(*) as total_items
    FROM yfc_items
")->fetch();

$offerStats = $pdo->query("
    SELECT 
        AVG(offer_amount) as avg_offer,
        MIN(offer_amount) as min_offer,
        MAX(offer_amount) as max_offer,
        COUNT(*) as total_offers
    FROM yfc_offers
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - YFClaim Admin</title>
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
        .date-filter {
            background: white;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .date-filter form {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .date-filter input, .date-filter button {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .date-filter button {
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        .section h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 1rem;
        }
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
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
        .price {
            font-weight: bold;
            color: #28a745;
        }
        .activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
        }
        .activity-type {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        .activity-type.offer {
            background: #ffc107;
            color: #000;
        }
        .activity-type.sale {
            background: #28a745;
            color: white;
        }
        .activity-type.seller {
            background: #007bff;
            color: white;
        }
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        .metric-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 3px;
        }
        .metric-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }
        .metric-label {
            font-size: 0.875rem;
            color: #666;
        }
        @media (max-width: 768px) {
            .two-column {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reports & Analytics</h1>
        <div class="header-nav">
            <a href="/claims/admin/">Dashboard</a>
            <a href="/admin/">YFEvents Admin</a>
            <a href="/admin/logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="nav">
            <a href="/claims/admin/index.php">Dashboard</a>
            <a href="/claims/admin/sellers.php">Manage Sellers</a>
            <a href="/claims/admin/sales.php">Manage Sales</a>
            <a href="/claims/admin/offers.php">Manage Offers</a>
            <a href="/claims/admin/buyers.php">Manage Buyers</a>
            <a href="/claims/admin/reports.php" class="active">Reports</a>
        </div>
        
        <div class="date-filter">
            <form method="get">
                <label>Date Range:</label>
                <input type="date" name="start_date" value="<?= $startDate ?>">
                <span>to</span>
                <input type="date" name="end_date" value="<?= $endDate ?>">
                <button type="submit">Update Report</button>
            </form>
        </div>
        
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_sellers'] ?></div>
                <div class="stat-label">Total Sellers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['active_sellers'] ?></div>
                <div class="stat-label">Active Sellers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_buyers'] ?></div>
                <div class="stat-label">Total Buyers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['active_sales'] ?></div>
                <div class="stat-label">Active Sales</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['available_items'] ?></div>
                <div class="stat-label">Available Items</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['sold_items'] ?></div>
                <div class="stat-label">Items Sold</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['pending_offers'] ?></div>
                <div class="stat-label">Pending Offers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['accepted_offers'] ?></div>
                <div class="stat-label">Accepted Offers</div>
            </div>
        </div>
        
        <div class="section">
            <h2>Activity in Selected Period (<?= date('M d, Y', strtotime($startDate)) ?> - <?= date('M d, Y', strtotime($endDate)) ?>)</h2>
            <div class="metric-grid">
                <div class="metric-item">
                    <div class="metric-value"><?= $dateStats['new_sales'] ?></div>
                    <div class="metric-label">New Sales</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value"><?= $dateStats['new_sellers'] ?></div>
                    <div class="metric-label">New Sellers</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value"><?= $dateStats['new_buyers'] ?></div>
                    <div class="metric-label">New Buyers</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value"><?= $dateStats['new_offers'] ?></div>
                    <div class="metric-label">New Offers</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>Price Analytics</h2>
            <div class="two-column">
                <div>
                    <h3>Item Prices</h3>
                    <div class="metric-grid">
                        <div class="metric-item">
                            <div class="metric-value price">$<?= number_format($priceStats['avg_price'], 2) ?></div>
                            <div class="metric-label">Average Price</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value price">$<?= number_format($priceStats['min_price'], 2) ?></div>
                            <div class="metric-label">Lowest Price</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value price">$<?= number_format($priceStats['max_price'], 2) ?></div>
                            <div class="metric-label">Highest Price</div>
                        </div>
                    </div>
                </div>
                <div>
                    <h3>Offer Amounts</h3>
                    <div class="metric-grid">
                        <div class="metric-item">
                            <div class="metric-value price">$<?= number_format($offerStats['avg_offer'], 2) ?></div>
                            <div class="metric-label">Average Offer</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value price">$<?= number_format($offerStats['min_offer'], 2) ?></div>
                            <div class="metric-label">Lowest Offer</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value price">$<?= number_format($offerStats['max_offer'], 2) ?></div>
                            <div class="metric-label">Highest Offer</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="two-column">
            <div class="section">
                <h2>Top Sellers by Sales Count</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Seller</th>
                            <th>Sales Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topSellers as $seller): ?>
                        <tr>
                            <td><?= htmlspecialchars($seller['name'] ?: $seller['fb_name'] ?: 'Unknown') ?></td>
                            <td><?= $seller['sale_count'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="section">
                <h2>Most Popular Items</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Price</th>
                            <th>Offers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topItems as $item): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($item['title']) ?>
                                <div style="font-size: 0.75rem; color: #666;">
                                    <?= htmlspecialchars($item['sale_title']) ?> by <?= htmlspecialchars($item['seller_name']) ?>
                                </div>
                            </td>
                            <td class="price">$<?= number_format($item['price'], 2) ?></td>
                            <td><?= $item['offer_count'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="section">
            <h2>Recent Activity</h2>
            <?php foreach ($recentActivity as $activity): ?>
                <div class="activity-item">
                    <div>
                        <span class="activity-type <?= $activity['type'] ?>"><?= $activity['type'] ?></span>
                        <span style="margin-left: 1rem;"><?= htmlspecialchars($activity['item_title']) ?></span>
                        <?php if ($activity['buyer_name']): ?>
                            <span style="color: #666; font-size: 0.875rem;">by <?= htmlspecialchars($activity['buyer_name']) ?></span>
                        <?php endif; ?>
                        <?php if ($activity['amount']): ?>
                            <span class="price" style="margin-left: 1rem;">$<?= number_format($activity['amount'], 2) ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="color: #666; font-size: 0.875rem;">
                        <?= date('M d, Y g:i A', strtotime($activity['created_at'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>