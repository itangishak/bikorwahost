<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and config
require_once('../../config/database.php');
require_once('../../config/config.php');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../auth/login.php');
    exit;
}

// Set page information
$page_title = "Inventaire";
$active_page = "stock";

// Check if user has gestionnaire role
$isGestionnaire = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'gestionnaire';

// Process the inventory business logic
require_once('inventaire.php');

// Start rendering the page
include('../layouts/header.php');
?>

<div class="container-fluid py-4">
    <!-- Page Title and Action Buttons -->
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0">Inventaire</h1>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <?php if ($isGestionnaire): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="fas fa-plus-circle me-1"></i> Nouveau produit
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Alert Messages -->
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <!-- Dashboard Stats -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                        <i class="fas fa-boxes fa-fw text-primary"></i>
                    </div>
                    <div>
                        <h6 class="card-subtitle text-muted mb-1">Produits</h6>
                        <h2 class="card-title mb-0"><?= $total_products ?></h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box rounded-circle bg-success bg-opacity-10 p-3 me-3">
                        <i class="fas fa-money-bill-wave fa-fw text-success"></i>
                    </div>
                    <div>
                        <h6 class="card-subtitle text-muted mb-1">Valeur du stock</h6>
                        <h2 class="card-title mb-0"><?= number_format($total_value, 0, ',', ' ') ?> F</h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                        <i class="fas fa-exclamation-triangle fa-fw text-warning"></i>
                    </div>
                    <div>
                        <h6 class="card-subtitle text-muted mb-1">Stock bas</h6>
                        <h2 class="card-title mb-0"><?= $low_stock ?></h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                        <i class="fas fa-times-circle fa-fw text-danger"></i>
                    </div>
                    <div>
                        <h6 class="card-subtitle text-muted mb-1">Rupture de stock</h6>
                        <h2 class="card-title mb-0"><?= $out_of_stock ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search and Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Rechercher un produit..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= isset($_GET['category']) && $_GET['category'] == $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="stock" class="form-select">
                        <option value="">Tous les niveaux de stock</option>
                        <option value="out" <?= isset($_GET['stock']) && $_GET['stock'] === 'out' ? 'selected' : '' ?>>Rupture de stock</option>
                        <option value="low" <?= isset($_GET['stock']) && $_GET['stock'] === 'low' ? 'selected' : '' ?>>Stock bas (≤ 10)</option>
                        <option value="in" <?= isset($_GET['stock']) && $_GET['stock'] === 'in' ? 'selected' : '' ?>>En stock (> 10)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Product Listing -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <!-- Mobile View Cards (shows on xs and sm screens) -->
            <div class="d-md-none">
                <?php if (empty($inventory)): ?>
                    <div class="p-4 text-center">
                        <p class="text-muted mb-0">Aucun produit trouvé.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($inventory as $product): ?>
                        <div class="product-card border-bottom p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="mb-0">
                                    <?= htmlspecialchars($product['nom']) ?>
                                    <?php if (!$product['actif']): ?>
                                        <span class="badge bg-secondary ms-1">Inactif</span>
                                    <?php endif; ?>
                                </h5>
                                <div>
                                    <?php if ($product['quantite_stock'] <= 0): ?>
                                        <span class="badge bg-danger">Rupture</span>
                                    <?php elseif ($product['quantite_stock'] <= 10): ?>
                                        <span class="badge bg-warning text-dark">Stock bas</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">En stock</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="small text-muted mb-2">
                                Code: <strong><?= htmlspecialchars($product['code']) ?></strong>
                                <?php if (!empty($product['categorie_nom'])): ?>
                                    | Catégorie: <strong><?= htmlspecialchars($product['categorie_nom']) ?></strong>
                                <?php endif; ?>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <div class="d-flex justify-content-between border rounded p-2">
                                        <span class="text-muted">Quantité:</span>
                                        <strong><?= number_format($product['quantite_stock'], 2, ',', ' ') ?> <?= htmlspecialchars($product['unite_mesure']) ?></strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex justify-content-between border rounded p-2">
                                        <span class="text-muted">Prix vente:</span>
                                        <strong><?= number_format($product['prix_vente'], 0, ',', ' ') ?> F</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                        data-bs-toggle="modal" data-bs-target="#viewProductModal" 
                                        data-id="<?= $product['id'] ?>"
                                        data-code="<?= htmlspecialchars($product['code']) ?>"
                                        data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                        data-description="<?= htmlspecialchars($product['description']) ?>"
                                        data-categorie="<?= htmlspecialchars($product['categorie_nom'] ?? 'Non catégorisé') ?>"
                                        data-unite="<?= htmlspecialchars($product['unite_mesure']) ?>"
                                        data-prix-achat="<?= number_format($product['prix_achat'], 0, ',', ' ') ?>"
                                        data-prix-vente="<?= number_format($product['prix_vente'], 0, ',', ' ') ?>"
                                        data-quantite="<?= number_format($product['quantite_stock'], 2, ',', ' ') ?>"
                                        data-valeur="<?= number_format($product['valeur_stock'], 0, ',', ' ') ?>"
                                        data-date="<?= (new DateTime($product['date_mise_a_jour']))->format('d/m/Y H:i') ?>"
                                        data-actif="<?= $product['actif'] ? 'Actif' : 'Inactif' ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <?php if ($isGestionnaire): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                                        data-bs-toggle="modal" data-bs-target="#adjustStockModal"
                                        data-id="<?= $product['id'] ?>"
                                        data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                        data-code="<?= htmlspecialchars($product['code']) ?>"
                                        data-unite="<?= htmlspecialchars($product['unite_mesure']) ?>"
                                        data-quantite="<?= number_format($product['quantite_stock'], 2, ',', ' ') ?>">
                                    <i class="fas fa-dolly-flatbed"></i>
                                </button>
                                
                                <button type="button" class="btn btn-sm btn-outline-info me-1"
                                        data-bs-toggle="modal" data-bs-target="#editProductModal"
                                        data-id="<?= $product['id'] ?>"
                                        data-code="<?= htmlspecialchars($product['code']) ?>"
                                        data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                        data-description="<?= htmlspecialchars($product['description']) ?>"
                                        data-categorie-id="<?= $product['categorie_id'] ?? '' ?>"
                                        data-unite="<?= htmlspecialchars($product['unite_mesure']) ?>"
                                        data-prix-achat="<?= $product['prix_achat'] ?>"
                                        data-prix-vente="<?= $product['prix_vente'] ?>"
                                        data-actif="<?= $product['actif'] ? '1' : '0' ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal" data-bs-target="#deleteProductModal"
                                        data-id="<?= $product['id'] ?>"
                                        data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                        data-code="<?= htmlspecialchars($product['code']) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Desktop View Table (shows on md and larger screens) -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Unité</th>
                            <th class="text-end">Quantité</th>
                            <th class="text-end">Prix achat</th>
                            <th class="text-end">Prix vente</th>
                            <th class="text-end">Valeur stock</th>
                            <th class="text-center">Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventory)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">Aucun produit trouvé.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inventory as $product): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($product['code']) ?></code></td>
                                    <td>
                                        <?= htmlspecialchars($product['nom']) ?>
                                        <?php if (!$product['actif']): ?>
                                            <span class="badge bg-secondary ms-1">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($product['categorie_nom'] ?? 'Non catégorisé') ?></td>
                                    <td><?= htmlspecialchars($product['unite_mesure']) ?></td>
                                    <td class="text-end">
                                        <?= number_format($product['quantite_stock'], 2, ',', ' ') ?>
                                    </td>
                                    <td class="text-end">
                                        <?= number_format($product['prix_achat'], 0, ',', ' ') ?> F
                                    </td>
                                    <td class="text-end">
                                        <?= number_format($product['prix_vente'], 0, ',', ' ') ?> F
                                    </td>
                                    <td class="text-end">
                                        <?= number_format($product['valeur_stock'], 0, ',', ' ') ?> F
                                    </td>
                                    <td class="text-center">
                                        <?php if ($product['quantite_stock'] <= 0): ?>
                                            <span class="badge bg-danger">Rupture</span>
                                        <?php elseif ($product['quantite_stock'] <= 10): ?>
                                            <span class="badge bg-warning text-dark">Stock bas</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">En stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewProductModal"
                                                       data-id="<?= $product['id'] ?>"
                                                       data-code="<?= htmlspecialchars($product['code']) ?>"
                                                       data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                                       data-description="<?= htmlspecialchars($product['description']) ?>"
                                                       data-categorie="<?= htmlspecialchars($product['categorie_nom'] ?? 'Non catégorisé') ?>"
                                                       data-unite="<?= htmlspecialchars($product['unite_mesure']) ?>"
                                                       data-prix-achat="<?= number_format($product['prix_achat'], 0, ',', ' ') ?>"
                                                       data-prix-vente="<?= number_format($product['prix_vente'], 0, ',', ' ') ?>"
                                                       data-quantite="<?= number_format($product['quantite_stock'], 2, ',', ' ') ?>"
                                                       data-valeur="<?= number_format($product['valeur_stock'], 0, ',', ' ') ?>"
                                                       data-date="<?= (new DateTime($product['date_mise_a_jour']))->format('d/m/Y H:i') ?>"
                                                       data-actif="<?= $product['actif'] ? 'Actif' : 'Inactif' ?>">
                                                        <i class="fas fa-eye me-2"></i> Voir détails
                                                    </a>
                                                </li>
                                                <?php if ($isGestionnaire): ?>
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#adjustStockModal"
                                                       data-id="<?= $product['id'] ?>"
                                                       data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                                       data-code="<?= htmlspecialchars($product['code']) ?>"
                                                       data-unite="<?= htmlspecialchars($product['unite_mesure']) ?>"
                                                       data-quantite="<?= number_format($product['quantite_stock'], 2, ',', ' ') ?>">
                                                        <i class="fas fa-dolly-flatbed me-2"></i> Ajuster stock
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editProductModal"
                                                       data-id="<?= $product['id'] ?>"
                                                       data-code="<?= htmlspecialchars($product['code']) ?>"
                                                       data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                                       data-description="<?= htmlspecialchars($product['description']) ?>"
                                                       data-categorie-id="<?= $product['categorie_id'] ?? '' ?>"
                                                       data-unite="<?= htmlspecialchars($product['unite_mesure']) ?>"
                                                       data-prix-achat="<?= $product['prix_achat'] ?>"
                                                       data-prix-vente="<?= $product['prix_vente'] ?>"
                                                       data-actif="<?= $product['actif'] ? '1' : '0' ?>">
                                                        <i class="fas fa-edit me-2"></i> Modifier
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#deleteProductModal"
                                                       data-id="<?= $product['id'] ?>"
                                                       data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                                       data-code="<?= htmlspecialchars($product['code']) ?>">
                                                        <i class="fas fa-trash me-2"></i> Supprimer
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include('inventaire_modals.php'); ?>
<?php include('../layouts/footer.php'); ?>
