<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addProductModalLabel">Ajouter un nouveau produit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST" id="addProductForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_product">
                    
                    <div class="row g-3">
                        <!-- Product Information -->
                      
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="categorie_id" class="form-label">Catégorie</label>
                                <select class="form-select" id="categorie_id" name="categorie_id">
                                    <option value="">Sélectionner une catégorie</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="nom" class="form-label">Nom du produit <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nom" name="nom" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="unite_mesure" class="form-label">Unité de mesure <span class="text-danger">*</span></label>
                                <select class="form-select" id="unite_mesure" name="unite_mesure" required onchange="toggleCustomUnit('unite_mesure', 'custom_unite_container')">
                                    <option value="">Sélectionner une unité</option>
                                    <option value="pièce">pièce</option>
                                    <option value="kg">kg</option>
                                    <option value="litre">litre</option>
                                    <option value="m">m</option>
                                    <option value="m²">m²</option>
                                    <option value="m³">m³</option>
                                    <option value="carton">carton</option>
                                    <option value="paquet">paquet</option>
                                    <option value="boîte">boîte</option>
                                    <option value="sac">sac</option>
                                    <option value="bouteille">bouteille</option>
                                    <option value="bidon">bidon</option>
                                    <option value="service">service</option>
                                    <option value="autre">autre</option>
                                </select>
                                <small class="form-text text-muted">Sélectionnez l'unité de mesure appropriée</small>
                                <div id="custom_unite_container" class="mt-2" style="display: none;">
                                    <input type="text" class="form-control" id="custom_unite" placeholder="Spécifiez l'unité personnalisée">
                                    <small class="form-text text-muted">Si vous avez sélectionné 'autre', veuillez spécifier l'unité</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="prix_achat" class="form-label">Prix d'achat <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="prix_achat" name="prix_achat" min="0" step="1" required>
                                    <span class="input-group-text">F</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="prix_vente" class="form-label">Prix de vente <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="prix_vente" name="prix_vente" min="0" step="1" required>
                                    <span class="input-group-text">F</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="quantite" class="form-label">Stock initial</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="quantite" name="quantite" min="0" step="0.01" value="0">
                                    <span class="input-group-text quantite-unite">unité</span>
                                </div>
                                <small class="form-text text-muted">Laisser à 0 si aucun stock initial</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProductModalLabel">Modifier le produit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST" id="editProductForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="produit_id" id="edit_produit_id">
                    
                    <div class="row g-3">
                        <!-- Product Information -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_code" class="form-label">Code produit <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_code" name="code" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_categorie_id" class="form-label">Catégorie</label>
                                <select class="form-select" id="edit_categorie_id" name="categorie_id">
                                    <option value="">Sélectionner une catégorie</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="edit_nom" class="form-label">Nom du produit <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_nom" name="nom" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_unite_mesure" class="form-label">Unité de mesure <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_unite_mesure" name="unite_mesure" required onchange="toggleCustomUnit('edit_unite_mesure', 'edit_custom_unite_container')">
                                    <option value="">Sélectionner une unité</option>
                                    <option value="pièce">pièce</option>
                                    <option value="kg">kg</option>
                                    <option value="litre">litre</option>
                                    <option value="m">m</option>
                                    <option value="m²">m²</option>
                                    <option value="m³">m³</option>
                                    <option value="carton">carton</option>
                                    <option value="paquet">paquet</option>
                                    <option value="boîte">boîte</option>
                                    <option value="sac">sac</option>
                                    <option value="bouteille">bouteille</option>
                                    <option value="bidon">bidon</option>
                                    <option value="service">service</option>
                                    <option value="autre">autre</option>
                                </select>
                                <small class="form-text text-muted">Sélectionnez l'unité de mesure appropriée</small>
                                <div id="edit_custom_unite_container" class="mt-2" style="display: none;">
                                    <input type="text" class="form-control" id="edit_custom_unite" placeholder="Spécifiez l'unité personnalisée">
                                    <small class="form-text text-muted">Si vous avez sélectionné 'autre', veuillez spécifier l'unité</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_prix_achat" class="form-label">Prix d'achat <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="edit_prix_achat" name="prix_achat" min="0" step="1" required>
                                    <span class="input-group-text">F</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_prix_vente" class="form-label">Prix de vente <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="edit_prix_vente" name="prix_vente" min="0" step="1" required>
                                    <span class="input-group-text">F</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit_actif" name="actif" checked>
                                <label class="form-check-label" for="edit_actif">Produit actif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Product Modal -->
