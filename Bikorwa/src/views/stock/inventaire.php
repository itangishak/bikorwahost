<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and config
require_once('../../config/database.php');
require_once('../../config/config.php');
require_once ('../../utils/ProductCodeGenerator.php'); 

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../auth/login.php');
    exit;
}

// Initialize database connection
$db = new Database();
$pdo = $db->getConnection();

// Set page information
$page_title = "Inventaire";
$active_page = "stock";

// Determine user roles and stock privileges. Receptionnistes may manage
// inventory but cannot delete products.
$isGestionnaire = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'gestionnaire';
$isReceptionniste = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'receptionniste';
$hasStockAccess = $isGestionnaire || $isReceptionniste;

// Process form actions
$message = "";
$messageType = "";

// Handle product operations (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle based on the action
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'add_product') {
            // Check if user has stock management rights (gestionnaire or réceptionniste)
            if (!$hasStockAccess) {
                throw new Exception("Vous n'avez pas les droits pour ajouter un produit.");
            }
            
            // Validate required fields
            $code= generateProductCode($pdo); 
            $nom = trim($_POST['nom']);
            $categorie_id = intval($_POST['categorie_id']);
            $unite_mesure = trim($_POST['unite_mesure']);
            $prix_achat = floatval(str_replace(',', '.', $_POST['prix_achat']));
            $prix_vente = floatval(str_replace(',', '.', $_POST['prix_vente']));
            $quantite = floatval(str_replace(',', '.', $_POST['quantite']));
            $description = trim($_POST['description'] ?? '');
            
            if (empty($code) || empty($nom) || empty($unite_mesure) || $prix_achat <= 0 || $prix_vente <= 0) {
                throw new Exception("Tous les champs obligatoires doivent être remplis et les prix doivent être supérieurs à zéro.");
            }
            
            // Check if code already exists
            $stmt = $pdo->prepare("SELECT id FROM produits WHERE code = :code");
            $stmt->execute(['code' => $code]);
            if ($stmt->rowCount() > 0) {
                throw new Exception("Un produit avec ce code existe déjà.");
            }

            // Check if product name already exists
            $stmt = $pdo->prepare("SELECT id FROM produits WHERE LOWER(nom) = LOWER(:nom)");
            $stmt->execute(['nom' => $nom]);
            if ($stmt->rowCount() > 0) {
                throw new Exception("Un produit portant ce nom existe déjà.");
            }
            
            // Insert product
            $stmt = $pdo->prepare("
                INSERT INTO produits (code, nom, description, categorie_id, unite_mesure)
                VALUES (:code, :nom, :description, :categorie_id, :unite_mesure)
            ");
            $stmt->execute([
                'code' => $code,
                'nom' => $nom,
                'description' => $description,
                'categorie_id' => $categorie_id ?: null,
                'unite_mesure' => $unite_mesure
            ]);
            $produit_id = $pdo->lastInsertId();
            
            // Insert price
            $stmt = $pdo->prepare("
                INSERT INTO prix_produits (produit_id, prix_achat, prix_vente, cree_par)
                VALUES (:produit_id, :prix_achat, :prix_vente, :cree_par)
            ");
            $stmt->execute([
                'produit_id' => $produit_id,
                'prix_achat' => $prix_achat,
                'prix_vente' => $prix_vente,
                'cree_par' => $_SESSION['user_id']
            ]);
            
            // Insert initial stock
            if ($quantite > 0) {
                // Create stock record
                $stmt = $pdo->prepare("
                    INSERT INTO stock (produit_id, quantite)
                    VALUES (:produit_id, :quantite)
                ");
                $stmt->execute([
                    'produit_id' => $produit_id,
                    'quantite' => $quantite
                ]);
                
                // Record stock movement
                $stmt = $pdo->prepare("
                    INSERT INTO mouvements_stock (produit_id, type_mouvement, quantite, prix_unitaire, valeur_totale, utilisateur_id, note, reference)
                    VALUES (:produit_id, 'entree', :quantite, :prix_unitaire, :valeur_totale, :utilisateur_id, :note, :reference)
                ");
                $stmt->execute([
                    'produit_id' => $produit_id,
                    'quantite' => $quantite,
                    'prix_unitaire' => $prix_achat,
                    'valeur_totale' => $prix_achat * $quantite,
                    'utilisateur_id' => $_SESSION['user_id'],
                    'note' => 'Stock initial',
                    'reference' => 'INIT-' . date('YmdHis')
                ]);
            } else {
                // Create empty stock record
                $stmt = $pdo->prepare("
                    INSERT INTO stock (produit_id, quantite)
                    VALUES (:produit_id, 0)
                ");
                $stmt->execute([
                    'produit_id' => $produit_id
                ]);
            }
            
            // Log activity
            $stmt = $pdo->prepare("
                INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
                VALUES (:utilisateur_id, 'create', 'produit', :entite_id, :details)
            ");
            $stmt->execute([
                'utilisateur_id' => $_SESSION['user_id'],
                'entite_id' => $produit_id,
                'details' => "Nouveau produit ajouté: {$nom} (Code: {$code})"
            ]);
            
            $message = "Produit ajouté avec succès.";
            $messageType = "success";
        }
        else if ($action === 'edit_product') {
            // Check if user has stock management rights (gestionnaire or réceptionniste)
            if (!$hasStockAccess) {
                throw new Exception("Vous n'avez pas les droits pour modifier un produit.");
            }
            
            $produit_id = intval($_POST['produit_id']);
            $code = trim($_POST['code']);
            $nom = trim($_POST['nom']);
            $categorie_id = intval($_POST['categorie_id']);
            $unite_mesure = trim($_POST['unite_mesure']);
            $prix_achat = floatval(str_replace(',', '.', $_POST['prix_achat']));
            $prix_vente = floatval(str_replace(',', '.', $_POST['prix_vente']));
            $description = trim($_POST['description'] ?? '');
            $actif = isset($_POST['actif']) ? 1 : 0;
            
            if (empty($code) || empty($nom) || empty($unite_mesure) || $prix_achat <= 0 || $prix_vente <= 0) {
                throw new Exception("Tous les champs obligatoires doivent être remplis et les prix doivent être supérieurs à zéro.");
            }
            
            // Check if code already exists for another product
            $stmt = $pdo->prepare("SELECT id FROM produits WHERE code = :code AND id != :id");
            $stmt->execute(['code' => $code, 'id' => $produit_id]);
            if ($stmt->rowCount() > 0) {
                throw new Exception("Un autre produit avec ce code existe déjà.");
            }

            // Check if product name already exists for another product
            $stmt = $pdo->prepare("SELECT id FROM produits WHERE LOWER(nom) = LOWER(:nom) AND id != :id");
            $stmt->execute(['nom' => $nom, 'id' => $produit_id]);
            if ($stmt->rowCount() > 0) {
                throw new Exception("Un autre produit portant ce nom existe déjà.");
            }
            
            // Update product
            $stmt = $pdo->prepare("
                UPDATE produits 
                SET code = :code, nom = :nom, description = :description, 
                    categorie_id = :categorie_id, unite_mesure = :unite_mesure,
                    actif = :actif
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $produit_id,
                'code' => $code,
                'nom' => $nom,
                'description' => $description,
                'categorie_id' => $categorie_id ?: null,
                'unite_mesure' => $unite_mesure,
                'actif' => $actif
            ]);
            
            // Check if price has changed
            $stmt = $pdo->prepare("
                SELECT prix_achat, prix_vente 
                FROM prix_produits 
                WHERE produit_id = :produit_id 
                AND date_fin IS NULL
            ");
            $stmt->execute(['produit_id' => $produit_id]);
            $currentPrice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($currentPrice && 
                (abs($currentPrice['prix_achat'] - $prix_achat) > 0.01 || 
                 abs($currentPrice['prix_vente'] - $prix_vente) > 0.01)) {
                
                // Close the current price period
                $stmt = $pdo->prepare("
                    UPDATE prix_produits 
                    SET date_fin = NOW() 
                    WHERE produit_id = :produit_id AND date_fin IS NULL
                ");
                $stmt->execute(['produit_id' => $produit_id]);
                
                // Add new price entry
                $stmt = $pdo->prepare("
                    INSERT INTO prix_produits (produit_id, prix_achat, prix_vente, cree_par)
                    VALUES (:produit_id, :prix_achat, :prix_vente, :cree_par)
                ");
                $stmt->execute([
                    'produit_id' => $produit_id,
                    'prix_achat' => $prix_achat,
                    'prix_vente' => $prix_vente,
                    'cree_par' => $_SESSION['user_id']
                ]);
            }
            
            // Log activity
            $stmt = $pdo->prepare("
                INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
                VALUES (:utilisateur_id, 'update', 'produit', :entite_id, :details)
            ");
            $stmt->execute([
                'utilisateur_id' => $_SESSION['user_id'],
                'entite_id' => $produit_id,
                'details' => "Produit modifié: {$nom} (Code: {$code})"
            ]);
            
            $message = "Produit mis à jour avec succès.";
            $messageType = "success";
        }
        else if ($action === 'delete_product') {
            // Only gestionnaires are allowed to delete products
            if (!$isGestionnaire) {
                throw new Exception("Vous n'avez pas les droits pour supprimer un produit.");
            }
            
            $produit_id = intval($_POST['produit_id']);
            
            // Get product info before deletion
            $stmt = $pdo->prepare("SELECT nom, code FROM produits WHERE id = :id");
            $stmt->execute(['id' => $produit_id]);
            $produit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$produit) {
                throw new Exception("Produit introuvable.");
            }
            
            // Check if product is used in sales
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM details_ventes WHERE produit_id = :produit_id
            ");
            $stmt->execute(['produit_id' => $produit_id]);
            $salesCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($salesCount > 0) {
                // Don't delete, just deactivate
                $stmt = $pdo->prepare("UPDATE produits SET actif = 0 WHERE id = :id");
                $stmt->execute(['id' => $produit_id]);
                
                $message = "Le produit a été désactivé car il est utilisé dans des ventes.";
                $messageType = "warning";
            } else {
                // Delete price history
                $stmt = $pdo->prepare("DELETE FROM prix_produits WHERE produit_id = :id");
                $stmt->execute(['id' => $produit_id]);
                
                // Delete stock movements
                $stmt = $pdo->prepare("DELETE FROM mouvements_stock WHERE produit_id = :id");
                $stmt->execute(['id' => $produit_id]);
                
                // Delete stock
                $stmt = $pdo->prepare("DELETE FROM stock WHERE produit_id = :id");
                $stmt->execute(['id' => $produit_id]);
                
                // Delete product
                $stmt = $pdo->prepare("DELETE FROM produits WHERE id = :id");
                $stmt->execute(['id' => $produit_id]);
                
                $message = "Produit supprimé avec succès.";
                $messageType = "success";
            }
            
            // Log activity
            $stmt = $pdo->prepare("
                INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
                VALUES (:utilisateur_id, 'delete', 'produit', :entite_id, :details)
            ");
            $stmt->execute([
                'utilisateur_id' => $_SESSION['user_id'],
                'entite_id' => $produit_id,
                'details' => "Produit supprimé/désactivé: {$produit['nom']} (Code: {$produit['code']})"
            ]);
        }
        else if ($action === 'adjust_stock') {
            // Check if user has stock management rights (gestionnaire or réceptionniste)
            if (!$hasStockAccess) {
                throw new Exception("Vous n'avez pas les droits pour ajuster le stock.");
            }
            
            $produit_id = intval($_POST['produit_id']);
            $type_mouvement = $_POST['type_mouvement'];
            $quantite = floatval(str_replace(',', '.', $_POST['quantite']));
            $note = trim($_POST['note'] ?? '');
            
            if ($quantite <= 0) {
                throw new Exception("La quantité doit être supérieure à zéro.");
            }
            
            if ($type_mouvement !== 'entree' && $type_mouvement !== 'sortie') {
                throw new Exception("Type de mouvement invalide.");
            }

            // Receptionnistes are not allowed to perform stock removals
            if (!$isGestionnaire && $type_mouvement === 'sortie') {
                throw new Exception("Seul le gestionnaire peut effectuer une sortie de stock.");
            }
            
            // Get current product info
            $stmt = $pdo->prepare("
                SELECT p.nom, p.code, s.quantite, pp.prix_achat 
                FROM produits p
                LEFT JOIN stock s ON p.id = s.produit_id
                LEFT JOIN (
                    SELECT produit_id, prix_achat
                    FROM prix_produits
                    WHERE date_fin IS NULL
                    GROUP BY produit_id
                ) pp ON p.id = pp.produit_id
                WHERE p.id = :id
            ");
            $stmt->execute(['id' => $produit_id]);
            $produit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$produit) {
                throw new Exception("Produit introuvable.");
            }
            
            // Check if we have enough stock for removal
            if ($type_mouvement === 'sortie' && $produit['quantite'] < $quantite) {
                throw new Exception("Stock insuffisant. Quantité actuelle: {$produit['quantite']}");
            }
            
            // Update stock
            $new_quantity = ($type_mouvement === 'entree') 
                ? $produit['quantite'] + $quantite 
                : $produit['quantite'] - $quantite;
            
            $stmt = $pdo->prepare("
                UPDATE stock 
                SET quantite = :quantite, date_mise_a_jour = NOW()
                WHERE produit_id = :produit_id
            ");
            $stmt->execute([
                'produit_id' => $produit_id,
                'quantite' => $new_quantity
            ]);
            
            // Record stock movement
            $reference = $type_mouvement === 'entree' ? 'AJOUT-' : 'RETRAIT-';
            $reference .= date('YmdHis');
            
            $stmt = $pdo->prepare("
                INSERT INTO mouvements_stock (
                    produit_id, type_mouvement, quantite, prix_unitaire, 
                    valeur_totale, utilisateur_id, note, reference
                )
                VALUES (
                    :produit_id, :type_mouvement, :quantite, :prix_unitaire, 
                    :valeur_totale, :utilisateur_id, :note, :reference
                )
            ");
            $stmt->execute([
                'produit_id' => $produit_id,
                'type_mouvement' => $type_mouvement,
                'quantite' => $quantite,
                'prix_unitaire' => $produit['prix_achat'],
                'valeur_totale' => $produit['prix_achat'] * $quantite,
                'utilisateur_id' => $_SESSION['user_id'],
                'note' => $note,
                'reference' => $reference
            ]);
            
            // Log activity
            $stmt = $pdo->prepare("
                INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
                VALUES (:utilisateur_id, 'adjust', 'stock', :entite_id, :details)
            ");
            $stmt->execute([
                'utilisateur_id' => $_SESSION['user_id'],
                'entite_id' => $produit_id,
                'details' => "Ajustement de stock: {$produit['nom']} - " . 
                             ($type_mouvement === 'entree' ? "Ajout" : "Retrait") . 
                             " de {$quantite} {$produit['unite_mesure']}"
            ]);
            
            $message = "Stock ajusté avec succès.";
            $messageType = "success";
        }
        
        $pdo->commit();
    } catch (Exception $e) {
         if ($pdo->inTransaction()) {
        $pdo->rollBack();
       }
        $message = "Erreur: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Get all categories for dropdowns
try {
    $stmt = $pdo->query("SELECT id, nom FROM categories ORDER BY nom");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
    if (empty($message)) {
        $message = "Erreur lors du chargement des catégories: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Calcul de la valeur totale avec méthode FIFO
try {
    $fifo_value_query = "WITH mouvements_restants AS (
        SELECT 
            m.produit_id,
            p.nom as produit_nom,
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
        JOIN produits p ON m.produit_id = p.id
        WHERE m.type_mouvement = 'entree'
        AND p.actif = 1
    )
    SELECT 
        produit_id,
        produit_nom,
        SUM(
            CASE 
                WHEN quantite_initiale > quantite_vendue THEN
                    (quantite_initiale - quantite_vendue)
                ELSE 0
            END
        ) as quantite_restante,
        SUM(
            CASE 
                WHEN quantite_initiale > quantite_vendue THEN
                    (quantite_initiale - quantite_vendue) * prix_unitaire
                ELSE 0
            END
        ) as valeur_restante
    FROM mouvements_restants
    GROUP BY produit_id, produit_nom
    HAVING quantite_restante > 0
    ORDER BY produit_nom";
    
    $inventory_items = $pdo->query($fifo_value_query)->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcul de la valeur totale
    // Valeur totale du stock selon le prix d'achat (FIFO)
    $total_value_achat = array_sum(array_column($inventory_items, 'valeur_restante'));
    
} catch (PDOException $e) {
    $error = "Erreur de calcul FIFO: " . $e->getMessage();
}

// Get inventory with joined tables
try {
    $whereClause = "";
    $params = [];
    
    // Apply search filter if provided
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $whereClause = " AND (p.nom LIKE :search OR p.code LIKE :search)";
        $params['search'] = $search;
    }
    
    // Apply category filter if provided
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $category = intval($_GET['category']);
        $whereClause .= " AND p.categorie_id = :category";
        $params['category'] = $category;
    }
    
    // Apply stock filter if provided
    if (isset($_GET['stock']) && $_GET['stock'] !== '') {
        switch ($_GET['stock']) {
            case 'low':
                $whereClause .= " AND s.quantite > 0 AND s.quantite <= 10";
                break;
            case 'out':
                $whereClause .= " AND s.quantite = 0";
                break;
            case 'in':
                $whereClause .= " AND s.quantite > 10";
                break;
        }
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            p.id, p.code, p.nom, p.description, p.unite_mesure, p.actif,
            c.nom as categorie_nom,
            COALESCE(s.quantite, 0) as quantite_stock,
            s.date_mise_a_jour,
            pp.prix_achat, pp.prix_vente,
            COALESCE(s.quantite, 0) * pp.prix_achat as valeur_stock
        FROM produits p
        LEFT JOIN categories c ON p.categorie_id = c.id
        LEFT JOIN stock s ON p.id = s.produit_id
        LEFT JOIN (
            SELECT produit_id, prix_achat, prix_vente
            FROM prix_produits pp1
            WHERE (pp1.date_fin IS NULL OR pp1.date_fin = (
                SELECT MAX(date_fin) 
                FROM prix_produits pp2 
                WHERE pp2.produit_id = pp1.produit_id
            ))
            GROUP BY produit_id
        ) pp ON p.id = pp.produit_id
        WHERE 1=1 $whereClause
        ORDER BY p.nom
    ");
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $total_products = count($inventory);
    $out_of_stock = 0;
    $low_stock = 0;
    $total_value_vente = 0;

    foreach ($inventory as $product) {
        if ($product['quantite_stock'] == 0) {
            $out_of_stock++;
        } elseif ($product['quantite_stock'] <= 10) {
            $low_stock++;
        }
        $total_value_vente += $product['quantite_stock'] * $product['prix_vente'];
    }
} catch (PDOException $e) {
    $inventory = [];
    $total_products = 0;
    $total_value_achat = 0;
    $total_value_vente = 0;
    $out_of_stock = 0;
    $low_stock = 0;
    
    if (empty($message)) {
        $message = "Erreur lors du chargement de l'inventaire: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Include header
include('../layouts/header.php');
?>

<div class="container-fluid py-4">
    <!-- Page Title and Action Buttons -->
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0">Inventaire</h1>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <?php if ($hasStockAccess): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="fas fa-plus-circle me-1"></i> Nouveau produit
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Alert Messages -->
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <!-- Dashboard Stats -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                        <i class="fas fa-boxes fa-fw text-primary"></i>
                    </div>
                    <div>
                        <h6 class="card-subtitle text-muted mb-1">Produits</h6>
                        <h2 class="card-title mb-0"><?= $total_products ?></h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box rounded-circle bg-success bg-opacity-10 p-3 me-3">
                        <i class="fas fa-money-bill-wave fa-fw text-success"></i>
                    </div>
                    <div>
                        <h6 class="card-subtitle text-muted mb-1">Valeur du stock</h6>
                        <p class="mb-1 fw-semibold">
                            Achat: <?= number_format($total_value_achat, 0, ',', ' ') ?> F
                        </p>
                        <p class="mb-0 fw-semibold">
                            Vente: <?= number_format($total_value_vente, 0, ',', ' ') ?> F
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                        <i class="fas fa-exclamation-triangle fa-fw text-warning"></i>
                    </div>
                    <div>
                        <h6 class="card-subtitle text-muted mb-1">Stock bas</h6>
                        <h2 class="card-title mb-0"><?= $low_stock ?></h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                        <i class="fas fa-times-circle fa-fw text-danger"></i>
                    </div>
                    <div>
                        <h6 class="card-subtitle text-muted mb-1">Rupture de stock</h6>
                        <h2 class="card-title mb-0"><?= $out_of_stock ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search and Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" id="inventorySearch" class="form-control" placeholder="Rechercher un produit..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= isset($_GET['category']) && $_GET['category'] == $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="stock" class="form-select">
                        <option value="">Tous les niveaux de stock</option>
                        <option value="out" <?= isset($_GET['stock']) && $_GET['stock'] === 'out' ? 'selected' : '' ?>>Rupture de stock</option>
                        <option value="low" <?= isset($_GET['stock']) && $_GET['stock'] === 'low' ? 'selected' : '' ?>>Stock bas (≤ 10)</option>
                        <option value="in" <?= isset($_GET['stock']) && $_GET['stock'] === 'in' ? 'selected' : '' ?>>En stock (> 10)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Product Listing -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <!-- Mobile View Cards (shows on xs and sm screens) -->
            <div class="d-md-none">
                <?php if (empty($inventory)): ?>
                    <div class="p-4 text-center">
                        <p class="text-muted mb-0">Aucun produit trouvé.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($inventory as $product): ?>
                        <div class="product-card border-bottom p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="mb-0">
                                    <?= htmlspecialchars($product['nom']) ?>
                                    <?php if (!$product['actif']): ?>
                                        <span class="badge bg-secondary ms-1">Inactif</span>
                                    <?php endif; ?>
                                </h5>
                                <div>
                                    <?php if ($product['quantite_stock'] <= 0): ?>
                                        <span class="badge bg-danger">Rupture</span>
                                    <?php elseif ($product['quantite_stock'] <= 10): ?>
                                        <span class="badge bg-warning text-dark">Stock bas</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">En stock</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="small text-muted mb-2">
                                Code: <strong><?= htmlspecialchars($product['code']) ?></strong>
                                <?php if (!empty($product['categorie_nom'])): ?>
                                    | Catégorie: <strong><?= htmlspecialchars($product['categorie_nom']) ?></strong>
                                <?php endif; ?>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <div class="d-flex justify-content-between border rounded p-2">
                                        <span class="text-muted">Quantité:</span>
                                        <strong><?= number_format($product['quantite_stock'], 2, ',', ' ') ?> <?= htmlspecialchars($product['unite_mesure']) ?></strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex justify-content-between border rounded p-2">
                                        <span class="text-muted">Prix vente:</span>
                                        <strong><?= number_format($product['prix_vente'], 0, ',', ' ') ?> F</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                        data-bs-toggle="modal" data-bs-target="#viewProductModal" 
                                        data-id="<?= $product['id'] ?>"
                                        data-code="<?= htmlspecialchars($product['code']) ?>"
                                        data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                        data-description="<?= htmlspecialchars($product['description']) ?>"
                                        data-categorie="<?= htmlspecialchars($product['categorie_nom'] ?? 'Non catégorisé') ?>"
                                        data-unite="<?= htmlspecialchars($product['unite_mesure']) ?>"
                                        data-prix-achat="<?= number_format($product['prix_achat'], 0, ',', ' ') ?>"
                                        data-prix-vente="<?= number_format($product['prix_vente'], 0, ',', ' ') ?>"
                                        data-quantite="<?= number_format($product['quantite_stock'], 2, ',', ' ') ?>"
                                        data-valeur="<?= number_format($product['valeur_stock'], 0, ',', ' ') ?>"
                                        data-date="<?= (new DateTime($product['date_mise_a_jour']))->format('d/m/Y H:i') ?>"
                                        data-actif="<?= $product['actif'] ? 'Actif' : 'Inactif' ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <?php if ($hasStockAccess): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary me-1 adjust-stock-btn"
                                        data-bs-toggle="modal" data-bs-target="#adjustStockModal"
                                        data-id="<?= $product['id'] ?>"
                                        data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                        data-code="<?= htmlspecialchars($product['code']) ?>"
                                        data-unite="<?= htmlspecialchars($product['unite_mesure']) ?>"
                                        data-prix-achat="<?= $product['prix_achat'] ?>"
                                        data-quantite="<?= number_format($product['quantite_stock'], 2, ',', ' ') ?>">
                                    <i class="fas fa-dolly-flatbed"></i>
                                </button>

                                <button type="button" class="btn btn-sm btn-outline-info me-1"
                                        data-bs-toggle="modal" data-bs-target="#editProductModal"
                                        data-id="<?= $product['id'] ?>"
                                        data-code="<?= htmlspecialchars($product['code']) ?>"
                                        data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                        data-description="<?= htmlspecialchars($product['description']) ?>"
                                        data-categorie-id="<?= $product['categorie_id'] ?? '' ?>"
                                        data-unite="<?= htmlspecialchars($product['unite_mesure']) ?>"
                                        data-prix-achat="<?= $product['prix_achat'] ?>"
                                        data-prix-vente="<?= $product['prix_vente'] ?>"
                                        data-actif="<?= $product['actif'] ? '1' : '0' ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($isGestionnaire): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal" data-bs-target="#deleteProductModal"
                                        data-id="<?= $product['id'] ?>"
                                        data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                        data-code="<?= htmlspecialchars($product['code']) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Desktop View Table (shows on md and larger screens) -->
            <div class="table-responsive" style="max-height: none; overflow: visible;">
                <table class="table table-hover table-bordered table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>
                                <button type="button" class="btn btn-link p-0 text-decoration-none" id="sortCode">Code <span class="sort-indicator"></span></button>
                            </th>
                            <th>
                                <button type="button" class="btn btn-link p-0 text-decoration-none" id="sortNom">Nom <span class="sort-indicator"></span></button>
                            </th>
                            <th>Catégorie</th>
                            <th>Unité</th>
                            <th class="text-end">Quantité</th>
                            <th class="text-end">Prix achat</th>
                            <th class="text-end">Prix vente</th>
                            <th class="text-end">Valeur stock</th>
                            <th class="text-center">Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventory)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">Aucun produit trouvé.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inventory as $product): ?>
                                <tr id="product-row-<?= $product['id'] ?>">
                                    <td><code><?= htmlspecialchars($product['code']) ?></code></td>
                                    <td>
                                        <?= htmlspecialchars($product['nom']) ?>
                                        <?php if (!$product['actif']): ?>
                                            <span class="badge bg-secondary ms-1">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($product['categorie_nom'] ?? 'Non catégorisé') ?></td>
                                    <td><?= htmlspecialchars($product['unite_mesure']) ?></td>
                                    <td class="text-end" id="qty-<?= $product['id'] ?>">
                                        <?= number_format($product['quantite_stock'], 2, ',', ' ') ?>
                                    </td>
                                    <td class="text-end">
                                        <?= number_format($product['prix_achat'], 0, ',', ' ') ?> F
                                    </td>
                                    <td class="text-end">
                                        <?= number_format($product['prix_vente'], 0, ',', ' ') ?> F
                                    </td>
                                    <td class="text-end" id="valeur-<?= $product['id'] ?>">
                                        <?= number_format($product['valeur_stock'], 0, ',', ' ') ?> F
                                    </td>
                                    <td class="text-center" id="status-<?= $product['id'] ?>">
                                        <?php if ($product['quantite_stock'] <= 0): ?>
                                            <span class="badge bg-danger">Rupture</span>
                                        <?php elseif ($product['quantite_stock'] <= 10): ?>
                                            <span class="badge bg-warning text-dark">Stock bas</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">En stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewProductModal"
                                                       data-id="<?= $product['id'] ?>"
                                                       data-code="<?= htmlspecialchars($product['code']) ?>"
                                                       data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                                       data-description="<?= htmlspecialchars($product['description']) ?>"
                                                       data-categorie="<?= htmlspecialchars($product['categorie_nom'] ?? 'Non catégorisé') ?>"
                                                       data-unite="<?= htmlspecialchars($product['unite_mesure']) ?>"
                                                       data-prix-achat="<?= number_format($product['prix_achat'], 0, ',', ' ') ?>"
                                                       data-prix-vente="<?= number_format($product['prix_vente'], 0, ',', ' ') ?>"
                                                       data-quantite="<?= number_format($product['quantite_stock'], 2, ',', ' ') ?>"
                                                       data-valeur="<?= number_format($product['valeur_stock'], 0, ',', ' ') ?>"
                                                       data-date="<?= (new DateTime($product['date_mise_a_jour']))->format('d/m/Y H:i') ?>"
                                                       data-actif="<?= $product['actif'] ? 'Actif' : 'Inactif' ?>">
                                                        <i class="fas fa-eye me-2"></i> Voir détails
                                                    </a>
                                                </li>
                                                <?php if ($hasStockAccess): ?>
                                                <li>
                                                    <a class="dropdown-item adjust-stock-btn" href="#" data-bs-toggle="modal" data-bs-target="#adjustStockModal"
                                                       data-id="<?= $product['id'] ?>"
                                                       data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                                       data-code="<?= htmlspecialchars($product['code']) ?>"
                                                       data-unite="<?= htmlspecialchars($product['unite_mesure']) ?>"
                                                       data-prix-achat="<?= $product['prix_achat'] ?>"
                                                       data-quantite="<?= number_format($product['quantite_stock'], 2, ',', ' ') ?>">
                                                        <i class="fas fa-dolly-flatbed me-2"></i> Ajuster stock
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editProductModal"
                                                       data-id="<?= $product['id'] ?>"
                                                       data-code="<?= htmlspecialchars($product['code']) ?>"
                                                       data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                                       data-description="<?= htmlspecialchars($product['description']) ?>"
                                                       data-categorie-id="<?= $product['categorie_id'] ?? '' ?>"
                                                       data-unite="<?= htmlspecialchars($product['unite_mesure']) ?>"
                                                       data-prix-achat="<?= $product['prix_achat'] ?>"
                                                       data-prix-vente="<?= $product['prix_vente'] ?>"
                                                       data-actif="<?= $product['actif'] ? '1' : '0' ?>">
                                                        <i class="fas fa-edit me-2"></i> Modifier
                                                    </a>
                                                </li>
                                                <?php if ($isGestionnaire): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#deleteProductModal"
                                                       data-id="<?= $product['id'] ?>"
                                                       data-nom="<?= htmlspecialchars($product['nom']) ?>"
                                                       data-code="<?= htmlspecialchars($product['code']) ?>">
                                                        <i class="fas fa-trash me-2"></i> Supprimer
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php include('inventaire_modals.php'); ?>
<?php include('../layouts/footer.php'); ?>
