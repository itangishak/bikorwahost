<?php
/**
 * Debug script to identify login issues
 * KUBIKOTI BAR
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>KUBIKOTI BAR - Login Debug</h1>";

// Check if we're in the right directory
$currentDir = __DIR__;
$baseDir = dirname(__DIR__, 3);

echo "<h2>Directory Information</h2>";
echo "<p><strong>Current Directory:</strong> $currentDir</p>";
echo "<p><strong>Base Directory:</strong> $baseDir</p>";

// Check if required files exist
$requiredFiles = [
    'Config' => $baseDir . '/src/config/config.php',
    'Database' => $baseDir . '/src/config/database.php',
    'Auth Utility' => $baseDir . '/src/utils/Auth.php',
    'User Model' => $baseDir . '/src/models/User.php',
    'Auth Controller' => $baseDir . '/src/controllers/AuthController.php'
];

echo "<h2>Required Files Check</h2>";
foreach ($requiredFiles as $name => $file) {
    $exists = file_exists($file);
    $status = $exists ? "✅ EXISTS" : "❌ MISSING";
    $color = $exists ? "green" : "red";
    echo "<p style='color: $color'><strong>$name:</strong> $status - $file</p>";
}

// Try to include the files
echo "<h2>File Inclusion Test</h2>";
$errors = [];

try {
    if (file_exists($baseDir . '/src/config/config.php')) {
        require_once $baseDir . '/src/config/config.php';
        echo "<p style='color: green'>✅ Config loaded successfully</p>";
    } else {
        $errors[] = "Config file missing";
    }
} catch (Exception $e) {
    $errors[] = "Config error: " . $e->getMessage();
}

try {
    if (file_exists($baseDir . '/src/config/database.php')) {
        require_once $baseDir . '/src/config/database.php';
        echo "<p style='color: green'>✅ Database class loaded successfully</p>";
    } else {
        $errors[] = "Database file missing";
    }
} catch (Exception $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

// Test database connection
echo "<h2>Database Connection Test</h2>";
try {
    if (class_exists('Database')) {
        $database = new Database();
        $conn = $database->getConnection();
        if ($conn) {
            echo "<p style='color: green'>✅ Database connection successful</p>";
            
            // Check if users table exists
            try {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM users");
                $stmt->execute();
                $count = $stmt->fetchColumn();
                echo "<p style='color: green'>✅ Users table exists with $count users</p>";
            } catch (Exception $e) {
                echo "<p style='color: red'>❌ Users table error: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: red'>❌ Database connection failed</p>";
        }
    } else {
        echo "<p style='color: red'>❌ Database class not found</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red'>❌ Database connection error: " . $e->getMessage() . "</p>";
}

// Try to load other required classes
$classes = [
    'Auth' => $baseDir . '/src/utils/Auth.php',
    'User' => $baseDir . '/src/models/User.php',
    'AuthController' => $baseDir . '/src/controllers/AuthController.php'
];

foreach ($classes as $className => $file) {
    try {
        if (file_exists($file)) {
            require_once $file;
            if (class_exists($className)) {
                echo "<p style='color: green'>✅ $className class loaded successfully</p>";
            } else {
                echo "<p style='color: red'>❌ $className class not found in file</p>";
            }
        } else {
            echo "<p style='color: red'>❌ $className file missing: $file</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red'>❌ $className error: " . $e->getMessage() . "</p>";
    }
}

// Test AuthController instantiation
echo "<h2>AuthController Test</h2>";
try {
    if (class_exists('AuthController')) {
        $authController = new AuthController();
        echo "<p style='color: green'>✅ AuthController instantiated successfully</p>";
        
        // Test login method with dummy data
        echo "<h3>Test Login Method</h3>";
        $result = $authController->login('test_user', 'test_password');
        echo "<p><strong>Login test result:</strong></p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    } else {
        echo "<p style='color: red'>❌ AuthController class not available</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red'>❌ AuthController error: " . $e->getMessage() . "</p>";
    echo "<p><strong>Stack trace:</strong></p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Show any collected errors
if (!empty($errors)) {
    echo "<h2>Errors Summary</h2>";
    foreach ($errors as $error) {
        echo "<p style='color: red'>❌ $error</p>";
    }
}

echo "<h2>Recommendations</h2>";
echo "<ol>";
echo "<li>Make sure all required files exist in the correct locations</li>";
echo "<li>Check database connection parameters in config.php</li>";
echo "<li>Verify that the users table exists and has the correct structure</li>";
echo "<li>Check file permissions (755 for directories, 644 for files)</li>";
echo "<li>Review the error logs in the /logs directory</li>";
echo "</ol>";

echo "<p><strong>Next steps:</strong> Run this script and check the output to identify missing files or configuration issues.</p>";
?>