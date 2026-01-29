<?php
// ===============================================================================
// PriviMetrics - Public Statistics Page (Read-Only)
// ===============================================================================

if (!file_exists('config.php')) {
    die('Configuration file not found.');
}
require_once 'config.php';

if (!file_exists('functions.php')) {
    die('Functions file not found.');
}
require_once 'functions.php';

if (!file_exists('storage.php')) {
    die('Storage file not found.');
}
require_once 'storage.php';

// Security: Rate limiting to prevent abuse
$clientIP = getClientIP();
if (!checkRateLimit('public_stats_' . $clientIP, 30, 60)) {
    http_response_code(429);
    die('Too many requests. Please try again later.');
}

// Get tracking code from URL parameter
$trackingCode = sanitize($_GET['site'] ?? '');
if (empty($trackingCode)) {
    http_response_code(400);
    die('Invalid request. Site parameter is required.');
}

// Configurable days - can be overridden per site or globally
$publicDays = (int)($config['public_stats_days'] ?? 30);
$publicDays = max(1, min(90, $publicDays)); // Limit between 1-90 days

// Load sites and find the matching site by tracking code
$sites = loadXMLFile($config['sites_file'], 'sites');
$currentSite = null;

foreach ($sites->site as $site) {
    if ((string)$site->tracking_code === $trackingCode && (string)$site->active === 'true') {
        $currentSite = $site;
        break;
    }
}

if (!$currentSite) {
    http_response_code(404);
    die('Site not found or statistics not available.');
}

// Check if public stats are enabled for this site (optional feature)
$publicStatsEnabled = !isset($currentSite->public_stats) || (string)$currentSite->public_stats !== 'false';
if (!$publicStatsEnabled) {
    http_response_code(403);
    die('Public statistics are not enabled for this site.');
}

// Get date range (last X days)
$endTime = time();
$startTime = strtotime("-" . ($publicDays - 1) . " days 00:00:00");
$dateRange = ['start' => $startTime, 'end' => $endTime];

// Load analytics data securely through storage manager
$storageType = (string)($currentSite->storage ?? 'xml');
$storageManager = new StorageManager($config);

$siteData = [
    'id' => (string)$currentSite->id,
    'name' => (string)$currentSite->name
];

$analyticsData = $storageManager->loadAnalytics($storageType, $siteData, $dateRange);

// Process analytics data - NO SENSITIVE DATA
$stats = [
    'total_visits' => count($analyticsData),
    'unique_pages' => 0,
    'top_pages' => [],
    'top_countries' => [],
    'top_referrers' => [],
];

$pages = [];
$countries = [];
$referrers = [];
$visitsByDate = [];

foreach ($analyticsData as $visit) {
    // Pages
    $url = (string)$visit['page_url'];
    if (!isset($pages[$url])) {
        $pages[$url] = ['url' => $url, 'title' => (string)$visit['page_title'], 'count' => 0];
    }
    $pages[$url]['count']++;
    
    // Countries
    $country = (string)$visit['country'];
    $countryCode = (string)$visit['country_code'];
    if (!empty($country) && $country !== 'Unknown') {
        $countryKey = $country . '|' . $countryCode;
        if (!isset($countries[$countryKey])) {
            $countries[$countryKey] = ['name' => $country, 'code' => $countryCode, 'count' => 0];
        }
        $countries[$countryKey]['count']++;
    }
    
    // Referrers (external only)
    $ref = (string)$visit['referrer'];
    if (!empty($ref) && $ref !== 'direct') {
        if (!isset($referrers[$ref])) {
            $referrers[$ref] = 0;
        }
        $referrers[$ref]++;
    }
    
    // Visits by date
    $date = (string)$visit['date'];
    if (!isset($visitsByDate[$date])) {
        $visitsByDate[$date] = 0;
    }
    $visitsByDate[$date]++;
}

// Sort and prepare data
$pagesList = array_values($pages);
usort($pagesList, function($a, $b) {
    return $b['count'] - $a['count'];
});

$countriesList = array_values($countries);
usort($countriesList, function($a, $b) {
    return $b['count'] - $a['count'];
});

arsort($referrers);

$stats['unique_pages'] = count($pagesList);
$stats['top_pages'] = array_slice($pagesList, 0, 10);
$stats['top_countries'] = array_slice($countriesList, 0, 10);
$stats['top_referrers'] = array_slice($referrers, 0, 10, true);

// Fill in missing dates
$allDates = [];
$current = $startTime;
while ($current <= $endTime) {
    $dateKey = gmdate('Y-m-d', $current);
    $allDates[$dateKey] = $visitsByDate[$dateKey] ?? 0;
    $current = strtotime('+1 day', $current);
}
ksort($allDates);

