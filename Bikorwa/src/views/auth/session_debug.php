<?php
/**
 * Session Debug Script - Fixed Version
 * Use this to check which session method is being used
 * BIKORWA SHOP
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><title>Session Debug - BIKORWA</title><style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style></head><body>";
echo "<h1>BIKORWA Session Debug</h1>";

// Function to display results with proper formatting
function displayDebugResult($message, $type = 'info') {
    $class = $type === 'success' ? 'success' : ($type === 'error' ? 'error' : 'info');
    echo "<div class='$class'>$message</div>";
}

// Try to include config files with proper error handling
$configPaths = [
    __DIR__ . '/../../config/config.php',
    __DIR__ . '/../../../src/config/config.php',
    dirname(__DIR__, 2) . '/config/config.php',
    dirname(__DIR__, 3) . '/src/config/config.php'
];

$configLoaded = false;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        try {
            require_once $path;
            displayDebugResult("✅ Config loaded from: $path", 'success');
            $configLoaded = true;
            break;
        } catch (Exception $e) {
            displayDebugResult("❌ Error loading config from $path: " . $e->getMessage(), 'error');
        }
    }
}

if (!$configLoaded) {
    displayDebugResult("❌ Could not find or load config.php", 'error');
    displayDebugResult("Searched paths:", 'info');
    foreach ($configPaths as $path) {
        displayDebugResult("- $path", 'info');
    }
}

// Try to load database class
$databasePaths = [
    __DIR__ . '/../../config/database.php',
    __DIR__ . '/../../../src/config/database.php',
    dirname(__DIR__, 2) . '/config/database.php',
    dirname(__DIR__, 3) . '/src/config/database.php'
];

$databaseLoaded = false;
foreach ($databasePaths as $path) {
    if (file_exists($path)) {
        try {
            require_once $path;
            displayDebugResult("✅ Database class loaded from: $path", 'success');
            $databaseLoaded = true;
            break;
        } catch (Exception $e) {
            displayDebugResult("❌ Error loading database from $path: " . $e->getMessage(), 'error');
        }
    }
}

echo "<hr>";

// Check if database session function exists
if (function_exists('startDbSession')) {
    displayDebugResult("✅ Database session function EXISTS", 'success');
    
    try {
        $sessionId = startDbSession();
        displayDebugResult("✅ Database session STARTED successfully", 'success');
        displayDebugResult("Session ID: " . $sessionId, 'info');
        displayDebugResult("Session Handler: " . session_get_save_handler(), 'info');
        
        // Check if we can write to database sessions
        $_SESSION['test'] = 'database_session_test_' . date('Y-m-d H:i:s');
        displayDebugResult("✅ Database session WRITE test successful", 'success');
        
    } catch (Exception $e) {
        displayDebugResult("❌ Database session FAILED: " . $e->getMessage(), 'error');
        displayDebugResult("Falling back to cookie sessions...", 'info');
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        displayDebugResult("Cookie session started. ID: " . session_id(), 'info');
        displayDebugResult("Session Handler: " . session_get_save_handler(), 'info');
    }
} else {
    displayDebugResult("❌ Database session function NOT FOUND", 'error');
    displayDebugResult("Using cookie-based sessions...", 'info');
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    displayDebugResult("Cookie session started. ID: " . session_id(), 'success');
    displayDebugResult("Session Handler: " . session_get_save_handler(), 'info');
}

// Display current session data
echo "<br><strong>Current Session Data:</strong><br>";
echo "<pre style='background-color: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
if (empty($_SESSION)) {
    echo "No session data found.";
} else {
    print_r($_SESSION);
}
echo "</pre>";

// Check session configuration
echo "<br><strong>Session Configuration:</strong><br>";
echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
echo "<tr><td><strong>Session Name:</strong></td><td>" . session_name() . "</td></tr>";
echo "<tr><td><strong>Session Save Path:</strong></td><td>" . session_save_path() . "</td></tr>";
echo "<tr><td><strong>Session Cookie Lifetime:</strong></td><td>" . session_get_cookie_params()['lifetime'] . " seconds</td></tr>";
echo "<tr><td><strong>Session Cookie Domain:</strong></td><td>" . (session_get_cookie_params()['domain'] ?: 'Not set') . "</td></tr>";
echo "<tr><td><strong>Session Cookie Path:</strong></td><td>" . session_get_cookie_params()['path'] . "</td></tr>";
echo "<tr><td><strong>Session Cookie Secure:</strong></td><td>" . (session_get_cookie_params()['secure'] ? 'Yes' : 'No') . "</td></tr>";
echo "<tr><td><strong>Session Cookie HttpOnly:</strong></td><td>" . (session_get_cookie_params()['httponly'] ? 'Yes' : 'No') . "</td></tr>";
echo "<tr><td><strong>Session Status:</strong></td><td>";
switch (session_status()) {
    case PHP_SESSION_DISABLED:
        echo "Sessions are disabled";
        break;
    case PHP_SESSION_NONE:
        echo "Sessions are enabled, but none exists";
        break;
    case PHP_SESSION_ACTIVE:
        echo "Sessions are enabled, and one exists";
        break;
}
echo "</td></tr>";
echo "</table>";

// Check if session file exists (for cookie sessions)
if (session_get_save_handler() === 'files') {
    $sessionFile = session_save_path() . '/sess_' . session_id();
    echo "<br><strong>File-based Session Info:</strong><br>";
    displayDebugResult("Session File Path: " . $sessionFile, 'info');
    displayDebugResult("Session File Exists: " . (file_exists($sessionFile) ? 'Yes' : 'No'), 
                      file_exists($sessionFile) ? 'success' : 'error');
    
    if (file_exists($sessionFile)) {
        $fileSize = filesize($sessionFile);
        $lastModified = date('Y-m-d H:i:s', filemtime($sessionFile));
        displayDebugResult("File Size: $fileSize bytes", 'info');
        displayDebugResult("Last Modified: $lastModified", 'info');
    }
}

// Test database connection for session storage
echo "<br><strong>Database Session Storage Test:</strong><br>";
try {
    if ($databaseLoaded && class_exists('Database')) {
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            displayDebugResult("✅ Database connection successful", 'success');
            
            // Check if sessions table exists
            $stmt = $conn->prepare("SHOW TABLES LIKE 'sessions'");
            $stmt->execute();
            $tableExists = $stmt->fetch();
            
            if ($tableExists) {
                displayDebugResult("✅ Sessions table exists", 'success');
                
                // Count current sessions in database
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sessions WHERE expires > NOW()");
                $stmt->execute();
                $result = $stmt->fetch();
                displayDebugResult("Active sessions in database: " . $result['count'], 'info');
                
                // Count total sessions
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sessions");
                $stmt->execute();
                $result = $stmt->fetch();
                displayDebugResult("Total sessions in database: " . $result['count'], 'info');
                
                // Show recent sessions
                $stmt = $conn->prepare("SELECT id, expires, created_at FROM sessions ORDER BY created_at DESC LIMIT 5");
                $stmt->execute();
                $sessions = $stmt->fetchAll();
                
                if ($sessions) {
                    echo "<br><strong>Recent Database Sessions:</strong><br>";
                    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
                    echo "<tr><th>Session ID (partial)</th><th>Expires</th><th>Created</th></tr>";
                    foreach ($sessions as $session) {
                        echo "<tr>";
                        echo "<td>" . substr($session['id'], 0, 20) . "...</td>";
                        echo "<td>" . $session['expires'] . "</td>";
                        echo "<td>" . ($session['created_at'] ?? 'N/A') . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
                
                // Test session cleanup
                $stmt = $conn->prepare("DELETE FROM sessions WHERE expires < NOW()");
                $stmt->execute();
                $cleanedUp = $stmt->rowCount();
                if ($cleanedUp > 0) {
                    displayDebugResult("Cleaned up $cleanedUp expired sessions", 'success');
                }
                
            } else {
                displayDebugResult("❌ Sessions table does NOT exist", 'error');
                displayDebugResult("You may need to create the sessions table for database session storage", 'info');
            }
        } else {
            displayDebugResult("❌ Database connection failed", 'error');
        }
    } else {
        displayDebugResult("❌ Database class not found or not loaded", 'error');
    }
} catch (Exception $e) {
    displayDebugResult("❌ Database error: " . $e->getMessage(), 'error');
}

// Add a test form to create session data
echo "<br><hr><br>";
echo "<h2>Session Test Form</h2>";
echo "<form method='post' action='" . $_SERVER['PHP_SELF'] . "'>";
echo "<div>Test Key: <input type='text' name='test_key' value='test_key' /></div><br>";
echo "<div>Test Value: <input type='text' name='test_value' value='test_value_" . date('His') . "' /></div><br>";
echo "<button type='submit' name='set_session'>Set Session Data</button>";
echo "<button type='submit' name='clear_session' style='margin-left: 10px;'>Clear Session</button>";
echo "</form>";

// Handle form submission
if (isset($_POST['set_session'])) {
    $key = $_POST['test_key'] ?? 'test';
    $value = $_POST['test_value'] ?? 'test_value';
    $_SESSION[$key] = $value;
    displayDebugResult("✅ Session data set: $key = $value", 'success');
    echo "<script>setTimeout(function(){ location.reload(); }, 1000);</script>";
}

if (isset($_POST['clear_session'])) {
    session_destroy();
    displayDebugResult("✅ Session cleared", 'success');
    echo "<script>setTimeout(function(){ location.reload(); }, 1000);</script>";
}

echo "</body></html>";
?>