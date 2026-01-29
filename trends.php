<?php
// ===============================================================================
// PriviMetrics - Trends Analysis Module
// ===============================================================================

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Extensions
require_once 'extensions-load.php';

if (!file_exists('storage.php')) {
    die('Storage file not found. Please check your installation.');
}
require_once 'storage.php';

// Define loadXML function if it doesn't exist
if (!function_exists('loadXML')) {
    function loadXML($file, $root = 'root') {
        if (!file_exists($file)) {
            $xml = new SimpleXMLElement("<{$root}></{$root}>");
            return $xml;
        }
        $content = file_get_contents($file);
        if ($content === false || trim($content) === '') {
            return new SimpleXMLElement("<{$root}></{$root}>");
        }
        try {
            return simplexml_load_string($content);
        } catch (Exception $e) {
            error_log("Error loading XML from $file: " . $e->getMessage());
            return new SimpleXMLElement("<{$root}></{$root}>");
        }
    }
}

// Define formatNumber function if it doesn't exist
if (!function_exists('formatNumber')) {
    function formatNumber($number) {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }
        return number_format($number);
    }
}

// Define sanitize function if it doesn't exist
if (!function_exists('sanitize')) {
    function sanitize($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

// Define loadXMLFile function if it doesn't exist
if (!function_exists('loadXMLFile')) {
    function loadXMLFile($file, $root = 'root') {
        if (!file_exists($file)) {
            $xml = new SimpleXMLElement("<{$root}></{$root}>");
            return $xml;
        }
        $content = file_get_contents($file);
        if ($content === false || trim($content) === '') {
            return new SimpleXMLElement("<{$root}></{$root}>");
        }
        try {
            return simplexml_load_string($content);
        } catch (Exception $e) {
            error_log("Error loading XML from $file: " . $e->getMessage());
            return new SimpleXMLElement("<{$root}></{$root}>");
        }
    }
}

// Define generateID function if it doesn't exist  
if (!function_exists('generateID')) {
    function generateID() {
        return uniqid(bin2hex(random_bytes(4)), true);
    }
}

// Session and security
if (!function_exists('startSecureSession')) {
    function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', 1);
            session_start();
        }
    }
}

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

// ===============================================================================
// TRENDS MODULE LOGIC
// ===============================================================================

// Get parameters
$selectedSiteId = $_GET['site'] ?? '';
$selectedWeek = $_GET['week'] ?? ''; // Format: 2025W02
$theme = $_GET['theme'] ?? 'dark';
$compareMode = $_GET['compare'] ?? 'none'; // none, previous, year

// Load sites
$sites = loadXML('sites.xml');
if (!$sites || !isset($sites->site)) {
    $sites = new SimpleXMLElement('<sites></sites>');
}

// Select first site if none selected
if (empty($selectedSiteId) && isset($sites->site[0])) {
    $selectedSiteId = (string)$sites->site[0]->id;
}

// Find current site
$currentSite = null;
foreach ($sites->site as $site) {
    if ((string)$site->id === $selectedSiteId) {
        $currentSite = $site;
        break;
    }
}

// ===============================================================================
// WEEK FUNCTIONS
// ===============================================================================

