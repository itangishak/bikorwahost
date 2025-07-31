<?php
// Employee Payment page for BIKORWA SHOP
$page_title = "Paiement des Salaires";
$active_page = "employes";

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
    header('Location: ' . BASE_URL . '/src/views/auth/login.php');
    exit;
}

// Restrict access to gestionnaires only
$userRole = $_SESSION['role'] ?? '';
if ($userRole !== 'gestionnaire') {
    header('Location: ' . BASE_URL . '/src/views/dashboard/index.php');
    exit;
}

// Get current user ID for logging actions
$current_user_id = $_SESSION['user_id'] ?? 0;

// Get employee ID from URL if provided
$employe_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get active employees for dropdown
$query = "SELECT id, nom, poste, salaire FROM employes WHERE actif = 1 ORDER BY nom ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$employes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected employee details if ID is provided
$employe_details = null;
if ($employe_id > 0) {
    $query = "SELECT id, nom, poste, salaire FROM employes WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $employe_id, PDO::PARAM_INT);
    $stmt->execute();
    $employe_details = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get recent payments (last 10)
$query = "SELECT s.*, e.nom as employe_nom, e.poste as employe_poste, u.nom as utilisateur_nom 
          FROM salaires s 
          JOIN employes e ON s.employe_id = e.id 
          JOIN users u ON s.utilisateur_id = u.id 
          ORDER BY s.date_paiement DESC LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute();
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment statistics
$stats_query = "SELECT 
                COUNT(*) as total_paiements,
                SUM(montant) as montant_total,
                COUNT(DISTINCT employe_id) as nb_employes_payes
                FROM salaires";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute();
$statistiques = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Include the header
include_once './../../../src/includes/header.php';
include_once './../../../src/includes/sidebar.php';
?>

<style>
    /* CSS Custom Properties for consistent styling */
    :root {
        --primary: #4e73df;
        --primary-light: #f8f9fc;
        --secondary: #858796;
        --success: #1cc88a;
        --info: #36b9cc;
        --warning: #f6c23e;
        --danger: #e74a3b;
        --light: #f8f9fc;
        --dark: #5a5c69;
        --border-radius: 0.35rem;
        --box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        --transition: all 0.2s ease-in-out;
    }

    /* Responsive card styles */
    .stat-card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        transition: var(--transition);
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    .stat-card .card-body {
        padding: 1.25rem;
    }
    
    .stat-card .stat-icon {
        font-size: 2rem;
        opacity: 0.85;
    }
    
    /* Form styles */
    .payment-form {
        background-color: var(--light);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        box-shadow: var(--box-shadow);
    }
    
    .payment-form label {
        font-weight: 600;
        color: var(--dark);
    }
    
    /* Toast notification styles */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }
    
    /* Table styles */
    .table-responsive {
        overflow-x: auto;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .stat-card {
            margin-bottom: 1rem;
        }
        
        .payment-form {
            padding: 1rem;
        }
    }
    
    /* Animation for loading */
    .loading-spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 0.2em solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        animation: spinner-border .75s linear infinite;
    }
    
    @keyframes spinner-border {
        to { transform: rotate(360deg); }
    }
