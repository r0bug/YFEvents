<?php
// YFClaim Sellers Management
require_once __DIR__ . '/bootstrap.php';

use YFEvents\Modules\YFClaim\Models\SellerModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;

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
$sellerModel = new SellerModel($pdo);
$saleModel = new SaleModel($pdo);

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'create_seller':
                    $data = [
                        'company_name' => $_POST['company_name'],
                        'contact_name' => $_POST['contact_name'],
                        'email' => $_POST['email'],
                        'phone' => $_POST['phone'] ?? null,
                        'password' => $_POST['password'],
                        'address' => $_POST['address'] ?? null,
                        'city' => $_POST['city'] ?? null,
                        'state' => $_POST['state'] ?? null,
                        'zip' => $_POST['zip'] ?? null,
                        'status' => 'active'
                    ];
                    $sellerId = $sellerModel->createSeller($data);
                    $message = "Seller created successfully!";
                    break;
                    
                case 'update_seller':
                    $data = [
                        'company_name' => $_POST['company_name'],
                        'contact_name' => $_POST['contact_name'],
                        'email' => $_POST['email'],
                        'phone' => $_POST['phone'] ?? null,
                        'address' => $_POST['address'] ?? null,
                        'city' => $_POST['city'] ?? null,
                        'state' => $_POST['state'] ?? null,
                        'zip' => $_POST['zip'] ?? null,
                        'status' => $_POST['status']
                    ];
                    if (!empty($_POST['password'])) {
                        $data['password'] = $_POST['password'];
                    }
                    $sellerModel->updateSeller($_POST['seller_id'], $data);
                    $message = "Seller updated successfully!";
                    break;
                    
                case 'delete_seller':
                    // Check if seller has active sales
                    $sellerStats = $sellerModel->getStats($_POST['seller_id']);
                    if ($sellerStats['active_sales'] > 0) {
                        $error = "Cannot delete seller with active sales!";
                    } else {
                        $sellerModel->deleteSeller($_POST['seller_id']);
                        $message = "Seller deleted successfully!";
                    }
                    break;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Get sellers
$sellers = $sellerModel->getAllSellers(100, 0);

// Add statistics to each seller
foreach ($sellers as &$seller) {
    $stats = $sellerModel->getStats($seller['id']);
    $seller['total_sales'] = $stats['total_sales'];
    $seller['active_sales'] = $stats['active_sales'];
}

// Apply filters
if ($status || $search) {
    $sellers = array_filter($sellers, function($seller) use ($status, $search) {
        if ($status && $seller['status'] !== $status) {
            return false;
        }
        if ($search) {
            $searchLower = strtolower($search);
            return stripos($seller['company_name'], $searchLower) !== false ||
                   stripos($seller['contact_name'], $searchLower) !== false ||
                   stripos($seller['email'], $searchLower) !== false;
        }
        return true;
    });
}

