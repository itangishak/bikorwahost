<?php

// Enable error reporting for debugging purposes
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Start a standard PHP session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the configuration file to get BASE_URL
require_once __DIR__ . '/../../../src/config/config.php';

// Login page for BIKORWA SHOP
$page_title = "Connexion";
$active_page = "login";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KUBIKOTI BAR - Connexion</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="./../../../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="login-container bg-white">
            <div class="login-logo">
                <h1 class="text-primary"><i class="fas fa-wine-glass-alt me-2"></i>KUBIKOTI BAR</h1>
                <p class="text-muted">Système de gestion de bar</p>
            </div>
            
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'danger'; ?> alert-dismissible fade show">
                    <?php echo $_SESSION['flash_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>
            
            <div id="login-message" class="mt-3"></div> <!-- Add this div for messages -->

            <form id="login-form" action="login_process.php" method="post">
                <div class="mb-3">
                    <label for="username" class="form-label">Nom d'utilisateur</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Mot de passe</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <button class="btn btn-outline-secondary toggle-password" type="button">
                            <i class="fas fa-eye" id="password-toggle-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                    </button>
                </div>
                <div class="text-center mt-3">
                    <a href="#" id="forgot-password-link" class="text-decoration-none">
                        <i class="fas fa-lock-open me-1"></i>Mot de passe oublié?
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Password Reset Modal -->
    <div class="modal fade" id="password-reset-modal" tabindex="-1" aria-labelledby="password-reset-modal-label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="password-reset-modal-label"><i class="fas fa-key me-2"></i>Réinitialisation du mot de passe</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="reset-message" class="mb-3"></div>
                    <form id="password-reset-form">
                        <div class="mb-3">
                            <label for="reset-username" class="form-label">Nom d'utilisateur</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="reset-username" name="username" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="reset-password" class="form-label">Nouveau mot de passe</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="reset-password" name="new_password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="reset-password">
                                    <i class="fas fa-eye" id="reset-password-toggle-icon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm-reset-password" class="form-label">Confirmer le mot de passe</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm-reset-password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm-reset-password">
                                    <i class="fas fa-eye" id="confirm-reset-password-toggle-icon"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="submit-reset">Réinitialiser</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- Add jQuery -->
    <script>
        $(document).ready(function() {
            // Password visibility toggle for all password fields
            $('.toggle-password').on('click', function() {
                var targetId = $(this).data('target') || 'password';
                var passwordField = $('#' + targetId);
                var passwordIcon = $(this).find('i');
                
                // Toggle password visibility
                if (passwordField.attr('type') === 'password') {
                    passwordField.attr('type', 'text');
                    passwordIcon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordField.attr('type', 'password');
                    passwordIcon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            // Show password reset modal
            $('#forgot-password-link').on('click', function(e) {
                e.preventDefault();
                // Clear previous form data and messages
                $('#password-reset-form')[0].reset();
                $('#reset-message').html('');
                $('#password-reset-modal').modal('show');
            });
            
            // Handle password reset form submission
            $('#submit-reset').on('click', function() {
                var $form = $('#password-reset-form');
                var $resetMessage = $('#reset-message');
                
                // Get form data
                var username = $('#reset-username').val().trim();
                var newPassword = $('#reset-password').val();
                var confirmPassword = $('#confirm-reset-password').val();
                
                // Validate form data
                if (!username || !newPassword || !confirmPassword) {
                    $resetMessage.html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Tous les champs sont obligatoires.</div>');
                    return;
                }
                
                if (newPassword !== confirmPassword) {
                    $resetMessage.html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Les mots de passe ne correspondent pas.</div>');
                    return;
                }
                
                if (newPassword.length < 6) {
                    $resetMessage.html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Le mot de passe doit contenir au moins 6 caractères.</div>');
                    return;
                }
                
                // Show loading message
                $resetMessage.html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i>Réinitialisation en cours...</div>');
                
                // Submit form via AJAX
                $.ajax({
                    type: 'POST',
                    url: 'reset_password_process.php',
                    data: {
                        username: username,
                        new_password: newPassword,
                        confirm_password: confirmPassword
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $resetMessage.html('<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + response.message + '</div>');
                            // Disable form inputs and submit button on success
                            $('#password-reset-form input').prop('disabled', true);
                            $('#submit-reset').prop('disabled', true).text('Réinitialisé');
                            // Change close button text
                            $('.modal-footer .btn-secondary').text('Fermer');
                        } else {
                            $resetMessage.html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' + response.message + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        var errorMessage = '';
                        
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.status === 0) {
                            errorMessage = 'Impossible de se connecter au serveur. Vérifiez votre connexion internet.';
                        } else {
                            errorMessage = 'Erreur lors de la réinitialisation du mot de passe. Veuillez réessayer.';
                        }
                        
                        $resetMessage.html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + errorMessage + '</div>');
                    }
                });
            });
            
            $('#login-form').on('submit', function(e) {
                e.preventDefault(); // Prevent default form submission
                
                // Show loading indicator
                var $loginMessage = $('#login-message');
                $loginMessage.html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i>Connexion en cours...</div>');
                
                var formData = $(this).serialize();
                var actionUrl = $(this).attr('action');
                
                // Debug - log the URL we're submitting to
                console.log('Submitting to URL:', actionUrl);
                
                $.ajax({
                    type: 'POST',
                    url: actionUrl,
                    data: formData,
                    dataType: 'json',
                    timeout: 15000, // 15 second timeout
                    success: function(response) {
                        console.log('Received response:', response);
                        if (response && response.success) {
                            console.log('I reach here');
                            console.log('Redirecting to:', response.redirectUrl);
                            if (response.sessionId) {
                                sessionStorage.setItem('sessionId', response.sessionId);
                            }
                            $loginMessage.html('<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + response.message + '</div>');
                            // Redirect after a short delay
                            if(response.redirectUrl) {
                                setTimeout(function() {
                                    console.log('Redirecting to:', response.redirectUrl);
                                    window.location.href = response.redirectUrl;
                                }, 1500);
                            }
                        } else {
                            $loginMessage.html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' + (response && response.message ? response.message : 'Échec de la connexion. Veuillez réessayer.') + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        console.log('Response:', xhr.responseText);
                        
                        // Handle different error scenarios
                        var errorMessage = '';
                        
                        if (status === 'timeout') {
                            errorMessage = 'Le serveur met trop de temps à répondre. Veuillez réessayer.';
                        } else if (status === 'error' && xhr.status === 0) {
                            errorMessage = 'Impossible de se connecter au serveur. Vérifiez votre connexion internet.';
                        } else if (xhr.status >= 500) {
                            errorMessage = 'Erreur serveur. Veuillez contacter l\'administrateur.';
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.responseText) {
                            // Try to parse if it's a stringified JSON
                            try {
                                var errResponse = JSON.parse(xhr.responseText);
                                if (errResponse && errResponse.message) {
                                    errorMessage = errResponse.message;
                                }
                            } catch (e) {
                                // Concise inline error handling

                            }
                        } else {
                            errorMessage = 'Erreur: ' + xhr.status + ' ' + xhr.statusText;
                        }
                        
                        $loginMessage.html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + errorMessage + '</div>');
                    }
                });
            });
        });
    </script>
</body>
</html>
