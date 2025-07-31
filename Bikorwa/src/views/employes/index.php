<?php
// Employes index page for BIKORWA SHOP
$page_title = "Gestion des Employés";
$active_page = "employes";
?>

<div class="row mb-4">
    <div class="col-md-6">
        <?php if ($auth->canModify()): ?>
            <a href="/employes/nouveau.php" class="btn btn-primary">
                <i class="fas fa-user-plus me-2"></i>Nouvel employé
            </a>
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <form action="/employes/index.php" method="get" class="d-flex justify-content-end">
            <div class="input-group">
                <select name="statut" class="form-select" style="max-width: 200px;">
                    <option value="">Tous les statuts</option>
                    <option value="actif" <?php echo (isset($_GET['statut']) && $_GET['statut'] == 'actif') ? 'selected' : ''; ?>>Actif</option>
                    <option value="inactif" <?php echo (isset($_GET['statut']) && $_GET['statut'] == 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                </select>
                <input type="text" class="form-control" name="search" placeholder="Rechercher un employé..." value="<?php echo $_GET['search'] ?? ''; ?>">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-search me-1"></i>Rechercher
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card dashboard-card mb-4">
    <div class="card-header bg-primary text-white">
        <h6 class="m-0 font-weight-bold">Liste des employés</h6>
    </div>
    <div class="card-body">
        <?php if (!empty($employes)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Poste</th>
                            <th>Téléphone</th>
                            <th>Date d'embauche</th>
                            <th>Salaire</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employes as $employe): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($employe['id']); ?></td>
                                <td><?php echo htmlspecialchars($employe['nom']); ?></td>
                                <td><?php echo htmlspecialchars($employe['poste']); ?></td>
                                <td><?php echo htmlspecialchars($employe['telephone']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($employe['date_embauche'])); ?></td>
                                <td class="text-end"><?php echo number_format($employe['salaire'], 0, ',', ' '); ?> F</td>
                                <td class="text-center">
                                    <?php if ($employe['actif']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="/employes/details.php?id=<?php echo $employe['id']; ?>" class="btn btn-sm btn-info btn-action" title="Détails">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="/employes/paiement.php?id=<?php echo $employe['id']; ?>" class="btn btn-sm btn-success btn-action" title="Enregistrer un paiement">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </a>
                                    <?php if ($auth->canModify()): ?>
                                        <a href="/employes/edit.php?id=<?php echo $employe['id']; ?>" class="btn btn-sm btn-warning btn-action" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($auth->canDelete()): ?>
                                            <a href="/employes/toggle_status.php?id=<?php echo $employe['id']; ?>" class="btn btn-sm <?php echo $employe['actif'] ? 'btn-danger' : 'btn-success'; ?> btn-action" title="<?php echo $employe['actif'] ? 'Désactiver' : 'Activer'; ?>" onclick="return confirm('Êtes-vous sûr de vouloir <?php echo $employe['actif'] ? 'désactiver' : 'activer'; ?> cet employé ?');">
                                                <i class="fas <?php echo $employe['actif'] ? 'fa-times' : 'fa-check'; ?>"></i>
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
                                <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo $_GET['search'] ?? ''; ?>&statut=<?php echo $_GET['statut'] ?? ''; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo $_GET['search'] ?? ''; ?>&statut=<?php echo $_GET['statut'] ?? ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo $_GET['search'] ?? ''; ?>&statut=<?php echo $_GET['statut'] ?? ''; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucun employé trouvé.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Employee Summary -->
<div class="card dashboard-card">
    <div class="card-header bg-info text-white">
        <h6 class="m-0 font-weight-bold">Résumé des employés</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card border-left-primary h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Nombre total d'employés</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statistiques['total_employes'] ?? 0; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-left-success h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Employés actifs</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statistiques['employes_actifs'] ?? 0; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-check fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-left-warning h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Masse salariale mensuelle</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($statistiques['masse_salariale'] ?? 0, 0, ',', ' '); ?> F</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-money-bill-alt fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Enable tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
</script>
