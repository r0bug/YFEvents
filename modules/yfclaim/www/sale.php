<?php
// YFClaim - Sale Detail Page
require_once __DIR__ . '/bootstrap.php';

use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\SellerModel;
use YFEvents\Modules\YFClaim\Models\OfferModel;
use YFEvents\Modules\YFClaim\Models\BuyerModel;

// Initialize models
$saleModel = new SaleModel($pdo);
$itemModel = new ItemModel($pdo);
$sellerModel = new SellerModel($pdo);
$offerModel = new OfferModel($pdo);
$buyerModel = new BuyerModel($pdo);

// Get sale ID
$saleId = $_GET['id'] ?? 0;
$isPreview = isset($_GET['preview']);

// Get sale details
$sale = $saleModel->getWithSeller($saleId);
if (!$sale) {
    header('Location: /claims/');
    exit;
}

// Check if sale is active or in preview
$now = time();
$previewStart = $sale['preview_start'] ? strtotime($sale['preview_start']) : 0;
$claimStart = strtotime($sale['claim_start']);
$claimEnd = strtotime($sale['claim_end']);

$canView = false;
$canClaim = false;
$status = '';

if ($now >= $claimStart && $now <= $claimEnd) {
    $canView = true;
    $canClaim = true;
    $status = 'active';
} elseif ($previewStart && $now >= $previewStart && $now < $claimStart) {
    $canView = true;
    $canClaim = false;
    $status = 'preview';
} elseif ($now < $claimStart) {
    $canView = $isPreview; // Only if explicitly previewing
    $canClaim = false;
    $status = 'upcoming';
} else {
    $canView = true;
    $canClaim = false;
    $status = 'ended';
}

if (!$canView) {
    header('Location: /claims/');
    exit;
}

// Get items with images and offer info
$items = $itemModel->getWithPrimaryImages($saleId);

// Get categories
$categories = [];
foreach ($items as $item) {
    if ($item['category'] && !in_array($item['category'], $categories)) {
        $categories[] = $item['category'];
    }
}
sort($categories);

// Add offer information to items
foreach ($items as &$item) {
    $item['offer_count'] = $offerModel->count(['item_id' => $item['id'], 'status' => 'active']);
    $item['highest_offer'] = $offerModel->getHighest($item['id']);
    
    // Get price range if sale shows price ranges
    if ($sale['show_price_ranges']) {
        $item['price_range'] = $itemModel->getPriceRange($item['id']);
    }
}

// Get sale statistics
$stats = $saleModel->getStats($saleId);

