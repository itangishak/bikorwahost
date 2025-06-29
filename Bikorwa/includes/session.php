<?php
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/utils/DbSessionHandler.php';

function startDbSession() {
    // If a session was already started using the default handler,
    // preserve the ID and data before reinitialising with the DB handler
    $existingId = null;
    $existingData = [];

    if (session_status() === PHP_SESSION_ACTIVE) {
        $existingId = session_id();
        $existingData = $_SESSION;
        session_write_close();
    } elseif (!empty($_COOKIE[session_name() ?? 'PHPSESSID'])) {
        // No active session but cookie might contain an ID
        $existingId = $_COOKIE[session_name() ?? 'PHPSESSID'];
    }

    $database = new Database();
    $pdo = $database->getConnection();
    if ($pdo instanceof PDO) {
        $handler = new DbSessionHandler($pdo);
        session_set_save_handler($handler, true);
    } else {
        error_log('Unable to initialize DB session handler: connection failed');
    }

    // Disable cookies to avoid cookie-based sessions
    ini_set('session.use_cookies', '0');
    ini_set('session.use_only_cookies', '0');

    // Check for session token in request
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $token = $headers['X-Session-Id'] ?? '';
    if (!$token && isset($_POST['session_id'])) {
        $token = $_POST['session_id'];
    }

    if (!$token && $existingId) {
        $token = $existingId;
    }

    if ($token) {
        session_id($token);
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Restore any previously stored data
    if (!empty($existingData)) {
        foreach ($existingData as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }

    return session_id();
}
?>
