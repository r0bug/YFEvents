<?php
// YFClaim - My Offers Page
require_once __DIR__ . '/bootstrap.php';

use YFEvents\Modules\YFClaim\Models\BuyerModel;
use YFEvents\Modules\YFClaim\Models\OfferModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;

// Initialize models
$buyerModel = new BuyerModel($pdo);
$offerModel = new OfferModel($pdo);
$itemModel = new ItemModel($pdo);
$saleModel = new SaleModel($pdo);

// Start session and check authentication
session_start();
$currentBuyer = null;

if (isset($_SESSION['buyer_token'])) {
    $currentBuyer = $buyerModel->validateSession($_SESSION['buyer_token']);
}

if (!$currentBuyer) {
    header('Location: /claims/');
    exit;
}

// Get buyer's offers
$offers = $buyerModel->getOffers($currentBuyer['id']);
$stats = $buyerModel->getStats($currentBuyer['id']);

// Group offers by status
$activeOffers = [];
$winningOffers = [];
$otherOffers = [];

foreach ($offers as $offer) {
    if ($offer['status'] === 'active') {
        $activeOffers[] = $offer;
    } elseif ($offer['status'] === 'winning') {
        $winningOffers[] = $offer;
    } else {
        $otherOffers[] = $offer;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Offers - YFClaim</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
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
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
        }
        
        .nav-links a:hover {
            opacity: 0.8;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            margin-bottom: 2rem;
            color: #2c3e50;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #7f8c8d;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        
        .offers-section {
            margin-bottom: 3rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            color: #2c3e50;
        }
        
        .badge {
            background: #e74c3c;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        
        .badge.active {
            background: #3498db;
        }
        
        .badge.winning {
            background: #27ae60;
        }
        
        .offers-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #666;
            border-bottom: 2px solid #e9ecef;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .item-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .item-number {
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        .offer-amount {
            font-size: 1.1rem;
            font-weight: bold;
            color: #27ae60;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #3498db;
            color: white;
        }
        
        .status-winning {
            background: #27ae60;
            color: white;
        }
        
        .status-outbid {
            background: #f39c12;
            color: white;
        }
        
        .status-rejected {
            background: #e74c3c;
            color: white;
        }
        
        .status-expired {
            background: #95a5a6;
            color: white;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #7f8c8d;
        }
        
        .winning-alert {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #c3e6cb;
        }
        
        .winning-alert h3 {
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .offers-table {
                overflow-x: auto;
            }
            
            table {
                min-width: 600px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="nav-links">
                <a href="/claims/">← Back to Sales</a>
            </div>
            <div class="nav-links">
                <span>Welcome, <?= htmlspecialchars($currentBuyer['name']) ?></span>
                <a href="/claims/logout.php">Logout</a>
            </div>
        </div>
    </header>
    
    <main class="container">
        <h1 class="page-title">My Offers</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_offers'] ?></div>
                <div class="stat-label">Total Offers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['active_offers'] ?></div>
                <div class="stat-label">Active Offers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['winning_offers'] ?></div>
                <div class="stat-label">Winning Offers</div>
            </div>
        </div>
        
        <?php if (!empty($winningOffers)): ?>
            <div class="winning-alert">
                <h3>🎉 Congratulations!</h3>
                <p>You have <?= count($winningOffers) ?> winning offer<?= count($winningOffers) != 1 ? 's' : '' ?>. Please check the pickup details for each item.</p>
            </div>
        <?php endif; ?>
        
        <!-- Winning Offers -->
        <?php if (!empty($winningOffers)): ?>
            <section class="offers-section">
                <div class="section-header">
                    <h2 class="section-title">Winning Offers</h2>
                    <span class="badge winning"><?= count($winningOffers) ?></span>
                </div>
                
                <div class="offers-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Sale</th>
                                <th>My Offer</th>
                                <th>Pickup Details</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($winningOffers as $offer): ?>
                                <?php
                                $sale = $saleModel->getSaleById($offer['sale_id']);
                                ?>
                                <tr>
                                    <td>
                                        <div class="item-title"><?= htmlspecialchars($offer['item_title']) ?></div>
                                        <div class="item-number">Item #<?= htmlspecialchars($offer['item_number']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($offer['sale_title']) ?></td>
                                    <td>
                                        <div class="offer-amount">$<?= number_format($offer['offer_amount'], 2) ?></div>
                                    </td>
                                    <td>
                                        <div><?= date('M j - M j', strtotime($sale['pickup_start']), strtotime($sale['pickup_end'])) ?></div>
                                        <div style="font-size: 0.9rem; color: #7f8c8d;">
                                            <?= htmlspecialchars($sale['address']) ?><br>
                                            <?= htmlspecialchars($sale['city']) ?>, <?= htmlspecialchars($sale['state']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="/claims/claim-details.php?offer=<?= $offer['id'] ?>" class="btn btn-primary">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
        
        <!-- Active Offers -->
        <?php if (!empty($activeOffers)): ?>
            <section class="offers-section">
                <div class="section-header">
                    <h2 class="section-title">Active Offers</h2>
                    <span class="badge active"><?= count($activeOffers) ?></span>
                </div>
                
                <div class="offers-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Sale</th>
                                <th>My Offer</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeOffers as $offer): ?>
                                <tr>
                                    <td>
                                        <div class="item-title"><?= htmlspecialchars($offer['item_title']) ?></div>
                                        <div class="item-number">Item #<?= htmlspecialchars($offer['item_number']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($offer['sale_title']) ?></td>
                                    <td>
                                        <div class="offer-amount">$<?= number_format($offer['offer_amount'], 2) ?></div>
                                        <div style="font-size: 0.9rem; color: #7f8c8d;">
                                            <?= date('M j, g:i A', strtotime($offer['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-active">Active</span>
                                    </td>
                                    <td>
                                        <a href="/claims/item.php?id=<?= $offer['item_id'] ?>" class="btn btn-primary">
                                            Update Offer
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
        
        <!-- Other Offers -->
        <?php if (!empty($otherOffers)): ?>
            <section class="offers-section">
                <div class="section-header">
                    <h2 class="section-title">Past Offers</h2>
                </div>
                
                <div class="offers-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Sale</th>
                                <th>My Offer</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($otherOffers as $offer): ?>
                                <tr>
                                    <td>
                                        <div class="item-title"><?= htmlspecialchars($offer['item_title']) ?></div>
                                        <div class="item-number">Item #<?= htmlspecialchars($offer['item_number']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($offer['sale_title']) ?></td>
                                    <td>
                                        <div class="offer-amount">$<?= number_format($offer['offer_amount'], 2) ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $offer['status'] ?>"><?= ucfirst($offer['status']) ?></span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($offer['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
        
        <?php if (empty($offers)): ?>
            <div class="empty-state">
                <h2>No Offers Yet</h2>
                <p>You haven't made any offers. <a href="/claims/">Browse current sales</a> to get started!</p>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>