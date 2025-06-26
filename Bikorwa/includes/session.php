<?php
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/utils/DbSessionHandler.php';

function startDbSession() {
    // Disable cookie-based sessions
    ini_set('session.use_cookies', 0);
    ini_set('session.use_only_cookies', 0);
    ini_set('session.use_trans_sid', 0);

    $database = new Database();
    $pdo = $database->getConnection();
    $handler = new DbSessionHandler($pdo);
    session_set_save_handler($handler, true);

    // Check for token in header
    $headers = getallheaders();
    $token = $headers['X-Session-Id'] ?? '';
    if (!$token && isset($_POST['session_id'])) {
        $token = $_POST['session_id'];
    }
    if ($token) {
        session_id($token);
    }

    session_start();

    return session_id();
}
?>
