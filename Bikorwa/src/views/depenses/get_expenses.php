<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define ABSPATH
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Initialize session
session_start();

// Include configuration and database
require_once '../../../src/config/config.php';
require_once '../../../src/config/database.php';
require_once '../../../src/utils/Auth.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Initialize authentication
$auth = new Auth($pdo);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

// Get current user ID for filtering
$current_user_id = $_SESSION['user_id'] ?? 0;

// Get parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$categorie = isset($_GET['categorie']) ? $_GET['categorie'] : '';
$mode_paiement = isset($_GET['mode_paiement']) ? $_GET['mode_paiement'] : '';
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

// Calculate offset
$offset = ($page - 1) * $limit;

// Build base query
$query = "SELECT d.*, c.nom as categorie_nom, u.nom as utilisateur_nom 
          FROM depenses d 
          LEFT JOIN categories_depenses c ON d.categorie_id = c.id 
          LEFT JOIN users u ON d.utilisateur_id = u.id 
          WHERE d.utilisateur_id = ?";
$count_query = "SELECT COUNT(*) AS total FROM depenses d WHERE d.utilisateur_id = ?";
$params = [$current_user_id];
$count_params = [$current_user_id];

// Add search conditions if any
if (!empty($search)) {
    $query .= " AND (d.description LIKE ? OR d.reference_paiement LIKE ? OR c.nom LIKE ?)";
    $count_query .= " AND (d.description LIKE ? OR d.reference_paiement LIKE ?)";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param);
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

// Add category filter if specified
if (!empty($categorie)) {
    $query .= " AND d.categorie_id = ?";
    $count_query .= " AND d.categorie_id = ?";
    array_push($params, $categorie);
    $count_params[] = $categorie;
}

// Add payment mode filter if specified
if (!empty($mode_paiement)) {
    $query .= " AND d.mode_paiement = ?";
    $count_query .= " AND d.mode_paiement = ?";
    array_push($params, $mode_paiement);
    $count_params[] = $mode_paiement;
}

// Add date range filter if specified
if (!empty($date_debut)) {
    $query .= " AND d.date_depense >= ?";
    $count_query .= " AND d.date_depense >= ?";
    array_push($params, $date_debut);
    $count_params[] = $date_debut;
}

if (!empty($date_fin)) {
    $query .= " AND d.date_depense <= ?";
    $count_query .= " AND d.date_depense <= ?";
    array_push($params, $date_fin);
    $count_params[] = $date_fin;
}

// Add order by and limit
$query .= " ORDER BY d.date_depense DESC, d.id DESC LIMIT ? OFFSET ?";
array_push($params, $limit, $offset);

try {
    // Execute count query
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_rows = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_rows / $limit);

    // Execute main query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates
    foreach ($expenses as &$expense) {
        $expense['date_depense'] = date('Y-m-d H:i:s', strtotime($expense['date_depense']));
    }

    // Prepare response
    $response = [
        'success' => true,
        'expenses' => $expenses,
        'total_count' => $total_rows,
        'total_pages' => $total_pages,
        'current_page' => $page
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
}
