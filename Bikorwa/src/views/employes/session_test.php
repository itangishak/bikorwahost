<?php
require_once './../../../includes/session.php';
require_once './../../../includes/init.php';

$page_title = "Test de Session";
$active_page = "session_test";

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
    <?php include_once './../../../includes/head.php'; ?>
</head>
<body class="sb-nav-fixed">
    <?php include_once './../../../includes/nav.php'; ?>
    <div id="layoutSidenav">
        <?php include_once './../../../includes/sidebar.php'; ?>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    <h1 class="mt-4">Test de Session</h1>
                    <div class="alert alert-info">
                        <p>Statut de la session PHP : <strong><?php echo $session_status == PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'; ?></strong></p>
                        <p>Connecté : <strong><?php echo $logged_in ? 'Oui' : 'Non'; ?></strong></p>
                        <p>Rôle de l'utilisateur : <strong><?php echo htmlspecialchars($user_role); ?></strong></p>
                        <p>ID de l'utilisateur : <strong><?php echo htmlspecialchars($user_id); ?></strong></p>
                        <p>Pour accéder à la page de gestion des utilisateurs, vous devez être connecté avec le rôle 'gestionnaire'.</p>
                    </div>
                    <a href="/dashboard/index.php" class="btn btn-primary">Retour au tableau de bord</a>
                    <a href="/login.php" class="btn btn-secondary">Se connecter</a>
                </div>
            </main>
            <?php include_once './../../../includes/footer.php'; ?>
        </div>
    </div>
    <?php include_once './../../../includes/scripts.php'; ?>
</body>
</html>
