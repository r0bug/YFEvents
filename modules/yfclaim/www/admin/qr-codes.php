<?php
// YFClaim QR Code Management
require_once __DIR__ . '/bootstrap.php';

use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Utils\QRCodeGenerator;

// Authentication check
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
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

// Initialize models
$saleModel = new SaleModel($pdo);
$itemModel = new ItemModel($pdo);

// Get sale ID from query
$saleId = $_GET['sale_id'] ?? 0;

if (!$saleId) {
    // Show error message instead of redirect
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invalid Sale - YFClaim Admin</title>
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
            <div class="error-title">❌ Invalid Sale ID</div>
            <div class="error-message">
                <p>No sale ID was provided. Please select a sale to view QR codes.</p>
            </div>
            <a href="/claims/admin/sales.php" class="login-button">Go to Sales Management</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get sale details
$sale = $saleModel->getWithSeller($saleId);
if (!$sale) {
    // Show error message instead of redirect
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sale Not Found - YFClaim Admin</title>
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
            <div class="error-title">❌ Sale Not Found</div>
            <div class="error-message">
                <p>The sale with ID <?= htmlspecialchars($saleId) ?> could not be found.</p>
            </div>
            <a href="/claims/admin/sales.php" class="login-button">Go to Sales Management</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get all items for the sale
$items = $itemModel->getBySale($saleId);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Codes - <?= htmlspecialchars($sale['title']) ?></title>
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
            font-size: 1.5rem;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
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
        }
        .sale-info {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .info-grid {
            display: grid;
            gap: 1rem;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .qr-container {
            text-align: center;
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
        }
        .qr-code {
            margin: 1rem 0;
        }
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        .item-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            page-break-inside: avoid;
        }
        .item-card h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
            color: #333;
        }
        .item-number {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        .item-price {
            font-size: 1.1rem;
            font-weight: bold;
            color: #27ae60;
            margin-bottom: 1rem;
        }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            margin-top: 1rem;
        }
        .btn:hover {
            background: #0056b3;
        }
        .print-button {
            background: #28a745;
        }
        .print-button:hover {
            background: #218838;
        }
        @media print {
            .header, .no-print {
                display: none;
            }
            .section {
                box-shadow: none;
                border: 1px solid #ddd;
                page-break-after: always;
            }
            .items-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="header no-print">
        <h1>QR Codes - <?= htmlspecialchars($sale['title']) ?></h1>
        <div>
            <a href="/claims/admin/sales.php" style="color: white; text-decoration: none;">← Back to Sales</a>
        </div>
    </div>
    
    <div class="container">
        <!-- Sale QR Code -->
        <div class="section">
            <h2>Sale Information & Master QR Code</h2>
            
            <div class="sale-info">
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Sale Title:</strong>
                        <span><?= htmlspecialchars($sale['title']) ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Company:</strong>
                        <span><?= htmlspecialchars($sale['company_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Access Code:</strong>
                        <span style="font-family: monospace; font-size: 1.2rem;"><?= htmlspecialchars($sale['access_code']) ?></span>
                    </div>
                    <div class="info-item">
                        <strong>QR Code:</strong>
                        <span style="font-family: monospace;"><?= htmlspecialchars($sale['qr_code']) ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Claim Period:</strong>
                        <span><?= date('M j, Y g:i A', strtotime($sale['claim_start'])) ?> - <?= date('M j, Y g:i A', strtotime($sale['claim_end'])) ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Total Items:</strong>
                        <span><?= count($items) ?></span>
                    </div>
                </div>
                
                <div class="qr-container">
                    <h3>Sale QR Code</h3>
                    <div class="qr-code">
                        <img src="<?= QRCodeGenerator::generateSaleQR($sale['id'], $sale['qr_code'], 250) ?>" alt="Sale QR Code">
                    </div>
                    <p style="margin: 0.5rem 0;">Scan to access sale</p>
                    <p style="font-size: 0.9rem; color: #666;">Code: <?= htmlspecialchars($sale['access_code']) ?></p>
                </div>
            </div>
            
            <div class="no-print">
                <button onclick="window.print()" class="btn print-button">Print All QR Codes</button>
                <a href="/claims/admin/sales.php?action=edit&id=<?= $sale['id'] ?>" class="btn">Edit Sale</a>
            </div>
        </div>
        
        <!-- Item QR Codes -->
        <div class="section">
            <h2>Item QR Codes</h2>
            
            <div class="items-grid">
                <?php foreach ($items as $item): ?>
                    <div class="item-card">
                        <h4><?= htmlspecialchars(substr($item['title'], 0, 50)) ?><?= strlen($item['title']) > 50 ? '...' : '' ?></h4>
                        <div class="item-number">Item #<?= htmlspecialchars($item['item_number']) ?></div>
                        <div class="item-price">$<?= number_format($item['starting_price'], 2) ?></div>
                        
                        <div class="qr-code">
                            <img src="<?= QRCodeGenerator::generateItemQR($item['id'], $item['qr_code'] ?? 'ITEM' . $item['id'], 150) ?>" alt="Item QR Code">
                        </div>
                        
                        <div style="font-size: 0.8rem; color: #666;">
                            <?= htmlspecialchars($item['qr_code'] ?? 'No QR Code') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Instructions -->
        <div class="section no-print">
            <h2>How to Use QR Codes</h2>
            
            <h3>For Sellers:</h3>
            <ol>
                <li>Print this page to get all QR codes for your sale</li>
                <li>Place the Sale QR Code at the entrance or registration table</li>
                <li>Attach individual Item QR Codes to each item or display them nearby</li>
                <li>Buyers can scan codes to quickly access item details and make offers</li>
            </ol>
            
            <h3>For Buyers:</h3>
            <ol>
                <li>Scan the Sale QR Code to see all items in the sale</li>
                <li>Scan individual Item QR Codes to view details and make offers</li>
                <li>Use the Access Code if QR scanning isn't available</li>
            </ol>
            
            <h3>Access Code Usage:</h3>
            <p>Buyers can also access the sale by visiting the YFClaim website and entering the access code: <strong style="font-family: monospace; font-size: 1.2rem; background: #f0f0f0; padding: 0.25rem 0.5rem;"><?= htmlspecialchars($sale['access_code']) ?></strong></p>
        </div>
    </div>
    
    <script>
        // Ensure all QR codes are generated when items don't have them
        document.addEventListener('DOMContentLoaded', function() {
            const missingQRItems = document.querySelectorAll('.item-card:has(.qr-code img[src*="ITEMundefined"])');
            if (missingQRItems.length > 0) {
                console.log('Some items are missing QR codes. Please regenerate them in the database.');
            }
        });
    </script>
</body>
</html>