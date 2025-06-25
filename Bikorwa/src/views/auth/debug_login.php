<?php
/**
 * Script de du00e9bogage pour le login
 * BIKORWA SHOP
 */

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclure les fichiers nu00e9cessaires
require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';
require_once './../../../src/models/User.php';
require_once './../../../src/controllers/AuthController.php';

// Fonction pour afficher les ru00e9sultats
function displayResult($title, $result, $success = true) {
    echo "<div style=\"margin: 10px 0; padding: 10px; border: 1px solid " . ($success ? "green" : "red") . "; background-color: " . ($success ? "#f0fff0" : "#fff0f0") . ";\">";    
    echo "<h3>$title</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    echo "</div>";
}

echo "<html><head><title>Du00e9bogage Login BIKORWA</title><style>body{font-family:Arial,sans-serif;margin:20px}</style></head><body>";

echo "<h1>Du00e9bogage de la connexion u00e0 BIKORWA SHOP</h1>";

try {
    // Vu00e9rifier la connexion u00e0 la base de donnu00e9es
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        displayResult("Connexion u00e0 la base de donnu00e9es", "Connexion ru00e9ussie");
        
        // Vu00e9rifier si la table users existe et contient des enregistrements
        try {
            $query = "SELECT COUNT(*) FROM users";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            displayResult("Table users", "$count utilisateur(s) trouvu00e9(s)");
            
            // Tester un compte utilisateur si aucun n'existe, en cru00e9er un
            if ($count == 0) {
                // Cru00e9er un utilisateur admin par du00e9faut
                $query = "INSERT INTO users (username, password, nom, role, email) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                
                $username = 'admin';
                $password = password_hash('admin123', PASSWORD_DEFAULT);
                $nom = 'Administrateur';
                $role = 'gestionnaire';
                $email = 'admin@bikorwa.com';
                
                $stmt->execute([$username, $password, $nom, $role, $email]);
                
                if ($stmt->rowCount() > 0) {
                    displayResult("Cru00e9ation d'utilisateur", "Utilisateur admin cru00e9u00e9 avec succu00e8s (identifiant: admin, mot de passe: admin123)");
                } else {
                    displayResult("Cru00e9ation d'utilisateur", "Erreur lors de la cru00e9ation de l'utilisateur admin", false);
                }
            } else {
                // Lister les utilisateurs disponibles (sans montrer les mots de passe)
                $query = "SELECT id, username, nom, role FROM users LIMIT 5";
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                displayResult("Utilisateurs disponibles", $users);
            }
            
            // Tester l'authentification avec un utilisateur connu
            if ($count > 0) {
                echo "<h2>Test d'authentification</h2>";
                echo "<form method='post' action='debug_login.php'>";
                echo "<div>Nom d'utilisateur: <input type='text' name='test_username' value='admin'></div>";
                echo "<div style='margin-top:10px'>Mot de passe: <input type='password' name='test_password' value='admin123'></div>";
                echo "<div style='margin-top:10px'><button type='submit' name='test_auth'>Tester l'authentification</button></div>";
                echo "</form>";
                
                // Traiter le test d'authentification
                if (isset($_POST['test_auth'])) {
                    $testUsername = $_POST['test_username'] ?? '';
                    $testPassword = $_POST['test_password'] ?? '';
                    
                    $authController = new AuthController();
                    $result = $authController->login($testUsername, $testPassword);
                    
                    displayResult("Ru00e9sultat de l'authentification", $result, $result['success']);
                }
            }
            
        } catch (PDOException $e) {
            displayResult("Erreur SQL", "Erreur lors de la requu00eate: " . $e->getMessage(), false);
        }
    } else {
        displayResult("Connexion u00e0 la base de donnu00e9es", "u00c9chec de la connexion", false);
    }
} catch (Exception $e) {
    displayResult("Erreur gu00e9nu00e9rale", "Exception: " . $e->getMessage(), false);
}

echo "</body></html>";
?>
