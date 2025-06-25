<?php
/**
 * BIKORWA SHOP - API for retrieving clients list with pagination and search
 */
header('Content-Type: application/json');
require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize authentication
$auth = new Auth($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour effectuer cette action.'
    ]);
    exit;
}

// Check access permissions
if (!$auth->hasAccess('clients')) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous n\'avez pas les permissions nécessaires pour effectuer cette action.'
    ]);
    exit;
}

// Set default values and get search parameters
$search = $_GET['search'] ?? '';
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($current_page - 1) * $items_per_page;

// Build the base query
$query = "SELECT * FROM clients WHERE 1=1";
$count_query = "SELECT COUNT(*) AS total FROM clients WHERE 1=1";
$params = [];
$count_params = [];

// Add search conditions if any
if (!empty($search)) {
    $query .= " AND (nom LIKE ? OR telephone LIKE ? OR email LIKE ? OR adresse LIKE ?)";
    $count_query .= " AND (nom LIKE ? OR telephone LIKE ? OR email LIKE ? OR adresse LIKE ?)";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param, $search_param);
    array_push($count_params, $search_param, $search_param, $search_param, $search_param);
}

// Add order by and pagination
$query .= " ORDER BY date_creation DESC, id DESC LIMIT ? OFFSET ?";

try {
    // Execute count query for pagination
    $count_stmt = $conn->prepare($count_query);

    // Bind parameters for count query if any
    if (!empty($count_params)) {
        for ($i = 0; $i < count($count_params); $i++) {
            $count_stmt->bindParam($i + 1, $count_params[$i]);
        }
    }

    $count_stmt->execute();
    $result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $total_rows = $result['total'];
    $total_pages = ceil($total_rows / $items_per_page);

    // Make sure current page is valid
    if ($current_page < 1) $current_page = 1;
    if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

    // Recalculate offset based on validated current page
    $offset = ($current_page - 1) * $items_per_page;

    // Execute the main query
    $stmt = $conn->prepare($query);

    // Bind parameters if any
    for ($i = 0; $i < count($params); $i++) {
        $stmt->bindParam($i + 1, $params[$i]);
    }

    // Bind pagination parameters
    $param_index = count($params) + 1;
    $stmt->bindParam($param_index++, $items_per_page, PDO::PARAM_INT);
    $stmt->bindParam($param_index, $offset, PDO::PARAM_INT);

    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get client statistics
    $stats_query = "SELECT 
                    COUNT(*) as total_clients,
                    SUM(limite_credit) as credit_total,
                    AVG(limite_credit) as credit_moyen,
                    MAX(limite_credit) as credit_max
                    FROM clients";
    $stats_stmt = $conn->prepare($stats_query);
    $stats_stmt->execute();
    $statistiques = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    // Format statistiques to handle NULL values
    $statistiques['credit_total'] = is_null($statistiques['credit_total']) ? 0 : (float)$statistiques['credit_total'];
    $statistiques['credit_moyen'] = is_null($statistiques['credit_moyen']) ? 0 : (float)$statistiques['credit_moyen'];
    $statistiques['credit_max'] = is_null($statistiques['credit_max']) ? 0 : (float)$statistiques['credit_max'];

    // Return the data
    echo json_encode([
        'success' => true,
        'clients' => $clients,
        'pagination' => [
            'current_page' => $current_page,
            'items_per_page' => $items_per_page,
            'total_rows' => $total_rows,
            'total_pages' => $total_pages
        ],
        'statistiques' => $statistiques
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>