<div class="modal fade" id="viewProductModal" tabindex="-1" aria-labelledby="viewProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewProductModalLabel">Détails du produit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Product Basic Information -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Nom:</strong> <span id="view_nom"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Code:</strong> <span id="view_code"></span></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Catégorie:</strong> <span id="view_categorie"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Unité de mesure:</strong> <span id="view_unite"></span></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Prix d'achat:</strong> <span id="view_prix_achat"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Prix de vente:</strong> <span id="view_prix_vente"></span></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Stock actuel:</strong> <span id="view_quantite"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Valeur en stock:</strong> <span id="view_valeur"></span></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Dernière mise à jour:</strong> <span id="view_date"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Statut:</strong> <span id="view_actif"></span></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <p><strong>Description:</strong> <span id="view_description"></span></p>
                    </div>
                </div>
                
                <!-- FIFO Batch Information Section -->
                <hr>
                <h5 class="mt-3 mb-3">Détails des lots en stock (FIFO)</h5>
                <div id="batchesLoadingSpinner" class="text-center mb-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-2">Chargement des détails des lots...</p>
                </div>
                <div id="batchesErrorMessage" class="alert alert-danger d-none">
                    Une erreur est survenue lors du chargement des lots.
                </div>
                <div id="noBatchesMessage" class="alert alert-info d-none">
                    Aucun lot disponible pour ce produit.
                </div>
                <div class="table-responsive d-none" id="batchesTableContainer">
                    <table class="table table-bordered table-striped table-sm">
                        <thead class="table-primary">
                            <tr>
                                <th>Date d'entrée</th>
                                <th>Prix d'achat</th>
                                <th>Quantité initiale</th>
                                <th>Quantité restante</th>
                                <th>Valeur en stock</th>
                                <th>Référence</th>
                            </tr>
                        </thead>
                        <tbody id="batchesTableBody">
                            <!-- Batch data will be loaded by JavaScript -->
                        </tbody>
                        <tfoot id="batchesTableFoot" class="table-info">
                            <tr>
                                <td colspan="3"><strong>Total</strong></td>
                                <td id="totalRemainingQuantity"></td>
                                <td id="totalBatchValue"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <?php if ($hasStockAccess): ?>
                <button type="button" class="btn btn-primary" id="viewToEditBtn">Modifier</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1" aria-labelledby="adjustStockModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adjustStockModalLabel">Ajuster le stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST" id="adjustStockForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="adjust_stock">
                    <input type="hidden" name="produit_id" id="adjust_produit_id">
                    
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Produit:</strong> <span id="adjust_nom_produit"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Code:</strong> <span id="adjust_code_produit"></span>
                            </div>
                        </div>
                        <div class="mt-2">
                            <strong>Stock actuel:</strong> <span id="adjust_stock_actuel"></span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Type de mouvement <span class="text-danger">*</span></label>
                        <div class="d-flex">
                            <div class="form-check me-4">
                                <input class="form-check-input" type="radio" name="type_mouvement" id="type_entree" value="entree" checked>
                                <label class="form-check-label" for="type_entree">
                                    <i class="fas fa-arrow-alt-circle-down text-success me-1"></i> Entrée
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type_mouvement" id="type_sortie" value="sortie" <?= $isGestionnaire ? '' : 'disabled' ?> >
                                <label class="form-check-label" for="type_sortie">
                                    <i class="fas fa-arrow-alt-circle-up text-danger me-1"></i> Sortie
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="adjust_quantite" class="form-label">Quantité <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="adjust_quantite" name="quantite" min="0.01" step="0.01" required>
                            <span class="input-group-text" id="adjust_unite">unité</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="adjust_note" class="form-label">Note</label>
                        <textarea class="form-control" id="adjust_note" name="note" rows="2" placeholder="Raison de l'ajustement..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="me-auto d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="adjustPrev" title="Précédent"><i class="fas fa-chevron-left"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="adjustNext" title="Suivant"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Product Modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteProductModalLabel">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-exclamation-triangle text-warning fa-4x mb-3"></i>
                    <h5>Êtes-vous sûr de vouloir supprimer ce produit?</h5>
                    <p class="mb-0" id="delete_product_name"></p>
                    <p class="text-muted small" id="delete_product_code"></p>
                </div>
                <div class="alert alert-danger">
                    <i class="fas fa-info-circle me-2"></i> Cette action est irréversible. Si le produit est utilisé dans des ventes, il sera désactivé au lieu d'être supprimé.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form action="" method="POST" class="d-inline">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="produit_id" id="delete_produit_id">
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Function to toggle custom unit field visibility
    function toggleCustomUnit(selectId, containerId) {
        const selectElement = document.getElementById(selectId);
        const containerElement = document.getElementById(containerId);
        
        if (selectElement.value === 'autre') {
            containerElement.style.display = 'block';
        } else {
            containerElement.style.display = 'none';
        }
    }
    
    // Function to handle form submission with custom unit
    function handleFormSubmit(formId) {
        const form = document.getElementById(formId);
        if (!form) return;
        
        form.addEventListener('submit', function(e) {
            // Check if 'autre' is selected and custom unit is provided
            if (formId === 'addProductForm') {
                const uniteSelect = document.getElementById('unite_mesure');
                const customUnite = document.getElementById('custom_unite');
                
                if (uniteSelect.value === 'autre' && customUnite.value.trim()) {
                    // Replace the 'autre' value with the custom unit
                    uniteSelect.value = customUnite.value.trim();
                }
            } else if (formId === 'editProductForm') {
                const uniteSelect = document.getElementById('edit_unite_mesure');
                const customUnite = document.getElementById('edit_custom_unite');
                
                if (uniteSelect.value === 'autre' && customUnite.value.trim()) {
                    // Replace the 'autre' value with the custom unit
                    uniteSelect.value = customUnite.value.trim();
                }
            }
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize custom unit toggles
        toggleCustomUnit('unite_mesure', 'custom_unite_container');
        toggleCustomUnit('edit_unite_mesure', 'edit_custom_unite_container');
        
        // Set up form submission handlers
        handleFormSubmit('addProductForm');
        handleFormSubmit('editProductForm');
        // For new product form
        const uniteMesureInput = document.getElementById('unite_mesure');
        const quantiteUnite = document.querySelector('.quantite-unite');
        
        if (uniteMesureInput && quantiteUnite) {
            uniteMesureInput.addEventListener('input', function() {
                quantiteUnite.textContent = this.value || 'unité';
            });
        }
        
        // Handle Add Product Modal
        const addProductModal = document.getElementById('addProductModal');
        if (addProductModal) {
            addProductModal.addEventListener('show.bs.modal', function () {
                // Reset form on modal open
                const form = addProductModal.querySelector('form');
                form.reset();
            });
        }
        
        // Handle Edit Product Modal
        const editProductModal = document.getElementById('editProductModal');
        if (editProductModal) {
            editProductModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                
                // Extract data
                const id = button.getAttribute('data-id');
                const code = button.getAttribute('data-code');
                const nom = button.getAttribute('data-nom');
                const description = button.getAttribute('data-description');
                const categorieId = button.getAttribute('data-categorie-id');
                const unite = button.getAttribute('data-unite');
                const prixAchat = button.getAttribute('data-prix-achat');
                const prixVente = button.getAttribute('data-prix-vente');
                const actif = button.getAttribute('data-actif');
                
                // Set values in the form
                document.getElementById('edit_produit_id').value = id;
                document.getElementById('edit_code').value = code;
                document.getElementById('edit_nom').value = nom;
                document.getElementById('edit_description').value = description || '';
                document.getElementById('edit_categorie_id').value = categorieId || '';
                
                // Handle the unit dropdown - set the selected option
                const uniteSelect = document.getElementById('edit_unite_mesure');
                
                // First check if the unit exists in the dropdown
                let unitExists = false;
                for (let i = 0; i < uniteSelect.options.length; i++) {
                    if (uniteSelect.options[i].value === unite) {
                        uniteSelect.value = unite;
                        unitExists = true;
                        break;
                    }
                }
                
                // If the unit doesn't exist in the dropdown and 'autre' is an option, select 'autre'
                if (!unitExists && unite) {
                    // Log the unit for debugging
                    console.log('Unit not found in dropdown: ' + unite);
                    // Try to select 'autre' option
                    uniteSelect.value = 'autre';
                }
                
                document.getElementById('edit_prix_achat').value = prixAchat;
                document.getElementById('edit_prix_vente').value = prixVente;
                document.getElementById('edit_actif').checked = actif === '1';
            });
        }
        
        // Handle View Product Modal
        const viewProductModal = document.getElementById('viewProductModal');
        if (viewProductModal) {
            viewProductModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                
                // Extract data
                const id = button.getAttribute('data-id');
                const nom = button.getAttribute('data-nom');
                const code = button.getAttribute('data-code');
                const categorie = button.getAttribute('data-categorie');
                const description = button.getAttribute('data-description') || 'Non spécifié';
                const unite = button.getAttribute('data-unite');
                const quantite = button.getAttribute('data-quantite');
                const prixAchat = button.getAttribute('data-prix-achat');
                const prixVente = button.getAttribute('data-prix-vente');
                const date = button.getAttribute('data-date');
                const actif = button.getAttribute('data-actif');
                const valeur = parseFloat(prixAchat) * parseFloat(quantite);
                
                // Set values in the modal
                document.getElementById('view_nom').textContent = nom;
                document.getElementById('view_code').textContent = code;
                document.getElementById('view_categorie').textContent = categorie;
                document.getElementById('view_description').textContent = description;
                document.getElementById('view_unite').textContent = unite;
                document.getElementById('view_prix_achat').textContent = prixAchat + ' F';
                document.getElementById('view_prix_vente').textContent = prixVente + ' F';
                document.getElementById('view_quantite').textContent = quantite + ' ' + unite;
                document.getElementById('view_valeur').textContent = valeur + ' F';
                document.getElementById('view_date').textContent = date;
                document.getElementById('view_actif').textContent = actif;
                
                // Load batch information (FIFO) via AJAX
                loadProductBatches(id);
                
                // Setup the Edit button
                const viewToEditBtn = document.getElementById('viewToEditBtn');
                if (viewToEditBtn) {
                    viewToEditBtn.onclick = function() {
                        // Close the view modal
                        const viewModal = bootstrap.Modal.getInstance(viewProductModal);
                        viewModal.hide();
                        
                        // Trigger the edit modal with this product's data
                        setTimeout(function() {
                            const editButton = document.querySelector(`[data-bs-target="#editProductModal"][data-id="${id}"]`);
                            if (editButton) {
                                editButton.click();
                            }
                        }, 500);
                    };
                }
            });
            
            // Reset batch display when modal is closed
            viewProductModal.addEventListener('hidden.bs.modal', function () {
                document.getElementById('batchesTableBody').innerHTML = '';
                document.getElementById('batchesLoadingSpinner').classList.remove('d-none');
                document.getElementById('batchesErrorMessage').classList.add('d-none');
                document.getElementById('noBatchesMessage').classList.add('d-none');
                document.getElementById('batchesTableContainer').classList.add('d-none');
                document.getElementById('totalRemainingQuantity').textContent = '';
                document.getElementById('totalBatchValue').textContent = '';
            });
        }
        
        /**
         * Load product batches via AJAX for FIFO inventory tracking
         */
        function loadProductBatches(productId) {
            // Show loading spinner
            document.getElementById('batchesLoadingSpinner').classList.remove('d-none');
            document.getElementById('batchesErrorMessage').classList.add('d-none');
            document.getElementById('noBatchesMessage').classList.add('d-none');
            document.getElementById('batchesTableContainer').classList.add('d-none');
            
            // Make AJAX request
            fetch(`../../ajax/get_product_batches.php?product_id=${productId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur réseau lors de la récupération des données');
                    }
                    return response.json();
                })
                .then(data => {
                    // Hide loading spinner
                    document.getElementById('batchesLoadingSpinner').classList.add('d-none');
                    
                    if (data.error) {
                        // Show error message
                        document.getElementById('batchesErrorMessage').textContent = data.error;
                        document.getElementById('batchesErrorMessage').classList.remove('d-none');
                        return;
                    }
                    
                    if (!data.batches || data.batches.length === 0) {
                        // Show no batches message
                        document.getElementById('noBatchesMessage').classList.remove('d-none');
                        return;
                    }
                    
                    // Display batch data
                    const tableBody = document.getElementById('batchesTableBody');
                    tableBody.innerHTML = '';
                    
                    let totalRemaining = 0;
                    let totalValue = 0;
                    
                    // Process and display each batch
                    data.batches.forEach(batch => {
                        const row = document.createElement('tr');
                        
                        // Format date
                        const batchDate = new Date(batch.date_mouvement);
                        const formattedDate = batchDate.toLocaleDateString('fr-FR') + 
                            ' ' + batchDate.toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'});
                        
                        // Calculate remaining value
                        const remainingValue = batch.quantite_restante * batch.prix_unitaire;
                        totalRemaining += parseFloat(batch.quantite_restante);
                        totalValue += remainingValue;
                        
                        // Create table row
                        row.innerHTML = `
                            <td>${formattedDate}</td>
                            <td>${parseInt(batch.prix_unitaire).toLocaleString('fr-FR')} F</td>
                            <td>${parseFloat(batch.quantite_initiale).toLocaleString('fr-FR')} ${data.product.unite_mesure}</td>
                            <td>${parseFloat(batch.quantite_restante).toLocaleString('fr-FR')} ${data.product.unite_mesure}</td>
                            <td>${parseInt(remainingValue).toLocaleString('fr-FR')} F</td>
                            <td>${batch.reference}</td>
                        `;
                        
                        tableBody.appendChild(row);
                    });
                    
                    // Update totals
                    document.getElementById('totalRemainingQuantity').textContent = 
                        totalRemaining.toLocaleString('fr-FR') + ' ' + data.product.unite_mesure;
                    document.getElementById('totalBatchValue').textContent = 
                        parseInt(totalValue).toLocaleString('fr-FR') + ' F';
                    
                    // Show table
                    document.getElementById('batchesTableContainer').classList.remove('d-none');
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    document.getElementById('batchesLoadingSpinner').classList.add('d-none');
                    document.getElementById('batchesErrorMessage').textContent = error.message;
                    document.getElementById('batchesErrorMessage').classList.remove('d-none');
                });
        }
        
        // Handle Adjust Stock Modal with navigation and AJAX
        const adjustStockModal = document.getElementById('adjustStockModal');
        const adjustForm = document.getElementById('adjustStockForm');
        const prevBtn = document.getElementById('adjustPrev');
        const nextBtn = document.getElementById('adjustNext');
        const adjustButtons = Array.from(document.querySelectorAll('[data-bs-target="#adjustStockModal"]'));
        adjustButtons.forEach((btn, idx) => btn.dataset.index = idx);
        let currentAdjustIndex = -1;

        function populateAdjustModal(button) {
            if (!button) return;
            const id = button.getAttribute('data-id');
            const nom = button.getAttribute('data-nom');
            const code = button.getAttribute('data-code');
            const unite = button.getAttribute('data-unite');
            const quantite = button.getAttribute('data-quantite');

            document.getElementById('adjust_produit_id').value = id;
            document.getElementById('adjust_nom_produit').textContent = nom;
            document.getElementById('adjust_code_produit').textContent = code;
            document.getElementById('adjust_stock_actuel').textContent = quantite + ' ' + unite;
            document.getElementById('adjust_unite').textContent = unite;
            document.getElementById('adjust_quantite').value = '';
            document.getElementById('adjust_note').value = '';
        }

        if (adjustStockModal) {
            adjustStockModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                currentAdjustIndex = parseInt(button.dataset.index);
                populateAdjustModal(button);
            });

            if (prevBtn) {
                prevBtn.addEventListener('click', function () {
                    if (!adjustButtons.length) return;
                    currentAdjustIndex = (currentAdjustIndex - 1 + adjustButtons.length) % adjustButtons.length;
                    populateAdjustModal(adjustButtons[currentAdjustIndex]);
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    if (!adjustButtons.length) return;
                    currentAdjustIndex = (currentAdjustIndex + 1) % adjustButtons.length;
                    populateAdjustModal(adjustButtons[currentAdjustIndex]);
                });
            }

            document.addEventListener('keydown', function (e) {
                if (!adjustStockModal.classList.contains('show')) return;
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    if (prevBtn) prevBtn.click();
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    if (nextBtn) nextBtn.click();
                }
            });

            if (adjustForm) {
                adjustForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(adjustForm);
                    fetch('../../ajax/adjust_stock.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (!data.success) {
                                alert(data.message || 'Erreur');
                                return;
                            }

                            const id = formData.get('produit_id');
                            document.getElementById('adjust_stock_actuel').textContent = data.new_quantity_formatted + ' ' + data.unite;

                            document.querySelectorAll('[data-bs-target="#adjustStockModal"][data-id="' + id + '"]').forEach(btn => {
                                btn.setAttribute('data-quantite', data.new_quantity_formatted);
                            });

                            const qtyCell = document.getElementById('qty-' + id);
                            if (qtyCell) qtyCell.textContent = data.new_quantity_formatted;
                            const valCell = document.getElementById('valeur-' + id);
                            if (valCell) valCell.textContent = data.new_valeur_formatted + ' F';
                            const statusCell = document.getElementById('status-' + id);
                            if (statusCell) statusCell.innerHTML = data.status_badge;
                        })
                        .catch(err => console.error(err));
                });
            }
        }
        
        // Handle Delete Product Modal
        const deleteProductModal = document.getElementById('deleteProductModal');
        if (deleteProductModal) {
            deleteProductModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                
                // Extract data
                const id = button.getAttribute('data-id');
                const nom = button.getAttribute('data-nom');
                const code = button.getAttribute('data-code');
                
                // Set values in the form
                document.getElementById('delete_produit_id').value = id;
                document.getElementById('delete_product_name').textContent = nom;
                document.getElementById('delete_product_code').textContent = 'Code: ' + code;
            });
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('inventorySearch');
        const table = document.querySelector('table.table');
        const mobileContainer = document.querySelector('.d-md-none');
        let sortState = JSON.parse(localStorage.getItem('inventorySort')) || { field: 'code', asc: true };

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                filterProducts(this.value.trim().toLowerCase());
            });
        }

        const sortCodeBtn = document.getElementById('sortCode');
        const sortNomBtn = document.getElementById('sortNom');
        if (sortCodeBtn) sortCodeBtn.addEventListener('click', () => sortProducts('code'));
        if (sortNomBtn) sortNomBtn.addEventListener('click', () => sortProducts('nom'));

        applySort();

        function filterProducts(keyword) {
            if (!table) return;
            table.querySelectorAll('tbody tr').forEach(row => {
                const code = row.children[0].textContent.toLowerCase();
                const nom = row.children[1].textContent.toLowerCase();
                row.style.display = code.includes(keyword) || nom.includes(keyword) ? '' : 'none';
            });

            if (mobileContainer) {
                mobileContainer.querySelectorAll('.product-card').forEach(card => {
                    const code = card.querySelector('.small strong').textContent.toLowerCase();
                    const nom = card.querySelector('h5').textContent.toLowerCase();
                    card.style.display = code.includes(keyword) || nom.includes(keyword) ? '' : 'none';
                });
            }
        }

        function sortProducts(field) {
            if (!table) return;
            const asc = sortState.field === field ? !sortState.asc : true;
            sortState = { field, asc };
            localStorage.setItem('inventorySort', JSON.stringify(sortState));
            applySort();
        }

        function applySort() {
            if (!table) return;
            const field = sortState.field;
            const modifier = sortState.asc ? 1 : -1;

            const rows = Array.from(table.querySelectorAll('tbody tr'));
            rows.sort((a, b) => {
                const aText = field === 'code' ? a.children[0].textContent.trim().toLowerCase() : a.children[1].textContent.trim().toLowerCase();
                const bText = field === 'code' ? b.children[0].textContent.trim().toLowerCase() : b.children[1].textContent.trim().toLowerCase();
                if (aText < bText) return -1 * modifier;
                if (aText > bText) return 1 * modifier;
                return 0;
            });
            const tbody = table.querySelector('tbody');
            rows.forEach(r => tbody.appendChild(r));

            if (mobileContainer) {
                const cards = Array.from(mobileContainer.querySelectorAll('.product-card'));
                cards.sort((a, b) => {
                    const aText = field === 'code' ? a.querySelector('.small strong').textContent.trim().toLowerCase() : a.querySelector('h5').textContent.trim().toLowerCase();
                    const bText = field === 'code' ? b.querySelector('.small strong').textContent.trim().toLowerCase() : b.querySelector('h5').textContent.trim().toLowerCase();
                    if (aText < bText) return -1 * modifier;
                    if (aText > bText) return 1 * modifier;
                    return 0;
                });
                cards.forEach(c => mobileContainer.appendChild(c));
            }

            updateIndicators();
        }

        function updateIndicators() {
            document.querySelectorAll('.sort-indicator').forEach(el => el.textContent = '');
            const target = sortState.field === 'code' ? sortCodeBtn : sortNomBtn;
            if (target) target.querySelector('.sort-indicator').textContent = sortState.asc ? '▲' : '▼';
        }
    });
</script>
