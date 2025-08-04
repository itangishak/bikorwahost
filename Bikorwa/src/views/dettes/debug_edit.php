<?php
// Debug page for testing the edit debt functionality
// Start session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../utils/Auth.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize authentication
$auth = new Auth($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    echo "Not logged in";
    exit;
}

// Get first debt for testing
$query = "SELECT d.*, c.nom as client_nom FROM dettes d LEFT JOIN clients c ON d.client_id = c.id LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->execute();
$test_debt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test_debt) {
    echo "No debts found for testing";
    exit;
}

$debt_id = $test_debt['id'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Edit Debt</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h2>Debug Edit Debt Functionality</h2>
    
    <p><strong>Test Debt ID:</strong> <?php echo $debt_id; ?></p>
    <p><strong>Client:</strong> <?php echo htmlspecialchars($test_debt['client_nom']); ?></p>
    <p><strong>Amount:</strong> <?php echo $test_debt['montant_initial']; ?> F</p>
    
    <button id="testEditBtn" data-id="<?php echo $debt_id; ?>">Test Edit Button</button>
    
    <div id="results" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc; background: #f9f9f9;">
        <h3>Results:</h3>
        <div id="output"></div>
    </div>

    <script>
        const baseUrl = '<?php echo BASE_URL; ?>';
        const userRole = '<?php echo $_SESSION['role'] ?? ''; ?>';
        const isManager = <?php echo ($_SESSION['role'] === 'gestionnaire') ? 'true' : 'false'; ?>;
        
        console.log('Debug info:', {
            baseUrl: baseUrl,
            userRole: userRole,
            isManager: isManager
        });
        
        document.getElementById('testEditBtn').addEventListener('click', function() {
            const detteId = this.getAttribute('data-id');
            const output = document.getElementById('output');
            
            output.innerHTML = '<p>Testing edit functionality for debt ID: ' + detteId + '</p>';
            
            // Test the API call directly
            const apiUrl = `${baseUrl}/src/api/dettes/get_dette.php?id=${detteId}`;
            
            output.innerHTML += '<p>Calling API: ' + apiUrl + '</p>';
            
            fetch(apiUrl)
                .then(response => {
                    output.innerHTML += '<p>Response status: ' + response.status + '</p>';
                    output.innerHTML += '<p>Response headers: ' + JSON.stringify([...response.headers.entries()]) + '</p>';
                    
                    return response.text();
                })
                .then(text => {
                    output.innerHTML += '<p>Raw response: <pre>' + text + '</pre></p>';
                    
                    try {
                        const data = JSON.parse(text);
                        output.innerHTML += '<p>Parsed JSON: <pre>' + JSON.stringify(data, null, 2) + '</pre></p>';
                        
                        if (data.success) {
                            output.innerHTML += '<p style="color: green;">SUCCESS: API call worked!</p>';
                        } else {
                            output.innerHTML += '<p style="color: red;">ERROR: ' + data.message + '</p>';
                        }
                    } catch (e) {
                        output.innerHTML += '<p style="color: red;">JSON Parse Error: ' + e.message + '</p>';
                    }
                })
                .catch(error => {
                    output.innerHTML += '<p style="color: red;">Fetch Error: ' + error.message + '</p>';
                });
        });
    </script>
</body>
</html>
