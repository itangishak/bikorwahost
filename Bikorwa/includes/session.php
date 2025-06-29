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
    }

    $database = new Database();
    $pdo = $database->getConnection();
    if ($pdo instanceof PDO) {
        // Ensure the sessions table exists. If it doesn't, create it
        try {
            $exists = $pdo->query("SHOW TABLES LIKE 'sessions'")->rowCount() > 0;
            if (!$exists) {
                $createQuery = "CREATE TABLE IF NOT EXISTS sessions (
                    id VARCHAR(128) PRIMARY KEY,
                    data TEXT NOT NULL,
                    expires DATETIME NOT NULL
                )";
                $pdo->exec($createQuery);
            }
        } catch (Exception $e) {
            error_log('Unable to verify sessions table: ' . $e->getMessage());
        }

        $handler = new DbSessionHandler($pdo);
        session_set_save_handler($handler, true);
    } else {
        error_log('Unable to initialize DB session handler: connection failed');
    }

    // Check for token in header
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $token = $headers['X-Session-Id'] ?? '';
    if (!$token && isset($_POST['session_id'])) {
        $token = $_POST['session_id'];
    }

    if ($token) {
        session_id($token);
    } elseif ($existingId) {
        session_id($existingId);
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
