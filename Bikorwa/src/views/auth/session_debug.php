<?php
/**
 * Session Debug Script
 * Use this to check which session method is being used
 */

// Include your config files
require_once 'https://uab.bumadventiste.org/Bikorwa/src/config/config.php';

// Check if database session function exists
if (function_exists('startDbSession')) {
    echo "✅ Database session function EXISTS<br>";
    
    try {
        $sessionId = startDbSession();
        echo "✅ Database session STARTED successfully<br>";
        echo "Session ID: " . $sessionId . "<br>";
        echo "Session Handler: " . session_get_save_handler() . "<br>";
        
        // Check if we can write to database sessions
        $_SESSION['test'] = 'database_session_test';
        echo "✅ Database session WRITE test successful<br>";
        
    } catch (Exception $e) {
        echo "❌ Database session FAILED: " . $e->getMessage() . "<br>";
        echo "Falling back to cookie sessions...<br>";
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        echo "Cookie session started. ID: " . session_id() . "<br>";
        echo "Session Handler: " . session_get_save_handler() . "<br>";
    }
} else {
    echo "❌ Database session function NOT FOUND<br>";
    echo "Using cookie-based sessions...<br>";
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    echo "Cookie session started. ID: " . session_id() . "<br>";
    echo "Session Handler: " . session_get_save_handler() . "<br>";
}

// Display current session data
echo "<br><strong>Current Session Data:</strong><br>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check session configuration
echo "<br><strong>Session Configuration:</strong><br>";
echo "Session Name: " . session_name() . "<br>";
echo "Session Save Path: " . session_save_path() . "<br>";
echo "Session Cookie Lifetime: " . session_get_cookie_params()['lifetime'] . "<br>";
echo "Session Cookie Domain: " . session_get_cookie_params()['domain'] . "<br>";
echo "Session Cookie Path: " . session_get_cookie_params()['path'] . "<br>";
echo "Session Cookie Secure: " . (session_get_cookie_params()['secure'] ? 'Yes' : 'No') . "<br>";
echo "Session Cookie HttpOnly: " . (session_get_cookie_params()['httponly'] ? 'Yes' : 'No') . "<br>";

// Check if session file exists (for cookie sessions)
if (session_get_save_handler() === 'files') {
    $sessionFile = session_save_path() . '/sess_' . session_id();
    echo "Session File Path: " . $sessionFile . "<br>";
    echo "Session File Exists: " . (file_exists($sessionFile) ? 'Yes' : 'No') . "<br>";
}

// Test database connection for session storage
try {
    if (class_exists('Database')) {
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            echo "<br>✅ Database connection successful<br>";
            
            // Check if sessions table exists
            $stmt = $conn->prepare("SHOW TABLES LIKE 'sessions'");
            $stmt->execute();
            $tableExists = $stmt->fetch();
            
            if ($tableExists) {
                echo "✅ Sessions table exists<br>";
                
                // Count current sessions in database
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sessions");
                $stmt->execute();
                $result = $stmt->fetch();
                echo "Current sessions in database: " . $result['count'] . "<br>";
                
                // Show recent sessions
                $stmt = $conn->prepare("SELECT id, expires FROM sessions ORDER BY expires DESC LIMIT 5");
                $stmt->execute();
                $sessions = $stmt->fetchAll();
                
                if ($sessions) {
                    echo "<br><strong>Recent Database Sessions:</strong><br>";
                    foreach ($sessions as $session) {
                        echo "ID: " . substr($session['id'], 0, 20) . "... | Expires: " . $session['expires'] . "<br>";
                    }
                }
            } else {
                echo "❌ Sessions table does NOT exist<br>";
            }
        } else {
            echo "❌ Database connection failed<br>";
        }
    } else {
        echo "❌ Database class not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}
?>