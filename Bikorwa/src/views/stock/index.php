<?php
// Redirect to inventory page
header('Location: ' . BASE_URL . '/src/views/stock/inventaire.php');
exit;
?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card dashboard-card mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">État du stock</h6>
        <div>
            <span class="badge bg-light text-dark">Valeur totale: <?php echo number_format($valeur_totale, 0, ',', ' '); ?> F</span>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($produits)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Produit</th>
                            <th>Catégorie</th>
                            <th>Quantité</th>
                            <th>Prix d'achat</th>
                            <th>Prix de vente</th>
                            <th>Valeur stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produits as $produit): ?>
                            <tr class="<?php echo $produit['quantite'] <= 10 ? 'table-danger' : ''; ?>">
                                <td><?php echo htmlspecialchars($produit['code']); ?></td>
                                <td><?php echo htmlspecialchars($produit['nom']); ?></td>
                                <td><?php echo htmlspecialchars($produit['categorie_nom']); ?></td>
                                <td class="text-center">
                                    <?php if ($produit['quantite'] <= 10): ?>
                                        <span class="badge bg-danger"><?php echo $produit['quantite']; ?> <?php echo $produit['unite_mesure']; ?></span>
                                    <?php else: ?>
                                        <?php echo $produit['quantite']; ?> <?php echo $produit['unite_mesure']; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?php echo number_format($produit['prix_achat_actuel'], 0, ',', ' '); ?> F</td>
                                <td class="text-end"><?php echo number_format($produit['prix_vente_actuel'], 0, ',', ' '); ?> F</td>
                                <td class="text-end"><?php echo number_format($produit['quantite'] * $produit['prix_achat_actuel'], 0, ',', ' '); ?> F</td>
                                <td class="text-center">
                                    <a href="/stock/mouvements.php?id=<?php echo $produit['id']; ?>" class="btn btn-sm btn-info btn-action" title="Mouvements">
                                        <i class="fas fa-exchange-alt"></i>
                                    </a>
                                    <a href="/stock/approvisionnement.php?id=<?php echo $produit['id']; ?>" class="btn btn-sm btn-success btn-action" title="Approvisionner">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                    <?php if ($auth->canModify()): ?>
                                        <a href="/produits/edit.php?id=<?php echo $produit['id']; ?>" class="btn btn-sm btn-warning btn-action" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
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
                                <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo $_GET['search'] ?? ''; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo $_GET['search'] ?? ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo $_GET['search'] ?? ''; ?>">
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

<!-- Stock Alerts -->
<div class="card dashboard-card">
    <div class="card-header bg-danger text-white">
        <h6 class="m-0 font-weight-bold">Alertes de stock</h6>
    </div>
    <div class="card-body">
        <?php if (!empty($stock_faible)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Produit</th>
                            <th>Quantité</th>
                            <th>Seuil d'alerte</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stock_faible as $produit): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($produit['produit_nom']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?php echo $produit['quantite']; ?> <?php echo $produit['unite_mesure']; ?></span>
                                </td>
                                <td class="text-center">10 <?php echo $produit['unite_mesure']; ?></td>
                                <td class="text-center">
                                    <a href="/stock/approvisionnement.php?id=<?php echo $produit['produit_id']; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus me-1"></i>Approvisionner
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>Tous les produits ont un stock suffisant.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Menu -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Gestion des Stocks</h3>
    </div>
    <div class="card-body">
        <div class="list-group">
            <a href="inventaire.php" class="list-group-item list-group-item-action">
                <i class="fas fa-list mr-2"></i> Inventaire
            </a>
            <a href="ajustement.php" class="list-group-item list-group-item-action">
                <i class="fas fa-adjust mr-2"></i> Ajustement
            </a>
            <a href="historique_approvisionnement.php" class="list-group-item list-group-item-action">
                <i class="fas fa-history mr-2"></i> Historique d'approvisionnement
            </a>
        </div>
    </div>
</div>

<!-- JavaScript for Stock Management -->
<script>
    // Enable tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
</script>
