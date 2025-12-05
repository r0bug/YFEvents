<?php
session_start();

// Check if seller is logged in
if (!isset($_SESSION['claim_seller_logged_in']) || $_SESSION['claim_seller_logged_in'] !== true) {
    header('Location: /claims/admin/login.php');
    exit;
}

require_once __DIR__ . '/bootstrap.php';

use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\OfferModel;
use YFEvents\Modules\YFClaim\Models\SellerModel;

$saleModel = new SaleModel($pdo);
$offerModel = new OfferModel($pdo);
$sellerModel = new SellerModel($pdo);

$sellerId = $_SESSION['claim_seller_id'];
$seller = $sellerModel->find($sellerId);

// Get sale ID from URL
$saleId = $_GET['sale_id'] ?? null;
$sale = null;
$offers = [];

if ($saleId) {
    $sale = $saleModel->find($saleId);
    // Verify seller owns this sale
    if (!$sale || $sale['seller_id'] != $sellerId) {
        header('Location: sales.php');
        exit;
    }
    
    // Get offers for this sale
    $sql = "
        SELECT o.*, i.title as item_title, i.item_number, b.name as buyer_name, b.contact_info as buyer_contact
        FROM yfc_offers o
        JOIN yfc_items i ON o.item_id = i.id
        LEFT JOIN yfc_buyers b ON o.buyer_id = b.id
        WHERE i.sale_id = ?
        ORDER BY o.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$saleId]);
    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$success = false;
$error = '';

