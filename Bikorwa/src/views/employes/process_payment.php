<?php
// Désactiver TOUS les affichages d'erreur
error_reporting(0);
ini_set('display_errors', 0);

// Vider tout buffer de sortie
while (ob_get_level()) ob_end_clean();

// Définir l'en-tête JSON
header('Content-Type: application/json');

// Create detailed error response function
function errorResponse($message, $details = []) {
    $response = [
        'success' => false,
        'message' => $message,
        'debug' => array_merge(
            ['timestamp' => date('d/m/Y H:i:s')],
            $details
        )
    ];
    file_put_contents(__DIR__.'/../../../payment_errors.log', 
        print_r($response, true)."\n", 
        FILE_APPEND);
    return json_encode($response);
}

// Initialize error collection
$errors = [];
$errors[] = "[".date('d/m/Y H:i:s')."] DÉBUT DU TRAITEMENT DU PAIEMENT";

// Initialize debug logging directly in project root
$debugFile = __DIR__.'/../../../payment_debug.log';
file_put_contents($debugFile, "[".date('Y-m-d H:i:s')."] Début du traitement\n", FILE_APPEND);

// Load dependencies
try {
    require_once __DIR__.'/../../../src/config/config.php';
    require_once __DIR__.'/../../../src/config/database.php';
    $errors[] = "Fichiers de configuration chargés avec succès";
    file_put_contents($debugFile, "[".date('Y-m-d H:i:s')."] Connexion DB réussie\n", FILE_APPEND);
} catch (Exception $e) {
    $errors[] = "Erreur de configuration : ".$e->getMessage();
    file_put_contents($debugFile, "Erreur de configuration : ".$e->getMessage()."\n", FILE_APPEND);
    die(errorResponse('Erreur de configuration', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTrace()
    ]));
}

// Initialize database
try {
    $database = new Database();
    $conn = $database->getConnection();
    $errors[] = "Connexion à la base de données établie avec succès";
    file_put_contents($debugFile, "[".date('Y-m-d H:i:s')."] Connexion DB réussie\n", FILE_APPEND);
    
    // Verify connection
    $conn->query("SELECT 1")->fetch();
    $errors[] = "Connexion à la base de données vérifiée avec succès";
    file_put_contents($debugFile, "Connexion à la base de données vérifiée avec succès\n", FILE_APPEND);
} catch (Exception $e) {
    $errors[] = "Erreur de connexion à la base de données : ".$e->getMessage();
    file_put_contents($debugFile, "Erreur de connexion à la base de données : ".$e->getMessage()."\n", FILE_APPEND);
    die(errorResponse('Connexion à la base de données échouée', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTrace()
    ]));
}

