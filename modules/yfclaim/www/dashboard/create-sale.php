<?php
session_start();

// Check if seller is logged in
if (!isset($_SESSION['claim_seller_logged_in']) || $_SESSION['claim_seller_logged_in'] !== true) {
    header('Location: /claims/admin/login.php');
    exit;
}

require_once __DIR__ . '/bootstrap.php';

use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\SellerModel;

$saleModel = new SaleModel($pdo);
$sellerModel = new SellerModel($pdo);

$sellerId = $_SESSION['claim_seller_id'];
$seller = $sellerModel->find($sellerId);

$success = false;
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? 'WA');
    $zip = trim($_POST['zip'] ?? '');
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $claimStart = $_POST['claim_start'] ?? '';
    $claimEnd = $_POST['claim_end'] ?? '';
    $pickupStart = $_POST['pickup_start'] ?? '';
    $pickupEnd = $_POST['pickup_end'] ?? '';
    
    // Basic validation
    if (empty($title) || empty($description) || empty($address) || empty($city)) {
        $error = 'Please fill in all required fields.';
    } elseif (empty($startDate) || empty($endDate) || empty($claimStart) || empty($claimEnd)) {
        $error = 'Please fill in all date fields.';
    } else {
        try {
            // Prepare sale data
            $saleData = [
                'seller_id' => $sellerId,
                'title' => $title,
                'description' => $description,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'claim_start' => $claimStart,
                'claim_end' => $claimEnd,
                'pickup_start' => $pickupStart ?: $endDate,
                'pickup_end' => $pickupEnd ?: date('Y-m-d H:i:s', strtotime($endDate . ' +3 days')),
                'status' => 'active'
            ];
            
            $saleId = $saleModel->createSale($saleData);
            
            if ($saleId) {
                $success = true;
                // Redirect to sales management
                header('Location: sales.php?created=' . $saleId);
                exit;
            } else {
                $error = 'Failed to create sale. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Sale - YFClaim</title>
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
            max-width: 800px;
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
        
        .page-header p {
            color: #666;
        }
        
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-section h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            flex: 1;
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
        }
        
        .required {
            color: #e74c3c;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        textarea {
            resize: vertical;
            height: 100px;
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
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e9ecef;
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
        
        .info-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .info-box h4 {
            color: #1976d2;
            margin-bottom: 0.5rem;
        }
        
        .info-box p {
            color: #424242;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .container {
                padding: 0 1rem;
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
            <h1>🎯 Create New Sale</h1>
            <p>Set up a new estate sale with claiming periods and pickup schedules</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                Sale created successfully! Redirecting...
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <!-- Basic Information -->
                <div class="form-section">
                    <h3>📋 Basic Information</h3>
                    
                    <div class="form-group">
                        <label for="title">Sale Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required 
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                               placeholder="e.g., Johnson Family Estate Sale">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description <span class="required">*</span></label>
                        <textarea id="description" name="description" required 
                                  placeholder="Describe the items and highlights of this sale..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <!-- Location -->
                <div class="form-section">
                    <h3>📍 Location</h3>
                    
                    <div class="form-group">
                        <label for="address">Street Address <span class="required">*</span></label>
                        <input type="text" id="address" name="address" required 
                               value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                               placeholder="123 Main Street">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City <span class="required">*</span></label>
                            <input type="text" id="city" name="city" required 
                                   value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                                   placeholder="Yakima">
                        </div>
                        <div class="form-group">
                            <label for="state">State</label>
                            <select id="state" name="state">
                                <option value="WA" <?= ($_POST['state'] ?? 'WA') === 'WA' ? 'selected' : '' ?>>Washington</option>
                                <option value="OR" <?= ($_POST['state'] ?? '') === 'OR' ? 'selected' : '' ?>>Oregon</option>
                                <option value="ID" <?= ($_POST['state'] ?? '') === 'ID' ? 'selected' : '' ?>>Idaho</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="zip">ZIP Code</label>
                            <input type="text" id="zip" name="zip" 
                                   value="<?= htmlspecialchars($_POST['zip'] ?? '') ?>"
                                   placeholder="98901">
                        </div>
                    </div>
                </div>
                
                <!-- Sale Dates -->
                <div class="form-section">
                    <h3>📅 Sale Schedule</h3>
                    
                    <div class="info-box">
                        <h4>How Sale Timing Works:</h4>
                        <p>Set your sale start/end dates, then define when buyers can start claiming items and when they must pick them up.</p>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Sale Start Date & Time <span class="required">*</span></label>
                            <input type="datetime-local" id="start_date" name="start_date" required 
                                   value="<?= $_POST['start_date'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_date">Sale End Date & Time <span class="required">*</span></label>
                            <input type="datetime-local" id="end_date" name="end_date" required 
                                   value="<?= $_POST['end_date'] ?? '' ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Claiming Period -->
                <div class="form-section">
                    <h3>🔓 Claiming Period</h3>
                    
                    <div class="info-box">
                        <h4>Claiming Period:</h4>
                        <p>This is when buyers can browse items and make offers. Usually starts before the physical sale.</p>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="claim_start">Claims Open <span class="required">*</span></label>
                            <input type="datetime-local" id="claim_start" name="claim_start" required 
                                   value="<?= $_POST['claim_start'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label for="claim_end">Claims Close <span class="required">*</span></label>
                            <input type="datetime-local" id="claim_end" name="claim_end" required 
                                   value="<?= $_POST['claim_end'] ?? '' ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Pickup Period -->
                <div class="form-section">
                    <h3>📦 Pickup Schedule</h3>
                    
                    <div class="info-box">
                        <h4>Pickup Period:</h4>
                        <p>When buyers can collect their claimed items. Leave blank to default to 3 days after sale end.</p>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="pickup_start">Pickup Start</label>
                            <input type="datetime-local" id="pickup_start" name="pickup_start" 
                                   value="<?= $_POST['pickup_start'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label for="pickup_end">Pickup End</label>
                            <input type="datetime-local" id="pickup_end" name="pickup_end" 
                                   value="<?= $_POST['pickup_end'] ?? '' ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Sale</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Auto-fill some dates based on sale dates
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate = new Date(this.value);
            if (startDate) {
                // Set claim start to 3 days before sale start
                const claimStart = new Date(startDate);
                claimStart.setDate(claimStart.getDate() - 3);
                document.getElementById('claim_start').value = claimStart.toISOString().slice(0, 16);
                
                // Set pickup start to same as sale end
                const pickupStart = document.getElementById('end_date').value;
                if (pickupStart) {
                    document.getElementById('pickup_start').value = pickupStart;
                }
            }
        });
        
        document.getElementById('end_date').addEventListener('change', function() {
            const endDate = new Date(this.value);
            if (endDate) {
                // Set claim end to sale end
                document.getElementById('claim_end').value = this.value;
                
                // Set pickup start to sale end
                document.getElementById('pickup_start').value = this.value;
                
                // Set pickup end to 3 days after sale end
                const pickupEnd = new Date(endDate);
                pickupEnd.setDate(pickupEnd.getDate() + 3);
                document.getElementById('pickup_end').value = pickupEnd.toISOString().slice(0, 16);
            }
        });
    </script>
</body>
</html>