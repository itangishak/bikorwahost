<?php
header('Content-Type: application/json');
require_once './../../config/config.php';
require_once './../../config/database.php';
require_once './../../utils/Auth.php';
require_once './../../utils/Settings.php';

$database = new Database();
$conn = $database->getConnection();
$auth = new Auth($conn);

if (!$auth->isLoggedIn() || !$auth->isManager()) {
    echo json_encode(['success' => false, 'message' => "Accès refusé"]);
    exit;
}

$settings = new Settings($conn);
$theme = $_POST['theme'] ?? 'light';
$shopName = trim($_POST['shop_name'] ?? '');
$itemsPerPage = isset($_POST['items_per_page']) ? (int)$_POST['items_per_page'] : 10;

$settings->set('theme', $theme === 'dark' ? 'dark' : 'light');
if ($shopName !== '') {
    $settings->set('shop_name', $shopName);
}
$settings->set('items_per_page', $itemsPerPage);

echo json_encode(['success' => true, 'message' => 'Paramètres enregistrés']);
