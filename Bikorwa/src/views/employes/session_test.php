<?php
require_once './../../../includes/session.php';
require_once './../../../includes/init.php';

require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize auth
$auth = new Auth($conn);

// Check session status
$session_status = session_status();
$logged_in = $auth->isLoggedIn();
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Non défini';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Non défini';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Session</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .alert { background-color: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; }
        .btn { display: inline-block; padding: 10px 20px; margin: 5px; color: white; text-decoration: none; border-radius: 5px; }
        .btn-primary { background-color: #007bff; }
        .btn-secondary { background-color: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test de Session</h1>
        <div class="alert">
            <p>Statut de la session PHP : <strong><?php echo $session_status == PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'; ?></strong></p>
            <p>Connecté : <strong><?php echo $logged_in ? 'Oui' : 'Non'; ?></strong></p>
            <p>Rôle de l'utilisateur : <strong><?php echo htmlspecialchars($user_role); ?></strong></p>
            <p>ID de l'utilisateur : <strong><?php echo htmlspecialchars($user_id); ?></strong></p>
            <p>Pour accéder à la page de gestion des utilisateurs, vous devez être connecté avec le rôle 'gestionnaire'.</p>
        </div>
        <a href="/dashboard/index.php" class="btn btn-primary">Retour au tableau de bord</a>
        <a href="/login.php" class="btn btn-secondary">Se connecter</a>
    </div>
</body>
</html>
