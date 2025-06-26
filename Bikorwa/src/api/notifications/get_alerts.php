<?php
/**
 * Provide manager alerts for low stock and upcoming debts
 */
header('Content-Type: application/json');

$config = require __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../models/Stock.php';
require_once __DIR__ . '/../../models/Dette.php';

$database = new Database();
$conn = $database->getConnection();

$auth = new Auth($conn);
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Only managers are allowed to fetch alerts
if (!$auth->isManager()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$alertThreshold = $config['stock']['alert_threshold'] ?? 10;

try {
    $stockModel = new Stock($conn);
    $stmtStock = $stockModel->getStockFaible($alertThreshold);
    $lowStock = $stmtStock->fetchAll(PDO::FETCH_ASSOC);
    $lowStockCount = count($lowStock);

    // Debts due soon or overdue (next 7 days)
    $query = "SELECT d.id, d.montant_restant, d.date_echeance, c.nom AS client_nom
              FROM dettes d
              LEFT JOIN clients c ON d.client_id = c.id
              WHERE d.statut IN ('active','partiellement_payee')
                AND d.date_echeance IS NOT NULL
                AND d.date_echeance <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
              ORDER BY d.date_echeance ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $dueDebts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dueCount = count($dueDebts);
    $overdueCount = 0;
    foreach ($dueDebts as $row) {
        if (strtotime($row['date_echeance']) < strtotime(date('Y-m-d'))) {
            $overdueCount++;
        }
    }

    $stmtToday = $conn->query("SELECT COUNT(*) FROM dettes WHERE DATE(date_creation) = CURDATE()");
    $todayAddedCount = (int)$stmtToday->fetchColumn();
} catch (Exception $e) {
    error_log('Failed to fetch alerts: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}

$htmlParts = [];
if ($lowStockCount > 0) {
    $htmlParts[] = '<li class="list-group-item fw-bold">Stock faible</li>';
    foreach ($lowStock as $prod) {
        $line = htmlspecialchars($prod['produit_nom']) . ' - ' . number_format($prod['quantite'], 0, ',', ' ') . ' ' . htmlspecialchars($prod['unite_mesure']);
        $htmlParts[] = '<li class="list-group-item small text-danger"><i class="fas fa-box-open me-2"></i>' . $line . '</li>';
    }
}
if ($dueCount > 0) {
    $htmlParts[] = '<li class="list-group-item fw-bold">Dettes à échéance</li>';
    foreach ($dueDebts as $dette) {
        $date = date('d/m', strtotime($dette['date_echeance']));
        $line = htmlspecialchars($dette['client_nom']) . ' - ' . number_format($dette['montant_restant'], 0, ',', ' ') . ' BIF (' . $date . ')';
        $class = (strtotime($dette['date_echeance']) < strtotime(date('Y-m-d'))) ? 'text-danger' : '';
        $htmlParts[] = '<li class="list-group-item small ' . $class . '"><i class="fas fa-calendar-alt me-2"></i>' . $line . '</li>';
    }
}
if (!$lowStockCount && !$dueCount) {
    $htmlParts[] = '<li class="list-group-item small text-muted text-center">Aucune alerte</li>';
}

$html = '<ul class="list-group list-group-flush">' . implode('', $htmlParts) . '</ul>';

$totalCount = $lowStockCount + $dueCount + $todayAddedCount;

echo json_encode([
    'success' => true,
    'html' => $html,
    'count' => $totalCount,
    'low_stock' => $lowStockCount,
    'due_debts' => $dueCount - $overdueCount,
    'overdue_debts' => $overdueCount,
    'today_debts' => $todayAddedCount
]);
