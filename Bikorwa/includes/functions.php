<?php
/**
 * Common utility functions for KUBIKOTI BAR
 */

/**
 * Sanitize input data
 * 
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Format a number as currency
 * 
 * @param float $amount Amount to format
 * @return string Formatted amount
 */
function formatMoney($amount) {
    return number_format($amount, 0, ',', ' ') . ' ' . CURRENCY;
}

/**
 * Format a date in human-readable format
 * 
 * @param string $date Date to format
 * @param string $format Format to use
 * @return string Formatted date
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    $datetime = new DateTime($date);
    return $datetime->format($format);
}

/**
 * Log activity in the system
 * 
 * @param string $action Action description
 * @param string $entite Entity type
 * @param int $entite_id Entity ID
 * @param string $details Additional details
 * @return bool Success status
 */
function logActivity($action, $entite, $entite_id, $details = '') {
    global $pdo;
    
    try {
        $utilisateur_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        $sql = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$utilisateur_id, $action, $entite, $entite_id, $details]);
        
        return true;
    } catch (PDOException $e) {
        error_log('Failed to log activity: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if user is an administrator (gestionnaire)
 * 
 * @return bool True if user is administrator
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'gestionnaire';
}

/**
 * Check if the current user can edit or delete records
 * 
 * @return bool True if user can edit/delete
 */
function canEdit() {
    return isset($_SESSION['user_role']);
}

/**
 * Check if the current user can delete records (gestionnaire only)
 * 
 * @return bool True if user can delete
 */
function canDelete() {
    return isAdmin();
}

/**
 * Generate a random reference code
 * 
 * @param string $prefix Prefix for the reference
 * @param int $length Length of the random part
 * @return string Generated reference
 */
function generateReference($prefix = 'REF', $length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_string = '';
    
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $prefix . $random_string;
}

/**
 * Display a flash message
 * 
 * @param string $type Message type (success, danger, warning, info)
 * @param string $message Message content
 * @return void
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 * 
 * @return array|null Flash message or null if none
 */
function getFlashMessage() {
    $message = null;
    
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
    }
    
    return $message;
}
