<?php
/**
 * Fetch recent activity notifications
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../models/ActivityLog.php';

$database = new Database();
$conn = $database->getConnection();

$auth = new Auth($conn);
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Only managers can view notifications
if (!$auth->isManager()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$activityLog = new ActivityLog($conn);
$activities = $activityLog->getRecentActivities(5);
$count = $activityLog->getTodayActivityCount();

$html = '';
if (!empty($activities)) {
    $html .= '<ul class="list-group list-group-flush">';
    foreach ($activities as $act) {
        $date = date('d/m H:i', strtotime($act['date_action']));
        $html .= '<li class="list-group-item small">';
        $html .= '<div><strong>' . htmlspecialchars($act['username']) . '</strong> ';
        $html .= htmlspecialchars($act['action']) . ' ' . htmlspecialchars($act['entite']);
        if (!empty($act['details'])) {
            $html .= ' - ' . htmlspecialchars($act['details']);
        }
        $html .= '</div><div class="text-muted">' . $date . '</div>';
        $html .= '</li>';
    }
    $html .= '</ul>';
} else {
    $html = '<div class="p-2 text-center text-muted small">Aucune notification</div>';
}

echo json_encode(['success' => true, 'html' => $html, 'count' => (int)$count]);
