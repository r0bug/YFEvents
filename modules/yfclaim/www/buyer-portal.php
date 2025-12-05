<?php
/**
 * YFClaim Enhanced Buyer Portal
 * Modern interface for buyers to browse sales and make offers
 */

require_once __DIR__ . '/bootstrap.php';

use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\BuyerModel;

// Initialize models
$saleModel = new SaleModel($pdo);
$itemModel = new ItemModel($pdo);
$buyerModel = new BuyerModel($pdo);

// Check authentication
session_start();
$currentBuyer = null;
$currentSale = null;

if (isset($_SESSION['buyer_token'])) {
    $currentBuyer = $buyerModel->validateSession($_SESSION['buyer_token']);
}

// Get sale ID from URL or session
$saleId = $_GET['sale_id'] ?? ($currentBuyer['sale_id'] ?? null);

if ($saleId) {
    $currentSale = $saleModel->find($saleId);
}

// Get current and upcoming sales for display
$currentSales = $saleModel->getCurrent();
$upcomingSales = $saleModel->getUpcoming();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFClaim - Estate Sale Buyer Portal</title>
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
        }
        
        .buyer-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .auth-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .sales-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .sale-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .sale-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.15);
        }
        
        .sale-header {
            background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%);
            color: white;
            padding: 1rem;
        }
        
        .sale-content {
            padding: 1.5rem;
        }
        
        .sale-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .sale-dates {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .item-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .item-card:hover {
            transform: translateY(-2px);
        }
        
        .item-image {
            width: 100%;
            height: 200px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
        }
        
        .item-content {
            padding: 1rem;
        }
        
        .item-title {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .item-price {
            color: #43a047;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .offer-info {
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 6px;
            font-size: 0.9rem;
            margin-bottom: 1rem;
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
            background: #43a047;
        }
        
        .btn-success:hover {
            background: #388e3c;
        }
        
        .btn-danger {
            background: #e53e3e;
        }
        
        .btn-danger:hover {
            background: #c53030;
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
            margin: 10% auto;
            padding: 2rem;
            border-radius: 12px;
            max-width: 500px;
            position: relative;
        }
        
        .close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
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
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
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
            background: #d4edda;
            color: #155724;
        }
        
        .status-winning {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-outbid {
            background: #f8d7da;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .sales-grid,
            .items-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                margin: 5% 1rem;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">🏷️ YFClaim</div>
            <div class="buyer-info">
                <?php if ($currentBuyer): ?>
                    <span>Welcome, <?= htmlspecialchars($currentBuyer['name']) ?></span>
                    <button class="btn" onclick="logout()">Logout</button>
                <?php else: ?>
                    <button class="btn" onclick="showAuthModal()">Join Sale</button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if (!$currentBuyer): ?>
            <!-- Authentication Section -->
            <div class="auth-section">
                <h2>Welcome to YFClaim</h2>
                <p>Join an estate sale to browse items and make offers. Select a sale below to get started.</p>
            </div>
        <?php endif; ?>

        <!-- Current Sales -->
        <?php if (!empty($currentSales)): ?>
            <h2>🔥 Current Sales (Accepting Offers)</h2>
            <div class="sales-grid">
                <?php foreach ($currentSales as $sale): ?>
                    <div class="sale-card">
                        <div class="sale-header">
                            <div class="sale-title"><?= htmlspecialchars($sale['title']) ?></div>
                            <div class="sale-dates">
                                Claim Period: <?= date('M j, g:i A', strtotime($sale['claim_start'])) ?> - 
                                <?= date('M j, g:i A', strtotime($sale['claim_end'])) ?>
                            </div>
                        </div>
                        <div class="sale-content">
                            <p><?= htmlspecialchars($sale['description']) ?></p>
                            <p><strong>📍 <?= htmlspecialchars($sale['address']) ?>, <?= htmlspecialchars($sale['city']) ?></strong></p>
                            <p><strong>Company:</strong> <?= htmlspecialchars($sale['company_name']) ?></p>
                            
                            <?php if ($currentBuyer && $currentBuyer['sale_id'] == $sale['id']): ?>
                                <button class="btn btn-success" onclick="browseSale(<?= $sale['id'] ?>)">Browse Items</button>
                            <?php elseif (!$currentBuyer): ?>
                                <button class="btn" onclick="joinSale(<?= $sale['id'] ?>)">Join This Sale</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Upcoming Sales -->
        <?php if (!empty($upcomingSales)): ?>
            <h2>⏰ Upcoming Sales</h2>
            <div class="sales-grid">
                <?php foreach ($upcomingSales as $sale): ?>
                    <div class="sale-card">
                        <div class="sale-header">
                            <div class="sale-title"><?= htmlspecialchars($sale['title']) ?></div>
                            <div class="sale-dates">
                                Starts: <?= date('M j, Y g:i A', strtotime($sale['claim_start'])) ?>
                            </div>
                        </div>
                        <div class="sale-content">
                            <p><?= htmlspecialchars($sale['description']) ?></p>
                            <p><strong>📍 <?= htmlspecialchars($sale['address']) ?>, <?= htmlspecialchars($sale['city']) ?></strong></p>
                            <p><strong>Company:</strong> <?= htmlspecialchars($sale['company_name']) ?></p>
                            <p><em>Registration will open when the sale starts.</em></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Items Section (if buyer is authenticated and sale is selected) -->
        <?php if ($currentBuyer && $currentSale): ?>
            <div id="items-section">
                <h2>🛍️ Items in <?= htmlspecialchars($currentSale['title']) ?></h2>
                <div id="items-container">
                    <div style="text-align: center; padding: 2rem;">
                        <div class="loading"></div>
                        <p>Loading items...</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Authentication Modal -->
    <div id="authModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAuthModal()">&times;</span>
            <div id="authContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Offer Modal -->
    <div id="offerModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeOfferModal()">&times;</span>
            <div id="offerContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        let currentSaleId = null;
        let currentBuyer = <?= $currentBuyer ? json_encode($currentBuyer) : 'null' ?>;

        // Authentication functions
        function showAuthModal() {
            document.getElementById('authModal').style.display = 'block';
            showJoinForm();
        }

        function closeAuthModal() {
            document.getElementById('authModal').style.display = 'none';
        }

        function joinSale(saleId) {
            currentSaleId = saleId;
            showAuthModal();
        }

        function showJoinForm() {
            document.getElementById('authContent').innerHTML = `
                <h3>Join Estate Sale</h3>
                <div id="authAlert"></div>
                <form id="joinForm">
                    <div class="form-group">
                        <label class="form-label">Your Name</label>
                        <input type="text" class="form-input" name="name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Method</label>
                        <select class="form-input" name="auth_method" onchange="updateContactField()">
                            <option value="email">Email</option>
                            <option value="phone">Phone/SMS</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" id="contactLabel">Email Address</label>
                        <input type="email" class="form-input" name="contact" id="contactInput" required>
                    </div>
                    <button type="submit" class="btn">Send Verification Code</button>
                </form>
            `;
            
            document.getElementById('joinForm').onsubmit = function(e) {
                e.preventDefault();
                joinSaleSubmit();
            };
        }

        function updateContactField() {
            const authMethod = document.querySelector('[name="auth_method"]').value;
            const label = document.getElementById('contactLabel');
            const input = document.getElementById('contactInput');
            
            if (authMethod === 'email') {
                label.textContent = 'Email Address';
                input.type = 'email';
                input.placeholder = 'your@email.com';
            } else {
                label.textContent = 'Phone Number';
                input.type = 'tel';
                input.placeholder = '(555) 123-4567';
            }
        }

        async function joinSaleSubmit() {
            const form = document.getElementById('joinForm');
            const formData = new FormData(form);
            formData.append('action', 'register');
            formData.append('sale_id', currentSaleId);

            try {
                const response = await fetch('/claims/api/buyer-auth.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showVerificationForm(result.buyer_id, result.auth_method, result.contact);
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred. Please try again.', 'error');
            }
        }

        function showVerificationForm(buyerId, authMethod, contact) {
            document.getElementById('authContent').innerHTML = `
                <h3>Verification Required</h3>
                <div id="authAlert"></div>
                <p>We've sent a verification code to ${contact}. Please enter it below:</p>
                <form id="verifyForm">
                    <div class="form-group">
                        <label class="form-label">Verification Code</label>
                        <input type="text" class="form-input" name="auth_code" placeholder="123456" required>
                    </div>
                    <button type="submit" class="btn">Verify & Join Sale</button>
                    <button type="button" class="btn" onclick="resendCode(${buyerId})">Resend Code</button>
                </form>
            `;
            
            document.getElementById('verifyForm').onsubmit = function(e) {
                e.preventDefault();
                verifyCode(buyerId);
            };
        }

        async function verifyCode(buyerId) {
            const form = document.getElementById('verifyForm');
            const formData = new FormData(form);
            formData.append('action', 'verify');
            formData.append('buyer_id', buyerId);

            try {
                const response = await fetch('/claims/api/buyer-auth.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    currentBuyer = result.buyer;
                    closeAuthModal();
                    location.reload(); // Refresh to show items
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred. Please try again.', 'error');
            }
        }

        async function resendCode(buyerId) {
            const formData = new FormData();
            formData.append('action', 'resend');
            formData.append('buyer_id', buyerId);

            try {
                const response = await fetch('/claims/api/buyer-auth.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                showAlert(result.message, result.success ? 'success' : 'error');
            } catch (error) {
                showAlert('An error occurred. Please try again.', 'error');
            }
        }

        async function logout() {
            try {
                const response = await fetch('/claims/api/buyer-auth.php', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'logout' })
                });

                const result = await response.json();
                if (result.success) {
                    location.reload();
                }
            } catch (error) {
                console.error('Logout error:', error);
                location.reload();
            }
        }

        function showAlert(message, type) {
            const alertDiv = document.getElementById('authAlert');
            alertDiv.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
        }

        // Items functions
        function browseSale(saleId) {
            window.location.href = `?sale_id=${saleId}`;
        }

        // Load items if buyer is authenticated
        if (currentBuyer && <?= $currentSale ? $currentSale['id'] : 'null' ?>) {
            loadItems(<?= $currentSale['id'] ?>);
        }

        async function loadItems(saleId) {
            try {
                const response = await fetch(`/claims/api/items.php?action=get_sale_items&sale_id=${saleId}`);
                const result = await response.json();

                if (result.success) {
                    displayItems(result.items);
                } else {
                    document.getElementById('items-container').innerHTML = `
                        <div style="text-align: center; padding: 2rem;">
                            <p>Unable to load items. Please refresh the page.</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading items:', error);
            }
        }

        function displayItems(items) {
            const container = document.getElementById('items-container');
            
            if (items.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 2rem;">
                        <p>No items available yet. Check back soon!</p>
                    </div>
                `;
                return;
            }

            const itemsHtml = items.map(item => `
                <div class="item-card">
                    <div class="item-image">
                        ${item.primary_image ? 
                            `<img src="/modules/yfclaim/uploads/${item.primary_image}" alt="${item.title}" style="width: 100%; height: 100%; object-fit: cover;">` :
                            '📦 No Image'
                        }
                    </div>
                    <div class="item-content">
                        <div class="item-title">${item.title}</div>
                        ${item.starting_price ? `<div class="item-price">Starting: $${parseFloat(item.starting_price).toFixed(2)}</div>` : ''}
                        <div class="offer-info">
                            <div>Offers: ${item.offer_count || 0}</div>
                            ${item.current_high_offer ? `<div>High Offer: $${parseFloat(item.current_high_offer).toFixed(2)}</div>` : ''}
                        </div>
                        <button class="btn" onclick="makeOffer(${item.id}, '${item.title}')">Make Offer</button>
                    </div>
                </div>
            `).join('');

            container.innerHTML = `<div class="items-grid">${itemsHtml}</div>`;
        }

        // Offer functions
        function makeOffer(itemId, itemTitle) {
            document.getElementById('offerModal').style.display = 'block';
            document.getElementById('offerContent').innerHTML = `
                <h3>Make Offer</h3>
                <p><strong>Item:</strong> ${itemTitle}</p>
                <div id="offerAlert"></div>
                <form id="offerForm">
                    <div class="form-group">
                        <label class="form-label">Your Offer ($)</label>
                        <input type="number" class="form-input" name="offer_amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Maximum Auto-Bid (Optional)</label>
                        <input type="number" class="form-input" name="max_offer" step="0.01" min="0.01">
                        <small>We'll automatically increase your bid up to this amount if outbid</small>
                    </div>
                    <button type="submit" class="btn">Submit Offer</button>
                </form>
            `;
            
            document.getElementById('offerForm').onsubmit = function(e) {
                e.preventDefault();
                submitOffer(itemId);
            };
        }

        function closeOfferModal() {
            document.getElementById('offerModal').style.display = 'none';
        }

        async function submitOffer(itemId) {
            const form = document.getElementById('offerForm');
            const formData = new FormData(form);
            formData.append('action', 'create');
            formData.append('item_id', itemId);

            try {
                const response = await fetch('/claims/api/offers.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    closeOfferModal();
                    showAlert('Offer submitted successfully!', 'success');
                    // Reload items to show updated offer counts
                    loadItems(<?= $currentSale ? $currentSale['id'] : 'null' ?>);
                } else {
                    document.getElementById('offerAlert').innerHTML = `<div class="alert alert-error">${result.message}</div>`;
                }
            } catch (error) {
                document.getElementById('offerAlert').innerHTML = `<div class="alert alert-error">An error occurred. Please try again.</div>`;
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const authModal = document.getElementById('authModal');
            const offerModal = document.getElementById('offerModal');
            
            if (event.target === authModal) {
                closeAuthModal();
            }
            if (event.target === offerModal) {
                closeOfferModal();
            }
        }
    </script>
</body>
</html>