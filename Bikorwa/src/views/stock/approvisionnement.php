<?php
// Stock approvisionnement page for BIKORWA SHOP
$page_title = "Nouvel Approvisionnement";
$active_page = "stock";
?>

<div class="row mb-4">
    <div class="col-md-12">
        <a href="/stock/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Retour à la liste
        </a>
    </div>
</div>

<div class="card dashboard-card mb-4">
    <div class="card-header bg-primary text-white">
        <h6 class="m-0 font-weight-bold">Enregistrer un nouvel approvisionnement</h6>
    </div>
    <div class="card-body">
        <form id="approForm" action="/stock/save_approvisionnement.php" method="post">
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="produit_id" class="form-label">Produit</label>
                        <select class="form-select" id="produit_id" name="produit_id" required onchange="updateProduitInfo()">
                            <option value="">Sélectionner un produit</option>
                            <?php foreach ($produits as $produit): ?>
                                <option value="<?php echo $produit['id']; ?>" 
                                        data-prix-achat="<?php echo $produit['prix_achat_actuel']; ?>"
                                        data-unite="<?php echo $produit['unite_mesure']; ?>"
                                        data-stock="<?php echo $produit['quantite_stock']; ?>"
                                        <?php echo (isset($produit_id) && $produit_id == $produit['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($produit['nom']); ?> 
                                    (Stock actuel: <?php echo $produit['quantite_stock']; ?> <?php echo $produit['unite_mesure']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="date_mouvement" class="form-label">Date d'approvisionnement</label>
                        <input type="datetime-local" class="form-control" id="date_mouvement" name="date_mouvement" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="quantite" class="form-label">Quantité</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="quantite" name="quantite" min="1" value="1" required onchange="updateValeurTotale()">
                            <span class="input-group-text" id="unite-mesure">unité</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="prix_unitaire" class="form-label">Prix d'achat unitaire</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="prix_unitaire" name="prix_unitaire" min="0" value="0" required onchange="updateValeurTotale()">
                            <span class="input-group-text">F</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="valeur_totale" class="form-label">Valeur totale</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="valeur_totale" name="valeur_totale" readonly value="0">
                            <span class="input-group-text">F</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="reference" class="form-label">Référence (Bon de livraison, facture, etc.)</label>
                        <input type="text" class="form-control" id="reference" name="reference">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="note" class="form-label">Note (optionnel)</label>
                        <textarea class="form-control" id="note" name="note" rows="3"></textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h6 class="m-0 font-weight-bold">Informations sur le stock</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Stock actuel</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="stock_actuel" readonly value="0">
                                    <span class="input-group-text" id="unite-mesure2">unité</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Nouveau stock</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="nouveau_stock" readonly value="0">
                                    <span class="input-group-text" id="unite-mesure3">unité</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Prix d'achat précédent</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="prix_precedent" readonly value="0">
                                    <span class="input-group-text">F</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="button" class="btn btn-secondary me-md-2" onclick="window.location.href='/stock/index.php'">Annuler</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>Enregistrer l'approvisionnement
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Update product information when product is selected
    function updateProduitInfo() {
        const select = document.getElementById('produit_id');
        const prixInput = document.getElementById('prix_unitaire');
        const stockActuel = document.getElementById('stock_actuel');
        const prixPrecedent = document.getElementById('prix_precedent');
        const uniteMesure = document.getElementById('unite-mesure');
        const uniteMesure2 = document.getElementById('unite-mesure2');
        const uniteMesure3 = document.getElementById('unite-mesure3');
        
        if (select.selectedIndex > 0) {
            const option = select.options[select.selectedIndex];
            const prixAchat = option.getAttribute('data-prix-achat');
            const stock = option.getAttribute('data-stock');
            const unite = option.getAttribute('data-unite');
            
            prixInput.value = prixAchat;
            stockActuel.value = stock;
            prixPrecedent.value = prixAchat;
            uniteMesure.textContent = unite;
            uniteMesure2.textContent = unite;
            uniteMesure3.textContent = unite;
            
            updateValeurTotale();
        } else {
            prixInput.value = 0;
            stockActuel.value = 0;
            prixPrecedent.value = 0;
            uniteMesure.textContent = 'unité';
            uniteMesure2.textContent = 'unité';
            uniteMesure3.textContent = 'unité';
            
            updateValeurTotale();
        }
    }
    
    // Update total value and new stock
    function updateValeurTotale() {
        const quantite = parseInt(document.getElementById('quantite').value) || 0;
        const prix = parseFloat(document.getElementById('prix_unitaire').value) || 0;
        const stockActuel = parseInt(document.getElementById('stock_actuel').value) || 0;
        
        const valeurTotale = quantite * prix;
        document.getElementById('valeur_totale').value = valeurTotale.toLocaleString('fr-FR');
        
        const nouveauStock = stockActuel + quantite;
        document.getElementById('nouveau_stock').value = nouveauStock;
    }
    
    // Form validation
    document.getElementById('approForm').addEventListener('submit', function(e) {
        const produitSelect = document.getElementById('produit_id');
        const quantite = parseInt(document.getElementById('quantite').value) || 0;
        const prix = parseFloat(document.getElementById('prix_unitaire').value) || 0;
        
        if (produitSelect.selectedIndex === 0) {
            e.preventDefault();
            alert('Veuillez sélectionner un produit.');
            return;
        }
        
        if (quantite <= 0) {
            e.preventDefault();
            alert('La quantité doit être supérieure à zéro.');
            return;
        }
        
        if (prix <= 0) {
            e.preventDefault();
            alert('Le prix unitaire doit être supérieur à zéro.');
            return;
        }
    });
    
    // Initialize product info if a product is pre-selected
    document.addEventListener('DOMContentLoaded', function() {
        updateProduitInfo();
    });
</script>