// Handle offer actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $offerId = $_POST['offer_id'] ?? '';
    
    if ($action && $offerId) {
        try {
            $offer = $offerModel->find($offerId);
            if ($offer) {
                if ($action === 'accept') {
                    $offerModel->update($offerId, ['status' => 'accepted']);
                    $success = "Offer accepted successfully!";
                } elseif ($action === 'decline') {
                    $offerModel->update($offerId, ['status' => 'declined']);
                    $success = "Offer declined.";
                }
                
                // Refresh offers
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$saleId]);
                $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>View Offers - YFClaim</title>
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
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
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
        
        .offers-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .offers-header {
            padding: 2rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .offers-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            padding: 1rem 2rem;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
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
        
        .filters {
            padding: 1rem 2rem;
            border-bottom: 1px solid #e9ecef;
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
        
        .offers-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .offer-card {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s;
        }
        
        .offer-card:hover {
            background: #f8f9fa;
        }
        
        .offer-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .offer-info h4 {
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .offer-meta {
            font-size: 0.9rem;
            color: #666;
        }
        
        .offer-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #27ae60;
        }
        
        .offer-status {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 0.5rem;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-accepted {
            background: #d4edda;
            color: #155724;
        }
        
        .status-declined {
            background: #f8d7da;
            color: #721c24;
        }
        
        .offer-details {
            margin-bottom: 1rem;
        }
        
        .offer-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .buyer-info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .buyer-info h5 {
            color: #1976d2;
            margin-bottom: 0.5rem;
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
        
        @media (max-width: 768px) {
            .offers-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                justify-content: space-between;
            }
            
            .offer-header {
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
                <a href="index.php">Dashboard</a> > <a href="sales.php">My Sales</a> > View Offers
            </div>
            <h1>💰 View Offers</h1>
            <?php if ($sale): ?>
                <p>Offers for: <strong><?= htmlspecialchars($sale['title']) ?></strong></p>
            <?php endif; ?>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
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
                <p>Please select a sale to view its offers.</p>
                <a href="sales.php" class="btn btn-primary">View My Sales</a>
            </div>
        <?php else: ?>
            <div class="offers-container">
                <div class="offers-header">
                    <div>
                        <h2>Offers (<?= count($offers) ?>)</h2>
                        <p>Review and manage buyer offers</p>
                    </div>
                    <a href="manage-items.php?sale_id=<?= $sale['id'] ?>" class="btn btn-secondary">Manage Items</a>
                </div>
                
                <?php if (!empty($offers)): ?>
                    <?php 
                    $totalOffers = count($offers);
                    $pendingOffers = count(array_filter($offers, fn($o) => $o['status'] === 'pending'));
                    $acceptedOffers = count(array_filter($offers, fn($o) => $o['status'] === 'accepted'));
                    $declinedOffers = count(array_filter($offers, fn($o) => $o['status'] === 'declined'));
                    ?>
                    <div class="offers-stats">
                        <div class="stat">
                            <div class="stat-value"><?= $totalOffers ?></div>
                            <div class="stat-label">Total Offers</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?= $pendingOffers ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?= $acceptedOffers ?></div>
                            <div class="stat-label">Accepted</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?= $declinedOffers ?></div>
                            <div class="stat-label">Declined</div>
                        </div>
                    </div>
                    
                    <div class="filters">
                        <div class="filter-group">
                            <label for="statusFilter">Status:</label>
                            <select id="statusFilter" onchange="filterOffers()">
                                <option value="all">All Offers</option>
                                <option value="pending">Pending</option>
                                <option value="accepted">Accepted</option>
                                <option value="declined">Declined</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="sortOrder">Sort by:</label>
                            <select id="sortOrder" onchange="filterOffers()">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="amount_high">Highest Amount</option>
                                <option value="amount_low">Lowest Amount</option>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="offers-list">
                    <?php if (empty($offers)): ?>
                        <div class="empty-state">
                            <h3>No Offers Yet</h3>
                            <p>Buyers haven't made any offers yet. Make sure your sale is active and items are visible!</p>
                            <a href="/claims/sale.php?id=<?= $sale['id'] ?>" class="btn btn-primary" target="_blank">View Public Sale Page</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($offers as $offer): ?>
                            <div class="offer-card" data-status="<?= $offer['status'] ?>" data-created="<?= strtotime($offer['created_at']) ?>" data-amount="<?= $offer['offer_amount'] ?>">
                                <div class="offer-header">
                                    <div class="offer-info">
                                        <h4><?= htmlspecialchars($offer['item_title']) ?></h4>
                                        <div class="offer-meta">
                                            <?php if ($offer['item_number']): ?>
                                                Item #<?= htmlspecialchars($offer['item_number']) ?> • 
                                            <?php endif; ?>
                                            Offered <?= date('M j, Y g:i A', strtotime($offer['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div class="offer-amount">$<?= number_format($offer['offer_amount'], 2) ?></div>
                                        <div class="offer-status status-<?= $offer['status'] ?>"><?= ucfirst($offer['status']) ?></div>
                                    </div>
                                </div>
                                
                                <?php if ($offer['buyer_name'] || $offer['buyer_contact']): ?>
                                <div class="buyer-info">
                                    <h5>Buyer Information</h5>
                                    <?php if ($offer['buyer_name']): ?>
                                        <p><strong>Name:</strong> <?= htmlspecialchars($offer['buyer_name']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($offer['buyer_contact']): ?>
                                        <p><strong>Contact:</strong> <?= htmlspecialchars($offer['buyer_contact']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($offer['notes']): ?>
                                <div class="offer-details">
                                    <strong>Buyer Notes:</strong> <?= htmlspecialchars($offer['notes']) ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($offer['status'] === 'pending'): ?>
                                <div class="offer-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="accept">
                                        <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-small" onclick="return confirm('Accept this offer?')">Accept Offer</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="decline">
                                        <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('Decline this offer?')">Decline</button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function filterOffers() {
            const statusFilter = document.getElementById('statusFilter').value;
            const sortOrder = document.getElementById('sortOrder').value;
            const offersList = document.querySelector('.offers-list');
            const offerCards = Array.from(offersList.querySelectorAll('.offer-card'));
            
            // Filter offers
            offerCards.forEach(card => {
                const cardStatus = card.dataset.status;
                if (statusFilter === 'all' || statusFilter === cardStatus) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Sort offers
            const visibleCards = offerCards.filter(card => card.style.display !== 'none');
            visibleCards.sort((a, b) => {
                switch (sortOrder) {
                    case 'newest':
                        return parseInt(b.dataset.created) - parseInt(a.dataset.created);
                    case 'oldest':
                        return parseInt(a.dataset.created) - parseInt(b.dataset.created);
                    case 'amount_high':
                        return parseFloat(b.dataset.amount) - parseFloat(a.dataset.amount);
                    case 'amount_low':
                        return parseFloat(a.dataset.amount) - parseFloat(b.dataset.amount);
                    default:
                        return 0;
                }
            });
            
            // Reorder in DOM
            visibleCards.forEach(card => offersList.appendChild(card));
        }
    </script>
</body>
</html>