// Start session for buyer authentication
session_start();
$currentBuyer = null;
if (isset($_SESSION['buyer_token'])) {
    $currentBuyer = $buyerModel->validateSession($_SESSION['buyer_token']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($sale['title']) ?> - Estate Sale in <?= htmlspecialchars($sale['city']) ?>, <?= htmlspecialchars($sale['state']) ?> | YFClaim</title>
    
    <?php 
    $currentUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $saleImage = 'https://' . $_SERVER['HTTP_HOST'] . '/claims/assets/estate-sale-default.jpg';
    $saleDescription = $sale['description'] ? strip_tags($sale['description']) : 'Estate sale featuring ' . count($items) . ' items including furniture, antiques, and collectibles.';
    $saleLocation = $sale['address'] . ', ' . $sale['city'] . ', ' . $sale['state'];
    $claimDates = date('M j, Y', strtotime($sale['claim_start'])) . ' - ' . date('M j, Y', strtotime($sale['claim_end']));
    ?>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= htmlspecialchars($saleDescription) ?> | Claim period: <?= $claimDates ?> | <?= count($items) ?> items available | <?= htmlspecialchars($sale['company_name']) ?>">
    <meta name="keywords" content="estate sale, <?= htmlspecialchars($sale['city']) ?> estate sale, <?= htmlspecialchars($sale['state']) ?> estate sales, antiques, furniture, collectibles, auction, <?= implode(', ', array_unique($categories)) ?>">
    <meta name="robots" content="index, follow">
    <meta name="author" content="<?= htmlspecialchars($sale['company_name']) ?>">
    <meta name="geo.region" content="US-<?= htmlspecialchars($sale['state']) ?>">
    <meta name="geo.placename" content="<?= htmlspecialchars($sale['city']) ?>, <?= htmlspecialchars($sale['state']) ?>">
    
    <!-- Open Graph Meta Tags for Facebook -->
    <meta property="og:title" content="<?= htmlspecialchars($sale['title']) ?> - Estate Sale">
    <meta property="og:description" content="<?= htmlspecialchars($saleDescription) ?> Claim period: <?= $claimDates ?>">
    <meta property="og:image" content="<?= $saleImage ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="<?= $currentUrl ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="YFClaim Estate Sales">
    <meta property="og:locale" content="en_US">
    
    <!-- Event-specific Open Graph -->
    <meta property="event:start_time" content="<?= date('c', strtotime($sale['claim_start'])) ?>">
    <meta property="event:end_time" content="<?= date('c', strtotime($sale['claim_end'])) ?>">
    <meta property="place:location:latitude" content="<?= $sale['latitude'] ?>">
    <meta property="place:location:longitude" content="<?= $sale['longitude'] ?>">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($sale['title']) ?> - Estate Sale">
    <meta name="twitter:description" content="<?= htmlspecialchars($saleDescription) ?>">
    <meta name="twitter:image" content="<?= $saleImage ?>">
    
    <!-- Schema.org Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org/",
        "@type": "Event",
        "name": "<?= htmlspecialchars($sale['title']) ?>",
        "description": "<?= htmlspecialchars($saleDescription) ?>",
        "image": "<?= $saleImage ?>",
        "startDate": "<?= date('c', strtotime($sale['claim_start'])) ?>",
        "endDate": "<?= date('c', strtotime($sale['claim_end'])) ?>",
        "eventStatus": "https://schema.org/EventScheduled",
        "eventAttendanceMode": "https://schema.org/OfflineEventAttendanceMode",
        "location": {
            "@type": "Place",
            "name": "<?= htmlspecialchars($sale['title']) ?>",
            "address": {
                "@type": "PostalAddress",
                "streetAddress": "<?= htmlspecialchars($sale['address']) ?>",
                "addressLocality": "<?= htmlspecialchars($sale['city']) ?>",
                "addressRegion": "<?= htmlspecialchars($sale['state']) ?>",
                "postalCode": "<?= htmlspecialchars($sale['zip']) ?>"
            },
            "geo": {
                "@type": "GeoCoordinates",
                "latitude": "<?= $sale['latitude'] ?>",
                "longitude": "<?= $sale['longitude'] ?>"
            }
        },
        "organizer": {
            "@type": "Organization",
            "name": "<?= htmlspecialchars($sale['company_name']) ?>",
            "url": "<?= htmlspecialchars($sale['website'] ?: 'https://' . $_SERVER['HTTP_HOST']) ?>"
        },
        "offers": [
            <?php foreach (array_slice($items, 0, 5) as $index => $item): ?>
            {
                "@type": "Offer",
                "itemOffered": {
                    "@type": "Product",
                    "name": "<?= htmlspecialchars($item['title']) ?>",
                    "category": "<?= htmlspecialchars($item['category']) ?>"
                },
                "price": "<?= $item['starting_price'] ?>",
                "priceCurrency": "USD",
                "availability": "<?= $item['status'] === 'available' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' ?>"
            }<?= $index < min(4, count($items) - 1) ? ',' : '' ?>
            <?php endforeach; ?>
        ]
    }
    </script>
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
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .back-link:hover {
            opacity: 0.8;
        }
        
        .buyer-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .sale-hero {
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
            color: white;
            padding: 3rem 0;
        }
        
        .sale-hero-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .sale-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .sale-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        
        .sale-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-banner {
            background: #e74c3c;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            display: inline-block;
            margin-top: 1rem;
        }
        
        .status-banner.active {
            background: #27ae60;
        }
        
        .status-banner.preview {
            background: #f39c12;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filter-group {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group label {
            font-weight: 600;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .item-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }
        
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .item-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }
        
        .item-body {
            padding: 1.5rem;
        }
        
        .item-number {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .item-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        
        .item-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .item-price {
            font-size: 1.3rem;
            font-weight: bold;
            color: #27ae60;
        }
        
        .item-condition {
            display: flex;
            gap: 2px;
        }
        
        .star {
            color: #f39c12;
        }
        
        .item-offers {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 5px;
            text-align: center;
        }
        
        .offer-count {
            font-weight: 600;
            color: #3498db;
        }
        
        .item-status {
            text-align: center;
            padding: 0.5rem;
            background: #e74c3c;
            color: white;
            font-weight: 600;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 0.75rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s;
            margin-top: 1rem;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-disabled {
            background: #95a5a6;
            color: white;
            cursor: not-allowed;
        }
        
        .stats-bar {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        
        .stat {
            flex: 1;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-label {
            color: #7f8c8d;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #7f8c8d;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        @media (max-width: 768px) {
            .sale-title {
                font-size: 1.8rem;
            }
            
            .items-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-bar {
                flex-wrap: wrap;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="/claims/" class="back-link">
                ← Back to Sales
            </a>
            <div class="buyer-info">
                <?php if ($currentBuyer): ?>
                    <span>Welcome, <?= htmlspecialchars($currentBuyer['name']) ?></span>
                    <a href="/claims/my-offers.php" style="color: white;">My Offers</a>
                    <a href="/claims/logout.php" style="color: white;">Logout</a>
                <?php elseif ($canClaim): ?>
                    <a href="/claims/item.php?sale=<?= $saleId ?>" class="btn btn-primary" style="width: auto; margin: 0; padding: 0.5rem 1rem;">
                        Sign In to Make Offers
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <section class="sale-hero">
        <div class="sale-hero-content">
            <h1 class="sale-title"><?= htmlspecialchars($sale['title']) ?></h1>
            <div class="sale-meta">
                <div class="sale-meta-item">
                    <span>🏢</span>
                    <span><?= htmlspecialchars($sale['company_name']) ?></span>
                </div>
                <div class="sale-meta-item">
                    <span>📍</span>
                    <span><?= htmlspecialchars($sale['address']) ?>, <?= htmlspecialchars($sale['city']) ?>, <?= htmlspecialchars($sale['state']) ?> <?= htmlspecialchars($sale['zip']) ?></span>
                </div>
                <?php if ($status === 'active'): ?>
                    <div class="sale-meta-item">
                        <span>⏰</span>
                        <span>Ends <?= date('M j \a\t g:i A', $claimEnd) ?></span>
                    </div>
                <?php elseif ($status === 'preview'): ?>
                    <div class="sale-meta-item">
                        <span>🔓</span>
                        <span>Claims open <?= date('M j \a\t g:i A', $claimStart) ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($sale['description']): ?>
                <p><?= nl2br(htmlspecialchars($sale['description'])) ?></p>
            <?php endif; ?>
            
            <div class="status-banner <?= $status ?>">
                <?php if ($status === 'active'): ?>
                    ✅ Claiming is OPEN - Make your offers now!
                <?php elseif ($status === 'preview'): ?>
                    👀 Preview Mode - Claiming opens soon
                <?php elseif ($status === 'upcoming'): ?>
                    🔒 Sale Not Yet Available
                <?php else: ?>
                    ⏹️ Sale Has Ended
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <main class="container">
        <div class="stats-bar">
            <div class="stat">
                <div class="stat-value"><?= $stats['total_items'] ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= $stats['total_offers'] ?></div>
                <div class="stat-label">Offers Made</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= $stats['items_with_offers'] ?></div>
                <div class="stat-label">Items with Offers</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= $stats['claimed_items'] ?></div>
                <div class="stat-label">Items Claimed</div>
            </div>
        </div>
        
        <div class="filters">
            <div class="filter-group">
                <label>Category:</label>
                <select id="categoryFilter" onchange="filterItems()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <label>Sort by:</label>
                <select id="sortBy" onchange="filterItems()">
                    <option value="number">Item Number</option>
                    <option value="title">Title</option>
                    <option value="price-low">Price: Low to High</option>
                    <option value="price-high">Price: High to Low</option>
                    <option value="offers">Most Offers</option>
                </select>
                
                <label>Search:</label>
                <input type="text" id="searchBox" placeholder="Search items..." onkeyup="filterItems()">
            </div>
        </div>
        
        <?php if (empty($items)): ?>
            <div class="empty-state">
                <h2>No Items Listed Yet</h2>
                <p>Items will be added soon. Please check back later!</p>
            </div>
        <?php else: ?>
            <div class="items-grid" id="itemsGrid">
                <?php foreach ($items as $item): ?>
                    <div class="item-card" data-category="<?= htmlspecialchars($item['category']) ?>" 
                         data-title="<?= htmlspecialchars(strtolower($item['title'])) ?>"
                         data-number="<?= $item['item_number'] ?>"
                         data-price="<?= $item['starting_price'] ?>"
                         data-offers="<?= $item['offer_count'] ?>"
                         onclick="viewItem(<?= $item['id'] ?>)">
                        
                        <?php if ($item['status'] === 'claimed'): ?>
                            <div class="item-status">CLAIMED</div>
                        <?php endif; ?>
                        
                        <?php if ($item['primary_image']): ?>
                            <img src="/uploads/yfclaim/items/<?= htmlspecialchars($item['primary_image']) ?>" 
                                 alt="<?= htmlspecialchars($item['title']) ?>" 
                                 class="item-image">
                        <?php else: ?>
                            <div class="item-image">No Image</div>
                        <?php endif; ?>
                        
                        <div class="item-body">
                            <div class="item-number">Item #<?= htmlspecialchars($item['item_number']) ?></div>
                            <h3 class="item-title"><?= htmlspecialchars($item['title']) ?></h3>
                            
                            <div class="item-details">
                                <div class="item-price">
                                    <?php if ($item['starting_price'] > 0): ?>
                                        $<?= number_format($item['starting_price'], 2) ?>
                                    <?php else: ?>
                                        Make Offer
                                    <?php endif; ?>
                                </div>
                                <div class="item-condition">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star"><?= $i <= $item['condition_rating'] ? '★' : '☆' ?></span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="item-offers">
                                <?php if ($item['offer_count'] > 0): ?>
                                    <span class="offer-count"><?= $item['offer_count'] ?> offer<?= $item['offer_count'] != 1 ? 's' : '' ?></span>
                                    <?php if ($sale['show_price_ranges'] && $item['price_range']): ?>
                                        <br>
                                        <small>$<?= number_format($item['price_range']['min_offer'], 2) ?> - $<?= number_format($item['price_range']['max_offer'], 2) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span>No offers yet</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($canClaim && $item['status'] !== 'claimed'): ?>
                                <button class="btn btn-primary">View & Make Offer</button>
                            <?php elseif ($item['status'] === 'claimed'): ?>
                                <button class="btn btn-disabled" disabled>Claimed</button>
                            <?php else: ?>
                                <button class="btn btn-primary">View Details</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <script>
        function filterItems() {
            const category = document.getElementById('categoryFilter').value.toLowerCase();
            const sortBy = document.getElementById('sortBy').value;
            const search = document.getElementById('searchBox').value.toLowerCase();
            const grid = document.getElementById('itemsGrid');
            const items = Array.from(grid.children);
            
            // Filter items
            items.forEach(item => {
                const itemCategory = item.dataset.category.toLowerCase();
                const itemTitle = item.dataset.title;
                const itemNumber = item.dataset.number;
                
                let show = true;
                
                if (category && itemCategory !== category) {
                    show = false;
                }
                
                if (search && !itemTitle.includes(search) && !itemNumber.includes(search)) {
                    show = false;
                }
                
                item.style.display = show ? 'block' : 'none';
            });
            
            // Sort visible items
            const visibleItems = items.filter(item => item.style.display !== 'none');
            
            visibleItems.sort((a, b) => {
                switch (sortBy) {
                    case 'title':
                        return a.dataset.title.localeCompare(b.dataset.title);
                    case 'price-low':
                        return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                    case 'price-high':
                        return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                    case 'offers':
                        return parseInt(b.dataset.offers) - parseInt(a.dataset.offers);
                    default: // number
                        return parseInt(a.dataset.number) - parseInt(b.dataset.number);
                }
            });
            
            // Reorder in DOM
            visibleItems.forEach(item => grid.appendChild(item));
        }
        
        function viewItem(itemId) {
            window.location.href = `/claims/item.php?id=${itemId}`;
        }
    </script>
</body>
</html>