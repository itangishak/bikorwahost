<?php
/**
 * Centralized Session Manager for BIKORWA SHOP
 * This file ensures consistent session handling across the entire application
 */

// Prevent multiple inclusions
if (defined('SESSION_MANAGER_LOADED') || class_exists('SessionManager')) {
    return;
}
define('SESSION_MANAGER_LOADED', true);

// Include required dependencies
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/utils/DbSessionHandler.php';

class SessionManager 
{
    private static $instance = null;
    private $pdo;
    private $sessionStarted = false;
    private $sessionHandler;
    
    // Session configuration
    private $sessionLifetime = 3600; // 1 hour
    private $sessionName = 'BIKORWA_SESSION';
    
    private function __construct() 
    {
        $this->initializeDatabase();
        $this->configureSession();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() 
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize database connection
     */
    private function initializeDatabase() 
    {
        try {
            $database = new Database();
            $this->pdo = $database->getConnection();
            
            if (!$this->pdo instanceof PDO) {
                throw new Exception('Failed to get database connection for session handler');
            }
            
            // Ensure sessions table exists
            $this->createSessionsTable();
            
        } catch (Exception $e) {
            error_log('SessionManager: Failed to initialize database: ' . $e->getMessage());
            // Fall back to file-based sessions
            $this->pdo = null;
        }
    }
    
    /**
     * Create sessions table if it doesn't exist
     */
    private function createSessionsTable() 
    {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(128) PRIMARY KEY,
                data TEXT NOT NULL,
                expires DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log('SessionManager: Failed to create sessions table: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Configure session settings
     */
    private function configureSession() 
    {
        // Configure session settings before starting
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Set session lifetime
        ini_set('session.gc_maxlifetime', $this->sessionLifetime);
        ini_set('session.cookie_lifetime', $this->sessionLifetime);
        
        // Set session name
        session_name($this->sessionName);
        
        // Set up database session handler if available
        if ($this->pdo) {
            try {
                $this->sessionHandler = new DbSessionHandler($this->pdo);
                session_set_save_handler($this->sessionHandler, true);
            } catch (Exception $e) {
                error_log('SessionManager: Failed to set database session handler: ' . $e->getMessage());
                // Continue with default file-based sessions
            }
        }
    }
    
    /**
     * Start session if not already started
     */
    public function startSession() 
    {
        if ($this->sessionStarted || session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        
        try {
            if (session_start()) {
                $this->sessionStarted = true;
                $this->initializeSessionData();
                $this->validateSession();
                return true;
            }
        } catch (Exception $e) {
            error_log('SessionManager: Failed to start session: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Initialize session data
     */
    private function initializeSessionData() 
    {
        if (!isset($_SESSION['created_at'])) {
            $_SESSION['created_at'] = time();
        }
        
        $_SESSION['last_activity'] = time();
        
        // Generate CSRF token if not exists
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
    
    /**
     * Validate session (check expiration, regenerate ID periodically)
     */
    private function validateSession() 
    {
        $now = time();
        
        // Check if session has expired
        if (isset($_SESSION['last_activity'])) {
            if ($now - $_SESSION['last_activity'] > $this->sessionLifetime) {
                $this->destroySession();
                return false;
            }
        }
        
        // Regenerate session ID periodically (every 30 minutes)
        if (isset($_SESSION['last_regeneration'])) {
            if ($now - $_SESSION['last_regeneration'] > 1800) {
                $this->regenerateSessionId();
            }
        } else {
            $_SESSION['last_regeneration'] = $now;
        }
        
        return true;
    }
    
    /**
     * Regenerate session ID
     */
    public function regenerateSessionId($deleteOldSession = true) 
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        
        try {
            session_regenerate_id($deleteOldSession);
            $_SESSION['last_regeneration'] = time();
            return true;
        } catch (Exception $e) {
            error_log('SessionManager: Failed to regenerate session ID: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Destroy session
     */
    public function destroySession() 
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            try {
                // Clear session data
                $_SESSION = array();
                
                // Delete session cookie
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }
                
                // Destroy session
                session_destroy();
                $this->sessionStarted = false;
                
                return true;
            } catch (Exception $e) {
                error_log('SessionManager: Failed to destroy session: ' . $e->getMessage());
                return false;
            }
        }
        return false;
    }
    
    /**
     * Set session variable
     */
    public function set($key, $value) 
    {
        if (!$this->sessionStarted) {
            $this->startSession();
        }
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get session variable
     */
    public function get($key, $default = null) 
    {
        if (!$this->sessionStarted) {
            $this->startSession();
        }
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }
    
    /**
     * Check if session variable exists
     */
    public function has($key) 
    {
        if (!$this->sessionStarted) {
            $this->startSession();
        }
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session variable
     */
    public function remove($key) 
    {
        if (!$this->sessionStarted) {
            $this->startSession();
        }
        unset($_SESSION[$key]);
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() 
    {
        return $this->has('user_id') && !empty($this->get('user_id')) && $this->get('logged_in') === true;
    }
    
    /**
     * Get current user ID
     */
    public function getUserId() 
    {
        return $this->get('user_id');
    }
    
    /**
     * Get current username
     */
    public function getUsername() 
    {
        return $this->get('username');
    }
    
    /**
     * Get current user role
     */
    public function getUserRole() 
    {
        return $this->get('user_role');
    }
    
    /**
     * Get current user name
     */
    public function getUserName() 
    {
        return $this->get('user_name');
    }
    
    /**
     * Check if user is active
     */
    public function isUserActive() 
    {
        return $this->get('user_active') === true;
    }
    
    /**
     * Check if user is manager
     */
    public function isManager() 
    {
        return $this->getUserRole() === 'gestionnaire';
    }
    
    /**
     * Check if user is receptionist
     */
    public function isReceptionist() 
    {
        return $this->getUserRole() === 'receptionniste';
    }
    
    /**
     * Login user
     */
    public function loginUser($userData) 
    {
        if (!$this->sessionStarted) {
            $this->startSession();
        }
        
        // Regenerate session ID for security
        $this->regenerateSessionId(true);
        
        // Set user session data
        $this->set('user_id', $userData['id']);
        $this->set('username', $userData['username']);
        $this->set('user_name', $userData['nom']);
        $this->set('user_role', $userData['role']);
        $this->set('user_active', $userData['actif']);
        $this->set('logged_in', true);
        $this->set('login_time', time());
        
        return true;
    }
    
    /**
     * Logout user
     */
    public function logoutUser() 
    {
        return $this->destroySession();
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() 
    {
        if (!$this->has('csrf_token')) {
            $this->set('csrf_token', bin2hex(random_bytes(32)));
        }
        return $this->get('csrf_token');
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token) 
    {
        $sessionToken = $this->get('csrf_token');
        return !empty($sessionToken) && hash_equals($sessionToken, $token);
    }
    
    /**
     * Get session ID
     */
    public function getSessionId() 
    {
        return session_id();
    }
    
    /**
     * Check if session is started
     */
    public function isSessionStarted() 
    {
        return $this->sessionStarted && session_status() === PHP_SESSION_ACTIVE;
    }
}

// Initialize session manager and start session
$sessionManager = SessionManager::getInstance();
$sessionManager->startSession();

// Define global helper functions for backward compatibility
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        global $sessionManager;
        return $sessionManager->isLoggedIn();
    }
}

if (!function_exists('is_manager')) {
    function is_manager() {
        global $sessionManager;
        return $sessionManager->isManager();
    }
}

if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        global $sessionManager;
        return $sessionManager->generateCSRFToken();
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        global $sessionManager;
        return $sessionManager->verifyCSRFToken($token);
    }
}
