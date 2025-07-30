<?php
/**
 * Session Compatibility Layer
 * This provides a fallback for when the complex session manager fails
 */

// Start basic session if not already started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Check if we're using simple authentication
function isSimpleAuth() {
    return isset($_SESSION['simple_auth']) && $_SESSION['simple_auth'] === true;
}

// Simple authentication check function
function simpleAuthCheck() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    if (!isset($_SESSION['logged_in'])) {
        return false;
    }
    
    // Handle various logged_in formats
    $loggedIn = $_SESSION['logged_in'];
    if ($loggedIn === true || $loggedIn === 'true' || $loggedIn === 1 || $loggedIn === '1') {
        return true;
    }
    
    return false;
}

// Override requireAuth function if using simple auth
if (!function_exists('requireAuth') || isSimpleAuth()) {
    function requireAuth() {
        if (isSimpleAuth()) {
            if (!simpleAuthCheck()) {
                header('Location: ' . BASE_URL . '/src/views/auth/simple_login.php');
                exit;
            }
        } else {
            // Try to use the session manager
            global $sessionManager;
            if (isset($sessionManager) && !$sessionManager->isLoggedIn()) {
                header('Location: ' . BASE_URL . '/src/views/auth/login.php');
                exit;
            } elseif (!isset($sessionManager)) {
                // Fallback to simple check
                if (!simpleAuthCheck()) {
                    header('Location: ' . BASE_URL . '/src/views/auth/login.php');
                    exit;
                }
            }
        }
    }
}

// Override requireRole function if using simple auth
if (!function_exists('requireRole') || isSimpleAuth()) {
    function requireRole($role) {
        requireAuth();
        
        if (isSimpleAuth()) {
            if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $role) {
                header('Location: ' . BASE_URL . '/src/views/auth/simple_login.php');
                exit;
            }
        } else {
            global $sessionManager;
            if (isset($sessionManager) && $sessionManager->getUserRole() !== $role) {
                header('Location: ' . BASE_URL . '/src/views/auth/login.php');
                exit;
            } elseif (!isset($sessionManager)) {
                // Fallback to simple check
                if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $role) {
                    header('Location: ' . BASE_URL . '/src/views/auth/login.php');
                    exit;
                }
            }
        }
    }
}

// Override requireManager function
if (!function_exists('requireManager') || isSimpleAuth()) {
    function requireManager() {
        requireRole('gestionnaire');
    }
}

// Override requireReceptionist function
if (!function_exists('requireReceptionist') || isSimpleAuth()) {
    function requireReceptionist() {
        requireRole('receptionniste');
    }
}

// Helper function to get current user info
function getCurrentUserInfo() {
    if (isSimpleAuth() || !isset($GLOBALS['sessionManager'])) {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'name' => $_SESSION['user_name'] ?? null,
            'role' => $_SESSION['user_role'] ?? null,
            'active' => $_SESSION['user_active'] ?? null,
            'login_time' => $_SESSION['login_time'] ?? null
        ];
    } else {
        global $sessionManager;
        return [
            'id' => $sessionManager->getUserId(),
            'username' => $sessionManager->getUsername(),
            'name' => $sessionManager->getFullName(),
            'role' => $sessionManager->getUserRole(),
            'active' => $sessionManager->isUserActive(),
            'login_time' => $sessionManager->get('login_time')
        ];
    }
}
?>
