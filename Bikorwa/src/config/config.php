<?php
/**
 * Configuration générale de l'application
 * BIKORWA SHOP
 */

// ====================================================
// 1. BASIC CONFIGURATIONS - Must come first
// ====================================================

// Only set session parameters if no session is active yet
if (session_status() === PHP_SESSION_NONE) {
    // Session settings must be set BEFORE session_start
    ini_set('session.cookie_lifetime', 86400); // 24 heures
    ini_set('session.gc_maxlifetime', 86400); // 24 heures
    
    // Start session
    session_start();
}

// Set timezone
date_default_timezone_set('Africa/Kigali');

// ====================================================
// 2. APPLICATION CONSTANTS
// ====================================================

// Basic application constants
if (!defined('APP_NAME'))     define('APP_NAME', 'BIKORWA SHOP');
if (!defined('APP_VERSION'))  define('APP_VERSION', '1.0.0');
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $protocol . $host . '/Bikorwa');
}

// Path constants
if (!defined('ROOT_PATH'))    define('ROOT_PATH', dirname(dirname(__DIR__)));
if (!defined('SRC_PATH'))     define('SRC_PATH', ROOT_PATH . '/src');
if (!defined('ASSETS_PATH'))  define('ASSETS_PATH', ROOT_PATH . '/assets');

// ====================================================
// 3. ERROR HANDLING
// ====================================================

error_reporting(E_ALL);
ini_set('display_errors', 1); // Set to 0 in production
ini_set('log_errors', 1);

// Create logs directory if it doesn't exist
if (!file_exists(ROOT_PATH . '/logs')) {
    mkdir(ROOT_PATH . '/logs', 0777, true);
}
ini_set('error_log', ROOT_PATH . '/logs/error.log');

// ====================================================
// 4. AUTOLOADER
// ====================================================

spl_autoload_register(function ($class_name) {
    $class_path = str_replace('\\', '/', $class_name) . '.php';
    
    $possible_paths = [
        SRC_PATH . '/models/' . $class_path,
        SRC_PATH . '/controllers/' . $class_path,
        SRC_PATH . '/utils/' . $class_path
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// ====================================================
// 5. HELPER FUNCTIONS
// ====================================================

// Language helper
if (!function_exists('load_language')) {
    function load_language($lang = 'fr') {
        $lang_file = SRC_PATH . '/lang/' . $lang . '.php';
        if (file_exists($lang_file)) {
            $LANG = require $lang_file;
            return $LANG;
        }
        return [];
    }
}

// Navigation helper
if (!function_exists('redirect')) {
    function redirect($url) {
        if (strpos($url, 'http') !== 0 && defined('BASE_URL')) {
            $url = BASE_URL . '/' . ltrim($url, '/');
        }
        header('Location: ' . $url);
        exit;
    }
}

// Authentication helpers
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('is_manager')) {
    function is_manager() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'gestionnaire';
    }
}

// Security helpers
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        return isset($_SESSION['csrf_token']) && 
               !empty($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Input sanitization
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        if (is_array($data)) {
            return array_map('sanitize_input', $data);
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

// ====================================================
// 6. APPLICATION CONFIGURATION
// ====================================================

return [
    // Shop information
    'shop_info' => [
        'name'     => APP_NAME,
        'address'  => 'Avenue de la Paix, Bujumbura, Burundi',
        'phone'    => '+257 12 34 56 78',
        'email'    => 'contact@bikorwashop.com',
        'tax_id'   => '123456789',
    ],
    
    // Application settings
    'app' => [
        'debug'         => true,
        'timezone'      => 'Africa/Bujumbura',
        'locale'        => 'fr_FR',
        'items_per_page' => 20,
    ],
    
    // Stock settings
    'stock' => [
        'alert_threshold'    => 10,
        'critical_threshold' => 5,
    ],
    
    // Report settings
    'reports' => [
        'default_period' => 30,
    ],
];
