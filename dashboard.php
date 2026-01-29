<?php
// ===============================================================================
// PriviMetrics - Admin Dashboard
// ===============================================================================

$settings = [];
if (file_exists('settings-config.php')) {
    $settings = require 'settings-config.php';
}

if (!file_exists('config.php')) {
    die('Configuration file not found. Please run install.php first.');
}
require_once 'config.php';

if (!file_exists('functions.php')) {
    die('Functions file not found. Please check your installation.');
}
require_once 'functions.php';

if (!file_exists('storage.php')) {
    die('Storage file not found. Please check your installation.');
}
require_once 'storage.php';

require_once __DIR__ . '/new_version.php';
require_once __DIR__ . '/assets/dashboard-logic.php';
require_once __DIR__ . '/extensions-load.php';

// Session and security
startSecureSession();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    session_unset();
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Session timeout check
$sessionTimeout = isset($config['session_timeout']) ? $config['session_timeout'] : 86400;
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $sessionTimeout) {
    session_unset();
    session_destroy();
    header('Location: admin.php?timeout=1');
    exit;
}

$_SESSION['last_activity'] = time();

// Initialize data
$dashboardData = initializeDashboard($config, $settings);
extract($dashboardData);

// Include HTML template
require_once __DIR__ . '/assets/dashboard-template.php';