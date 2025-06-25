<?php
require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Get ID from URL parameter
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die('ID invalide');
}

try {
    // Get expense details
    $query = "SELECT * FROM depenses WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        die('Dépense non trouvée');
    }

    // Get categories for dropdown
    $categoriesQuery = "SELECT id, nom FROM categories_depenses ORDER BY nom";
    $categories = $conn->query($categoriesQuery)->fetchAll(PDO::FETCH_ASSOC);

    // Generate edit form HTML
    ?>
    <form id="editExpenseForm" action="update_expense.php" method="POST">
        <input type="hidden" name="id" value="<?php echo $expense['id']; ?>">
        
        <div class="mb-3">
            <label class="form-label">Date</label>
            <input type="date" class="form-control" name="date_depense" value="<?php echo $expense['date_depense']; ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Catégorie</label>
            <select class="form-select" name="categorie_id" required>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $expense['categorie_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['nom']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Montant (FBU)</label>
            <input type="number" class="form-control" name="montant" min="0" step="0.01" value="<?php echo $expense['montant']; ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <input type="text" class="form-control" name="description" value="<?php echo htmlspecialchars($expense['description']); ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Mode de paiement</label>
            <select class="form-select" name="mode_paiement" required>
                <option value="especes" <?php echo $expense['mode_paiement'] == 'especes' ? 'selected' : ''; ?>>Espèces</option>
                <option value="cheque" <?php echo $expense['mode_paiement'] == 'cheque' ? 'selected' : ''; ?>>Chèque</option>
                <option value="virement" <?php echo $expense['mode_paiement'] == 'virement' ? 'selected' : ''; ?>>Virement</option>
                <option value="carte" <?php echo $expense['mode_paiement'] == 'carte' ? 'selected' : ''; ?>>Carte</option>
                <option value="mobile_money" <?php echo $expense['mode_paiement'] == 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Référence paiement (optionnel)</label>
            <input type="text" class="form-control" name="reference_paiement" value="<?php echo htmlspecialchars($expense['reference_paiement'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Note (optionnel)</label>
            <textarea class="form-control" name="note"><?php echo htmlspecialchars($expense['note'] ?? ''); ?></textarea>
        </div>

        <div class="text-end">
            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
    </form>

    <script>
    // Handle form submission
    document.getElementById('editExpenseForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch(this.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success toast
                toastr.success(data.message, 'Succès');
                
                // Close modal after delay
                setTimeout(() => {
                    $('#editExpenseModal').modal('hide');
                    // Refresh expense data without full page reload
                    loadExpenses($('#itemsPerPage').val(), $('input[name="page"]').val());
                }, 1500);
            } else {
                toastr.error(data.message, 'Erreur');
            }
        })
        .catch(error => {
            toastr.error('Une erreur est survenue', 'Erreur');
            console.error('Error:', error);
        });
    });

    // Function to reload expenses (should match your existing implementation)
    function loadExpenses(itemsPerPage, page) {
        // Implement your existing expense loading logic here
        // This should match how you load expenses initially
        console.log('Reloading expenses...');
        // Example: $('#expenseTable').load('historique.php #expenseTable > *');
    }
    </script>
    <?php
} catch (PDOException $e) {
    die('Erreur de base de données: ' . $e->getMessage());
}