function getAllAvailableWeeks($siteId, $siteName, $storageType, $config) {
    $weeks = [];
    $oldestTimestamp = null;
    
    try {
        if ($storageType === 'mysql') {
            // Load from MySQL
            $storage = new StorageManager($config);
            
            // Get date range for all time
            $allTimeRange = [
                'start' => strtotime('-10 years'),
                'end' => time()
            ];
            
            $siteData = ['id' => $siteId, 'name' => $siteName];
            $data = $storage->loadAnalytics($storageType, $siteData, $allTimeRange);
            
            if (!empty($data)) {
                foreach ($data as $entry) {
                    $timestamp = strtotime($entry['date']);
                    if ($oldestTimestamp === null || $timestamp < $oldestTimestamp) {
                        $oldestTimestamp = $timestamp;
                    }
                }
            }
        } else {
            // Load from XML files
            $baseDir = rtrim($config['data_dir'], '/');
            $siteDir = $baseDir . '/' . preg_replace('/[^a-z0-9\-]/i', '-', $siteName);
            
            if (is_dir($siteDir)) {
                $files = glob($siteDir . '/*.xml');
                
                foreach ($files as $file) {
                    $fileDateStr = basename($file, '.xml');
                    $timestamp = strtotime($fileDateStr . ' UTC');
                    
                    if ($oldestTimestamp === null || $timestamp < $oldestTimestamp) {
                        $oldestTimestamp = $timestamp;
                    }
                }
            }
        }
        
        if ($oldestTimestamp === null) {
            return $weeks;
        }
        
        // Generate all weeks from oldest to current
        $currentWeekStart = strtotime('monday this week');
        $weekStart = strtotime('monday this week', $oldestTimestamp);
        
        while ($weekStart <= $currentWeekStart) {
            $weekEnd = strtotime('+6 days 23:59:59', $weekStart);
            $weekNumber = (int)date('W', $weekStart);
            $year = (int)date('o', $weekStart); // 'o' for ISO-8601 year
            
            $weeks[] = [
                'id' => $year . 'W' . str_pad($weekNumber, 2, '0', STR_PAD_LEFT),
                'year' => $year,
                'week' => $weekNumber,
                'start' => $weekStart,
                'end' => $weekEnd,
                'start_date' => date('Y-m-d', $weekStart),
                'end_date' => date('Y-m-d', $weekEnd),
                'label' => 'Week ' . $weekNumber . ' ' . $year,
                'date_range' => date('M d', $weekStart) . ' - ' . date('M d, Y', $weekEnd)
            ];
            
            $weekStart = strtotime('+7 days', $weekStart);
        }
        
        return array_reverse($weeks); // Newest first
        
    } catch (Exception $e) {
        error_log('Error getting available weeks: ' . $e->getMessage());
        return [];
    }
}

function parseWeekId($weekId) {
    if (preg_match('/^(\d{4})W(\d{2})$/', $weekId, $matches)) {
        $year = (int)$matches[1];
        $week = (int)$matches[2];
        
        $weekStart = strtotime($year . 'W' . str_pad($week, 2, '0', STR_PAD_LEFT));
        $weekEnd = strtotime('+6 days 23:59:59', $weekStart);
        
        return [
            'year' => $year,
            'week' => $week,
            'start' => $weekStart,
            'end' => $weekEnd,
            'start_date' => date('Y-m-d', $weekStart),
            'end_date' => date('Y-m-d', $weekEnd)
        ];
    }
    
    return null;
}

