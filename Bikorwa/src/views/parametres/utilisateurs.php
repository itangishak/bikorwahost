<?php
// Parametres utilisateurs page for BIKORWA SHOP
$page_title = "Gestion des Utilisateurs";
$active_page = "parametres";
?>

<div class="row mb-4">
    <div class="col-md-6">
        <?php if ($auth->canModify()): ?>
            <a href="/parametres/utilisateur_nouveau.php" class="btn btn-primary">
                <i class="fas fa-user-plus me-2"></i>Nouvel utilisateur
            </a>
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <form action="/parametres/utilisateurs.php" method="get" class="d-flex justify-content-end">
            <div class="input-group">
                <select name="role" class="form-select" style="max-width: 200px;">
                    <option value="">Tous les rôles</option>
                    <option value="gestionnaire" <?php echo (isset($_GET['role']) && $_GET['role'] == 'gestionnaire') ? 'selected' : ''; ?>>Gestionnaire</option>
                    <option value="receptionniste" <?php echo (isset($_GET['role']) && $_GET['role'] == 'receptionniste') ? 'selected' : ''; ?>>Réceptionniste</option>
                </select>
                <input type="text" class="form-control" name="search" placeholder="Rechercher un utilisateur..." value="<?php echo $_GET['search'] ?? ''; ?>">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-search me-1"></i>Rechercher
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card dashboard-card mb-4">
    <div class="card-header bg-primary text-white">
        <h6 class="m-0 font-weight-bold">Liste des utilisateurs</h6>
    </div>
    <div class="card-body">
        <?php if (!empty($utilisateurs)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nom d'utilisateur</th>
                            <th>Nom complet</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Dernière connexion</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $utilisateur): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($utilisateur['id']); ?></td>
                                <td><?php echo htmlspecialchars($utilisateur['username']); ?></td>
                                <td><?php echo htmlspecialchars($utilisateur['nom']); ?></td>
                                <td><?php echo htmlspecialchars($utilisateur['email']); ?></td>
                                <td>
                                    <?php if ($utilisateur['role'] == 'gestionnaire'): ?>
                                        <span class="badge bg-primary">Gestionnaire</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">Réceptionniste</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($utilisateur['derniere_connexion']): ?>
                                        <?php echo date('d/m/Y H:i', strtotime($utilisateur['derniere_connexion'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Jamais</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($utilisateur['actif']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($auth->canModify()): ?>
                                        <a href="/parametres/utilisateur_edit.php?id=<?php echo $utilisateur['id']; ?>" class="btn btn-sm btn-warning btn-action" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($auth->canDelete() && $utilisateur['id'] != $_SESSION['user_id']): ?>
                                            <a href="/parametres/utilisateur_toggle_status.php?id=<?php echo $utilisateur['id']; ?>" class="btn btn-sm <?php echo $utilisateur['actif'] ? 'btn-danger' : 'btn-success'; ?> btn-action" title="<?php echo $utilisateur['actif'] ? 'Désactiver' : 'Activer'; ?>" onclick="return confirm('Êtes-vous sûr de vouloir <?php echo $utilisateur['actif'] ? 'désactiver' : 'activer'; ?> cet utilisateur ?');">
                                                <i class="fas <?php echo $utilisateur['actif'] ? 'fa-times' : 'fa-check'; ?>"></i>
                                            </a>
                                            <a href="/parametres/utilisateur_reset_password.php?id=<?php echo $utilisateur['id']; ?>" class="btn btn-sm btn-info btn-action" title="Réinitialiser le mot de passe" onclick="return confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe de cet utilisateur ?');">
                                                <i class="fas fa-key"></i>
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
                                <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo $_GET['search'] ?? ''; ?>&role=<?php echo $_GET['role'] ?? ''; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo $_GET['search'] ?? ''; ?>&role=<?php echo $_GET['role'] ?? ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo $_GET['search'] ?? ''; ?>&role=<?php echo $_GET['role'] ?? ''; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucun utilisateur trouvé.
            </div>
        <?php endif; ?>
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
