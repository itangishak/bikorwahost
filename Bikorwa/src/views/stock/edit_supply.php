<?php
require_once __DIR__ . '/../../../includes/init.php';
require_once __DIR__ . '/../../../src/config/database.php';

// Only gestionnaires can access this page
requireManager();

// Database connection
$database = new Database();
$pdo = $database->getConnection();

// Get supply entry ID
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: historique_approvisionnement.php');
    exit;
}

// Fetch entry data
$query = "SELECT * FROM mouvements_stock WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    header('Location: historique_approvisionnement.php');
    exit;
}

// Fetch products
$products_query = "SELECT id, nom FROM produits ORDER BY nom";
$products_stmt = $pdo->prepare($products_query);
$products_stmt->execute();
$products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Modifier l'entrée d'approvisionnement</h3>
                    </div>
                    <div class="card-body">
                        <form id="edit-supply-form" method="POST" action="update_supply.php">
                            <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                            
                            <div class="form-group">
                                <label for="produit_id">Produit</label>
                                <select class="form-control" id="produit_id" name="produit_id" required>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?= $product['id'] ?>" 
                                            <?= $product['id'] == $entry['produit_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($product['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="quantite">Quantité</label>
                                <input type="number" step="0.01" class="form-control" id="quantite" 
                                    name="quantite" value="<?= $entry['quantite'] ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="prix_unitaire">Prix Unitaire</label>
                                <input type="number" step="0.01" class="form-control" id="prix_unitaire" 
                                    name="prix_unitaire" value="<?= $entry['prix_unitaire'] ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="date_mouvement">Date</label>
                                <input type="datetime-local" class="form-control" id="date_mouvement" 
                                    name="date_mouvement" value="<?= date('Y-m-d\TH:i', strtotime($entry['date_mouvement'])) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="reference">Référence</label>
                                <input type="text" class="form-control" id="reference" 
                                    name="reference" value="<?= htmlspecialchars($entry['reference'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="note">Note</label>
                                <textarea class="form-control" id="note" name="note"><?= htmlspecialchars($entry['note'] ?? '') ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                            <a href="historique_approvisionnement.php" class="btn btn-secondary">Annuler</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
