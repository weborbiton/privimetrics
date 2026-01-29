<?php
// ===============================================================================
// PriviMetrics - Dashboard HTML Template
// ===============================================================================

$showSearchesSetting = strtolower(trim((string)($settings['show_searches'] ?? 'show')));
?>
<!DOCTYPE html>
<?php ext_hook('before_render'); ?>
<html lang="en" data-theme="<?= $theme ?>" data-font="<?= htmlspecialchars($settings['ui_font'] ?? 'sora') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= sanitize($config['site_name']) ?></title>
    <link rel="stylesheet" media="screen and (max-width: 900px)" href="styles-mobile.css?v=<?= time(); ?>">
    <link rel="stylesheet" media="screen and (min-width: 901px)" href="styles.css?v=<?= time(); ?>" >
    <link rel="preload" href="fonts/sora.ttf" as="font" type="font/ttf" crossorigin>
</head>
<body>

    <?php
        $UpdateNotify = true; // @user-config // Change to false to disable update notifications
        // If you want to completely disable checking for updates, disable the feature in new_version.php ($enableVersionCheck)
        if ($UpdateNotify && isset($newVersionAvailable) && $newVersionAvailable) { ?>
        <div id="updateModal" class="update-modal">
            <div class="update-modal-content">
                <div class="update-modal-header">
                    <strong>New Version Available</strong>
                    <span class="close" onclick="tempCloseUpdateModal()">&times;</span>
                </div>
                <div class="update-modal-body">
                    <p>Current version:
                        <?= htmlspecialchars($currentVersion) ?>
                    </p>
                    <p>Latest version:
                        <?= htmlspecialchars($latestVersion) ?>
                    </p>
                    <p>Download the latest version and update your installation.</p>
                    <p><a href="https://weborbiton.click/update-privimetrics/" class="update-modal-text">How to update</a></p>
                </div>
                <div class="update-modal-footer">
                    <button class="btn btn-secondary" onclick="permanentCloseUpdateModal()">Close</button>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                if (!sessionStorage.getItem('updateModalClosed')) {
                    document.getElementById('updateModal').style.display = 'flex';
                }
            });
            function tempCloseUpdateModal() {
                document.getElementById('updateModal').style.display = 'none';
            }
            function permanentCloseUpdateModal() {
                document.getElementById('updateModal').style.display = 'none';
                sessionStorage.setItem('updateModalClosed', 'true');
            }
        </script>
    <?php } ?>

    <div class="header">
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
        </div>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="openAddSiteModal()">+ Add Site</button>

            <a href="?site=<?= $selectedSiteId ?>&range=<?= $dateRange ?>&theme=<?= $theme === 'dark' ? 'light' : 'dark' ?>&view=<?= $view ?>" class="btn btn-secondary">
                <?= $theme === 'dark' ? '<svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"> <circle cx="12" cy="12" r="5" /> <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" /></svg>' : '<svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"> <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" /></svg>' ?>
            </a>
            <a href="settings.php" class="btn btn-secondary">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                Settings
            </a>
            <a href="admin.php?logout=1" class="btn btn-secondary">Logout</a>
        </div>
    </div>

    <div class="container">
        <div style="text-align: right; font-size: 11px; color: var(--text-tertiary); margin-bottom: 16px;">
            Last updated: <span id="lastUpdate"><?= date('H:i:s') ?></span>
        </div>

        <?php if (isset($success)): ?>
        <div class="success-message"><?= sanitize($success) ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="error" style="margin-bottom: 20px;"><?= sanitize($error) ?></div>
        <?php endif; ?>

        <?php if ($currentSite): ?>
        <div class="toolbar">
            <div class="toolbar-left">
                <select onchange="window.location.href='?site=' + this.value + '&range=<?= $dateRange ?>&theme=<?= $theme ?>&view=<?= $view ?>'">
                    <?php foreach ($sites->site as $site): ?>
                    <option value="<?= (string)$site->id ?>" <?=(string)$site->id === $selectedSiteId ? 'selected' : '' ?>>
                        <?= sanitize((string)$site->name) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <select onchange="window.location.href='?site=<?= $selectedSiteId ?>&range=' + this.value + '&theme=<?= $theme ?>&view=<?= $view ?>'">
                    <option value="24h" <?=$dateRange==='24h' ? 'selected' : '' ?>>Today</option>
                    <option value="7d" <?=$dateRange==='7d' ? 'selected' : '' ?>>Last 7 days</option>
                    <option value="30d" <?=$dateRange==='30d' ? 'selected' : '' ?>>Last 30 days</option>
                    <option value="90d" <?=$dateRange==='90d' ? 'selected' : '' ?>>Last 90 days</option>
                    <option value="1y" <?=$dateRange==='1y' ? 'selected' : '' ?>>This year</option>
                    <option value="5y" <?=$dateRange==='5y' ? 'selected' : '' ?>>Last 5 years</option>
                </select>
            </div>

            <div class="toolbar-left">
                <button class="btn btn-sm btn-secondary" onclick="window.open('trends.php' + window.location.search, '_self')">Trends</button>
                <button class="btn btn-sm btn-secondary" onclick="openTrackingModal()">Get Code</button>
                <button class="btn btn-sm btn-secondary" onclick="openManageModal()">Manage</button>

                <?php 
                $storageType = strtolower((string)$currentSite->storage);
                $limit = $chosenLimits[$storageType] ?? ['requests' => 0, 'window' => 1];
                ?>
                <span class="badge badge-<?= $limit['requests'] >= 10 ? 'success' : 'warning' ?>"
                    title="Current rate limit for <?= strtoupper($storageType) ?> storage"
                    style="cursor: help; padding: 6px 12px;">
                    <?= $limit['requests'] ?> req/s
                </span>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Visits</div>
                <div class="stat-value"><?= formatNumber($stats['total_visits']) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Unique Pages</div>
                <div class="stat-value"><?= formatNumber($stats['unique_pages']) ?></div>
            </div>

            <?php if (strtolower(trim((string)($settings['show_countries'] ?? 'show'))) !== 'hide'): ?>
            <div class="stat-card">
                <div class="stat-label">Countries</div>
                <div class="stat-value"><?= count($stats['top_countries']) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($showSearchesSetting !== 'hide'): ?>
            <div class="stat-card">
                <div class="stat-label">Searches</div>
                <div class="stat-value"><?= formatNumber(array_sum($stats['top_searches'])) ?></div>
            </div>
            <?php endif; ?>

            <?php echo ext_hook('custom_dashboard_widget', ''); ?>
        </div>

        <?php if ($stats['total_visits'] > 0): ?>
        <?php require __DIR__ . '/dashboard-chart.php'; ?>

        <div class="tabs">
            <button class="tab <?= $view === 'overview' ? 'active' : '' ?>"
                onclick="window.location.href='?site=<?= $selectedSiteId ?>&range=<?= $dateRange ?>&theme=<?= $theme ?>&view=overview'">Pages</button>
            <button class="tab <?= $view === 'countries' ? 'active' : '' ?>"
                onclick="window.location.href='?site=<?= $selectedSiteId ?>&range=<?= $dateRange ?>&theme=<?= $theme ?>&view=countries'">Countries</button>
            <button class="tab <?= $view === 'referrers' ? 'active' : '' ?>"
                onclick="window.location.href='?site=<?= $selectedSiteId ?>&range=<?= $dateRange ?>&theme=<?= $theme ?>&view=referrers'">Referrers</button>
            <button class="tab <?= $view === 'searches' ? 'active' : '' ?>"
                onclick="window.location.href='?site=<?= $selectedSiteId ?>&range=<?= $dateRange ?>&theme=<?= $theme ?>&view=searches'">Searches</button>
        </div>

        <?php require __DIR__ . '/dashboard-tables.php'; ?>

        <?php else: ?>
        <div class="no-data">
            <h3>No data available</h3>
            <p>Add the tracking code to your website to start collecting analytics.</p>
            <button class="btn btn-primary" onclick="openTrackingModal()" style="margin-top: 16px;">Get Tracking Code</button>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="no-data">
            <h3>No sites configured</h3>
            <p>Add your first site to start tracking analytics.</p>
            <button class="btn btn-primary" onclick="openAddSiteModal()" style="margin-top: 16px;">Add Site</button>
        </div>
        <?php endif; ?>
    </div>

    <?php require __DIR__ . '/dashboard-modals.php'; ?>

    <script>
        function refreshStats() {
            fetch(location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    const newStats = doc.querySelectorAll('.stat-card .stat-value');
                    const currentStats = document.querySelectorAll('.stat-card .stat-value');
                    newStats.forEach((el, i) => {
                        if (currentStats[i]) {
                            currentStats[i].innerHTML = el.innerHTML;
                        }
                    });

                    const newTable = doc.querySelector('.table-card table tbody');
                    const currentTable = document.querySelector('.table-card table tbody');
                    if (newTable && currentTable) {
                        currentTable.innerHTML = newTable.innerHTML;
                    }

                    const updateElem = document.getElementById('lastUpdate');
                    if (updateElem) {
                        updateElem.textContent = new Date().toLocaleTimeString('pl-PL');
                    }
                })
                .catch(err => {
                    console.error('Refresh failed:', err);
                });
        }

        setInterval(refreshStats, <?= $refreshMs ?>);
    </script>

    <script src="scripts.js?v=<?= time(); ?>"></script>
</body>
</html>