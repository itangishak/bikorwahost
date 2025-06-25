<?php
declare(strict_types=1);

session_start();                              // always first
ini_set('display_errors', '0');               // keep JSON clean
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Méthode non autorisée']);
    exit;
}

/* ── Sécurité CSRF ─────────────────────────────────────────────── */
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    echo json_encode(['status'=>'error','message'=>'Token CSRF invalide']);
    exit;
}

/* ── Champs obligatoires ───────────────────────────────────────── */
$current = trim($_POST['current_password']  ?? '');
$new     = trim($_POST['new_password']      ?? '');
$confirm = trim($_POST['confirm_password']  ?? '');

if ($current === '' || $new === '' || $confirm === '') {
    echo json_encode(['status'=>'error','message'=>'Tous les champs sont obligatoires.']);
    exit;
}
if ($new !== $confirm) {
    echo json_encode(['status'=>'error','message'=>'Les mots de passe ne correspondent pas.']);
    exit;
}
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/', $new)) {
    echo json_encode(['status'=>'error','message'=>'Mot de passe trop faible.']);
    exit;
}

/* ── BDD ───────────────────────────────────────────────────────── */
require_once __DIR__.'/../../config/database.php';
$pdo = (new Database())->getConnection();

$stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$hash = $stmt->fetchColumn();

if (!$hash || !password_verify($current, $hash)) {
    echo json_encode(['status'=>'error','message'=>'Mot de passe actuel incorrect.']);
    exit;
}

$stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
$stmt->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);

echo json_encode(['status'=>'success','message'=>'Mot de passe changé avec succès.']);
