<?php
try {
    // Toujours mettre ce header en PREMIER
    header('Content-Type: application/json');

    // Ensuite seulement les autres require
    require_once __DIR__.'/../../config/config.php';
    require_once __DIR__.'/../../config/database.php';

    // Désactiver TOUS les affichages d'erreur qui pourraient polluer le JSON
    error_reporting(0);
    ini_set('display_errors', 0);

    // Empêcher la mise en cache pour le développement
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    $database = new Database();
    $pdo = $database->getConnection();

    $term = isset($_GET['term']) ? trim($_GET['term']) : '';

    if (empty($term) || strlen($term) < 2) {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $query = "SELECT DISTINCT nom FROM produits WHERE nom LIKE :term ORDER BY nom LIMIT 10";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':term', '%'.$term.'%', PDO::PARAM_STR);
    $stmt->execute();

    $suggestions = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $suggestions[] = htmlspecialchars($row['nom'], ENT_QUOTES, 'UTF-8');
    }

    // Envoyer la réponse JSON
    if (json_last_error() === JSON_ERROR_NONE) {
        echo json_encode($suggestions, JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'JSON encoding error'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error'], JSON_UNESCAPED_UNICODE);
}
?>
