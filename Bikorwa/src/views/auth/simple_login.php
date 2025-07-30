<?php
// Start simple session
session_start();

// Check if already logged in with simple auth
if (isset($_SESSION['simple_auth']) && $_SESSION['simple_auth'] === true && isset($_SESSION['user_id'])) {
    header('Location: simple_dashboard_test.php');
    exit;
}

// Include the configuration file to get BASE_URL
require_once __DIR__ . '/../../config/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KUBIKOTI BAR - Simple Login Test</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <h1 class="text-primary"><i class="fas fa-wine-glass-alt me-2"></i>KUBIKOTI BAR</h1>
            <p class="text-muted">Simple Login Test</p>
        </div>
        
        <div id="login-message" class="mt-3"></div>

        <form id="login-form" action="simple_login_process.php" method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Nom d'utilisateur</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" value="admin" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Mot de passe</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" value="admin123" required>
                </div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>Se connecter (Simple)
                </button>
            </div>
        </form>
        
        <hr>
        <div class="text-center">
            <p><a href="login.php">Regular Login</a> | <a href="debug_session_flow.php">Debug Session</a></p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#login-form').on('submit', function(e) {
                e.preventDefault();
                
                var $loginMessage = $('#login-message');
                $loginMessage.html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i>Connexion en cours...</div>');
                
                var formData = $(this).serialize();
                var actionUrl = $(this).attr('action');
                
                $.ajax({
                    type: 'POST',
                    url: actionUrl,
                    data: formData,
                    dataType: 'json',
                    timeout: 15000,
                    success: function(response) {
                        console.log('Response:', response);
                        if (response && response.success) {
                            $loginMessage.html('<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + response.message + '</div>');
                            if(response.redirectUrl) {
                                setTimeout(function() {
                                    window.location.href = response.redirectUrl;
                                }, 1500);
                            }
                        } else {
                            $loginMessage.html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' + (response && response.message ? response.message : 'Échec de la connexion.') + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        console.log('Response:', xhr.responseText);
                        
                        var errorMessage = 'Erreur de connexion. Veuillez réessayer.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        
                        $loginMessage.html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + errorMessage + '</div>');
                    }
                });
            });
        });
    </script>
</body>
</html>