// Get seller for edit modal
$editSeller = null;
if (isset($_GET['edit'])) {
    $editSeller = $sellerModel->getSellerById($_GET['edit']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sellers - YFClaim Admin</title>
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
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-warning {
            background: #ffc107;
            color: #000;
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
        .status.pending {
            background: #ffc107;
            color: #000;
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
    </style>
</head>
<body>
    <div class="header">
        <h1>Manage Sellers</h1>
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
            <a href="/claims/admin/sellers.php" class="active">Manage Sellers</a>
            <a href="/claims/admin/sales.php">Manage Sales</a>
            <a href="/claims/admin/offers.php">Manage Offers</a>
            <a href="/claims/admin/buyers.php">Manage Buyers</a>
            <a href="/claims/admin/reports.php">Reports</a>
        </div>
        
        <div class="section">
            <div class="section-header">
                <h2>Sellers</h2>
                <button class="btn btn-primary" onclick="showCreateModal()">+ Add New Seller</button>
            </div>
            
            <form method="get" class="filters">
                <input type="text" name="search" placeholder="Search sellers..." value="<?= htmlspecialchars($search) ?>">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="/claims/admin/sellers.php" class="btn">Clear</a>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Company Name</th>
                        <th>Contact Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Sales</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sellers as $seller): ?>
                    <tr>
                        <td><?= $seller['id'] ?></td>
                        <td>
                            <?= htmlspecialchars($seller['company_name']) ?>
                            <?php if ($seller['active_sales'] > 0): ?>
                                <span class="badge"><?= $seller['active_sales'] ?> active</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($seller['contact_name']) ?></td>
                        <td><?= htmlspecialchars($seller['email']) ?></td>
                        <td><?= htmlspecialchars($seller['phone'] ?? '-') ?></td>
                        <td><?= $seller['total_sales'] ?></td>
                        <td>
                            <span class="status <?= $seller['status'] ?>">
                                <?= ucfirst($seller['status']) ?>
                            </span>
                        </td>
                        <td><?= date('M d, Y', strtotime($seller['created_at'])) ?></td>
                        <td>
                            <div class="actions">
                                <button onclick="editSeller(<?= $seller['id'] ?>)" class="btn btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">Edit</button>
                                <form method="post" style="margin: 0;">
                                    <input type="hidden" name="seller_id" value="<?= $seller['id'] ?>">
                                    <button type="submit" name="action" value="delete_seller" class="btn btn-danger" 
                                            style="padding: 0.25rem 0.75rem; font-size: 0.875rem;"
                                            onclick="return confirm('Delete this seller? This cannot be undone.')">Delete</button>
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
    <div id="sellerModal" class="modal <?= $editSeller ? 'active' : '' ?>">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"><?= $editSeller ? 'Edit Seller' : 'Create New Seller' ?></h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="post" id="sellerForm">
                <input type="hidden" name="action" value="<?= $editSeller ? 'update_seller' : 'create_seller' ?>">
                <input type="hidden" name="seller_id" value="<?= $editSeller['id'] ?? '' ?>">
                
                <div class="form-group">
                    <label>Company Name *</label>
                    <input type="text" name="company_name" value="<?= htmlspecialchars($editSeller['company_name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Contact Name *</label>
                    <input type="text" name="contact_name" value="<?= htmlspecialchars($editSeller['contact_name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($editSeller['email'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Password <?= $editSeller ? '(leave blank to keep current)' : '*' ?></label>
                    <input type="password" name="password" <?= !$editSeller ? 'required' : '' ?>>
                </div>
                
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($editSeller['phone'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($editSeller['address'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" value="<?= htmlspecialchars($editSeller['city'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>State</label>
                    <input type="text" name="state" value="<?= htmlspecialchars($editSeller['state'] ?? '') ?>" maxlength="2">
                </div>
                
                <div class="form-group">
                    <label>ZIP Code</label>
                    <input type="text" name="zip" value="<?= htmlspecialchars($editSeller['zip'] ?? '') ?>" maxlength="10">
                </div>
                
                <?php if ($editSeller): ?>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="pending" <?= $editSeller['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="active" <?= $editSeller['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="suspended" <?= $editSeller['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $editSeller ? 'Update Seller' : 'Create Seller' ?>
                    </button>
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create New Seller';
            document.getElementById('sellerForm').reset();
            document.querySelector('[name="action"]').value = 'create_seller';
            document.querySelector('[name="seller_id"]').value = '';
            document.querySelector('[name="password"]').required = true;
            document.getElementById('sellerModal').classList.add('active');
        }
        
        function editSeller(id) {
            window.location.href = '?edit=' + id;
        }
        
        function closeModal() {
            document.getElementById('sellerModal').classList.remove('active');
            if (window.location.search.includes('edit=')) {
                window.location.href = '/claims/admin/sellers.php';
            }
        }
        
        // Close modal on outside click
        document.getElementById('sellerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>