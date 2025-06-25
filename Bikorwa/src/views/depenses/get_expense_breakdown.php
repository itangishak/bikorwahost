<?php
session_start();
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';

$database = new Database();
$pdo = $database->getConnection();
$auth = new Auth($pdo);

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'total':
        $data = getTotalExpenseBreakdown($pdo);
        break;
    case 'monthly':
        $data = getMonthlyExpenseBreakdown($pdo);
        break;
    case 'annual':
        $data = getAnnualExpenseBreakdown($pdo);
        break;
    case 'category':
        $data = getCategoryExpenseBreakdown($pdo);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Type invalide']);
        exit;
}

echo json_encode($data);

function getTotalExpenseBreakdown($pdo) {
    $categories = [];
    $amounts = [];
    $details = [];

    // Get category breakdown
    $stmt = $pdo->prepare("
        SELECT c.nom as categorie, 
               SUM(d.montant) as total, 
               COUNT(*) as nombre 
        FROM depenses d 
        LEFT JOIN categories_depenses c ON d.categorie_id = c.id 
        WHERE d.utilisateur_id = :user_id
        GROUP BY c.id 
        ORDER BY total DESC
    ");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    
    try {
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categories[] = $row['categorie'];
            $amounts[] = $row['total'];
            $details[] = [
                'categorie' => $row['categorie'],
                'montant' => number_format($row['total'], 0, ',', ' '),
                'nombre' => $row['nombre']
            ];
        }
        return [
            'categories' => $categories,
            'amounts' => $amounts,
            'details' => $details
        ];
    } catch (PDOException $e) {
        error_log("Error in getTotalExpenseBreakdown: " . $e->getMessage());
        throw new Exception("Erreur lors du chargement des détails");
    }
}

function getMonthlyExpenseBreakdown($pdo) {
    $dates = [];
    $amounts = [];
    $details = [];

    // Get daily breakdown for current month
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(date_depense, '%d/%m/%Y') as date, 
               SUM(montant) as total, 
               c.nom as categorie 
        FROM depenses d 
        LEFT JOIN categories_depenses c ON d.categorie_id = c.id 
        WHERE MONTH(date_depense) = MONTH(CURRENT_DATE()) 
        AND YEAR(date_depense) = YEAR(CURRENT_DATE())
        AND d.utilisateur_id = :user_id
        GROUP BY date_depense 
        ORDER BY date_depense
    ");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    
    try {
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dates[] = $row['date'];
            $amounts[] = $row['total'];
            $details[] = [
                'date' => $row['date'],
                'montant' => number_format($row['total'], 0, ',', ' '),
                'categorie' => $row['categorie']
            ];
        }
        return [
            'dates' => $dates,
            'amounts' => $amounts,
            'details' => $details
        ];
    } catch (PDOException $e) {
        error_log("Error in getMonthlyExpenseBreakdown: " . $e->getMessage());
        throw new Exception("Erreur lors du chargement des détails mensuels");
    }
}

function getAnnualExpenseBreakdown($pdo) {
    $months = [];
    $amounts = [];
    $details = [];

    // Get monthly breakdown for current year
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(date_depense, '%m/%Y') as mois, 
               SUM(montant) as total, 
               COUNT(*) as nombre 
        FROM depenses 
        WHERE YEAR(date_depense) = YEAR(CURRENT_DATE())
        AND utilisateur_id = :user_id
        GROUP BY MONTH(date_depense)
        ORDER BY MONTH(date_depense)
    ");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    
    try {
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $months[] = $row['mois'];
            $amounts[] = $row['total'];
            $details[] = [
                'mois' => $row['mois'],
                'montant' => number_format($row['total'], 0, ',', ' '),
                'nombre' => $row['nombre']
            ];
        }
        return [
            'months' => $months,
            'amounts' => $amounts,
            'details' => $details
        ];
    } catch (PDOException $e) {
        error_log("Error in getAnnualExpenseBreakdown: " . $e->getMessage());
        throw new Exception("Erreur lors du chargement des détails annuels");
    }
}

function getCategoryExpenseBreakdown($pdo) {
    $categories = [];
    $amounts = [];
    $details = [];

    // Get category breakdown
    $stmt = $pdo->prepare("
        SELECT c.nom as categorie, 
               SUM(d.montant) as total, 
               COUNT(*) as nombre 
        FROM depenses d 
        LEFT JOIN categories_depenses c ON d.categorie_id = c.id 
        WHERE d.utilisateur_id = :user_id
        GROUP BY c.id 
        ORDER BY total DESC
    ");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    
    try {
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categories[] = $row['categorie'];
            $amounts[] = $row['total'];
            $details[] = [
                'categorie' => $row['categorie'],
                'montant' => number_format($row['total'], 0, ',', ' '),
                'nombre' => $row['nombre']
            ];
        }
        return [
            'categories' => $categories,
            'amounts' => $amounts,
            'details' => $details
        ];
    } catch (PDOException $e) {
        error_log("Error in getCategoryExpenseBreakdown: " . $e->getMessage());
        throw new Exception("Erreur lors du chargement des détails par catégorie");
    }
}
