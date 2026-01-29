<?php
// ===============================================================================
// PriviMetrics - Dashboard Logic
// ===============================================================================

function initializeDashboard($config, $settings) {
    $config = ext_hook('before_dashboard_init', $config);

    // Load rate limits
    $chosenLimits = file_exists('chosen-limits.php') ? include 'chosen-limits.php' : [
        'xml' => ['requests' => 2, 'window' => 1],
        'mysql' => ['requests' => 5, 'window' => 1]
    ];

    // Version check
    $currentVersion = '0.0.0';
    $versionFile = __DIR__ . '/../version.txt';
    if (file_exists($versionFile)) {
        if (preg_match('/Version:\s*([0-9.]+)/', file_get_contents($versionFile), $m)) {
            $currentVersion = $m[1];
        }
    }

    $latestVersion = checkLatestVersion12h();
    $newVersionAvailable = version_compare($latestVersion, $currentVersion, '>');

    // CSRF token
    $csrfToken = generateCSRFToken();

    // Load sites
    $sites = loadXMLFile($config['sites_file'], 'sites');

    // Get parameters
    $selectedSiteId = sanitize($_GET['site'] ?? '');
    $dateRange = sanitize($_GET['range'] ?? '7d');
    $theme = sanitize($_GET['theme'] ?? $_SESSION['theme'] ?? $config['default_theme'] ?? 'dark');
    $view = sanitize($_GET['view'] ?? 'overview');

    $_SESSION['theme'] = $theme;

    // Periodic cache cleanup (1% chance)
    if (rand(1, 100) === 1) {
        cleanupCache();
    }

    // Handle POST actions
    $success = null;
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid security token';
        } else {
            $result = handleSiteAction($_POST, $sites, $config);
            $success = $result['success'] ?? null;
            $error = $result['error'] ?? null;
            $sites = $result['sites'];
        }
    }

    // Get first site if none selected
    if (empty($selectedSiteId) && isset($sites->site[0])) {
        $selectedSiteId = (string)$sites->site[0]->id;
    }

    // Load analytics
    $analyticsData = [];
    $currentSite = null;

    if (!empty($selectedSiteId)) {
        foreach ($sites->site as $site) {
            if ((string)$site->id === $selectedSiteId) {
                $currentSite = $site;
                break;
            }
        }

        if ($currentSite) {
            $range = getDateRange($dateRange);
            $storageType = (string)($currentSite->storage ?? 'xml');
            $storageManager = new StorageManager($config);

            $beforeAnalyticsPayload = compact('currentSite', 'range', 'storageType');
            $beforeAnalyticsPayload = ext_hook('before_analytics_load', $beforeAnalyticsPayload);
            $currentSite = $beforeAnalyticsPayload['currentSite'] ?? $currentSite;
            $range = $beforeAnalyticsPayload['range'] ?? $range;
            $storageType = $beforeAnalyticsPayload['storageType'] ?? $storageType;

            $siteData = [
                'id' => (string)$currentSite->id,
                'name' => (string)$currentSite->name
            ];

            $analyticsData = $storageManager->loadAnalytics($storageType, $siteData, $range);

            $afterAnalyticsPayload = ext_hook('after_analytics_load', compact('currentSite', 'analyticsData'));
            $analyticsData = $afterAnalyticsPayload['analyticsData'] ?? $analyticsData;
        }
    }

    // Process stats
    $beforeStatsPayload = ext_hook('before_stats', compact('analyticsData', 'settings'));
    $analyticsData = $beforeStatsPayload['analyticsData'] ?? $analyticsData;
    $settings = $beforeStatsPayload['settings'] ?? $settings;

    $stats = processAnalytics($analyticsData, $settings);

    $afterStatsPayload = ext_hook('after_stats', compact('stats', 'analyticsData', 'settings'));
    $stats = $afterStatsPayload['stats'] ?? $stats;

    // Refresh rate
    $refreshRateSeconds = calculateRefreshRate($dateRange, $settings);
    $refreshMs = $refreshRateSeconds * 1000;

    // Build final payload
    $data = compact(
        'config', 'settings', 'chosenLimits', 'currentVersion', 'latestVersion',
        'newVersionAvailable', 'csrfToken', 'sites', 'selectedSiteId', 'dateRange',
        'theme', 'view', 'success', 'error', 'currentSite', 'analyticsData',
        'stats', 'refreshMs'
    );

    $data = ext_hook('after_dashboard_init', $data);

    return $data;
}

function cleanupCache() {
    $cacheDir = __DIR__ . '/../data/cache';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/rate_*.txt');
        $now = time();
        foreach ($files as $file) {
            if (($now - filemtime($file)) > 3600) {
                @unlink($file);
            }
        }
    }
}

function handleSiteAction($post, $sites, $config) {
    $action = sanitize($post['action'] ?? '');
    $success = null;
    $error = null;

    if ($action === 'add_site') {
        $result = addSite($post, $sites, $config);
        $success = $result['success'];
        $error = $result['error'];
        $sites = $result['sites'];
    } elseif ($action === 'delete_site') {
        $result = deleteSite($post, $sites, $config);
        $success = $result['success'];
        $error = $result['error'];
        $sites = $result['sites'];
    } elseif ($action === 'toggle_site') {
        $result = toggleSite($post, $sites, $config);
        $success = $result['success'];
        $sites = $result['sites'];
    }

    return compact('success', 'error', 'sites');
}

