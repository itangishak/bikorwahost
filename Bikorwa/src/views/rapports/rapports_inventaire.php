<?php
// Page de Suivi des Stocks
$page_title = "Suivi des Stocks";
$active_page = "rapports";

require_once __DIR__.'/../../../src/config/config.php';
require_once __DIR__.'/../../../src/config/database.php';
require_once __DIR__.'/../../../src/utils/Auth.php';
require_once __DIR__.'/../../../src/utils/access_control.php';
require_gestionnaire_access();

// Vérification des permissions
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'gestionnaire') {
    header('Location: ' . BASE_URL . '/src/views/auth/login.php');
    exit;
}

// Connexion à la base
$database = new Database();
$pdo = $database->getConnection();

// Récupération des données
$low_stock = [];
$stock_value = 0;
$category_stats = [];

try {
    // Calcul de la valeur totale avec méthode FIFO
    // 1. Valeur totale du stock (méthode FIFO)
    $value_query = "WITH mouvements_restants AS (
        SELECT 
            m.produit_id,
            m.quantite as quantite_initiale,
            m.prix_unitaire,
            m.valeur_totale,
            m.date_mouvement,
            (
                SELECT COALESCE(SUM(m2.quantite), 0)
                FROM mouvements_stock m2
                WHERE m2.produit_id = m.produit_id
                AND m2.type_mouvement = 'sortie'
                AND m2.date_mouvement > m.date_mouvement
            ) as quantite_vendue
        FROM mouvements_stock m
        WHERE m.type_mouvement = 'entree'
    )
    SELECT 
        SUM(
            CASE 
                WHEN quantite_initiale > quantite_vendue THEN
                    (quantite_initiale - quantite_vendue) * prix_unitaire
                ELSE 0
            END
        ) as valeur_totale
    FROM mouvements_restants";
    
    $stock_value = $pdo->query($value_query)->fetch(PDO::FETCH_ASSOC)['valeur_totale'] ?? 0;
    
    // 2. Stock faible (moins de 5 unités)
    $low_stock_query = "SELECT p.nom, s.quantite 
                        FROM stock s 
                        JOIN produits p ON s.produit_id = p.id 
                        WHERE s.quantite < 5 AND p.actif = 1";
    $low_stock = $pdo->query($low_stock_query)->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Stats par catégorie
    $category_query = "SELECT 
                        c.nom as categorie, 
                        COUNT(p.id) as nb_produits,
                        SUM(s.quantite) as total_stock
                    FROM categories c
                    LEFT JOIN produits p ON c.id = p.categorie_id
                    LEFT JOIN stock s ON p.id = s.produit_id
                    GROUP BY c.nom";
    $category_stats = $pdo->query($category_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur base de données: " . $e->getMessage();
}

