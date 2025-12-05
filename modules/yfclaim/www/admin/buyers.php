<?php
// YFClaim Buyers Management
require_once __DIR__ . '/bootstrap.php';

use YFEvents\Modules\YFClaim\Models\BuyerModel;

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

// Initialize models
$buyerModel = new BuyerModel($pdo);

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'create_buyer':
                    $data = [
                        'sale_id' => $_POST['sale_id'],
                        'name' => $_POST['name'],
                        'email' => $_POST['email'] ?? null,
                        'phone' => $_POST['phone'] ?? null,
                        'auth_method' => $_POST['auth_method'] ?? 'email',
                        'auth_verified' => isset($_POST['auth_verified']) ? 1 : 0
                    ];
                    $buyerId = $buyerModel->createBuyer($data);
                    $message = "Buyer created successfully!";
                    break;
                    
                case 'update_buyer':
                    $data = [
                        'name' => $_POST['name'],
                        'email' => $_POST['email'] ?? null,
                        'phone' => $_POST['phone'] ?? null,
                        'auth_method' => $_POST['auth_method'] ?? 'email',
                        'auth_verified' => isset($_POST['auth_verified']) ? 1 : 0
                    ];
                    $buyerModel->updateBuyer($_POST['buyer_id'], $data);
                    $message = "Buyer updated successfully!";
                    break;
                    
                case 'delete_buyer':
                    try {
                        $buyerModel->deleteBuyer($_POST['buyer_id']);
                        $message = "Buyer deleted successfully!";
                    } catch (Exception $e) {
                        $error = "Cannot delete buyer: " . $e->getMessage();
                    }
                    break;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$authVerified = $_GET['auth_verified'] ?? '';
$search = $_GET['search'] ?? '';
$saleId = $_GET['sale_id'] ?? '';

// Build query with proper statistics
$query = "SELECT b.*, 
          s.title as sale_title,
          sel.company_name,
          (SELECT COUNT(*) FROM yfc_offers WHERE buyer_id = b.id) as total_offers,
          (SELECT COUNT(*) FROM yfc_offers WHERE buyer_id = b.id AND status = 'active') as active_offers,
          (SELECT COUNT(*) FROM yfc_offers WHERE buyer_id = b.id AND status = 'winning') as winning_offers,
          b.session_token,
          b.session_expires
          FROM yfc_buyers b 
          LEFT JOIN yfc_sales s ON b.sale_id = s.id
          LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
          WHERE 1=1";
$params = [];

if ($authVerified !== '') {
    $query .= " AND b.auth_verified = ?";
    $params[] = $authVerified;
}

if ($saleId) {
    $query .= " AND b.sale_id = ?";
    $params[] = $saleId;
}

