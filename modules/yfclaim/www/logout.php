<?php
// YFClaim - Buyer Logout
session_start();

// Clear all buyer session data
unset($_SESSION['buyer_token']);
unset($_SESSION['pending_buyer_id']);
unset($_SESSION['auth_method']);
unset($_SESSION['auth_contact']);

// Redirect to home
header('Location: /claims/');
exit;