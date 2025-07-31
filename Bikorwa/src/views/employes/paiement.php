<?php
// Production error settings
error_reporting(0);
ini_set('display_errors', 0);

ob_start();

try {
    // Start session if not active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Include dependencies
    require_once __DIR__.'/../../../src/config/config.php';
    require_once __DIR__.'/../../../src/config/database.php';
    require_once __DIR__.'/../../../includes/session.php';
    require_once __DIR__.'/../../../src/utils/Auth.php';

    // Employee Payments page for BIKORWA SHOP
    $pageTitle = "Paiement du Personnel";
    $active_page = "employes";

    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();

    // Initialize authentication
    $auth = new Auth($conn);

    // Verify authentication and role
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
        !isset($_SESSION['role']) || $_SESSION['role'] !== 'gestionnaire') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            http_response_code(403);
            exit(json_encode(['error' => 'Accès non autorisé']));
        } else {
            header('Location: ../auth/login.php');
            exit;
        }
    }

    // Get current user ID for logging
    $current_user_id = $_SESSION['user_id'] ?? 0;

// Fetch active employees
try {
    $query = "SELECT id, nom,actif FROM employes WHERE actif = 1 ORDER BY nom";

    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $employes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    

    
    if (empty($employes)) {

        $noEmployees = true;
    }
} catch (PDOException $e) {

    $employes = [];
    $noEmployees = true;
}

// Gestion de la pagination
$currentPage = (int)($_GET['page'] ?? 1);
$perPage = 10; // Nombre d'éléments par page

// Calcul des limites
$offset = ($currentPage - 1) * $perPage;

// Fetch payment history
$paiements = [];
try {
    $stmt = $conn->prepare(
        "SELECT s.*, e.nom as employe_nom ".
        "FROM salaires s ".
        "JOIN employes e ON s.employe_id = e.id ".
        "ORDER BY s.date_paiement DESC ".
        "LIMIT :offset, :perPage"
    );
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcul du total
    $totalStmt = $conn->query("SELECT COUNT(*) FROM salaires");
    $total = (int)$totalStmt->fetchColumn();
    $totalPages = max(1, ceil($total / $perPage)); // Au moins 1 page

    // Validation de la page courante
    if($currentPage < 1) $currentPage = 1;
    if($currentPage > $totalPages) $currentPage = $totalPages;
} catch (PDOException $e) {

}

require __DIR__.'/../layouts/header.php';
?>