if ($search) {
    $query .= " AND (b.name LIKE ? OR b.email LIKE ? OR b.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$buyers = $stmt->fetchAll();

// Get buyer for edit modal
$editBuyer = null;
if (isset($_GET['edit'])) {
    $editBuyer = $buyerModel->getBuyerById($_GET['edit']);
    if ($editBuyer) {
        // Get buyer statistics
        $editBuyer['stats'] = $buyerModel->getStats($_GET['edit']);
    }
}

// Get sales for dropdown
$salesQuery = "SELECT id, title FROM yfc_sales WHERE status = 'active' ORDER BY created_at DESC";
$salesStmt = $pdo->query($salesQuery);
$sales = $salesStmt->fetchAll();

// Get statistics
$stats = [];
$stats['total_buyers'] = count($buyers);
$stats['verified_buyers'] = count(array_filter($buyers, fn($b) => $b['auth_verified'] == 1));
$stats['total_offers'] = array_sum(array_column($buyers, 'total_offers'));
$stats['active_offers'] = array_sum(array_column($buyers, 'active_offers'));
$stats['winning_offers'] = array_sum(array_column($buyers, 'winning_offers'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Buyers - YFClaim Admin</title>
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
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        .section h2 {
            margin: 0;
            color: #333;
        }
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .filters input, .filters select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 1rem;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn:hover {
            opacity: 0.8;
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
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 3px;
            font-size: 0.875rem;
            font-weight: bold;
            display: inline-block;
        }
        .status.active {
            background: #28a745;
            color: white;
        }
        .status.suspended {
            background: #dc3545;
            color: white;
        }
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
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
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 5px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .modal-header h3 {
            margin: 0;
        }
        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #333;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .badge {
            background: #6c757d;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        .badge.pending {
            background: #ffc107;
            color: #000;
        }
        .badge.accepted {
            background: #28a745;
        }
        .buyer-contact {
            font-size: 0.875rem;
        }
        .sale-info {
            line-height: 1.3;
        }
        .auth-icon {
            margin-right: 0.25rem;
        }
        input[type="checkbox"] {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Manage Buyers</h1>
        <div class="header-nav">
            <a href="/claims/admin/">Dashboard</a>
            <a href="/admin/">YFEvents Admin</a>
            <a href="/admin/logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="nav">
            <a href="/claims/admin/index.php">Dashboard</a>
            <a href="/claims/admin/sellers.php">Manage Sellers</a>
            <a href="/claims/admin/sales.php">Manage Sales</a>
            <a href="/claims/admin/offers.php">Manage Offers</a>
            <a href="/claims/admin/buyers.php" class="active">Manage Buyers</a>
            <a href="/claims/admin/reports.php">Reports</a>
        </div>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_buyers'] ?></div>
                <div class="stat-label">Total Buyers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['verified_buyers'] ?></div>
                <div class="stat-label">Verified Buyers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_offers'] ?></div>
                <div class="stat-label">Total Offers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['active_offers'] ?></div>
                <div class="stat-label">Active Offers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['winning_offers'] ?></div>
                <div class="stat-label">Winning Offers</div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-header">
                <h2>Buyers</h2>
                <button class="btn btn-primary" onclick="showCreateModal()">+ Add New Buyer</button>
            </div>
            
            <form method="get" class="filters">
                <input type="text" name="search" placeholder="Search buyers..." value="<?= htmlspecialchars($search) ?>">
                <select name="auth_verified">
                    <option value="">All Buyers</option>
                    <option value="1" <?= $authVerified === '1' ? 'selected' : '' ?>>Verified</option>
                    <option value="0" <?= $authVerified === '0' ? 'selected' : '' ?>>Unverified</option>
                </select>
                <select name="sale_id">
                    <option value="">All Sales</option>
                    <?php foreach ($sales as $sale): ?>
                        <option value="<?= $sale['id'] ?>" <?= $saleId == $sale['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sale['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="/claims/admin/buyers.php" class="btn">Clear</a>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Sale</th>
                        <th>Auth</th>
                        <th>Offers</th>
                        <th>Last Activity</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($buyers as $buyer): ?>
                    <tr>
                        <td><?= $buyer['id'] ?></td>
                        <td>
                            <a href="/claims/admin/offers.php?buyer_id=<?= $buyer['id'] ?>" style="color: #007bff; text-decoration: none;">
                                <?= htmlspecialchars($buyer['name']) ?>
                            </a>
                            <?php if ($buyer['active_offers'] > 0): ?>
                                <span class="badge pending"><?= $buyer['active_offers'] ?> active</span>
                            <?php endif; ?>
                            <?php if ($buyer['winning_offers'] > 0): ?>
                                <span class="badge accepted"><?= $buyer['winning_offers'] ?> winning</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($buyer['auth_method'] === 'email'): ?>
                                📧 <?= htmlspecialchars($buyer['email'] ?? '-') ?>
                            <?php else: ?>
                                📱 <?= htmlspecialchars($buyer['phone'] ?? '-') ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($buyer['sale_title']): ?>
                                <small><?= htmlspecialchars($buyer['sale_title']) ?></small><br>
                                <small style="color: #666;"><?= htmlspecialchars($buyer['company_name'] ?? '') ?></small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($buyer['auth_verified']): ?>
                                <span class="status active">Verified</span>
                                <?php if ($buyer['session_token'] && strtotime($buyer['session_expires']) > time()): ?>
                                    <br><small style="color: #28a745;">Session Active</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="status suspended">Unverified</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $buyer['total_offers'] ?></td>
                        <td><?= date('M d, g:i a', strtotime($buyer['last_activity'])) ?></td>
                        <td><?= date('M d, Y', strtotime($buyer['created_at'])) ?></td>
                        <td>
                            <div class="actions">
                                <button onclick="editBuyer(<?= $buyer['id'] ?>)" class="btn btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">Edit</button>
                                <form method="post" style="margin: 0;">
                                    <input type="hidden" name="buyer_id" value="<?= $buyer['id'] ?>">
                                    <button type="submit" name="action" value="delete_buyer" class="btn btn-danger" 
                                            style="padding: 0.25rem 0.75rem; font-size: 0.875rem;"
                                            onclick="return confirm('Delete this buyer and all their offers? This cannot be undone.')">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Create/Edit Modal -->
    <div id="buyerModal" class="modal <?= $editBuyer ? 'active' : '' ?>">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"><?= $editBuyer ? 'Edit Buyer' : 'Create New Buyer' ?></h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="post" id="buyerForm">
                <input type="hidden" name="action" value="<?= $editBuyer ? 'update_buyer' : 'create_buyer' ?>">
                <input type="hidden" name="buyer_id" value="<?= $editBuyer['id'] ?? '' ?>">
                
                <?php if (!$editBuyer): ?>
                <div class="form-group">
                    <label>Sale *</label>
                    <select name="sale_id" required>
                        <option value="">Select a sale...</option>
                        <?php foreach ($sales as $sale): ?>
                            <option value="<?= $sale['id'] ?>"><?= htmlspecialchars($sale['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editBuyer['name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Authentication Method *</label>
                    <select name="auth_method" id="authMethod" required>
                        <option value="email" <?= ($editBuyer['auth_method'] ?? 'email') === 'email' ? 'selected' : '' ?>>Email</option>
                        <option value="sms" <?= ($editBuyer['auth_method'] ?? '') === 'sms' ? 'selected' : '' ?>>SMS</option>
                    </select>
                </div>
                
                <div class="form-group" id="emailGroup">
                    <label>Email <span id="emailRequired">*</span></label>
                    <input type="email" name="email" value="<?= htmlspecialchars($editBuyer['email'] ?? '') ?>" id="emailInput">
                </div>
                
                <div class="form-group" id="phoneGroup">
                    <label>Phone <span id="phoneRequired"></span></label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($editBuyer['phone'] ?? '') ?>" id="phoneInput">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="auth_verified" <?= ($editBuyer['auth_verified'] ?? 0) ? 'checked' : '' ?>>
                        Mark as Verified
                    </label>
                </div>
                
                <?php if ($editBuyer && isset($editBuyer['stats'])): ?>
                <div class="form-group" style="background: #f8f9fa; padding: 1rem; border-radius: 5px;">
                    <strong>Buyer Statistics:</strong><br>
                    Total Offers: <?= $editBuyer['stats']['total_offers'] ?><br>
                    Active Offers: <?= $editBuyer['stats']['active_offers'] ?><br>
                    Winning Offers: <?= $editBuyer['stats']['winning_offers'] ?><br>
                    Last Activity: <?= date('M d, Y g:i a', strtotime($editBuyer['last_activity'])) ?>
                </div>
                <?php endif; ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $editBuyer ? 'Update Buyer' : 'Create Buyer' ?>
                    </button>
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create New Buyer';
            document.getElementById('buyerForm').reset();
            document.querySelector('[name="action"]').value = 'create_buyer';
            document.querySelector('[name="buyer_id"]').value = '';
            document.getElementById('buyerModal').classList.add('active');
        }
        
        function editBuyer(id) {
            window.location.href = '?edit=' + id;
        }
        
        function closeModal() {
            document.getElementById('buyerModal').classList.remove('active');
            if (window.location.search.includes('edit=')) {
                window.location.href = '/claims/admin/buyers.php';
            }
        }
        
        // Close modal on outside click
        document.getElementById('buyerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Toggle email/phone requirement based on auth method
        function updateAuthFields() {
            const authMethod = document.getElementById('authMethod').value;
            const emailRequired = document.getElementById('emailRequired');
            const phoneRequired = document.getElementById('phoneRequired');
            const emailInput = document.getElementById('emailInput');
            const phoneInput = document.getElementById('phoneInput');
            
            if (authMethod === 'email') {
                emailRequired.textContent = '*';
                phoneRequired.textContent = '';
                emailInput.required = true;
                phoneInput.required = false;
            } else {
                emailRequired.textContent = '';
                phoneRequired.textContent = '*';
                emailInput.required = false;
                phoneInput.required = true;
            }
        }
        
        document.getElementById('authMethod').addEventListener('change', updateAuthFields);
        updateAuthFields();
    </script>
</body>
</html>