// Inclusion du header
require_once __DIR__.'/../../../src/views/layouts/header.php';
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<main class="main" id="main">
    <div class="pagetitle">
        <h1><?= $page_title ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard/index.php">Accueil</a></li>
                <li class="breadcrumb-item">Rapports</li>
                <li class="breadcrumb-item active">Suivi des Stocks</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <!-- Cartes indicateurs -->
            <div class="col-lg-4">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Valeur Totale</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= number_format($stock_value, 0, ',', ' ') ?> BIF</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">Produits en Stock Faible</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= count($low_stock) ?></h6>
                                <span class="text-danger small pt-1 fw-bold">À réapprovisionner</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card info-card customers-card">
                    <div class="card-body">
                        <h5 class="card-title">Catégories</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-tags"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= count($category_stats) ?></h6>
                                <span class="text-muted small pt-1 fw-bold">Catégories actives</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Stock Faible -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Alertes Stock Faible</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th>Quantité</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($low_stock as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['nom']) ?></td>
                                        <td><?= $item['quantite'] ?></td>
                                        <td>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-exclamation-circle me-1"></i> Critique
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Catégories -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Répartition par Catégorie</h5>
                        <div id="pieChart" style="min-height: 300px;"></div>
                    </div>
                </div>
            </div>

            <!-- Section Lots disponibles par type de prix -->
            <div class="col-lg-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="form-group position-relative">
                            <label for="product-search">Rechercher un produit (tapez 2 lettres minimum) :</label>
                            <input type="text" class="form-control" id="product-search" 
                                   placeholder="Nom du produit... Cliquez sur un résultat pour voir les détails" 
                                   autocomplete="off">
                            <small class="form-text text-muted">Les lots disponibles s'afficheront après sélection</small>
                            <div id="search-suggestions" class="list-group position-absolute d-none" style="z-index: 1000; width: 100%; max-height: 200px; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>

                <div id="search-results"></div>

                <script>
                $(document).ready(function() {
                    const searchInput = $('#product-search');
                    const suggestionsContainer = $('#search-suggestions');
                    const resultsContainer = $('#search-results');
                    
                    // Gestion de la recherche
                    searchInput.on('input', debounce(function() {
                        const searchTerm = $(this).val().trim();
                        if (searchTerm.length < 2) {
                            resultsContainer.empty();
                            return;
                        }
                        
                        fetchProducts(searchTerm);
                    }, 300));
                    
                    // Fonction pour récupérer les produits
                    function fetchProducts(searchTerm) {
                        console.log('Recherche en cours pour:', searchTerm);
                        
                        $.ajax({
                            url: '<?= BASE_URL ?>/src/views/rapports/api/get_product_prices.php',
                            type: 'GET',
                            data: { search: searchTerm },
                            dataType: 'json',
                            beforeSend: function() {
                                console.log('Requête AJAX envoyée');
                                resultsContainer.html('<div class="text-center"><div class="spinner-border" role="status"></div></div>');
                            },
                            success: function(response, status, xhr) {
                                console.log('Réponse reçue:', response);
                                console.log('Status:', status);
                                console.log('Content-Type:', xhr.getResponseHeader('Content-Type'));
                                
                                if (response.success && response.data.length > 0) {
                                    renderProducts(response.data);
                                } else {
                                    resultsContainer.html('<div class="alert alert-info">Aucun produit trouvé</div>');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Erreur AJAX:', status, error);
                                console.error('Réponse serveur:', xhr.responseText);
                                const errorMsg = xhr.responseJSON?.error || 'Erreur lors de la recherche';
                                resultsContainer.html(`<div class="alert alert-danger">${errorMsg}</div>`);
                            }
                        });
                    }
                    
                    // Fonction pour afficher les résultats
                    function renderProducts(data) {
                        // Créer la liste des produits
                        let productsList = '<div class="list-group mb-3" id="products-list">';
                        let productsData = {};
                        
                        // Première passe pour extraire les noms de produits
                        data.forEach(item => {
                            if (item.type === 'start-product') {
                                productsData[item.product] = [];
                                productsList += `
                                    <a href="#" class="list-group-item list-group-item-action" 
                                       data-product="${encodeURIComponent(item.product)}">
                                        ${item.product}
                                    </a>`;
                            }
                            else if (item.type === 'price' && item.product) {
                                productsData[item.product].push(item);
                            }
                        });
                        
                        productsList += '</div>';
                        resultsContainer.html(productsList);
                        
                        // Gestion du clic sur un produit
                        $('#products-list a').on('click', function(e) {
                            e.preventDefault();
                            const productName = decodeURIComponent($(this).data('product'));
                            showProductDetails(productName, productsData[productName]);
                        });
                    }
                    
                    // Nouvelle fonction pour afficher les détails d'un produit
                    function showProductDetails(productName, prices) {
                        let html = `
                        <div class="card mb-3" id="product-details">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">${productName}</h5>
                                <button class="btn btn-sm btn-outline-secondary close-product-details">
                                    <i class="fas fa-times"></i> Fermer
                                </button>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Prix Unitaire</th>
                                            <th>Quantité</th>
                                            <th>Période</th>
                                            <th>Nb Achats</th>
                                            <th>Valeur Totale</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                        
                        prices.forEach(price => {
                            html += `
                                            <tr>
                                                <td>${price.price} BIF</td>
                                                <td>${price.quantity}</td>
                                                <td>${price.period}</td>
                                                <td>${price.purchases}</td>
                                                <td>${price.total} BIF</td>
                                            </tr>`;
                        });
                        
                        html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>`;
                        
                        // Ajouter ou remplacer les détails
                        $('#product-details').remove();
                        resultsContainer.append(html);
                        
                        // Ajouter l'événement de fermeture
                        $('.close-product-details').on('click', function(e) {
                            e.preventDefault();
                            $('#product-details').remove();
                        });
                    }
                    
                    // Fonction debounce pour limiter les requêtes
                    function debounce(func, wait) {
                        let timeout;
                        return function() {
                            const context = this, args = arguments;
                            clearTimeout(timeout);
                            timeout = setTimeout(() => func.apply(context, args), wait);
                        };
                    }
                });
                </script>
            </div>
        </div>
    </section>
</main>

<?php 
// Inclusion du footer
require_once __DIR__.'/../../../src/views/layouts/footer.php';
?>

<!-- Scripts pour les graphiques -->
<script>
// Graphique circulaire (à implémenter)
document.addEventListener('DOMContentLoaded', function() {
    // Ici viendra le code pour initialiser les graphiques
});
</script>
