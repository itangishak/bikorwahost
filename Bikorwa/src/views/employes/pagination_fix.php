<?php
// This is a complete pagination solution for your employee list
// Copy and paste this code to replace the pagination section in liste.php

// Set default values and get search parameters
$search = $_GET['search'] ?? '';
$statut = $_GET['statut'] ?? '';
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($current_page - 1) * $items_per_page;

// Build the base query
$query = "SELECT * FROM employes WHERE 1=1";
$count_query = "SELECT COUNT(*) AS total FROM employes WHERE 1=1";
$params = [];

// Add search conditions if any
if (!empty($search)) {
    $query .= " AND (nom LIKE ? OR telephone LIKE ? OR email LIKE ? OR poste LIKE ?)";
    $count_query .= " AND (nom LIKE ? OR telephone LIKE ? OR email LIKE ? OR poste LIKE ?)";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param, $search_param);
}

// Add status filter if specified
if ($statut === 'actif') {
    $query .= " AND actif = 1";
    $count_query .= " AND actif = 1";
} elseif ($statut === 'inactif') {
    $query .= " AND actif = 0";
    $count_query .= " AND actif = 0";
}

// Add order by and pagination
$query .= " ORDER BY nom ASC LIMIT ? OFFSET ?";

// Execute count query for pagination
$count_stmt = $conn->prepare($count_query);

// Bind parameters for count query if any
for ($i = 0; $i < count($params); $i++) {
    $count_stmt->bindParam($i + 1, $params[$i], PDO::PARAM_STR);
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
    $stmt->bindParam($i + 1, $params[$i], PDO::PARAM_STR);
}

// Bind pagination parameters
$param_index = count($params) + 1;
$stmt->bindParam($param_index++, $items_per_page, PDO::PARAM_INT);
$stmt->bindParam($param_index, $offset, PDO::PARAM_INT);

$stmt->execute();
$employes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
