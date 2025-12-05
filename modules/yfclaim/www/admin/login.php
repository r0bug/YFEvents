<?php
session_start();

// Check if already logged in
if (isset($_SESSION['claim_seller_logged_in']) && $_SESSION['claim_seller_logged_in'] === true) {
    header('Location: /seller/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Login - YFClaim</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo h1 {
            color: #333;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .logo p {
            color: #666;
            font-size: 1rem;
        }
        
        .welcome-text {
            background: #f8f9ff;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid #667eea;
        }
        
        .welcome-text h3 {
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .welcome-text p {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 600;
        }
        
        input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        button {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border: 1px solid transparent;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border-color: #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border-color: #cfc;
        }
        
        .links {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e1e8ed;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            margin: 0 1rem;
            font-weight: 500;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .loading {
            display: none;
            text-align: center;
            margin-top: 1rem;
        }
        
        .spinner {
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .register-link {
            background: #f8f9ff;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            margin-top: 1rem;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>YFClaim</h1>
            <p>Seller Portal</p>
        </div>
        
        <div class="welcome-text">
            <h3>Welcome back!</h3>
            <p>Sign in to manage your claim sales, upload items, and review offers from buyers.</p>
        </div>
        
        <div id="alerts"></div>
        
        <form id="loginForm">
            <div class="form-group">
                <label for="username">Email or Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" id="loginBtn">Sign In</button>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Signing you in...</p>
            </div>
        </form>
        
        <div class="register-link">
            <p>New seller? <a href="register.php">Register for an account</a></p>
        </div>
        
        <div class="links">
            <a href="/">← Back to Calendar</a>
            <a href="#" onclick="alert('Password reset feature coming soon!')">Forgot Password?</a>
        </div>
    </div>
    
    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const loginBtn = document.getElementById('loginBtn');
            const loading = document.getElementById('loading');
            const alerts = document.getElementById('alerts');
            
            // Clear previous alerts
            alerts.innerHTML = '';
            
            // Show loading
            loginBtn.disabled = true;
            loading.style.display = 'block';
            
            try {
                const formData = new FormData();
                formData.append('username', username);
                formData.append('password', password);
                
                const response = await fetch('../api/seller-auth.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alerts.innerHTML = '<div class="alert alert-success">Login successful! Redirecting to dashboard...</div>';
                    setTimeout(() => {
                        window.location.href = '/seller/';
                    }, 1500);
                } else {
                    alerts.innerHTML = `<div class="alert alert-error">${data.error || 'Login failed'}</div>`;
                    loginBtn.disabled = false;
                }
            } catch (error) {
                alerts.innerHTML = '<div class="alert alert-error">An error occurred. Please try again.</div>';
                loginBtn.disabled = false;
            } finally {
                loading.style.display = 'none';
            }
        });
    </script>
</body>
</html>