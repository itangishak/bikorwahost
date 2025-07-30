<?php
session_start();

require_once __DIR__ . '/../../../src/config/config.php';
require_once __DIR__ . '/../../../src/config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    try {
        $database = new Database();
        $conn = $database->getConnection();

        if (!$conn) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit();
        }

        $stmt = $conn->prepare("SELECT id, username, password, nom, role, actif FROM users WHERE username = ? AND actif = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Determine effective role (treat admin username as gestionnaire)
            $effectiveRole = $user['role'];
            if ($user['username'] === 'admin') {
                $effectiveRole = 'gestionnaire';
            }
            
            // Store session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['nom'];
            $_SESSION['role'] = $effectiveRole;
            $_SESSION['logged_in'] = true;
            $_SESSION['last_activity'] = time();

            // Determine redirect based on effective role
            if ($effectiveRole === 'gestionnaire') {
                $link = BASE_URL . '/src/views/dashboard/index.php';
            } elseif ($effectiveRole === 'receptionniste') {
                $link = BASE_URL . '/src/views/dashboard/receptionniste.php';
            } else {
                $link = BASE_URL . '/src/views/dashboard/index.php';
            }

            echo json_encode(['success' => true, 'redirectUrl' => $link]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'An error occurred']);
    }
}
?>