<?php
/**
 * Database-based Session Manager using user_id as key
 * No cookies, only user_id based sessions
 */

class DatabaseSessionManager {
    private $pdo;
    private $current_user_id = null;
    private $session_data = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->createSessionTable();
    }
    
    /**
     * Create session table if it doesn't exist
     */
    private function createSessionTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
                user_id INT PRIMARY KEY,
                data TEXT NOT NULL,
                expires DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log('SessionManager: Failed to create user_sessions table: ' . $e->getMessage());
        }
    }
    
    /**
     * Start session for a specific user
     */
    public function startSession($user_id) {
        $this->current_user_id = $user_id;
        $this->loadSessionData();
        return true;
    }
    
    /**
     * Load session data from database
     */
    private function loadSessionData() {
        if (!$this->current_user_id) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT data FROM user_sessions WHERE user_id = :user_id AND expires > NOW()");
            $stmt->execute(['user_id' => $this->current_user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $this->session_data = json_decode($row['data'], true) ?: [];
            } else {
                $this->session_data = [];
            }
            
            return true;
        } catch (PDOException $e) {
            error_log('SessionManager: Failed to load session data: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Save session data to database
     */
    private function saveSessionData() {
        if (!$this->current_user_id) {
            return false;
        }
        
        try {
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            $data = json_encode($this->session_data);
            
            $stmt = $this->pdo->prepare("REPLACE INTO user_sessions (user_id, data, expires) VALUES (:user_id, :data, :expires)");
            return $stmt->execute([
                'user_id' => $this->current_user_id,
                'data' => $data,
                'expires' => $expires
            ]);
        } catch (PDOException $e) {
            error_log('SessionManager: Failed to save session data: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set session variable
     */
    public function set($key, $value) {
        $this->session_data[$key] = $value;
        $this->saveSessionData();
    }
    
    /**
     * Get session variable
     */
    public function get($key, $default = null) {
        return isset($this->session_data[$key]) ? $this->session_data[$key] : $default;
    }
    
    /**
     * Check if session variable exists
     */
    public function has($key) {
        return isset($this->session_data[$key]);
    }
    
    /**
     * Remove session variable
     */
    public function remove($key) {
        unset($this->session_data[$key]);
        $this->saveSessionData();
    }
    
    /**
     * Login user and create session
     */
    public function loginUser($user_data) {
        $this->current_user_id = $user_data['id'];
        $this->session_data = [
            'user_id' => $user_data['id'],
            'username' => $user_data['username'],
            'user_name' => $user_data['nom'],
            'user_role' => $user_data['role'],
            'user_active' => $user_data['actif'],
            'logged_in' => true,
            'login_time' => time(),
            'last_activity' => time()
        ];
        
        return $this->saveSessionData();
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn($user_id = null) {
        if ($user_id) {
            $this->startSession($user_id);
        }
        
        return $this->has('logged_in') && $this->get('logged_in') === true && $this->has('user_id');
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $this->get('user_id'),
            'username' => $this->get('username'),
            'name' => $this->get('user_name'),
            'role' => $this->get('user_role'),
            'active' => $this->get('user_active'),
            'login_time' => $this->get('login_time')
        ];
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role, $user_id = null) {
        if ($user_id) {
            $this->startSession($user_id);
        }
        
        return $this->isLoggedIn() && $this->get('user_role') === $role;
    }
    
    /**
     * Logout user
     */
    public function logout($user_id = null) {
        if ($user_id) {
            $this->current_user_id = $user_id;
        }
        
        if (!$this->current_user_id) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE user_id = :user_id");
            $result = $stmt->execute(['user_id' => $this->current_user_id]);
            
            $this->session_data = [];
            $this->current_user_id = null;
            
            return $result;
        } catch (PDOException $e) {
            error_log('SessionManager: Failed to logout user: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cleanup expired sessions
     */
    public function cleanupExpiredSessions() {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE expires < NOW()");
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('SessionManager: Failed to cleanup expired sessions: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if session exists for user
     */
    public function sessionExists($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id = :user_id AND expires > NOW()");
            $stmt->execute(['user_id' => $user_id]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log('SessionManager: Failed to check session existence: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update last activity
     */
    public function updateActivity($user_id = null) {
        if ($user_id) {
            $this->startSession($user_id);
        }
        
        if ($this->isLoggedIn()) {
            $this->set('last_activity', time());
        }
    }
    
    /**
     * Get all session data
     */
    public function getAllData() {
        return $this->session_data;
    }
}
