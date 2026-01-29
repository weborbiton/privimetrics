<?php
// ===============================================================================
// PriviMetrics - Trends Template
// ===============================================================================
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>" data-font="<?= htmlspecialchars($settings['ui_font'] ?? 'sora') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Trends - <?= sanitize($config['site_name']) ?></title>
    <link rel="stylesheet" media="screen and (max-width: 900px)" href="styles-mobile.css?v=<?= time(); ?>">
    <link rel="stylesheet" media="screen and (min-width: 901px)" href="styles.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="trends.css?v=<?= time(); ?>">
    <link rel="preload" href="fonts/sora.ttf" as="font" type="font/ttf" crossorigin>
    <style>
        .trends-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding: 16px;
            background: var(--card-bg);
            border-radius: 12px;
        }
        
        .week-navigation {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .week-info {
            text-align: center;
            padding: 0 24px;
        }
        
        .week-info h2 {
            margin: 0 0 4px 0;
            font-size: 1.5em;
            color: var(--text-primary);
        }
        
        .week-info .date-range {
            font-size: 0.9em;
            color: var(--text-secondary);
        }
        
        .comparison-selector {
            display: flex;
            gap: 8px;
            padding: 12px;
            background: var(--bg-secondary);
            border-radius: 8px;
            margin-bottom: 16px;
        }
        
        .comparison-selector button {
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .comparison-selector button.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .trend-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .trend-up {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }
        
        .trend-down {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .trend-neutral {
            background: rgba(156, 163, 175, 0.1);
            color: #9ca3af;
        }
        
        .daily-chart {
            margin: 24px 0;
            padding: 20px;
            background: var(--card-bg);
            border-radius: 12px;
        }
        
        .daily-bars {
            display: flex;
            gap: 8px;
            align-items: flex-end;
            height: 200px;
            margin-top: 16px;
        }
        
        .day-bar {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        
        .bar-container {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            flex: 1;
        }
        
        .bar {
            width: 100%;
            background: linear-gradient(180deg, var(--primary-color), var(--primary-color-dark));
            border-radius: 6px 6px 0 0;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }
        
        .bar:hover {
            opacity: 0.8;
        }
        
        .bar-value {
            position: absolute;
            top: -24px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.75em;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
        }
        
        .day-label {
            font-size: 0.85em;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .comparison-table {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 24px;
        }
        
        @media (max-width: 768px) {
            .trends-header {
                flex-direction: column;
                gap: 16px;
            }
            
            .week-navigation {
                width: 100%;
                justify-content: space-between;
            }
            
            .comparison-table {
                grid-template-columns: 1fr;
            }
            
            .daily-bars {
                height: 150px;
            }
        }
    </style>
</head>
<body>
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
            <div class="logo-text"><span style="font-weight: 300;">| Trends</span></div>
        </div>
        <div class="header-actions">
            <a href="dashboard.php<?= $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" class="btn btn-secondary">←</a>
            <a href="?site=<?= $selectedSiteId ?>&week=<?= $selectedWeek ?>&theme=<?= $theme === 'dark' ? 'light' : 'dark' ?>&compare=<?= $compareMode ?>" class="btn btn-secondary">
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
        <?php if ($currentSite): ?>
        
        <!-- Site Selector -->
        <div class="toolbar" style="margin-bottom: 16px;">
            <select onchange="window.location.href='?site=' + this.value + '&week=<?= $selectedWeek ?>&theme=<?= $theme ?>&compare=<?= $compareMode ?>'">
                <?php foreach ($sites->site as $site): ?>
                <option value="<?= (string)$site->id ?>" <?=(string)$site->id === $selectedSiteId ? 'selected' : '' ?>>
                    <?= sanitize((string)$site->name) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (!empty($availableWeeks)): ?>
        
        <!-- Week Navigation -->
        <div class="trends-header">
            <div class="week-navigation">
                <?php
                $currentIndex = array_search($selectedWeek, array_column($availableWeeks, 'id'));
                $hasPrev = $currentIndex !== false && $currentIndex < count($availableWeeks) - 1;
                $hasNext = $currentIndex !== false && $currentIndex > 0;
                $prevWeek = $hasPrev ? $availableWeeks[$currentIndex + 1]['id'] : '';
                $nextWeek = $hasNext ? $availableWeeks[$currentIndex - 1]['id'] : '';
                ?>
                
                <button class="btn btn-secondary" 
                    <?= !$hasPrev ? 'disabled' : '' ?>
                    onclick="window.location.href='?site=<?= $selectedSiteId ?>&week=<?= $prevWeek ?>&theme=<?= $theme ?>&compare=<?= $compareMode ?>'">
                    ← Previous
                </button>
                
                <select class="btn" style="min-width: 200px;"
                    onchange="window.location.href='?site=<?= $selectedSiteId ?>&week=' + this.value + '&theme=<?= $theme ?>&compare=<?= $compareMode ?>'">
                    <?php foreach ($availableWeeks as $week): ?>
                    <option value="<?= $week['id'] ?>" <?= $week['id'] === $selectedWeek ? 'selected' : '' ?>>
                        <?= htmlspecialchars($week['label'] . ' - ' . $week['date_range']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <button class="btn btn-secondary"
                    <?= !$hasNext ? 'disabled' : '' ?>
                    onclick="window.location.href='?site=<?= $selectedSiteId ?>&week=<?= $nextWeek ?>&theme=<?= $theme ?>&compare=<?= $compareMode ?>'">
                    Next →
                </button>
            </div>
        </div>

        <?php if ($currentWeekInfo): ?>
        <div class="week-info" style="text-align: center; margin-bottom: 24px;">
            <h2>Week <?= $currentWeekInfo['week'] ?>, <?= $currentWeekInfo['year'] ?></h2>
            <div class="date-range">
                <?= date('F d', $currentWeekInfo['start']) ?> - <?= date('F d, Y', $currentWeekInfo['end']) ?>
            </div>
        </div>

        <!-- Comparison Mode -->
        <div class="comparison-selector">
            <span style="align-self: center; margin-right: 8px; color: var(--text-secondary);">Compare with:</span>
            <button class="<?= $compareMode === 'none' ? 'active' : '' ?>"
                onclick="window.location.href='?site=<?= $selectedSiteId ?>&week=<?= $selectedWeek ?>&theme=<?= $theme ?>&compare=none'">
                No Comparison
            </button>
            <button class="<?= $compareMode === 'previous' ? 'active' : '' ?>"
                onclick="window.location.href='?site=<?= $selectedSiteId ?>&week=<?= $selectedWeek ?>&theme=<?= $theme ?>&compare=previous'">
                Previous Week
            </button>
            <button class="<?= $compareMode === 'year' ? 'active' : '' ?>"
                onclick="window.location.href='?site=<?= $selectedSiteId ?>&week=<?= $selectedWeek ?>&theme=<?= $theme ?>&compare=year'">
                Same Week Last Year
            </button>
        </div>

        <?php if ($weekStats && $weekStats['total_visits'] > 0): ?>
        <!-- Main Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Visits</div>
                <div class="stat-value">
                    <?= formatNumber($weekStats['total_visits']) ?>
                    <?php if ($changes && isset($changes['visits'])): ?>
                    <span class="trend-indicator trend-<?= $changes['visits'] > 0 ? 'up' : ($changes['visits'] < 0 ? 'down' : 'neutral') ?>">
                        <?= $changes['visits'] > 0 ? '↑' : ($changes['visits'] < 0 ? '↓' : '→') ?>
                        <?= abs($changes['visits']) ?>%
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Pages Visited</div>
                <div class="stat-value"><?= count($weekStats['pages']) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Top Country</div>
                <div class="stat-value">
                    <?php if (!empty($weekStats['countries'])): ?>
                        <?= htmlspecialchars(array_key_first($weekStats['countries'])) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Searches</div>
                <div class="stat-value"><?= count($weekStats['searches']) ?></div>
            </div>
        </div>

        <!-- Daily Breakdown Chart -->
        <div class="daily-chart">
            <h3 style="margin: 0 0 16px 0;">Daily Breakdown</h3>
            <div class="daily-bars">
                <?php 
                $maxVisits = max(array_column($weekStats['daily_breakdown'], 'visits'));
                $dayOrder = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                
                foreach ($dayOrder as $day):
                    $dayData = $weekStats['daily_breakdown'][$day] ?? ['visits' => 0];
                    $height = $maxVisits > 0 ? ($dayData['visits'] / $maxVisits) * 100 : 0;
                ?>
                <div class="day-bar">
                    <div class="bar-container">
                        <?php if ($dayData['visits'] > 0): ?>
                        <div class="bar" style="height: <?= $height ?>%;" title="<?= $dayData['visits'] ?> visits">
                            <div class="bar-value"><?= $dayData['visits'] ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="day-label"><?= $day ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top Lists -->
        <div class="comparison-table">
            <!-- Top Pages -->
            <div class="table-card">
                <h3>Top Pages</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th style="text-align: right;">Visits</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $topPages = array_slice($weekStats['pages'], 0, 10, true);
                        foreach ($topPages as $page => $count): 
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($page) ?></td>
                            <td style="text-align: right;"><?= formatNumber($count) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topPages)): ?>
                        <tr><td colspan="2" style="text-align: center; color: var(--text-tertiary);">No data</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Top Countries -->
            <div class="table-card">
                <h3>Top Countries</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th style="text-align: right;">Visits</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $topCountries = array_slice($weekStats['countries'], 0, 10, true);
                        foreach ($topCountries as $country => $count): 
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($country) ?></td>
                            <td style="text-align: right;"><?= formatNumber($count) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topCountries)): ?>
                        <tr><td colspan="2" style="text-align: center; color: var(--text-tertiary);">No data</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <div class="no-data">
            <h3>No data for this week</h3>
            <p>No visits were recorded during this week.</p>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php else: ?>
        <div class="no-data">
            <h3>No data available</h3>
            <p>Start tracking to see weekly trends.</p>
            <a href="index.php" class="btn btn-primary" style="margin-top: 16px;">Go to Dashboard</a>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="no-data">
            <h3>No sites configured</h3>
            <p>Add your first site to start tracking.</p>
            <a href="index.php" class="btn btn-primary" style="margin-top: 16px;">Go to Dashboard</a>
        </div>
        <?php endif; ?>
    </div>

    <script src="scripts.js?v=<?= time(); ?>"></script>
</body>
</html>