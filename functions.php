<?php
// ===============================================================================
// PriviMetrics - Common Functions
// ===============================================================================

// Start secure session
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Lax'); // Changed from Strict to Lax for better compatibility
        ini_set('session.cookie_lifetime', 0); // Session cookie expires when browser closes
        ini_set('session.gc_maxlifetime', 86400); // 24 hours
        session_name('PRIVIMETRICS_SESSION');
        session_start();
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
}

// Get country from IP
function getCountryFromIP($ip) {
    global $config;
    
    $provider = $config['geo_provider'] ?? 'privacy-friendly'; // @user-config // Change to your preferred default provider: ip-api-com, ip2location-io, privacy-friendly
    $modulePath = __DIR__ . "/getCountryFrom/{$provider}.php";
    
    if (file_exists($modulePath)) {
        require_once $modulePath;
        
        if (function_exists('fetch_location')) {
            $data = fetch_location($ip);
            return [
                'country' => $data['country'] ?? 'Unknown',
                'code'    => $data['code'] ?? 'XX'
            ];
        }
    }
    
    return ['country' => 'Unknown', 'code' => 'XX'];
}

// Get date range
function getDateRange($range = '7d') {
    $now = time();
    $end = $now;

    switch ($range) {
        case '24h':
            $start = strtotime('today 00:00:00');
            $end   = strtotime('tomorrow 00:00:00') - 1;
            break;

        case '7d':
            $start = strtotime('-6 days 00:00:00');
            break;

        case '30d':
            $start = strtotime('-29 days 00:00:00');
            break;

        case '90d':
            $start = strtotime('-89 days 00:00:00');
            break;

        case '1y':
            $start = strtotime('first day of January ' . date('Y') . ' 00:00:00');
            $end   = strtotime('last day of December ' . date('Y') . ' 23:59:59');
            break;

        case '5y':
            $start = strtotime('first day of January ' . (date('Y') - 4) . ' 00:00:00');
            $end   = strtotime('last day of December ' . date('Y') . ' 23:59:59');
            break;

        default:
            $start = strtotime('-6 days 00:00:00');
    }

    return [
        'start' => $start,
        'end'   => $end
    ];
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Anonymize IP address
function anonymizeIP($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return preg_replace('/\.\d+$/', '.0', $ip);
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return preg_replace('/:[^:]+:[^:]+$/', ':0:0', $ip);
    }
    return '0.0.0.0';
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Get client IP
function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return $ip; // Return real IP for geo-location, will be anonymized when stored
}

// Get anonymized client IP
function getAnonymizedClientIP() {
    return anonymizeIP(getClientIP());
}

// Get user agent (sanitized)
function getUserAgent() {
    return sanitize($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
}

// Check if DNT is enabled
function isDNTEnabled() {
    return isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] == '1';
}

// Load XML file safely
function loadXMLFile($filepath, $rootElement = 'root') {
    if (!file_exists($filepath)) {
        $xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8'?><{$rootElement}></{$rootElement}>");
        $xml->asXML($filepath);
    }
    
    $content = file_get_contents($filepath);
    if ($content === false || empty(trim($content))) {
        $xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8'?><{$rootElement}></{$rootElement}>");
        $xml->asXML($filepath);
        return $xml;
    }
    
    try {
        return simplexml_load_file($filepath);
    } catch (Exception $e) {
        $xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8'?><{$rootElement}></{$rootElement}>");
        return $xml;
    }
}

// Save XML file safely
function saveXMLFile($xml, $filepath) {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    return $dom->save($filepath);
}

// Generate unique ID
function generateID() {
    return uniqid(bin2hex(random_bytes(4)), true);
}

// Validate domain
function validateDomain($domain) {
    $domain = strtolower(trim($domain));
    return filter_var('http://' . $domain, FILTER_VALIDATE_URL) !== false;
}

// Check domain match
function checkDomainMatch($allowed, $current, $mode) {
    $allowed = strtolower(trim($allowed));
    $current = strtolower(trim($current));
    
    // Remove protocol and path
    $allowed = preg_replace('#^https?://#', '', $allowed);
    $allowed = preg_replace('#/.*$#', '', $allowed);
    $current = preg_replace('#^https?://#', '', $current);
    $current = preg_replace('#/.*$#', '', $current);
    
    switch ($mode) {
        case 'full':
            // Main domain + all subdomains
            return $current === $allowed || preg_match('/\.' . preg_quote($allowed, '/') . '$/', $current);
        case 'main':
            // Main domain only (no subdomains)
            return $current === $allowed;
        case 'none':
            // No restrictions
            return true;
        default:
            return false;
    }
}

// Format number
function formatNumber($number) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    return $number;
}

// Rate limiting
function checkRateLimit($identifier, $maxAttempts = 60, $timeWindow = 60) {
    $cacheFile = __DIR__ . '/data/cache/rate_' . md5($identifier) . '.txt';
    
    if (!is_dir(__DIR__ . '/data/cache')) {
        mkdir(__DIR__ . '/data/cache', 0755, true);
    }
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data && $data['time'] > time() - $timeWindow) {
            if ($data['count'] >= $maxAttempts) {
                return false;
            }
            $data['count']++;
        } else {
            $data = ['count' => 1, 'time' => time()];
        }
    } else {
        $data = ['count' => 1, 'time' => time()];
    }
    
    file_put_contents($cacheFile, json_encode($data));
    return true;
}

// Clean old data
function cleanOldData($days = 365) {
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) return;
    
    $cutoff = time() - ($days * 86400);
    $files = glob($dataDir . '/*.xml');
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
        }
    }
}