</style>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../dashboard/index.php">Tableau de bord</a></li>
        <li class="breadcrumb-item"><a href="index.php">Employés</a></li>
        <li class="breadcrumb-item active">Paiement des Salaires</li>
    </ol>
    
    <!-- Toast Container for Notifications -->
    <div class="toast-container"></div>
    
    <!-- Statistics Row -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="stat-card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Total Paiements</div>
                            <div class="h5 mb-0 font-weight-bold" id="paymentsCount"><?php echo number_format($statistiques['total_paiements'] ?? 0, 0, ',', ' '); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-check-alt fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="stat-card bg-success text-white h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Montant Total</div>
                            <div class="h5 mb-0 font-weight-bold" id="amountTotal"><?php echo number_format($statistiques['montant_total'] ?? 0, 0, ',', ' '); ?> F</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="stat-card bg-info text-white h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Employés Payés</div>
                            <div class="h5 mb-0 font-weight-bold" id="employeesPaid"><?php echo number_format($statistiques['nb_employes_payes'] ?? 0, 0, ',', ' '); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Row -->
    <div class="row">
        <!-- Payment Form Card -->
        <div class="col-xl-5 col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Enregistrer un Paiement</h6>
                </div>
                <div class="card-body">
                    <form id="paymentForm" class="payment-form needs-validation" novalidate>
                        <input type="hidden" name="utilisateur_id" value="<?php echo $current_user_id; ?>">
                        
                        <div class="mb-3">
                            <label for="employee" class="form-label">Employé</label>
                            <select class="form-select" id="employee" name="employe_id" required>
                                <option value="">Choisir un employé...</option>
                                <?php foreach($employes as $employe): ?>
                                <option value="<?php echo $employe['id']; ?>" data-salary="<?php echo $employe['salaire']; ?>" <?php echo ($employe_id == $employe['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employe['nom']); ?> (<?php echo htmlspecialchars($employe['poste']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un employé</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="montant" class="form-label">Montant</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="montant" name="montant" min="1" required value="<?php echo $employe_details ? $employe_details['salaire'] : ''; ?>">
                                <span class="input-group-text">F</span>
                                <div class="invalid-feedback">Veuillez entrer un montant valide</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="periode_debut" class="form-label">Période début</label>
                                <input type="date" class="form-control" id="periode_debut" name="periode_debut" required>
                                <div class="invalid-feedback">Veuillez sélectionner une date de début</div>
                            </div>
                            <div class="col-md-6">
                                <label for="periode_fin" class="form-label">Période fin</label>
                                <input type="date" class="form-control" id="periode_fin" name="periode_fin" required>
                                <div class="invalid-feedback">Veuillez sélectionner une date de fin</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2" placeholder="Optionnel"></textarea>
                        </div>
                        
                        <button type="submit" id="savePaymentBtn" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Enregistrer le Paiement
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Recent Payments Card -->
        <div class="col-xl-7 col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Paiements Récents</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="recentPaymentsTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Employé</th>
                                    <th>Montant</th>
                                    <th>Période</th>
                                    <th>Par</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_payments) > 0): ?>
                                    <?php foreach($recent_payments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($payment['date_paiement'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($payment['employe_nom']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($payment['employe_poste']); ?></small>
                                        </td>
                                        <td class="text-end"><?php echo number_format($payment['montant'], 0, ',', ' '); ?> F</td>
                                        <td>
                                            <small>
                                                <?php echo date('d/m/Y', strtotime($payment['periode_debut'])); ?>
                                                - 
                                                <?php echo date('d/m/Y', strtotime($payment['periode_fin'])); ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment['utilisateur_nom']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-3">Aucun paiement enregistré</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for AJAX form submission and toast notifications -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Variables
        const paymentForm = document.getElementById('paymentForm');
        const employeeSelect = document.getElementById('employee');
        const montantInput = document.getElementById('montant');
        const savePaymentBtn = document.getElementById('savePaymentBtn');
        const toastContainer = document.querySelector('.toast-container');
        const recentPaymentsTable = document.getElementById('recentPaymentsTable').querySelector('tbody');
        const paymentsCount = document.getElementById('paymentsCount');
        const amountTotal = document.getElementById('amountTotal');
        const employeesPaid = document.getElementById('employeesPaid');
        
        // Initialize date fields with current month
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        
        document.getElementById('periode_debut').valueAsDate = firstDay;
        document.getElementById('periode_fin').valueAsDate = lastDay;
        
        // Update montant when employee changes
        employeeSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const salary = selectedOption.getAttribute('data-salary');
                montantInput.value = salary || '';
            } else {
                montantInput.value = '';
            }
        });
        
        // Form submission
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Form validation
            if (!paymentForm.checkValidity()) {
                e.stopPropagation();
                paymentForm.classList.add('was-validated');
                showToast('Veuillez remplir tous les champs obligatoires', 'error');
                return;
            }
            
            // Additional validation
            const periodeDebut = new Date(document.getElementById('periode_debut').value);
            const periodeFin = new Date(document.getElementById('periode_fin').value);
            
            if (periodeFin < periodeDebut) {
                showToast('La date de fin doit être postérieure à la date de début', 'error');
                return;
            }
            
            // Show loading state
            savePaymentBtn.disabled = true;
            savePaymentBtn.innerHTML = '<span class="loading-spinner me-2"></span>Enregistrement en cours...';
            
            // Prepare form data
            const formData = new FormData(paymentForm);
            
            // Send AJAX request
            fetch('paiement_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset loading state
                savePaymentBtn.disabled = false;
                savePaymentBtn.innerHTML = '<i class="fas fa-save me-1"></i> Enregistrer le Paiement';
                
                if (data.success) {
                    // Show success message
                    showToast(data.message, 'success');
                    
                    // Reset form
                    paymentForm.reset();
                    paymentForm.classList.remove('was-validated');
                    
                    // Reset date fields
                    document.getElementById('periode_debut').valueAsDate = firstDay;
                    document.getElementById('periode_fin').valueAsDate = lastDay;
                    
                    // Update recent payments table
                    if (data.html) {
                        recentPaymentsTable.innerHTML = data.html;
                    }
                    
                    // Update statistics
                    if (data.stats) {
                        if (data.stats.total_paiements) {
                            paymentsCount.textContent = formatNumber(data.stats.total_paiements);
                        }
                        if (data.stats.montant_total) {
                            amountTotal.textContent = formatNumber(data.stats.montant_total) + ' F';
                        }
                        if (data.stats.nb_employes_payes) {
                            employeesPaid.textContent = formatNumber(data.stats.nb_employes_payes);
                        }
                    } else {
                        // If no data is returned, reload the page to refresh data
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                } else {
                    // Show error message
                    showToast(data.message || 'Une erreur est survenue', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                savePaymentBtn.disabled = false;
                savePaymentBtn.innerHTML = '<i class="fas fa-save me-1"></i> Enregistrer le Paiement';
                showToast('Une erreur est survenue lors de la communication avec le serveur', 'error');
            });
        });
        
        // Function to show toast notifications
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast show align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'primary'} border-0 mb-2`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            // Add event listener for close button
            const closeBtn = toast.querySelector('.btn-close');
            closeBtn.addEventListener('click', function() {
                toast.remove();
            });
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }
        
        // Helper function to format numbers
        function formatNumber(number) {
            return new Intl.NumberFormat('fr-FR').format(number);
        }
    });
</script>