// Vérification session et rôle
try {
    @session_start();
    if(!isset($_SESSION['user_id'])) {
        throw new Exception('Non autorisé - Non connecté');
    }
    
    // Vérification rôle gestionnaire
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userRole = $stmt->fetchColumn();
    
    if($userRole !== 'gestionnaire') {
        throw new Exception('Non autorisé - Permissions insuffisantes');
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// Check if user exists in users table
try {
    $userCheck = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $userCheck->execute([(int)$_SESSION['user_id']]);
    if (!$userCheck->fetch()) {
        throw new Exception('Utilisateur non trouvé dans la base de données');
    }
    $errors[] = "Validation de l'utilisateur réussie";
    file_put_contents($debugFile, "Validation de l'utilisateur réussie\n", FILE_APPEND);
} catch (Exception $e) {
    $errors[] = "Erreur de validation de l'utilisateur : ".$e->getMessage();
    file_put_contents($debugFile, "Erreur de validation de l'utilisateur : ".$e->getMessage()."\n", FILE_APPEND);
    die(errorResponse('Validation utilisateur échouée', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTrace()
    ]));
}

// Log received data
file_put_contents($debugFile, "DONNÉES POST : ".print_r($_POST, true)."\n", FILE_APPEND);

// Validate required fields
$required = ['employe_id', 'montant', 'date_paiement', 'periode_debut', 'periode_fin'];
$missing = array_diff($required, array_keys($_POST));
if (!empty($missing)) {
    $errors[] = "Champs manquants : ".implode(', ', $missing);
    file_put_contents($debugFile, "Champs manquants : ".implode(', ', $missing)."\n", FILE_APPEND);
    die(errorResponse('Champs manquants : '.implode(', ', $missing), [
        'missing_fields' => $missing
    ]));
}

try {
    // Prepare data with type casting
    $paymentData = [
        'employe_id' => (int)$_POST['employe_id'],
        'montant' => (float)$_POST['montant'],
        'periode_debut' => $_POST['periode_debut'],
        'periode_fin' => $_POST['periode_fin'],
        'date_paiement' => $_POST['date_paiement'],
        'utilisateur_id' => (int)$_SESSION['user_id']
    ];
    $errors[] = "Données de paiement : ".print_r($paymentData, true);
    file_put_contents($debugFile, "Données de paiement : ".print_r($paymentData, true)."\n", FILE_APPEND);

    // Insert payment
    $query = "INSERT INTO salaires ".
              "(employe_id, montant, periode_debut, periode_fin, date_paiement, utilisateur_id) ".
              "VALUES (:employe_id, :montant, :periode_debut, :periode_fin, :date_paiement, :utilisateur_id)";
    $errors[] = "Requête : $query";
    file_put_contents($debugFile, "Requête : $query\n", FILE_APPEND);
    
    $stmt = $conn->prepare($query);
    if (!$stmt->execute($paymentData)) {
        $errorInfo = $stmt->errorInfo();
        $errors[] = "Erreur SQL : ".print_r($errorInfo, true);
        file_put_contents($debugFile, "Erreur SQL : ".print_r($errorInfo, true)."\n", FILE_APPEND);
        throw new Exception('Erreur de base de données');
    }
    $errors[] = "Paiement enregistré avec succès";
    file_put_contents($debugFile, "Paiement enregistré avec succès\n", FILE_APPEND);

    // Log success
    $paymentId = $conn->lastInsertId();
    $errors[] = "ID de paiement $paymentId créé avec succès";
    file_put_contents($debugFile, "ID de paiement $paymentId créé avec succès\n", FILE_APPEND);
    
    // Vérification explicite
    $checkQuery = "SELECT * FROM salaires WHERE id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute([$paymentId]);
    $insertedData = $checkStmt->fetch();

    if (!$insertedData) {
        throw new Exception('Données non persistées en base malgré l\'insertion');
    }

    $errors[] = "Vérification réussie. Données insérées : " . print_r($insertedData, true);
    file_put_contents($debugFile, "Vérification base: " . print_r($insertedData, true) . "\n", FILE_APPEND);

    // Get employee name for better logging
    $employeeStmt = $conn->prepare("SELECT nom FROM employes WHERE id = ?");
    $employeeStmt->execute([$paymentData['employe_id']]);
    $employeeName = $employeeStmt->fetchColumn() ?: 'Employé inconnu';

    // Log activity to journal_activites table
    try {
        $logStmt = $conn->prepare("INSERT INTO journal_activites ".
            "(utilisateur_id, action, entite, entite_id, details, date_action) ".
            "VALUES (:user_id, :action, :entite, :payment_id, :details, NOW())");
        
        $activityDetails = sprintf(
            "Paiement de salaire de %s FBU pour %s (Période: %s au %s)",
            number_format($paymentData['montant'], 0, ',', ' '),
            $employeeName,
            date('d/m/Y', strtotime($paymentData['periode_debut'])),
            date('d/m/Y', strtotime($paymentData['periode_fin']))
        );
        
        $logStmt->execute([
            'user_id' => $_SESSION['user_id'],
            'action' => 'paiement_salaire',
            'entite' => 'salaires', // Using 'salaires' to match table name
            'payment_id' => $paymentId,
            'details' => $activityDetails
        ]);
        
        $errors[] = "Activité enregistrée avec succès dans journal_activites";
        file_put_contents($debugFile, "Activité enregistrée avec succès dans journal_activites\n", FILE_APPEND);
        
        // Verify the activity was logged
        $verifyStmt = $conn->prepare("SELECT * FROM journal_activites WHERE entite_id = ? AND entite = 'salaires' ORDER BY id DESC LIMIT 1");
        $verifyStmt->execute([$paymentId]);
        $loggedActivity = $verifyStmt->fetch();
        
        if ($loggedActivity) {
            $errors[] = "Vérification activité réussie: " . print_r($loggedActivity, true);
            file_put_contents($debugFile, "Vérification activité réussie: " . print_r($loggedActivity, true) . "\n", FILE_APPEND);
        } else {
            $errors[] = "ATTENTION: Activité non trouvée après insertion";
            file_put_contents($debugFile, "ATTENTION: Activité non trouvée après insertion\n", FILE_APPEND);
        }
        
    } catch (Exception $e) {
        $errors[] = "Erreur d'enregistrement d'activité : " . $e->getMessage();
        file_put_contents($debugFile, "Erreur d'enregistrement d'activité : " . $e->getMessage()."\n", FILE_APPEND);
        // Don't fail the entire payment process if activity logging fails
    }

    $response = [
        'success' => true,
        'message' => 'Paiement enregistré avec succès',
        'payment_id' => $paymentId
    ];

    echo json_encode($response);
    exit();
} catch (Exception $e) {
    die(errorResponse('Échec du paiement', [
        'exception' => $e->getMessage(),
        'donnees_post' => $_POST,
        'session' => $_SESSION ?? []
    ]));
}

file_put_contents($debugFile, "[".date('Y-m-d H:i:s')."] TRAITEMENT TERMINÉ\n\n", FILE_APPEND);
?>
