<?php
/**
 * Fetch realtime notifications for the dashboard
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';

$database = new Database();
$conn = $database->getConnection();

$auth = new Auth($conn);
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Collect notifications
$notifications = [];

// 1. Low stock (less than 10 units)
$stockQuery = "SELECT p.nom, s.quantite, p.unite_mesure
               FROM stock s
               JOIN produits p ON s.produit_id = p.id
               WHERE s.quantite < 10 AND p.actif = 1
               ORDER BY s.quantite ASC
               LIMIT 5";
$stmt = $conn->prepare($stockQuery);
$stmt->execute();
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($stocks as $row) {
    $notifications[] = 'Stock bas: ' . $row['nom'] . ' - ' .
        (float)$row['quantite'] . ' ' . $row['unite_mesure'];
}

// 2. Debts due or overdue (within 2 days)
$dueQuery = "SELECT c.nom AS client_nom, d.date_echeance, d.montant_restant
             FROM dettes d
             JOIN clients c ON d.client_id = c.id
             WHERE d.statut NOT IN ('payee','annulee')
               AND d.date_echeance IS NOT NULL
               AND d.date_echeance <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
             ORDER BY d.date_echeance ASC
             LIMIT 5";
$stmt = $conn->prepare($dueQuery);
$stmt->execute();
$dues = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($dues as $row) {
    $date = date('d/m', strtotime($row['date_echeance']));
    $notifications[] = 'Dette de ' . $row['client_nom'] .
        ' due le ' . $date . ' - ' .
        number_format($row['montant_restant'], 0, ',', ' ') . ' BIF';
}

// 3. New debts today
$todayQuery = "SELECT c.nom AS client_nom, d.montant_initial
               FROM dettes d
               JOIN clients c ON d.client_id = c.id
               WHERE DATE(d.date_creation) = CURDATE()
               ORDER BY d.date_creation DESC
               LIMIT 5";
$stmt = $conn->prepare($todayQuery);
$stmt->execute();
$newDebts = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($newDebts as $row) {
    $notifications[] = 'Nouvelle dette pour ' . $row['client_nom'] . ' - ' .
        number_format($row['montant_initial'], 0, ',', ' ') . ' BIF';
}

// Build HTML output
$count = count($notifications);
$html = '';
if ($count > 0) {
    $html .= '<ul class="list-group list-group-flush">';
    foreach ($notifications as $note) {
        $html .= '<li class="list-group-item small">' . htmlspecialchars($note) . '</li>';
    }
    $html .= '</ul>';
} else {
    $html = '<div class="p-2 text-center text-muted small">Aucune notification</div>';
}

echo json_encode(['success' => true, 'html' => $html, 'count' => $count]);
