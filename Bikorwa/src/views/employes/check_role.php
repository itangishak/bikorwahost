<?php
require_once './../../../includes/session.php';
require_once './../../../includes/init.php';

$page_title = "Vérification du Rôle";
$active_page = "check_role";

require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize auth
$auth = new Auth($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// Get user role from session
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Non défini';

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
                    <h1 class="mt-4">Vérification du Rôle</h1>
                    <div class="alert alert-info">
                        <p>Votre rôle actuel est : <strong><?php echo htmlspecialchars($user_role); ?></strong></p>
                        <p>Pour accéder à la page de gestion des utilisateurs, vous devez avoir le rôle 'gestionnaire'.</p>
                    </div>
                    <a href="/dashboard/index.php" class="btn btn-primary">Retour au tableau de bord</a>
                </div>
            </main>
            <?php include_once './../../../includes/footer.php'; ?>
        </div>
    </div>
    <?php include_once './../../../includes/scripts.php'; ?>
</body>
</html>
