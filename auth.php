<?php
// auth.php

// 1. Secure Session Settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
// ini_set('session.cookie_secure', 1); // Enable this if using HTTPS

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' fonts.googleapis.com; font-src 'self' fonts.gstatic.com; script-src 'self' 'unsafe-inline';");

// 3. CSRF Token Generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 4. Authentication Check
if (!isset($_SESSION['user_id'])) {
    // Prevent redirect loop if we are already on login.php
    $current_page = basename($_SERVER['PHP_SELF']);
    if ($current_page !== 'login.php') {
        // If it's an AJAX request, return unauthorized error
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('HTTP/1.1 401 Unauthorized');
            exit("Unauthorized");
        }
        
        // For regular page loads, redirect to login
        header("Location: login.php");
        exit();
    }
}
?>
