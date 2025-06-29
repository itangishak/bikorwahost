<?php
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/utils/DbSessionHandler.php';

function startDbSession() {
    // Initialize session parameters
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.gc_maxlifetime', 86400); // 24 hours
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);

    // Get database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo instanceof PDO) {
        error_log('Failed to get database connection for session handler');
        return false;
    }

    // Ensure sessions table exists
    try {
        $stmt = $pdo->prepare(<<<SQL
            CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(128) PRIMARY KEY,
                data TEXT NOT NULL,
                expires DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );
        $stmt->execute();
    } catch (Exception $e) {
        error_log('Failed to create sessions table: ' . $e->getMessage());
        return false;
    }

    // Initialize session handler
    try {
        $handler = new DbSessionHandler($pdo);
        session_set_save_handler($handler, true);
    } catch (Exception $e) {
        error_log('Failed to initialize session handler: ' . $e->getMessage());
        return false;
    }

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Handle session ID from request
    $session_id = session_id();
    if (empty($session_id)) {
        // Generate new session ID
        $session_id = bin2hex(random_bytes(16));
        session_id($session_id);
        session_start();
    }

    // Store session creation time
    if (!isset($_SESSION['created_at'])) {
        $_SESSION['created_at'] = time();
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();

    return $session_id;
}

// Function to destroy session
function destroySession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
        session_write_close();
        setcookie(session_name(), '', time() - 3600, '/');
        return true;
    }
    return false;
}

// Function to regenerate session ID with proper cleanup
function regenerateSessionId($deleteOldSession = true) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $oldSessionData = $_SESSION;
        session_write_close();
        session_regenerate_id($deleteOldSession);
        session_start();
        
        // Restore session data
        foreach ($oldSessionData as $key => $value) {
            $_SESSION[$key] = $value;
        }
        
        return true;
    }
    return false;
}
?>
