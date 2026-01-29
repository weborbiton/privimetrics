<?php
// ===============================================================================
// PriviMetrics - Privacy-Focused Web Analytics Tracker
// ===============================================================================
require_once 'extensions-load.php';

if (!file_exists('config.php')) {
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}
require_once 'config.php';

if (!file_exists('functions.php')) {
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}
require_once 'functions.php';

if (!file_exists('storage.php')) {
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}
require_once 'storage.php';

header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

if (!empty($config['respect_dnt']) && isDNTEnabled()) {
    echo $gif;
    exit;
}

$trackingCode = sanitize($_GET['t'] ?? '');
$pageUrl      = sanitize($_GET['p'] ?? '');
$pageTitle    = sanitize($_GET['title'] ?? '');
$referrer     = sanitize($_GET['r'] ?? '');
$hasJS        = isset($_GET['js']) && $_GET['js'] === '1';

$parsedUrl = parse_url($pageUrl);
$cleanUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
if (isset($parsedUrl['port'])) $cleanUrl .= ':' . $parsedUrl['port'];
$cleanUrl .= $parsedUrl['path'] ?? '';

$searchQuery = '';
if (!empty($parsedUrl['query'])) {
    parse_str($parsedUrl['query'], $queryParams);
    foreach (['q', 'search', 'results'] as $param) {
        if (!empty($queryParams[$param])) {
            $searchQuery = sanitize($queryParams[$param]);
            break;
        }
    }
}

function isExternalReferrer(string $referrer, string $pageUrl): bool {
    if ($referrer === '' || $pageUrl === '') return false;

    $refHost  = parse_url($referrer, PHP_URL_HOST);
    $pageHost = parse_url($pageUrl, PHP_URL_HOST);

    if (!$refHost || !$pageHost) return false;

    $refHost  = preg_replace('/^www\./i', '', strtolower($refHost));
    $pageHost = preg_replace('/^www\./i', '', strtolower($pageHost));

    return $refHost !== $pageHost;
}

function getReferrerDomain(string $referrer): string {
    $host = parse_url($referrer, PHP_URL_HOST);
    if (!$host) return '';
    return preg_replace('/^www\./i', '', strtolower($host));
}

if ($trackingCode === '') {
    echo $gif;
    exit;
}

$sites = loadXMLFile($config['sites_file'], 'sites');
$currentSite = null;
foreach ($sites->site as $site) {
    if ((string)$site->tracking_code === $trackingCode && (string)$site->active === 'true') {
        $domain = parse_url($pageUrl, PHP_URL_HOST) ?? '';
        if (checkDomainMatch((string)$site->domain, $domain, (string)$site->restriction_mode)) {
            $currentSite = $site;
            break;
        }
    }
}
if (!$currentSite) {
    echo $gif;
    exit;
}

$storageType = strtolower((string)($currentSite->storage ?? 'xml'));

$rateLimits = require 'chosen-limits.php';
$rateLimit = $rateLimits[$storageType] ?? ['requests' => 2, 'window' => 1];

if ($rateLimit['requests'] === 0) {
    http_response_code(204);
    exit;
}

if (!checkRateLimit('privimetrics_'.$trackingCode, $rateLimit['requests'], $rateLimit['window'])) {
    http_response_code(204);
    exit;
}

$trackIpParam = $_GET['track-ip'] ?? 'true';
$trackIp = !in_array(strtolower($trackIpParam), ['0', 'false', 'no'], true);

if ($trackIp) {
    $realIP = getClientIP();
    $anonIP = '0.0.0.0';
    if (filter_var($realIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $realIP);
        $anonIP = $parts[0] . '.' . $parts[1] . '.X.X';
    } elseif (filter_var($realIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $realIP);
        $anonIP = $parts[0] . ':' . $parts[1] . ':XXXX:XXXX:XXXX:XXXX:XXXX:XXXX';
    }

    $geo = getCountryFromIP($realIP);
    $userHash = md5($anonIP . '|' . getUserAgent());
} else {
    $anonIP = '0.0.0.0';
    $geo = getCountryFromIP($anonIP);
    $userHash = md5('anon|' . getUserAgent());
}

$storageType = (string)($currentSite->storage ?? 'xml');
$storageManager = new StorageManager($config);

$siteData = [
    'id' => (string)$currentSite->id,
    'name' => (string)$currentSite->name
];

$trackingData = [
    'user_hash' => $userHash,
    'ip' => $anonIP,
    'country' => $geo['country'] ?? 'Unknown',
    'country_code' => $geo['code'] ?? 'XX',
    'user_agent' => getUserAgent(),
    'page_url' => $cleanUrl,
    'page_title' => $pageTitle,
    'search_query' => $searchQuery,
    'referrer' => isExternalReferrer($referrer, $pageUrl) ? getReferrerDomain($referrer) : ''
];

$storageManager->saveTracking($storageType, $siteData, $trackingData);

echo $gif;
exit;