<?php
// Add this include at the beginning of process.php, before the 'add' and 'edit' case sections

// Function to check for duplicate employees (active ones with the same name)
function checkDuplicateEmployee($conn, $nom, $current_id = 0) {
    $query = "SELECT COUNT(*) as count FROM employes WHERE nom = ? AND actif = 1 AND id != ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $nom, PDO::PARAM_STR);
    $stmt->bindParam(2, $current_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return ($result['count'] > 0);
}

// Usage example:
// Before inserting/updating an employee:
//
// $nom = trim($_POST['nom']);
// if (checkDuplicateEmployee($conn, $nom, $id)) {
//     // Handle duplicate error
//     if ($is_ajax) {
//         echo json_encode(['success' => false, 'message' => 'Un employé actif avec ce nom existe déjà']);
//         exit;
//     } else {
//         $_SESSION['error'] = 'Un employé actif avec ce nom existe déjà';
//         header('Location: ./liste.php');
//         exit;
//     }
// }
?>