function getWeekStats($siteId, $siteName, $weekInfo, $storageType, $config) {
    $stats = [
        'total_visits' => 0,
        'unique_visitors' => [],
        'pages' => [],
        'countries' => [],
        'referrers' => [],
        'searches' => [],
        'daily_breakdown' => []
    ];
    
    try {
        // Initialize daily breakdown
        for ($i = 0; $i < 7; $i++) {
            $day = strtotime("+{$i} days", $weekInfo['start']);
            $dayName = date('D', $day);
            $stats['daily_breakdown'][$dayName] = [
                'date' => date('Y-m-d', $day),
                'visits' => 0,
                'unique' => []
            ];
        }
        
        // Load data from storage
        $storage = new StorageManager($config);
        $dateRange = [
            'start' => $weekInfo['start'],
            'end' => $weekInfo['end']
        ];
        
        $siteData = ['id' => $siteId, 'name' => $siteName];
        $data = $storage->loadAnalytics($storageType, $siteData, $dateRange);
        
        foreach ($data as $entry) {
            $stats['total_visits']++;
            
            // Pages
            $page = $entry['page_url'] ?? '/';
            $stats['pages'][$page] = ($stats['pages'][$page] ?? 0) + 1;
            
            // Countries
            if (!empty($entry['country'])) {
                $stats['countries'][$entry['country']] = ($stats['countries'][$entry['country']] ?? 0) + 1;
            }
            
            // Referrers
            if (!empty($entry['referrer']) && $entry['referrer'] !== 'direct') {
                $stats['referrers'][$entry['referrer']] = ($stats['referrers'][$entry['referrer']] ?? 0) + 1;
            }
            
            // Searches
            if (!empty($entry['search_query'])) {
                $stats['searches'][$entry['search_query']] = ($stats['searches'][$entry['search_query']] ?? 0) + 1;
            }
            
            // Daily breakdown
            $entryDate = $entry['date'];
            $dayName = date('D', strtotime($entryDate));
            
            if (isset($stats['daily_breakdown'][$dayName])) {
                $stats['daily_breakdown'][$dayName]['visits']++;
            }
        }
        
        // Sort and limit
        arsort($stats['pages']);
        arsort($stats['countries']);
        arsort($stats['referrers']);
        arsort($stats['searches']);
        
        // For unique visitors, we'll use a simplified approach
        // since we don't have visitor IDs in the current data structure
        $stats['unique_visitors_count'] = count($stats['unique_visitors']);
        
        // Calculate daily unique counts
        foreach ($stats['daily_breakdown'] as &$day) {
            $day['unique_count'] = count($day['unique']);
            unset($day['unique']);
        }
        
        return $stats;
        
    } catch (Exception $e) {
        error_log('Error getting week stats: ' . $e->getMessage());
        return $stats;
    }
}

function getComparisonStats($siteId, $siteName, $currentWeek, $compareMode, $storageType, $config) {
    if ($compareMode === 'none') {
        return null;
    }
    
    $compareWeek = null;
    
    if ($compareMode === 'previous') {
        // Previous week
        $prevStart = strtotime('-7 days', $currentWeek['start']);
        $compareWeek = parseWeekId(date('o', $prevStart) . 'W' . date('W', $prevStart));
    } elseif ($compareMode === 'year') {
        // Same week last year
        $yearAgo = strtotime('-1 year', $currentWeek['start']);
        $compareWeek = parseWeekId(date('o', $yearAgo) . 'W' . date('W', $yearAgo));
    }
    
    if ($compareWeek) {
        return getWeekStats($siteId, $siteName, $compareWeek, $storageType, $config);
    }
    
    return null;
}

function calculatePercentChange($old, $new) {
    if ($old == 0) return $new > 0 ? 100 : 0;
    return round((($new - $old) / $old) * 100, 1);
}

// ===============================================================================
// LOAD DATA
// ===============================================================================

$availableWeeks = [];
$currentWeekInfo = null;
$weekStats = null;
$comparisonStats = null;

if ($currentSite) {
    $storageType = strtolower((string)$currentSite->storage);
    $siteName = (string)$currentSite->name;
    
    $availableWeeks = getAllAvailableWeeks($selectedSiteId, $siteName, $storageType, $config);
    
    // Select current week if none selected
    if (empty($selectedWeek) && !empty($availableWeeks)) {
        $selectedWeek = $availableWeeks[0]['id'];
    }
    
    // Parse selected week
    if (!empty($selectedWeek)) {
        $currentWeekInfo = parseWeekId($selectedWeek);
        
        if ($currentWeekInfo) {
            $weekStats = getWeekStats($selectedSiteId, $siteName, $currentWeekInfo, $storageType, $config);
            
            if ($compareMode !== 'none') {
                $comparisonStats = getComparisonStats($selectedSiteId, $siteName, $currentWeekInfo, $compareMode, $storageType, $config);
            }
        }
    }
}

// Calculate changes if comparison is active
$changes = null;
if ($weekStats && $comparisonStats) {
    $changes = [
        'visits' => calculatePercentChange($comparisonStats['total_visits'], $weekStats['total_visits']),
        'unique' => calculatePercentChange($comparisonStats['unique_visitors_count'], $weekStats['unique_visitors_count'])
    ];
}

// Include HTML template
require_once __DIR__ . '/assets/trends-template.php';