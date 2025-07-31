<?php
// AJAX endpoint to adjust stock without reloading page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour effectuer cette action']);
    exit;
}

$db = new Database();
$pdo = $db->getConnection();

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit;
}

$isGestionnaire = isset($_SESSION['role']) && $_SESSION['role'] === 'gestionnaire';
$isReceptionniste = isset($_SESSION['role']) && $_SESSION['role'] === 'receptionniste';
$hasStockAccess = $isGestionnaire || $isReceptionniste;

if (!$hasStockAccess) {
    echo json_encode(['success' => false, 'message' => "Vous n'avez pas les droits pour ajuster le stock."]);
    exit;
}

$produit_id = intval($_POST['produit_id'] ?? 0);
$type_mouvement = $_POST['type_mouvement'] ?? '';
$quantite = floatval(str_replace(',', '.', $_POST['quantite'] ?? '0'));
$note = trim($_POST['note'] ?? '');
$date_mouvement = isset($_POST['date_mouvement']) && $_POST['date_mouvement'] !== ''
    ? date('Y-m-d H:i:s', strtotime($_POST['date_mouvement']))
    : date('Y-m-d H:i:s');

if ($quantite <= 0) {
    echo json_encode(['success' => false, 'message' => 'La quantité doit être supérieure à zéro.']);
    exit;
}

if ($type_mouvement !== 'entree' && $type_mouvement !== 'sortie') {
    echo json_encode(['success' => false, 'message' => 'Type de mouvement invalide.']);
    exit;
}

if (!$isGestionnaire && $type_mouvement === 'sortie') {
    echo json_encode(['success' => false, 'message' => 'Seul le gestionnaire peut effectuer une sortie de stock.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT p.nom, p.code, p.unite_mesure, s.quantite, pp.prix_achat, c.nom AS categorie_nom
                            FROM produits p
                            LEFT JOIN stock s ON p.id = s.produit_id
                            LEFT JOIN categories c ON p.categorie_id = c.id
                            LEFT JOIN (
                                SELECT produit_id, prix_achat
                                FROM prix_produits
                                WHERE date_fin IS NULL
                                GROUP BY produit_id
                            ) pp ON p.id = pp.produit_id
                            WHERE p.id = :id");
    $stmt->execute(['id' => $produit_id]);
    $produit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produit) {
        throw new Exception('Produit introuvable.');
    }

    if ($type_mouvement === 'sortie' && $produit['quantite'] < $quantite) {
        throw new Exception('Stock insuffisant. Quantité actuelle: ' . $produit['quantite']);
    }

    $new_quantity = ($type_mouvement === 'entree') ? $produit['quantite'] + $quantite : $produit['quantite'] - $quantite;

    $stmt = $pdo->prepare("UPDATE stock SET quantite = :q, date_mise_a_jour = :date_mouvement WHERE produit_id = :id");
    $stmt->execute(['q' => $new_quantity, 'id' => $produit_id, 'date_mouvement' => $date_mouvement]);

    $reference = ($type_mouvement === 'entree' ? 'AJOUT-' : 'RETRAIT-') . date('YmdHis');

    $stmt = $pdo->prepare("INSERT INTO mouvements_stock (produit_id, type_mouvement, quantite, prix_unitaire, valeur_totale, utilisateur_id, note, reference, quantity_remaining, date_mouvement)
                           VALUES (:produit_id, :type_mouvement, :quantite, :prix_unitaire, :valeur_totale, :utilisateur_id, :note, :reference, :quantity_remaining, :date_mouvement)");
    $stmt->execute([
        'produit_id' => $produit_id,
        'type_mouvement' => $type_mouvement,
        'quantite' => $quantite,
        'prix_unitaire' => $produit['prix_achat'],
        'valeur_totale' => $produit['prix_achat'] * $quantite,
        'utilisateur_id' => $_SESSION['user_id'],
        'note' => $note,
        'reference' => $reference,
        'quantity_remaining' => $type_mouvement === 'entree' ? $quantite : null,
        'date_mouvement' => $date_mouvement
    ]);

    $stmt = $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
                           VALUES (:utilisateur_id, 'adjust', 'stock', :entite_id, :details)");
    $stmt->execute([
        'utilisateur_id' => $_SESSION['user_id'],
        'entite_id' => $produit_id,
        'details' => 'Ajustement de stock: ' . $produit['nom'] . ' - ' . ($type_mouvement === 'entree' ? 'Ajout' : 'Retrait') . ' de ' . $quantite . ' ' . $produit['unite_mesure']
    ]);

    $pdo->commit();

    $valeur_stock = $new_quantity * $produit['prix_achat'];

    // Déterminer le seuil de stock bas selon la catégorie
    $threshold = 10;
    switch ($produit['categorie_nom']) {
        case 'Spiritueux':
        case 'Vins':
            $threshold = 1;
            break;
        case 'Sodas':
            $threshold = 15;
            break;
        case 'Bières':
            $threshold = 10;
            break;
        default:
            $threshold = 10;
    }

    if ($new_quantity <= 0) {
        $status_badge = '<span class="badge bg-danger">Rupture</span>';
    } elseif ($new_quantity <= $threshold) {
        $status_badge = '<span class="badge bg-warning text-dark">Stock bas</span>';
    } else {
        $status_badge = '<span class="badge bg-success">En stock</span>';
    }

    echo json_encode([
        'success' => true,
        'new_quantity' => $new_quantity,
        'new_quantity_formatted' => number_format($new_quantity, 2, ',', ' '),
        'new_valeur' => $valeur_stock,
        'new_valeur_formatted' => number_format($valeur_stock, 0, ',', ' '),
        'unite' => $produit['unite_mesure'],
        'status_badge' => $status_badge
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
