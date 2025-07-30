<?php
/**
 * Session debug tool - check session state
 */

// Start session
session_start();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .info { background: #e7f3ff; padding: 10px; margin: 10px 0; border-left: 4px solid #2196F3; }
        .success { background: #d4edda; padding: 10px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; padding: 10px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; padding: 10px; margin: 10px 0; border-left: 4px solid #ffc107; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow: auto; }
        .btn { padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; display: inline-block; }
        .btn-danger { background: #dc3545; }
        .btn-success { background: #28a745; }
    </style>
</head>
<body>
    <h1>Session Debug Tool</h1>
    
    <?php
    // Check if user wants to simulate login
    if (isset($_GET['simulate_login'])) {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'test_user';
        $_SESSION['user_name'] = 'Test User';
        $_SESSION['user_role'] = 'gestionnaire';
        $_SESSION['user_active'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        echo '<div class="success">✓ Simulated login - session variables set!</div>';
    }
    
    // Check if user wants to clear session
    if (isset($_GET['clear_session'])) {
        session_unset();
        session_destroy();
        session_start(); // Start new session
        echo '<div class="warning">⚠ Session cleared!</div>';
    }
    ?>

    <div class="info">
        <strong>Session Status:</strong> <?= session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE' ?><br>
        <strong>Session ID:</strong> <?= session_id() ?><br>
        <strong>Session Name:</strong> <?= session_name() ?><br>
        <strong>Cookie Settings:</strong>
        <ul>
            <li>Path: <?= session_get_cookie_params()['path'] ?></li>
            <li>Domain: <?= session_get_cookie_params()['domain'] ?></li>
            <li>Secure: <?= session_get_cookie_params()['secure'] ? 'Yes' : 'No' ?></li>
            <li>HttpOnly: <?= session_get_cookie_params()['httponly'] ? 'Yes' : 'No' ?></li>
            <li>Lifetime: <?= session_get_cookie_params()['lifetime'] ?> seconds</li>
        </ul>
    </div>

    <h3>Session Variables:</h3>
    <?php if (empty($_SESSION)): ?>
        <div class="error">❌ No session variables found!</div>
    <?php else: ?>
        <div class="success">✓ Session variables found:</div>
        <pre><?php print_r($_SESSION); ?></pre>
    <?php endif; ?>

    <h3>Login Status Check:</h3>
    <?php
    $is_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    ?>
    <div class="<?= $is_logged_in ? 'success' : 'error' ?>">
        <?= $is_logged_in ? '✓ User appears to be logged in' : '❌ User is NOT logged in' ?>
    </div>

    <?php if ($is_logged_in): ?>
        <div class="info">
            <strong>User Details:</strong><br>
            ID: <?= $_SESSION['user_id'] ?? 'Not set' ?><br>
            Username: <?= $_SESSION['username'] ?? 'Not set' ?><br>
            Name: <?= $_SESSION['user_name'] ?? 'Not set' ?><br>
            Role: <?= $_SESSION['user_role'] ?? 'Not set' ?><br>
            Active: <?= ($_SESSION['user_active'] ?? false) ? 'Yes' : 'No' ?><br>
        </div>
    <?php endif; ?>

    <h3>Auth Check Test:</h3>
    <?php
    // Test the auth check logic
    $auth_check_result = 'PASS';
    $auth_check_message = 'User would be allowed to access dashboard';
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        $auth_check_result = 'FAIL';
        $auth_check_message = 'User would be redirected to login (missing user_id or logged_in not true)';
    } elseif (isset($_SESSION['user_active']) && $_SESSION['user_active'] !== true) {
        $auth_check_result = 'FAIL';
        $auth_check_message = 'User would be redirected to login (account not active)';
    }
    ?>
    <div class="<?= $auth_check_result === 'PASS' ? 'success' : 'error' ?>">
        <?= $auth_check_result === 'PASS' ? '✓' : '❌' ?> <?= $auth_check_message ?>
    </div>

    <h3>Actions:</h3>
    <a href="?simulate_login=1" class="btn btn-success">Simulate Login</a>
    <a href="?clear_session=1" class="btn btn-danger">Clear Session</a>
    <a href="debug_session.php" class="btn">Refresh</a>
    
    <?php if ($is_logged_in): ?>
        <a href="src/views/dashboard/index.php" class="btn">Test Dashboard Access</a>
    <?php endif; ?>
    
    <a href="src/views/auth/login.php" class="btn">Go to Login</a>

    <h3>Server Info:</h3>
    <div class="info">
        <strong>PHP Version:</strong> <?= PHP_VERSION ?><br>
        <strong>Server Software:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?><br>
        <strong>Document Root:</strong> <?= $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown' ?><br>
        <strong>Current Script:</strong> <?= $_SERVER['SCRIPT_NAME'] ?? 'Unknown' ?><br>
        <strong>Request URI:</strong> <?= $_SERVER['REQUEST_URI'] ?? 'Unknown' ?><br>
        <strong>HTTP Host:</strong> <?= $_SERVER['HTTP_HOST'] ?? 'Unknown' ?><br>
    </div>

    <h3>Session File Location:</h3>
    <div class="info">
        <strong>Session Save Path:</strong> <?= session_save_path() ?: 'Default (usually /tmp)' ?><br>
        <strong>Session Save Handler:</strong> <?= ini_get('session.save_handler') ?><br>
        <strong>Session GC Probability:</strong> <?= ini_get('session.gc_probability') ?>/<?= ini_get('session.gc_divisor') ?><br>
        <strong>Session Lifetime:</strong> <?= ini_get('session.gc_maxlifetime') ?> seconds<br>
    </div>

</body>
</html>
