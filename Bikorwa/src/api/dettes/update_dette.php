<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db.php';

$response = ['success' => false, 'message' => ''];

try {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verify authentication
    if (!Auth::isLoggedIn() || !isset($_SESSION['user_id'])) {
        throw new Exception('Session expirée ou non autorisée', 401);
    }

    // Only managers can update debts
    if ($_SESSION['role'] !== 'gestionnaire') {
        throw new Exception('Permission refusée: rôle gestionnaire requis', 403);
    }

    // Validate required fields
    if (empty($_POST['dette_id']) || empty($_POST['client_id']) || empty($_POST['montant_initial'])) {
        throw new Exception('Données manquantes', 400);
    }

    $pdo = getPDO();
    $pdo->beginTransaction();

    try {
        // Update debt
        $stmt = $pdo->prepare("UPDATE dettes SET 
            client_id = :client_id, 
            montant_initial = :montant_initial,
            note = :note,
            date_echeance = :date_echeance,
            updated_at = NOW(),
            updated_by = :user_id
            WHERE id = :dette_id");

        $stmt->execute([
            ':client_id' => $_POST['client_id'],
            ':montant_initial' => $_POST['montant_initial'],
            ':note' => $_POST['note'] ?? null,
            ':date_echeance' => !empty($_POST['date_echeance']) ? $_POST['date_echeance'] : null,
            ':user_id' => $_SESSION['user_id'],
            ':dette_id' => $_POST['dette_id']
        ]);

        $pdo->commit();
        $response['success'] = true;
        $response['message'] = 'Dette mise à jour avec succès';
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw new Exception('Erreur de base de données: ' . $e->getMessage(), 500);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    $response['message'] = $e->getMessage();
    $response['debug'] = [
        'session' => $_SESSION ?? null,
        'post_data' => $_POST
    ];
}

echo json_encode($response);
