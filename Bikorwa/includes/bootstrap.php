<?php
/**
 * Bootstrap file for BIKORWA SHOP
 * This file ensures all core components are loaded and session is properly managed
 */

// Prevent multiple inclusions
if (defined('BIKORWA_BOOTSTRAP_LOADED')) {
    return;
}
define('BIKORWA_BOOTSTRAP_LOADED', true);

// Include configuration
require_once __DIR__ . '/../src/config/config.php';

// Include session manager
require_once __DIR__ . '/session_manager.php';

// Ensure session is started
global $sessionManager;
if (!isset($sessionManager) && class_exists('SessionManager')) {
    $sessionManager = SessionManager::getInstance();
    $sessionManager->startSession();
}