$theme = sanitize($_GET['theme'] ?? 'dark');
$theme = in_array($theme, ['dark', 'light']) ? $theme : 'dark';

$siteName = sanitize((string)$currentSite->name);
$siteLogo = (string)($config['site_logo'] ?? 'PM');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Statistics - <?= $siteName ?></title>
    <meta name="robots" content="index,follow">
    <style>
        :root[data-theme="dark"] {
            --bg-primary: #0a0a0a;
            --bg-secondary: #151515;
            --bg-tertiary: #1a1a1a;
            --border-color: #252525;
            --text-primary: #e5e5e5;
            --text-secondary: #a0a0a0;
            --text-tertiary: #707070;
            --accent: #f1484e;
            --accent-hover: #d53b40;
        }

        :root[data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f9fafb;
            --bg-tertiary: #f3f4f6;
            --border-color: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-tertiary: #9ca3af;
            --accent: #f1484e;
            --accent-hover: #d53b40;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
        }

        .logo-text {
            font-size: 18px;
            font-weight: 600;
        }

        .badge {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 13px;
            border: 1px solid var(--border-color);
        }

        .theme-toggle {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 8px 12px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .theme-toggle:hover {
            background: var(--bg-secondary);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--accent);
        }

        .chart-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .chart-header {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-primary);
        }

        .chart {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            height: 200px;
            gap: 4px;
            padding: 10px 0;
        }

        .bar {
            flex: 1;
            background: var(--accent);
            border-radius: 4px 4px 0 0;
            min-height: 2px;
            transition: opacity 0.2s;
            cursor: pointer;
        }

        .bar:hover {
            opacity: 0.8;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            border-bottom: 2px solid var(--border-color);
        }

        th {
            text-align: left;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--border-color);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: var(--bg-tertiary);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .footer {
            text-align: center;
            padding: 32px 24px;
            color: var(--text-tertiary);
            font-size: 13px;
            border-top: 1px solid var(--border-color);
            margin-top: 48px;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .chart {
                height: 150px;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <div class="logo-icon">
                    <?php 
                    if (str_starts_with($siteLogo, 'img:')) {
                        $logoFile = substr($siteLogo, 4);
                        echo '<img src="' . htmlspecialchars($logoFile) . '" alt="' . $siteName . '" style="max-width:100%; max-height:100%;">';
                    } else {
                        echo htmlspecialchars($siteLogo);
                    }
                    ?>
                </div>
                <div>
                    <div class="logo-text">
                        <?= $siteName ?>
                    </div>
                    <div class="badge">Public Statistics</div>
                </div>
            </div>
            <a href="?site=<?= htmlspecialchars($trackingCode) ?>&theme=<?= $theme === 'dark' ? 'light' : 'dark' ?>"
                class="btn btn-secondary" style="width:40px; height:40px; display:flex; align-items:center; justify-content:center; border-radius:8px;">
                <?= $theme === 'dark' ? '<svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"> <circle cx="12" cy="12" r="5" /> <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" /></svg>' : '<svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"> <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" /></svg>' ?>
            </a>
        </div>
    </header>

    <main class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Visits (Last
                    <?= $publicDays ?> Days)
                </div>
                <div class="stat-value">
                    <?= number_format($stats['total_visits']) ?>
                </div>
            </div>
        </div>

        <?php if ($stats['total_visits'] > 0): ?>
        <div class="chart-card">
            <div class="chart-header">Visits Over Time (Last
                <?= $publicDays ?> Days)
            </div>
            <div class="chart">
                <?php
                    $maxVisits = max(array_values($allDates));
                    if ($maxVisits === 0) $maxVisits = 1;
                    
                    foreach ($allDates as $date => $count):
                        $heightPercent = ($count / $maxVisits) * 100;
                        if ($heightPercent < 2 && $count > 0) $heightPercent = 2;
                    ?>
                <div class="bar" title="<?= htmlspecialchars($date) ?>: <?= $count ?> visits"
                    style="height: <?= $heightPercent ?>%;"></div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php else: ?>
        <div class="chart-card">
            <div class="empty-state">
                <div class="empty-state-icon">📊</div>
                <h3>No Data Available</h3>
                <p>No statistics have been recorded for the last
                    <?= $publicDays ?> days.
                </p>
            </div>
        </div>
        <?php endif; ?>

        <br><br><br><br><br><br><br><br><br><br><br><br><br><br>

        <div class="footer">
            Powered by PriviMetrics
            <br>
            Data shown: Last
            <?= $publicDays ?> days
        </div>
    </main>
</body>

</html>