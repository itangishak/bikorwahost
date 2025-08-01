<?php
// Production error settings
error_reporting(0);
ini_set('display_errors', 0);

ob_start();

try {
    // Start session if not active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Page de configuration de l'application
    $page_title = "Paramètres de l'application";
    $active_page = "parametres";

    // Include dependencies
    require_once('../../config/database.php');
    require_once('../../config/config.php');
    require_once('../../../includes/session.php');
    require_once('../../utils/Auth.php');
    require_once('../../utils/Settings.php');

    // Initialize database and authentication
    $database = new Database();
    $pdo = $database->getConnection();
    $auth = new Auth($pdo);

    // Verify authentication and role
    if (!$auth->isLoggedIn() || !$auth->isManager()) {
        header('Location: ' . BASE_URL . '/src/views/dashboard/index.php');
        exit;
    }

    // Load current settings
    $settings = new Settings($pdo);
    $current_theme = $settings->get('theme', 'light');
    $shop_name = $settings->get('shop_name', APP_NAME);
    $items_per_page = $settings->get('items_per_page', 10);

    require_once __DIR__ . '/../layouts/header.php';
?>
<div class="container">
    <h2 class="mb-4">Paramètres Généraux</h2>
    <form id="settingsForm">
        <div class="mb-3">
            <label class="form-label" for="shop_name">Nom du shop / application</label>
            <input type="text" class="form-control" id="shop_name" name="shop_name" value="<?= htmlspecialchars($shop_name) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label" for="theme">Mode de thème</label>
            <select class="form-select" id="theme" name="theme">
                <option value="light" <?= $current_theme==='light'?'selected':'' ?>>Clair</option>
                <option value="dark" <?= $current_theme==='dark'?'selected':'' ?>>Sombre</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="items_per_page">Éléments par page</label>
            <input type="number" min="1" class="form-control" id="items_per_page" name="items_per_page" value="<?= intval($items_per_page) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </form>
    <div id="settingsAlert" class="mt-3"></div>
</div>
<script>
const form = document.getElementById('settingsForm');
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = new FormData(form);
    const res = await fetch('../../api/settings/update.php', { method: 'POST', body: data });
    const json = await res.json();
    const alert = `<div class="alert alert-${json.success?'success':'danger'}">${json.message}</div>`;
    document.getElementById('settingsAlert').innerHTML = alert;
});
</script>
<?php
    require_once __DIR__ . '/../layouts/footer.php';
} catch (Exception $e) {
    echo '<div style="background:#ffeeee;padding:20px;border:2px solid red;margin:20px">';
    echo '<h3>Error Debug Information</h3>';
    echo '<p><strong>Error:</strong> '.htmlspecialchars($e->getMessage()).'</p>';
    echo '<pre>Stack Trace: '.htmlspecialchars($e->getTraceAsString()).'</pre>';
    echo '</div>';
    error_log('Application Settings Error: '.$e->getMessage());
    exit;
}
?>
