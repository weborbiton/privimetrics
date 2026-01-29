<?php
// ===============================================================================
// PriviMetrics - Settings Panel
// ===============================================================================
require_once 'config.php';
require_once 'functions.php';
require_once 'extensions-load.php';

startSecureSession();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

$adminFile = 'admin.php';
$configFile = 'settings-config.php';
$limitsFile = 'chosen-limits.php';
$limitsOptionsFile = 'limits-options.php';
$tab = $_GET['tab'] ?? 'general';
$success = false;
$error = false;

$availableFonts = [
    'sora' => 'Sora',
    'space' => 'Space Grotesk',
    'moderno' => 'Moderno',
    'dm-mono' => 'DM Mono',
    'playwrite' => 'Playwrite',
    'hyperlegible' => 'Atkinson Hyperlegible Mono',
];

if (!file_exists($configFile)) {
    $defaultSettings = [
        'show_searches' => 'show', 'show_countries' => 'show', 'refresh_rate' => 30, 'ui_font' => 'sora'
    ];
    file_put_contents($configFile, "<?php\nreturn " . var_export($defaultSettings, true) . ";", LOCK_EX);
}
$interfaceConfig = include $configFile;

// Load limits configuration
$limitsOptions = file_exists($limitsOptionsFile) ? include $limitsOptionsFile : [];
$chosenLimits = file_exists($limitsFile) ? include $limitsFile : ['xml' => ['requests' => 2, 'window' => 1], 'mysql' => ['requests' => 5, 'window' => 1]];

$cacheFiles = glob('data/cache/*.txt') ?: [];
$totalCacheSize = 0;
foreach($cacheFiles as $file) {
    if(file_exists($file)) $totalCacheSize += filesize($file);
}
$cacheCount = count($cacheFiles);

function getFullDirectorySize($path) {
    $size = 0;
    $path = realpath($path);
    if($path && file_exists($path)){
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object){
            try { $size += $object->getSize(); } catch (Exception $e) {}
        }
    }
    return $size;
}
$totalSystemSize = getFullDirectorySize(__DIR__);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        if ($tab === 'general') {
            $newSettings = [
                'show_searches' => isset($_POST['show_searches']) ? 'show' : 'hide',
                'show_countries' => isset($_POST['show_countries']) ? 'show' : 'hide',
                'refresh_rate' => max(1, (int)($_POST['refresh_rate'] ?? 30)),
                'ui_font' => array_key_exists($_POST['ui_font'] ?? '', $availableFonts) ? $_POST['ui_font'] : 'sora',

                'limit_top_pages'      => max(1, (int)($_POST['limit_top_pages'] ?? 10)),
                'limit_top_referrers'  => max(1, (int)($_POST['limit_top_referrers'] ?? 10)),
                'limit_top_countries'  => max(1, (int)($_POST['limit_top_countries'] ?? 10)),
                'limit_top_searches'   => max(1, (int)($_POST['limit_top_searches'] ?? 10)),
            ];
            file_put_contents($configFile, "<?php\nreturn " . var_export($newSettings, true) . ";", LOCK_EX);
            $interfaceConfig = $newSettings;
            $success = "Settings updated successfully!";
        } 
        elseif ($tab === 'limits') {
            $xmlLimit = sanitize($_POST['xml_limit'] ?? '2req');
            $mysqlLimit = sanitize($_POST['mysql_limit'] ?? '5req');
            
            if (isset($limitsOptions['xml'][$xmlLimit]) && isset($limitsOptions['mysql'][$mysqlLimit])) {
                $newLimits = [
                    'xml' => [
                        'requests' => $limitsOptions['xml'][$xmlLimit]['requests'],
                        'window' => $limitsOptions['xml'][$xmlLimit]['window']
                    ],
                    'mysql' => [
                        'requests' => $limitsOptions['mysql'][$mysqlLimit]['requests'],
                        'window' => $limitsOptions['mysql'][$mysqlLimit]['window']
                    ]
                ];
                
                file_put_contents($limitsFile, "<?php\nreturn " . var_export($newLimits, true) . ";", LOCK_EX);
                $chosenLimits = $newLimits;
                $success = "Rate limits updated successfully!";
            } else {
                $error = "Invalid limit configuration selected.";
            }
        }
        elseif ($tab === 'security') {
            $newUser = sanitize($_POST['admin_user'] ?? '');
            $newPass = $_POST['admin_pass'] ?? '';
            if (!empty($newUser) && !empty($newPass)) {
                $adminContent = file_get_contents($adminFile);
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $userLine = "define('ADMIN_USERNAME', " . var_export($newUser, true) . ");";
                $safeHash = str_replace('$', '\\$', var_export($newHash, true));
                $hashLine = "define('ADMIN_PASSWORD_HASH', " . $safeHash . ");";
                $tempContent = preg_replace("/define\('ADMIN_USERNAME',.*?\);/", $userLine, $adminContent);
                $tempContent = preg_replace("/define\('ADMIN_PASSWORD_HASH',.*?\);/", $hashLine, $tempContent);
                if ($tempContent !== null && file_put_contents($adminFile, $tempContent, LOCK_EX)) {
                    $success = "Credentials updated!";
                } else { $error = "Write error on admin.php"; }
            }
        }
    }
}

