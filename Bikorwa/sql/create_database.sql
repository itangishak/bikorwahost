-- Script de création de la base de données pour BIKORWA SHOP
-- Création de la base de données
CREATE DATABASE IF NOT EXISTS bikorwa_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bikorwa_shop;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    role ENUM('receptionniste', 'gestionnaire') NOT NULL,
    email VARCHAR(100),
    telephone VARCHAR(20),
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion DATETIME,
    actif BOOLEAN NOT NULL DEFAULT TRUE
);

-- Table des catégories de produits
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    description TEXT
);

-- Table des produits
CREATE TABLE IF NOT EXISTS produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    categorie_id INT,
    unite_mesure VARCHAR(20) NOT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Table des prix des produits (historique)
CREATE TABLE IF NOT EXISTS prix_produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    prix_achat DECIMAL(10,2) NOT NULL,
    prix_vente DECIMAL(10,2) NOT NULL,
    date_debut DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_fin DATETIME DEFAULT NULL,
    cree_par INT NOT NULL,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE,
    FOREIGN KEY (cree_par) REFERENCES users(id)
);

-- Table du stock
CREATE TABLE IF NOT EXISTS stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    quantite DECIMAL(10,2) NOT NULL DEFAULT 0,
    date_mise_a_jour DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
);

-- Table des mouvements de stock
CREATE TABLE IF NOT EXISTS mouvements_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    type_mouvement ENUM('entree', 'sortie') NOT NULL,
    quantity_remaining DECIMAL(10,2) DEFAULT NULL,
    quantite DECIMAL(10,2) NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    valeur_totale DECIMAL(10,2) NOT NULL,
    reference VARCHAR(100),
    date_mouvement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    utilisateur_id INT NOT NULL,
    note TEXT,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES users(id)
);

-- Table des clients
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    telephone VARCHAR(20),
    adresse TEXT,
    email VARCHAR(100),
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    limite_credit DECIMAL(10,2) NOT NULL DEFAULT 0,
    note TEXT
);

-- Table des ventes
CREATE TABLE IF NOT EXISTS ventes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_facture VARCHAR(20) NOT NULL UNIQUE,
    date_vente DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    client_id INT,
    utilisateur_id INT NOT NULL,
    montant_total DECIMAL(10,2) NOT NULL,
    montant_paye DECIMAL(10,2) NOT NULL,
    statut_paiement ENUM('paye', 'partiel', 'credit') NOT NULL,
    statut_vente ENUM('active', 'annulee') DEFAULT 'active',
    note TEXT,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES users(id)
);

-- Table des détails des ventes
CREATE TABLE IF NOT EXISTS details_ventes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vente_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite DECIMAL(10,2) NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    montant_total DECIMAL(10,2) NOT NULL,
    prix_achat_unitaire DECIMAL(10,2) NOT NULL,
    benefice DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
);

-- Table des dettes
CREATE TABLE IF NOT EXISTS dettes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    vente_id INT,
    montant_initial DECIMAL(10,2) NOT NULL,
    montant_restant DECIMAL(10,2) NOT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_echeance DATE,
    statut ENUM('active', 'partiellement_payee', 'payee', 'annulee') NOT NULL DEFAULT 'active',
    note TEXT,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE SET NULL
);

-- Table des paiements de dettes
CREATE TABLE IF NOT EXISTS paiements_dettes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dette_id INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    date_paiement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    utilisateur_id INT NOT NULL,
    methode_paiement VARCHAR(50) NOT NULL,
    reference VARCHAR(100),
    note TEXT,
    FOREIGN KEY (dette_id) REFERENCES dettes(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES users(id)
);

-- Table des employés
CREATE TABLE IF NOT EXISTS employes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    telephone VARCHAR(20),
    adresse TEXT,
    email VARCHAR(100),
    poste VARCHAR(50) NOT NULL,
    date_embauche DATE NOT NULL,
    salaire DECIMAL(10,2) NOT NULL,
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    note TEXT
);

-- Table des salaires
CREATE TABLE IF NOT EXISTS salaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employe_id INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    periode_debut DATE NOT NULL,
    periode_fin DATE NOT NULL,
    date_paiement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    utilisateur_id INT NOT NULL,
    note TEXT,
    FOREIGN KEY (employe_id) REFERENCES employes(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES users(id)
);

-- Table du journal d'activités
CREATE TABLE IF NOT EXISTS journal_activites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT,
    action VARCHAR(255) NOT NULL,
    entite VARCHAR(50) NOT NULL,
    entite_id INT,
    date_action DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    details TEXT,
    FOREIGN KEY (utilisateur_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS categories_depenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
);

-- Expense tracking table
CREATE TABLE IF NOT EXISTS depenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_depense DATE NOT NULL,  -- Date expense occurred
    date_enregistrement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  -- Date recorded
    categorie_id INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL CHECK (montant > 0),
    description VARCHAR(255) NOT NULL,
    mode_paiement ENUM('Espèces', 'Cheque', 'Virement', 'Carte', 'Mobile Money') NOT NULL,
    reference_paiement VARCHAR(100),  -- Check/transaction number
    utilisateur_id INT NOT NULL,
    note TEXT,
    FOREIGN KEY (categorie_id) REFERENCES categories_depenses(id),
    FOREIGN KEY (utilisateur_id) REFERENCES users(id)
);
-- Insertion d'un utilisateur administrateur par défaut (mot de passe: admin123)
INSERT INTO users (username, password, nom, role) VALUES 
('admin', '$2y$10$8MuRXEbkI.UlPuHOXiND7uVUmT5/6VdT.6FJP0q9H.QQQzhlfnpYO', 'Administrateur', 'gestionnaire');

-- Insertion de quelques catégories de base
INSERT INTO categories (nom, description) VALUES 
('Bières', 'Toutes les bières'),
('Sodas', 'Boissons gazeuses non alcoolisées'),
('Spiritueux', 'Boissons alcoolisées fortes'),
('Vins', 'Vins et champagnes'),
('Autres', 'Autres produits');


-- Table des parametres de l'application
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    value TEXT NOT NULL
);

-- Valeurs par defaut pour les parametres
INSERT INTO settings (name, value) VALUES
('theme','light'),
('shop_name','BIKORWA SHOP');
