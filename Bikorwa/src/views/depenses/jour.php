<?php
// Daily Expenses page for BIKORWA SHOP
$page_title = "Dépenses du Jour";
$active_page = "depenses-jour";

require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize authentication
$auth = new Auth($conn);

// Check if user is logged in and has appropriate role
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/src/views/dashboard/index.php');
    exit;
}

// Get current user ID for logging actions
$current_user_id = $_SESSION['user_id'] ?? 0;

// Get today's date
$today = date('Y-m-d');

// Query to get today's expenses
$query = "SELECT depenses.*, categories_depenses.nom as categorie_nom, users.nom as user_nom 
          FROM depenses 
          JOIN categories_depenses ON categories_depenses.id = depenses.categorie_id 
          JOIN users ON users.id = depenses.utilisateur_id 
          WHERE DATE(depenses.date_depense) = ?
          ORDER BY depenses.date_depense DESC, depenses.id DESC";

$stmt = $conn->prepare($query);
$stmt->execute([$today]);
$depenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for the form
$categories_query = "SELECT id, nom FROM categories_depenses ORDER BY nom";
$categories_stmt = $conn->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total amount for today
$total_amount = 0;
foreach ($depenses as $depense) {
    $total_amount += $depense['montant'];
}

// Include the header
include_once './../../../src/views/layouts/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIKORWA SHOP - <?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.12/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        .card {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgb(33 40 50 / 15%);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e3e6ec;
        }
        .table th {
            font-weight: 600;
            background-color: #f8f9fa;
        }
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .modal-content {
            border: none;
            border-radius: 0.5rem;
        }
        .modal-header {
            border-radius: 0.5rem 0.5rem 0 0;
        }
        .form-control:focus, .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgb(13 110 253 / 25%);
        }
        .table-hover tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .breadcrumb {
            background-color: transparent;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            border-radius: 0.25rem;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <div class="container-fluid px-4 py-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-2 text-gray-800"><?php echo $page_title; ?></h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard/index.php" class="text-decoration-none">Tableau de bord</a></li>
                            <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                        </ol>
                    </nav>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                    <i class="fas fa-plus-circle me-2"></i>Nouvelle dépense
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card stats-card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="h6 mb-0 text-white-50">Total Dépenses Aujourd'hui</div>
                                    <div class="h3 mb-0"><?php echo number_format($total_amount, 0, ',', ' '); ?> FBU</div>
                                </div>
                                <div class="float-right">
                                    <i class="fas fa-money-bill-wave fa-2x text-white-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card stats-card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="h6 mb-0 text-white-50">Nombre de Dépenses</div>
                                    <div class="h3 mb-0"><?php echo count($depenses); ?></div>
                                </div>
                                <div class="float-right">
                                    <i class="fas fa-list fa-2x text-white-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expenses List Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list me-2"></i>Dépenses du <?php echo date('d/m/Y', strtotime($today)); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($depenses)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th>Montant</th>
                                        <th>Catégorie</th>
                                        <th>Mode de Paiement</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($depenses as $depense): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($depense['description']); ?></td>
                                        <td class="fw-bold"><?php echo number_format($depense['montant'], 0, ',', ' '); ?> FBU</td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo htmlspecialchars($depense['categorie_nom']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($depense['mode_paiement']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-warning edit-depense-btn me-1" data-id="<?php echo $depense['id']; ?>" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-depense-btn" data-id="<?php echo $depense['id']; ?>" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info d-flex align-items-center" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            <div>Aucune dépense enregistrée aujourd'hui.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add Expense Modal -->
            <div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="addExpenseModalLabel">
                                <i class="fas fa-plus-circle me-2"></i>Nouvelle dépense
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="addExpenseForm" method="post" action="add_expense.php" class="needs-validation" novalidate>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="description" name="description" placeholder="Description" required>
                                            <label for="description">Description</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="montant" name="montant" placeholder="Montant" step="1" min="0" required>
                                            <label for="montant">Montant (FBU)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <input type="date" class="form-control" id="date_depense" name="date_depense" value="<?php echo date('Y-m-d'); ?>" required>
                                            <label for="date_depense">Date de la dépense</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="categorie_id" name="categorie_id" required>
                                                <option value="">Sélectionner une catégorie</option>
                                                <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>">
                                                    <?php echo htmlspecialchars($category['nom']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label for="categorie_id">Catégorie</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="mode_paiement" name="mode_paiement" required>
                                                <option value="">Sélectionner un mode</option>
                                                <option value="Espèces">Espèces</option>
                                                <option value="Cheque">Chèque</option>
                                                <option value="Virement">Virement</option>
                                                <option value="Carte">Carte</option>
                                                <option value="Mobile Money">Mobile Money</option>
                                            </select>
                                            <label for="mode_paiement">Mode de Paiement</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="reference_paiement" name="reference_paiement" placeholder="Référence">
                                            <label for="reference_paiement">Référence de Paiement (optionnel)</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer border-top-0">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i>Annuler
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Expense Modal -->
            <div class="modal fade" id="editExpenseModal" tabindex="-1" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-white">
                            <h5 class="modal-title" id="editExpenseModalLabel">
                                <i class="fas fa-edit me-2"></i>Modifier la dépense
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="editExpenseForm" method="post" class="needs-validation" novalidate>
                                <input type="hidden" id="edit_id" name="id">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="edit_description" name="description" placeholder="Description" required>
                                            <label for="edit_description">Description</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="edit_montant" name="montant" placeholder="Montant" step="1" min="0" required>
                                            <label for="edit_montant">Montant (FBU)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <input type="date" class="form-control" id="edit_date_depense" name="date_depense" required>
                                            <label for="edit_date_depense">Date de la dépense</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="edit_categorie_id" name="categorie_id" required>
                                                <option value="">Sélectionner une catégorie</option>
                                                <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>">
                                                    <?php echo htmlspecialchars($category['nom']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label for="edit_categorie_id">Catégorie</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="edit_mode_paiement" name="mode_paiement" required>
                                                <option value="">Sélectionner un mode</option>
                                                <option value="Espèces">Espèces</option>
                                                <option value="Cheque">Chèque</option>
                                                <option value="Virement">Virement</option>
                                                <option value="Carte">Carte</option>
                                                <option value="Mobile Money">Mobile Money</option>
                                            </select>
                                            <label for="edit_mode_paiement">Mode de Paiement</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="edit_reference_paiement" name="reference_paiement" placeholder="Référence">
                                            <label for="edit_reference_paiement">Référence de Paiement (optionnel)</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer border-top-0">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i>Annuler
                                    </button>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-save me-1"></i>Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.12/dist/sweetalert2.all.min.js"></script>
    
    <!-- Form Submission Handler -->
    <script>
    document.getElementById('addExpenseForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Enregistrement...';
        
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                Swal.fire({
                    title: 'Succès!',
                    text: data.message,
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    // Close modal and refresh page
                    bootstrap.Modal.getInstance(document.getElementById('addExpenseModal')).hide();
                    location.reload();
                });
            } else {
                // Show error message
                Swal.fire({
                    title: 'Erreur!',
                    text: data.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                title: 'Erreur!',
                text: 'Une erreur est survenue lors de l\'envoi du formulaire.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save me-1"></i>Enregistrer';
        });
    });
    </script>
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    </script>
    <script>
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-depense-btn')) {
            const button = e.target.closest('.delete-depense-btn');
            const expenseId = button.dataset.id;
            
            Swal.fire({
                title: 'Confirmer la suppression',
                text: 'Êtes-vous sûr de vouloir supprimer cette dépense?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading on button
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    button.disabled = true;
                    
                    fetch('delete_expense.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: expenseId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire(
                                'Supprimé!',
                                data.message,
                                'success'
                            ).then(() => {
                                // Remove row from table
                                button.closest('tr').remove();
                                // Optional: Reload page to update totals
                                location.reload();
                            });
                        } else {
                            Swal.fire(
                                'Erreur!',
                                data.message,
                                'error'
                            );
                        }
                    })
                    .catch(error => {
                        Swal.fire(
                            'Erreur!',
                            'Une erreur est survenue lors de la suppression',
                            'error'
                        );
                    })
                    .finally(() => {
                        button.innerHTML = '<i class="fas fa-trash"></i>';
                        button.disabled = false;
                    });
                }
            });
        }
    });
    </script>
    <script>
    document.addEventListener('click', function(e) {
        if (e.target.closest('.edit-depense-btn')) {
            const button = e.target.closest('.edit-depense-btn');
            const expenseId = button.dataset.id;
            
            // Show loading state
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;
            
            fetch('get_expense.php?id=' + expenseId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Populate form
                    document.getElementById('edit_id').value = data.expense.id;
                    document.getElementById('edit_description').value = data.expense.description;
                    document.getElementById('edit_montant').value = data.expense.montant;
                    document.getElementById('edit_date_depense').value = data.expense.date_depense;
                    document.getElementById('edit_categorie_id').value = data.expense.categorie_id;
                    document.getElementById('edit_mode_paiement').value = data.expense.mode_paiement;
                    document.getElementById('edit_reference_paiement').value = data.expense.reference_paiement || '';
                    
                    // Show modal
                    const editModal = new bootstrap.Modal(document.getElementById('editExpenseModal'));
                    editModal.show();
                } else {
                    Swal.fire('Erreur!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erreur!', 'Une erreur est survenue lors du chargement', 'error');
            })
            .finally(() => {
                button.innerHTML = '<i class="fas fa-edit"></i>';
                button.disabled = false;
            });
        }
    });
    
    // Handle edit form submission
    document.getElementById('editExpenseForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Enregistrement...';
        
        fetch('update_expense.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Succès!',
                    text: data.message,
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    // Close modal and refresh page
                    bootstrap.Modal.getInstance(document.getElementById('editExpenseModal')).hide();
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Erreur!',
                    text: data.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                title: 'Erreur!',
                text: 'Une erreur est survenue lors de la mise à jour',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save me-1"></i>Enregistrer';
        });
    });
    </script>
</body>
</html>
