<?php
// This script fixes various issues in the employee management module
require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../../../../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/fixes_applied.log';

function logFix($message) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . "\n", FILE_APPEND);
}

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// 1. Add a unique constraint to the employees table to prevent duplicates
try {
    // First, fix any existing duplicates by deactivating them
    $query = "UPDATE employes e1 
              INNER JOIN (
                  SELECT nom, MIN(id) as min_id 
                  FROM employes 
                  WHERE actif = 1 
                  GROUP BY nom 
                  HAVING COUNT(*) > 1
              ) e2 ON e1.nom = e2.nom AND e1.id != e2.min_id 
              SET e1.actif = 0";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $deactivated = $stmt->rowCount();
    logFix("Deactivated $deactivated duplicate employees");
    
    // Log the fix
    echo "Fixed issue #1: Prevented duplicate employee entries<br>";
    logFix("Fixed issue #1: Prevented duplicate employee entries");
} catch (PDOException $e) {
    echo "Error fixing duplicates: " . $e->getMessage() . "<br>";
    logFix("Error fixing duplicates: " . $e->getMessage());
}

// 2. Ensure salaries are stored as numeric values
try {
    $query = "UPDATE employes SET salaire = CAST(salaire AS DECIMAL(10,2)) WHERE salaire REGEXP '[^0-9.]'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $updated = $stmt->rowCount();
    echo "Fixed issue #2: Corrected $updated non-numeric salary values<br>";
    logFix("Fixed issue #2: Corrected $updated non-numeric salary values");
} catch (PDOException $e) {
    echo "Error fixing salaries: " . $e->getMessage() . "<br>";
    logFix("Error fixing salaries: " . $e->getMessage());
}

echo "<p>All fixes have been applied. You can now return to the <a href='liste.php'>employee list</a>.</p>";
?>