<!-- Add these right after header inclusion if not already in header.php -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4 text-gray-800">
                <i class="fas fa-money-bill-wave mr-2"></i>Paiement du Personnel
            </h1>
            
            <!-- Formulaire de paiement -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-plus-circle mr-1"></i>Nouveau Paiement
                    </h6>
                </div>
                <div class="card-body">
                    <form id="paymentForm" method="post">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="employe_id" class="form-label">Employé <span class="text-danger">*</span></label>
                                <select class="form-select" id="employe_id" name="employe_id" required>
                                    <option value="">Sélectionner un employé</option>
                                    <?php if (!empty($employes)): ?>
                                        <?php foreach ($employes as $employe): ?>
                                        <option value="<?= htmlspecialchars($employe['id']) ?>">
                                            <?= htmlspecialchars($employe['nom']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>Aucun employé actif trouvé</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="montant" class="form-label">Montant (FBU)</label>
                                <input type="number" step="0.01" class="form-control" id="montant" name="montant" required>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col">
                                    <label for="periode_debut" class="form-label">Période début</label>
                                    <input type="date" class="form-control" id="periode_debut" name="periode_debut" required>
                                </div>
                                <div class="col">
                                    <label for="periode_fin" class="form-label">Période fin</label>
                                    <input type="date" class="form-control" id="periode_fin" name="periode_fin" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="date_paiement" class="form-label">Date de paiement</label>
                                <input type="datetime-local" class="form-control" id="date_paiement" name="date_paiement" required>
                            </div>
                            
                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <span id="submitText">Enregistrer le paiement</span>
                                    <span id="submitSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Historique des paiements -->
            <div class="card shadow">
                <div class="card-header py-3 bg-info text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-history mr-1"></i>Historique des paiements
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="paymentTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Employé</th>
                                    <th>Montant</th>
                                    <th>Période</th>
                                    <th>Date Paiement</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($paiements as $paiement): ?>
                                <tr>
                                    <td><?= $paiement['id'] ?></td>
                                    <td><?= htmlspecialchars($paiement['employe_nom']) ?></td>
                                    <td><?= number_format($paiement['montant'], 2, ',', ' ') ?> FBU</td>
                                    <td><?= date('d/m/Y', strtotime($paiement['periode_debut'])) ?> - <?= date('d/m/Y', strtotime($paiement['periode_fin'])) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($paiement['date_paiement'])) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-primary edit-payment" data-id="<?= $paiement['id'] ?>" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-payment" data-id="<?= $paiement['id'] ?>" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php if($currentPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $currentPage-1 ?>">Précédent</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if($currentPage < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $currentPage+1 ?>">Suivant</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#paymentForm').on('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        $('#submitText').addClass('d-none');
        $('#submitSpinner').removeClass('d-none');
        $('#submitBtn').prop('disabled', true);
        
        // Submit form via AJAX
        var formData = $(this).serialize();
        console.log('Envoi de la requête à:', 'process_payment.php');
        console.log('Données envoyées:', formData);
        
        $.ajax({
            url: 'process_payment.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                console.log('Requête en cours...');
            },
            success: function(response, status, xhr) {
                console.log('Réponse du serveur:', response);
                console.log('Statut:', status);
                console.log('Objet XHR:', xhr);
                if(response.success) {
                    Swal.fire('Succès', 'Paiement enregistré', 'success');
                    location.reload();
                } else {
                    let errorMsg = response.message || 'Erreur inconnue';
                    if(response.debug) {
                        errorMsg += '\n\nDétails: ' + response.debug;
                    }
                    Swal.fire('Erreur', errorMsg, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erreur AJAX:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    readyState: xhr.readyState,
                    statusText: xhr.statusText
                });
                Swal.fire(
                    'Erreur réseau', 
                    'Impossible de contacter le serveur. Vérifiez votre connexion.', 
                    'error'
                );
            }
        })
        .always(function() {
            // Reset button state
            $('#submitText').removeClass('d-none');
            $('#submitSpinner').addClass('d-none');
            $('#submitBtn').prop('disabled', false);
        });
    });
    
    function showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: message,
            confirmButtonText: 'OK'
        });
    }
    
    // Écouteur sur la sélection d'un employé
    $('#employe_id').change(function() {
        const employeId = $(this).val();
        
        if(employeId) {
            // Requête AJAX pour récupérer le salaire
            $.ajax({
                url: 'get_employee_salary.php',
                method: 'POST',
                data: { employe_id: employeId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        $('#montant').val(response.salaire);
                    } else {
                        console.error('Erreur:', response.message);
                    }
                },
                error: function(xhr) {
                    console.error('Erreur AJAX:', xhr.responseText);
                }
            });
        } else {
            $('#montant').val('');
        }
    });
    
    // Gestion de l'édition
    $(document).on('click', '.edit-payment', function() {
        const paymentId = $(this).data('id');
        
        $.ajax({
            url: 'get_payment.php',
            method: 'GET',
            data: { id: paymentId },
            dataType: 'json',
            beforeSend: function() {
                Swal.showLoading();
            },
            success: function(response) {
                if(response.success) {
                    const payment = response.data;
                    
                    Swal.fire({
                        title: 'Modifier le paiement',
                        html: `<div class="mb-3">
                                <label class="form-label">Employé</label>
                                <select class="form-select" id="edit-employe" disabled>
                                    <option value="${payment.employe_id}" selected>${payment.employe_nom}</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Montant (FBU)</label>
                                <input type="number" class="form-control" id="edit-montant" value="${payment.montant}">
                            </div>
                            <div class="row mb-3">
                                <div class="col">
                                    <label class="form-label">Période début</label>
                                    <input type="date" class="form-control" id="edit-periode-debut" value="${payment.periode_debut}">
                                </div>
                                <div class="col">
                                    <label class="form-label">Période fin</label>
                                    <input type="date" class="form-control" id="edit-periode-fin" value="${payment.periode_fin}">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date paiement</label>
                                <input type="date" class="form-control" id="edit-date-paiement" value="${payment.date_paiement}">
                            </div>`,
                        showCancelButton: true,
                        confirmButtonText: 'Enregistrer',
                        cancelButtonText: 'Annuler',
                        focusConfirm: false,
                        preConfirm: () => {
                            return {
                                id: paymentId,
                                employe_id: payment.employe_id,
                                montant: $('#edit-montant').val(),
                                periode_debut: $('#edit-periode-debut').val(),
                                periode_fin: $('#edit-periode-fin').val(),
                                date_paiement: $('#edit-date-paiement').val()
                            };
                        }
                    }).then((result) => {
                        if(result.isConfirmed) {
                            $.ajax({
                                url: 'update_payment.php',
                                method: 'POST',
                                data: result.value,
                                dataType: 'json',
                                beforeSend: function() {
                                    Swal.showLoading();
                                },
                                success: function(response) {
                                    if(response.success) {
                                        Swal.fire('Succès', 'Paiement mis à jour', 'success');
                                        location.reload();
                                    } else {
                                        Swal.fire('Erreur', response.message, 'error');
                                    }
                                },
                                error: function() {
                                    Swal.fire('Erreur', 'Une erreur est survenue', 'error');
                                }
                            });
                        }
                    });
                } else {
                    Swal.fire('Erreur', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Erreur', 'Impossible de charger les données', 'error');
            }
        });
    });

    // Gestion de la suppression
    $(document).on('click', '.delete-payment', function() {
        const paymentId = $(this).data('id');
        
        Swal.fire({
            title: 'Confirmer la suppression',
            text: 'Voulez-vous vraiment supprimer ce paiement ?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'delete_payment.php',
                    method: 'POST',
                    data: { id: paymentId },
                    dataType: 'json',
                    beforeSend: function() {
                        Swal.showLoading();
                    },
                    success: function(response) {
                        if(response.success) {
                            Swal.fire('Supprimé !', 'Le paiement a été supprimé.', 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            Swal.fire('Erreur', response.message, 'error');
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Erreur', 'Une erreur est survenue', 'error');
                    }
                });
            }
        });
    });
});
</script>

<?php require __DIR__.'/../layouts/footer.php'; ?>
<?php } catch (Exception $e) {
    error_log('Paiement Error: ' . $e->getMessage());
    exit;
} ?>