// Find current selected option keys
$currentXmlKey = '2req';
$currentMysqlKey = '5req';
foreach ($limitsOptions['xml'] as $key => $option) {
    if ($option['requests'] == $chosenLimits['xml']['requests'] && $option['window'] == $chosenLimits['xml']['window']) {
        $currentXmlKey = $key;
        break;
    }
}
foreach ($limitsOptions['mysql'] as $key => $option) {
    if ($option['requests'] == $chosenLimits['mysql']['requests'] && $option['window'] == $chosenLimits['mysql']['window']) {
        $currentMysqlKey = $key;
        break;
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $_SESSION['theme'] ?? 'dark' ?>" data-font="<?= htmlspecialchars($interfaceConfig['ui_font'] ?? 'sora') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - PriviMetrics</title>
    <link rel="stylesheet" href="styles-mobile.css?v=<?= time(); ?>" media="screen and (max-width: 900px)">
    <link rel="stylesheet" href="styles.css?v=<?= time(); ?>" media="screen and (min-width: 901px)">
    <link rel="preload" href="fonts/sora.ttf" as="font" type="font/ttf" crossorigin>
    <style>
        .settings-layout { display: grid; grid-template-columns: 250px 1fr; gap: 32px; align-items: start; }
        .settings-menu { display: flex; flex-direction: column; gap: 4px; }
        .menu-item { 
            padding: 12px 16px; border-radius: 8px; color: var(--text-secondary); 
            text-decoration: none; font-size: 14px; transition: 0.2s; 
        }
        .menu-item:hover { background: var(--bg-tertiary); color: var(--text-primary); }
        .menu-item.active { background: var(--bg-tertiary); color: var(--accent); font-weight: 600; }
        
        .form-card { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; padding: 32px; }
        .setting-row { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 20px 0; border-bottom: 1px solid var(--border-color); 
        }
        .setting-row:last-of-type { border-bottom: none; }
        .setting-info h4 { margin-bottom: 4px; font-size: 16px; }
        .setting-info p { color: var(--text-tertiary); font-size: 13px; }

        .switch { position: relative; display: inline-block; width: 40px; height: 20px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { 
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; 
            background-color: var(--bg-tertiary); transition: .3s; border-radius: 20px; 
            border: 1px solid var(--border-color);
        }
        .slider:before { 
            position: absolute; content: ""; height: 14px; width: 14px; left: 2px; bottom: 2px; 
            background-color: var(--text-secondary); transition: .3s; border-radius: 50%; 
        }
        input:checked + .slider { background-color: var(--accent); border-color: var(--accent); }
        input:checked + .slider:before { transform: translateX(20px); background-color: white; }
        
        .health-val { font-family: 'Courier New', monospace; font-weight: 600; color: var(--text-primary); }
        
        .limit-select {
            min-width: 280px;
            padding: 10px 16px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
        }
        .limit-select:focus {
            outline: none;
            border-color: var(--accent);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <div class="logo-icon">
                <?php 
                $logo = $config['site_logo'];
                if (str_starts_with($logo, 'img:')) {
                    $logoFile = substr($logo, 4);
                    echo '<img src="' . htmlspecialchars($logoFile) . '" alt="' . htmlspecialchars($config['site_name']) . '" style="height:40px;">';
                } else {
                    echo '<span>' . htmlspecialchars($logo) . '</span>';
                }
                ?>
            </div>
            <div class="logo-text"><?= sanitize($config['site_name']) ?></div>
            <div class="logo-text"><span style="font-weight: 300;">| Settings</span></div>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            <a href="admin.php?logout=1" class="btn btn-secondary">Logout</a>
        </div>
    </header>

    <main class="container">
        <?php if ($success): ?>
            <div class="success-message" style="margin-bottom: 24px;">
                <span class="badge badge-success">Success</span> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message" style="margin-bottom: 24px; padding: 12px 16px; background: var(--error-bg); border: 1px solid var(--error-border); border-radius: 8px; color: var(--error-text);">
                <span class="badge badge-danger">Error</span> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="settings-layout">
            <aside class="settings-menu">
                <a href="?tab=general" class="menu-item <?= $tab === 'general' ? 'active' : '' ?>">Dashboard UI</a>
                <a href="?tab=limits" class="menu-item <?= $tab === 'limits' ? 'active' : '' ?>">Rate Limits</a>
                <a href="?tab=health" class="menu-item <?= $tab === 'health' ? 'active' : '' ?>">System Health</a>
                <a href="?tab=security" class="menu-item <?= $tab === 'security' ? 'active' : '' ?>">Access Security</a>
                <a href="extensions.php" class="menu-item">Extensions</a>
                <a href="updater/" class="menu-item">Updater</a>

                <?php 
                    $customTabs = ext_hook('custom_settings_tab', []);
                    if (!empty($customTabs)) {
                        foreach ($customTabs as $tabId => $tabName) {
                            echo '<a href="?tab='.htmlspecialchars($tabId).'" class="menu-item">'
                                .htmlspecialchars($tabName).'</a>';
                        }
                    }
                ?>
            </aside>

            <section class="form-card">
                <form method="POST" action="?tab=<?= htmlspecialchars($tab) ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <?php if ($tab === 'general'): ?>

                        <!-- UI -->
                        <div class="chart-header">UI Preferences</div>

                        <div class="setting-row">
                            <div class="setting-info">
                                <h4>Interface Font</h4>
                                <p>Choose the font used in the dashboard interface.</p>
                            </div>

                            <select name="ui_font" class="limit-select">
                                <?php foreach ($availableFonts as $fontKey => $fontName): ?>
                                    <option value="<?= htmlspecialchars($fontKey) ?>" <?= ($interfaceConfig['ui_font'] ?? 'sora') === $fontKey ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($fontName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="setting-row">
                            <div class="setting-info">
                                <h4>Countries Widget</h4>
                                <p>Show or hide total countries for the selected date range.</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="show_countries" <?= (!isset($interfaceConfig['show_countries']) || $interfaceConfig['show_countries'] === 'show') ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="setting-row">
                            <div class="setting-info">
                                <h4>Searches Widget</h4>
                                <p>Show or hide total searches widget for the selected date range.</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="show_searches" <?= ($interfaceConfig['show_searches'] ?? 'show') === 'show' ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <!-- Statistics Limits -->
                        <div class="chart-header" style="margin-top:60px;">Statistics Limits</div>
                        <div class="setting-row">
                            <div class="setting-info">
                                <h4>Top Pages</h4>
                                <p>Number of most visited pages shown</p>
                            </div>
                            <input type="number" name="limit_top_pages"
                                value="<?= (int)($interfaceConfig['limit_top_pages'] ?? 10) ?>"
                                class="btn btn-secondary" style="width:80px;text-align:center;">
                        </div>

                        <div class="setting-row">
                            <div class="setting-info">
                                <h4>Top Referrers</h4>
                                <p>Number of referrer domains shown</p>
                            </div>
                            <input type="number" name="limit_top_referrers"
                                value="<?= (int)($interfaceConfig['limit_top_referrers'] ?? 10) ?>"
                                class="btn btn-secondary" style="width:80px;text-align:center;">
                        </div>

                        <div class="setting-row">
                            <div class="setting-info">
                                <h4>Top Countries</h4>
                                <p>Number of countries displayed</p>
                            </div>
                            <input type="number" name="limit_top_countries"
                                value="<?= (int)($interfaceConfig['limit_top_countries'] ?? 10) ?>"
                                class="btn btn-secondary" style="width:80px;text-align:center;">
                        </div>

                        <div class="setting-row">
                            <div class="setting-info">
                                <h4>Top Searches</h4>
                                <p>Number of search queries displayed</p>
                            </div>
                            <input type="number" name="limit_top_searches"
                                value="<?= (int)($interfaceConfig['limit_top_searches'] ?? 10) ?>"
                                class="btn btn-secondary" style="width:80px;text-align:center;">
                        </div>

                        <!-- Other -->
                        <div class="chart-header" style="margin-top:60px;">Other</div>
                        <div class="setting-row">
                            <div class="setting-info">
                                <h4>Refresh Interval</h4>
                                <p>Seconds between automatic data reloads.</p>
                            </div>
                            <input type="number" name="refresh_rate" class="btn btn-secondary" style="width: 80px; text-align: center;" value="<?= (int)$interfaceConfig['refresh_rate'] ?>">
                        </div>

                    <?php elseif ($tab === 'limits'): ?>
                        <div class="chart-header">Rate Limiting Configuration</div>
                        <p style="color: var(--text-secondary); margin-bottom: 24px;">
                            Configure request limits to protect your server from overload. Higher limits allow more traffic but require stronger server resources.
                        </p>

                        <div class="setting-row">
                            <div class="setting-info">
                                <h4>XML Storage Limit</h4>
                                <p>Maximum requests per second for XML-based analytics storage.</p>
                            </div>
                            <select name="xml_limit" class="limit-select">
                                <?php foreach ($limitsOptions['xml'] as $key => $option): ?>
                                    <option value="<?= htmlspecialchars($key) ?>" <?= $key === $currentXmlKey ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($option['text']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="setting-row">
                            <div class="setting-info">
                                <h4>MySQL Storage Limit</h4>
                                <p>Maximum requests per second for MySQL database storage.</p>
                            </div>
                            <select name="mysql_limit" class="limit-select">
                                <?php foreach ($limitsOptions['mysql'] as $key => $option): ?>
                                    <option value="<?= htmlspecialchars($key) ?>" <?= $key === $currentMysqlKey ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($option['text']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="margin-top: 24px; padding: 16px; background: var(--bg-primary); border-radius: 8px; border: 1px solid var(--border-color);">
                            <h4 style="margin-bottom: 8px; font-size: 14px;">Current Configuration</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; font-size: 13px; color: var(--text-secondary);">
                                <div>
                                    <strong style="color: var(--text-primary);">XML:</strong> 
                                    <?= $chosenLimits['xml']['requests'] ?> request(s) per <?= $chosenLimits['xml']['window'] ?> second(s)
                                </div>
                                <div>
                                    <strong style="color: var(--text-primary);">MySQL:</strong> 
                                    <?= $chosenLimits['mysql']['requests'] ?> request(s) per <?= $chosenLimits['mysql']['window'] ?> second(s)
                                </div>
                            </div>
                        </div>

                    <?php elseif ($tab === 'health'): ?>
                        <div class="chart-header">System Diagnostics</div>
                        <div class="setting-row">
                            <div class="setting-info"><h4>Platform Version</h4><p>Current PHP engine status.</p></div>
                            <span class="badge badge-success">PHP <?= PHP_VERSION ?></span>
                        </div>
                        <div class="setting-row">
                            <div class="setting-info"><h4>Project Footprint</h4><p>Total size of all files in directory.</p></div>
                            <span class="health-val"><?= round($totalSystemSize / 1024 / 1024, 2) ?> MB</span>
                        </div>
                        <div class="setting-row">
                            <div class="setting-info"><h4>Cache Records</h4><p>Stored .txt files in /data/cache.</p></div>
                            <div style="text-align: right;">
                                <div class="health-val"><?= $cacheCount ?> files</div>
                                <small style="color: var(--text-tertiary);"><?= round($totalCacheSize / 1024, 1) ?> KB</small>
                            </div>
                        </div>
                        <div class="setting-row">
                            <div class="setting-info"><h4>Instant RAM Usage</h4><p>Memory used by this script request.</p></div>
                            <span class="health-val"><?= round(memory_get_usage() / 1024 / 1024, 2) ?> MB</span>
                        </div>

                    <?php elseif ($tab === 'security'): ?>
                        <div class="chart-header">Update Authentication</div>
                        <div class="form-group">
                            <label>New Administrator Username</label>
                            <input type="text" name="admin_user" placeholder="Enter new username" required>
                        </div>
                        <div class="form-group">
                            <label>New Administrator Password</label>
                            <input type="password" name="admin_pass" placeholder="••••••••" required>
                        </div>
                    <?php endif; ?>

                    <?php if ($tab !== 'health'): ?>
                        <div style="margin-top: 32px; display: flex; justify-content: flex-end;">
                            <button type="submit" name="save_settings" class="btn btn-primary">Apply Changes</button>
                        </div>
                    <?php endif; ?>
                </form>
            </section>
        </div>
    </main>
</body>
</html>