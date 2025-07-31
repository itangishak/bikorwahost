<?php
require_once __DIR__ . '/../../config/config.php';
require_gestionnaire_access();

// Produits index page for BIKORWA SHOP
$page_title = "Gestion des Produits";
$active_page = "produits";
?>

<div class="row mb-4">
    <div class="col-md-6">
        <?php if ($auth->canModify()): ?>
            <a href="/produits/nouveau.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i>Nouveau produit
            </a>
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <form action="/produits/index.php" method="get" class="d-flex justify-content-end">
            <div class="input-group">
                <select name="categorie" class="form-select" style="max-width: 200px;">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $categorie): ?>
                        <option value="<?php echo $categorie['id']; ?>" <?php echo (isset($_GET['categorie']) && $_GET['categorie'] == $categorie['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($categorie['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" class="form-control" name="search" placeholder="Rechercher un produit..." value="<?php echo $_GET['search'] ?? ''; ?>">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-search me-1"></i>Rechercher
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card dashboard-card mb-4">
    <div class="card-header bg-primary text-white">
        <h6 class="m-0 font-weight-bold">Liste des produits</h6>
    </div>
    <div class="card-body">
        <?php if (!empty($produits)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Prix d'achat</th>
                            <th>Prix de vente</th>
                            <th>Marge</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produits as $produit): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($produit['code']); ?></td>
                                <td><?php echo htmlspecialchars($produit['nom']); ?></td>
                                <td><?php echo htmlspecialchars($produit['categorie_nom']); ?></td>
                                <td class="text-end"><?php echo number_format($produit['prix_achat_actuel'], 0, ',', ' '); ?> F</td>
                                <td class="text-end"><?php echo number_format($produit['prix_vente_actuel'], 0, ',', ' '); ?> F</td>
                                <td class="text-end">
                                    <?php 
                                        $marge = $produit['prix_vente_actuel'] - $produit['prix_achat_actuel'];
                                        $pourcentage = ($produit['prix_achat_actuel'] > 0) ? ($marge / $produit['prix_achat_actuel'] * 100) : 0;
                                        echo number_format($marge, 0, ',', ' ') . ' F (' . number_format($pourcentage, 1) . '%)';
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($produit['actif']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="/produits/details.php?id=<?php echo $produit['id']; ?>" class="btn btn-sm btn-info btn-action" title="Détails">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($auth->canModify()): ?>
                                        <a href="/produits/edit.php?id=<?php echo $produit['id']; ?>" class="btn btn-sm btn-warning btn-action" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($auth->canDelete()): ?>
                                            <a href="/produits/toggle_status.php?id=<?php echo $produit['id']; ?>" class="btn btn-sm <?php echo $produit['actif'] ? 'btn-danger' : 'btn-success'; ?> btn-action" title="<?php echo $produit['actif'] ? 'Désactiver' : 'Activer'; ?>">
                                                <i class="fas <?php echo $produit['actif'] ? 'fa-times' : 'fa-check'; ?>"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Navigation des pages">
                    <ul class="pagination justify-content-center">
                        <?php if ($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo $_GET['search'] ?? ''; ?>&categorie=<?php echo $_GET['categorie'] ?? ''; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo $_GET['search'] ?? ''; ?>&categorie=<?php echo $_GET['categorie'] ?? ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo $_GET['search'] ?? ''; ?>&categorie=<?php echo $_GET['categorie'] ?? ''; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucun produit trouvé.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Price History Modal -->
<div class="modal fade" id="priceHistoryModal" tabindex="-1" aria-labelledby="priceHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="priceHistoryModalLabel">Historique des prix</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="priceHistoryContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Function to load price history
    function loadPriceHistory(productId) {
        const modal = new bootstrap.Modal(document.getElementById('priceHistoryModal'));
        modal.show();
        
        // AJAX request to get price history
        fetch(`/produits/get_price_history.php?id=${productId}`)
            .then(response => response.json())
            .then(data => {
                let content = `<h6>Produit: ${data.product_name}</h6>`;
                
                if (data.history.length > 0) {
                    content += `
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Prix d'achat</th>
                                        <th>Prix de vente</th>
                                        <th>Modifié par</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    data.history.forEach(item => {
                        content += `
                            <tr>
                                <td>${new Date(item.date_debut).toLocaleString('fr-FR')}</td>
                                <td class="text-end">${parseInt(item.prix_achat).toLocaleString('fr-FR')} F</td>
                                <td class="text-end">${parseInt(item.prix_vente).toLocaleString('fr-FR')} F</td>
                                <td>${item.utilisateur_nom}</td>
                            </tr>
                        `;
                    });
                    
                    content += `
                                </tbody>
                            </table>
                        </div>
                    `;
                } else {
                    content += `<div class="alert alert-info">Aucun historique de prix disponible.</div>`;
                }
                
                document.getElementById('priceHistoryContent').innerHTML = content;
            })
            .catch(error => {
                document.getElementById('priceHistoryContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>Erreur lors du chargement de l'historique des prix.
                    </div>
                `;
                console.error('Error:', error);
            });
    }
    
    // Enable tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
</script>
