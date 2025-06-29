<?php
/**
 * Session initialization for BIKORWA SHOP
 * This ensures consistent session settings across the application
 */

// Prevent multiple inclusions
if (defined('SESSION_INIT_LOADED')) {
    return;
}
define('SESSION_INIT_LOADED', true);

// Configure session settings before starting session
if (session_status() === PHP_SESSION_NONE) {
    // Session configuration
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_lifetime', 3600); // 1 hour
    ini_set('session.gc_maxlifetime', 3600);
    
    // Set session name
    session_name('BIKORWA_SESSION');
    
    // Set session cookie parameters
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to true for HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    // Start the session
    session_start();
}

// Function to check if user is logged in
function is_user_logged_in() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['logged_in']) && 
           $_SESSION['logged_in'] === true &&
           !empty($_SESSION['user_id']);
}

// Function to get current user data
function get_current_user() {
    if (!is_user_logged_in()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'name' => $_SESSION['user_name'] ?? null,
        'role' => $_SESSION['user_role'] ?? null,
        'active' => $_SESSION['user_active'] ?? false,
        'login_time' => $_SESSION['login_time'] ?? null
    ];
}

// Function to require authentication
function require_auth($redirect_url = null) {
    if (!is_user_logged_in()) {
        if ($redirect_url) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        }
        
        // Determine login page path based on current location
        $login_path = '';
        $current_path = $_SERVER['SCRIPT_NAME'];
        
        if (strpos($current_path, '/dashboard/') !== false) {
            $login_path = '../auth/login.php';
        } elseif (strpos($current_path, '/views/') !== false) {
            $login_path = '../auth/login.php';
        } else {
            $login_path = 'src/views/auth/login.php';
        }
        
        header('Location: ' . $login_path);
        exit;
    }
}

// Function to require specific role
function require_role($required_role) {
    require_auth();
    
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $required_role) {
        header('Location: ../auth/login.php');
        exit;
    }
}

// Function to check if user has role
function has_role($role) {
    return is_user_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

// Update last activity
if (is_user_logged_in()) {
    $_SESSION['last_activity'] = time();
}
