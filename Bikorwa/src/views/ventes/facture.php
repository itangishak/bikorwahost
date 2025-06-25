<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration and database
$config = require_once('../../config/config.php');
require_once('../../config/database.php');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../auth/login.php');
    exit;
}

// Initialize database connection
$db = new Database();
$pdo = $db->getConnection();

$vente_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($vente_id <= 0) {
    die('ID de vente invalide');
}

// Fetch sale information
$stmt = $pdo->prepare("SELECT v.*, c.nom AS client_nom, c.telephone AS client_telephone, c.adresse AS client_adresse, u.nom AS utilisateur_nom FROM ventes v LEFT JOIN clients c ON v.client_id = c.id LEFT JOIN users u ON v.utilisateur_id = u.id WHERE v.id = ?");
$stmt->execute([$vente_id]);
$vente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vente) {
    die('Vente introuvable');
}

// Fetch sale products
$stmt = $pdo->prepare("SELECT dv.*, p.nom AS produit_nom, p.code AS produit_code, p.unite_mesure FROM details_ventes dv JOIN produits p ON dv.produit_id = p.id WHERE dv.vente_id = ?");
$stmt->execute([$vente_id]);
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture - <?= htmlspecialchars($vente['numero_facture']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
<div class="container my-4">
    <div class="d-flex justify-content-between">
        <div>
            <h4><?= htmlspecialchars($config['shop_info']['name']) ?></h4>
            <p class="mb-0"><?= htmlspecialchars($config['shop_info']['address']) ?></p>
            <p class="mb-0"><?= htmlspecialchars($config['shop_info']['phone']) ?></p>
        </div>
        <div class="text-end">
            <h4>Facture</h4>
            <p class="mb-0">N°: <?= htmlspecialchars($vente['numero_facture']) ?></p>
            <p class="mb-0">Date: <?= date('d/m/Y H:i', strtotime($vente['date_vente'])) ?></p>
        </div>
    </div>
    <hr>
    <div class="mb-3">
        <strong>Client :</strong> <?= htmlspecialchars($vente['client_nom'] ?: 'Client anonyme') ?><br>
        <?php if (!empty($vente['client_telephone'])): ?>
        <strong>Téléphone :</strong> <?= htmlspecialchars($vente['client_telephone']) ?><br>
        <?php endif; ?>
        <?php if (!empty($vente['client_adresse'])): ?>
        <strong>Adresse :</strong> <?= htmlspecialchars($vente['client_adresse']) ?><br>
        <?php endif; ?>
    </div>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Produit</th>
                <th class="text-end">Quantité</th>
                <th class="text-end">Prix U.</th>
                <th class="text-end">Montant</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($produits as $produit): ?>
            <tr>
                <td><?= htmlspecialchars($produit['produit_nom']) ?> (<?= htmlspecialchars($produit['produit_code']) ?>)</td>
                <td class="text-end"><?= number_format($produit['quantite'], 2, ',', ' ') ?> <?= htmlspecialchars($produit['unite_mesure']) ?></td>
                <td class="text-end"><?= number_format($produit['prix_unitaire'], 0, ',', ' ') ?> BIF</td>
                <td class="text-end"><?= number_format($produit['montant_total'], 0, ',', ' ') ?> BIF</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3" class="text-end">Montant total</th>
                <th class="text-end"><?= number_format($vente['montant_total'], 0, ',', ' ') ?> BIF</th>
            </tr>
            <tr>
                <th colspan="3" class="text-end">Montant payé</th>
                <th class="text-end"><?= number_format($vente['montant_paye'], 0, ',', ' ') ?> BIF</th>
            </tr>
        </tfoot>
    </table>

    <?php if (!empty($vente['note'])): ?>
    <p><strong>Note :</strong> <?= nl2br(htmlspecialchars($vente['note'])) ?></p>
    <?php endif; ?>

    <p class="mt-4">Vendeur : <?= htmlspecialchars($vente['utilisateur_nom']) ?></p>
    <button class="btn btn-primary no-print" onclick="window.print();">Imprimer</button>
</div>
</body>
</html>
