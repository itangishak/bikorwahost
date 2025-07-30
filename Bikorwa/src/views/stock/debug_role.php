<?php
session_start();

echo "<h2>Session Debug for Stock Page Access</h2>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";

echo "<h3>All Session Variables:</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

echo "<h3>Role Check Results:</h3>";
echo "<p><strong>isset(\$_SESSION['role']):</strong> " . (isset($_SESSION['role']) ? 'YES' : 'NO') . "</p>";
echo "<p><strong>\$_SESSION['role'] value:</strong> " . ($_SESSION['role'] ?? 'NOT SET') . "</p>";
echo "<p><strong>Role === 'gestionnaire':</strong> " . (($_SESSION['role'] ?? '') === 'gestionnaire' ? 'YES' : 'NO') . "</p>";

echo "<h3>Access Check:</h3>";
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'gestionnaire') {
    echo "<p style='color: red;'><strong>ACCESS DENIED</strong> - This is why you see 'Accès non autorisé'</p>";
} else {
    echo "<p style='color: green;'><strong>ACCESS GRANTED</strong> - You should be able to access the stock page</p>";
}

echo "<p><a href='historique_approvisionnement.php'>Try Stock Page Again</a></p>";
echo "<p><a href='../auth/session_test.php'>Session Test Page</a></p>";
?>