function addSite($post, $sites, $config) {
    $siteName = sanitize($post['site_name'] ?? '');
    $domain = sanitize($post['domain'] ?? '');
    $restrictionMode = sanitize($post['restriction_mode'] ?? 'full');
    $storageType = sanitize($post['storage_type'] ?? 'xml');

    $allowedStorageTypes = ['xml', 'mysql'];
    if (!in_array($storageType, $allowedStorageTypes)) {
        $storageType = 'xml';
    }

    $allowedRestrictionModes = ['full', 'main', 'none'];
    if (!in_array($restrictionMode, $allowedRestrictionModes)) {
        $restrictionMode = 'full';
    }

    if (!empty($siteName) && !empty($domain) && validateDomain($domain)) {
        $site = $sites->addChild('site');
        $site->addChild('id', generateID());
        $site->addChild('name', $siteName);
        $site->addChild('domain', $domain);
        $site->addChild('restriction_mode', $restrictionMode);
        $site->addChild('storage', $storageType);
        $site->addChild('tracking_code', bin2hex(random_bytes(16)));
        $site->addChild('created', gmdate('Y-m-d H:i:s'));
        $site->addChild('active', 'true');

        saveXMLFile($sites, $config['sites_file']);
        $sites = loadXMLFile($config['sites_file'], 'sites');

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        return ['success' => null, 'error' => 'Invalid site data provided', 'sites' => $sites];
    }
}

function deleteSite($post, $sites, $config) {
    $siteId = sanitize($post['site_id'] ?? '');
    $deleted = false;
    $index = 0;

    foreach ($sites->site as $site) {
        if ((string)$site->id === $siteId) {
            unset($sites->site[$index]);
            if (saveXMLFile($sites, $config['sites_file'])) {
                $deleted = true;
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                return ['success' => null, 'error' => 'Failed to delete site', 'sites' => $sites];
            }
        }
        $index++;
    }

    if (!$deleted) {
        return ['success' => null, 'error' => 'Site not found', 'sites' => $sites];
    }
}

function toggleSite($post, $sites, $config) {
    $siteId = sanitize($post['site_id'] ?? '');
    foreach ($sites->site as $site) {
        if ((string)$site->id === $siteId) {
            $site->active = (string)$site->active === 'true' ? 'false' : 'true';
            saveXMLFile($sites, $config['sites_file']);
            break;
        }
    }
    $sites = loadXMLFile($config['sites_file'], 'sites');
    return ['success' => 'Site status updated', 'sites' => $sites];
}

function processAnalytics($analyticsData, $settings) {
    $stats = [
        'total_visits' => count($analyticsData),
        'unique_pages' => 0,
        'top_pages' => [],
        'top_referrers' => [],
        'top_countries' => [],
        'top_searches' => [],
    ];

    $pages = [];
    $referrers = [];
    $countries = [];
    $searches = [];

    foreach ($analyticsData as $visit) {
        $url = (string)$visit['page_url'];
        $ref = (string)$visit['referrer'];
        $country = (string)$visit['country'];
        $countryCode = (string)$visit['country_code'];

        if (!isset($pages[$url])) {
            $pages[$url] = ['url' => $url, 'title' => (string)$visit['page_title'], 'count' => 0];
        }
        $pages[$url]['count']++;

        if (!empty($ref) && $ref !== 'direct') {
            if (!isset($referrers[$ref])) {
                $referrers[$ref] = 0;
            }
            $referrers[$ref]++;
        }

        if (!empty($country) && $country !== 'Unknown') {
            $countryKey = $country . '|' . $countryCode;
            if (!isset($countries[$countryKey])) {
                $countries[$countryKey] = ['name' => $country, 'code' => $countryCode, 'count' => 0];
            }
            $countries[$countryKey]['count']++;
        }

        $searchQuery = isset($visit['search_query']) ? trim((string)$visit['search_query']) : '';
        if (!empty($searchQuery)) {
            if (!isset($searches[$searchQuery])) {
                $searches[$searchQuery] = 0;
            }
            $searches[$searchQuery]++;
        }
    }

    $limits = [
        'pages'      => max(1, (int)($settings['limit_top_pages'] ?? 10)),
        'referrers'  => max(1, (int)($settings['limit_top_referrers'] ?? 10)),
        'countries'  => max(1, (int)($settings['limit_top_countries'] ?? 10)),
        'searches'   => max(1, (int)($settings['limit_top_searches'] ?? 10)),
    ];

    $pagesList = array_values($pages);
    usort($pagesList, function($a, $b) {
        return $b['count'] - $a['count'];
    });

    $countriesList = array_values($countries);
    usort($countriesList, function($a, $b) {
        return $b['count'] - $a['count'];
    });

    arsort($referrers);
    arsort($searches);

    $stats['unique_pages'] = count($pagesList);
    $stats['top_pages'] = array_slice($pagesList, 0, $limits['pages']);
    $stats['top_referrers'] = array_slice($referrers, 0, $limits['referrers'], true);
    $stats['top_countries'] = array_slice($countriesList, 0, $limits['countries']);
    $stats['top_searches'] = array_slice($searches, 0, $limits['searches'], true);

    return $stats;
}

function calculateRefreshRate($dateRange, $settings) {
    $refreshRateSeconds = isset($settings['refresh_rate']) ? (int)$settings['refresh_rate'] : 60;

    if ($dateRange === '24h') {
        $refreshRateSeconds = max(30, $refreshRateSeconds);
    } elseif ($dateRange === '7d') {
        $refreshRateSeconds = max(60, $refreshRateSeconds);
    } else {
        $refreshRateSeconds = max(120, $refreshRateSeconds);
    }

    return $refreshRateSeconds;
}
