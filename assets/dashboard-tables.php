<?php
// ===============================================================================
// PriviMetrics - Dashboard Tables Component
// ===============================================================================
?>

<?php if ($view === 'overview' && !empty($stats['top_pages'])): ?>
<div class="table-card">
    <div class="chart-header">Top Pages</div>
    <table>
        <thead>
            <tr>
                <th>Page</th>
                <th>Visits</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stats['top_pages'] as $page): ?>
            <tr>
                <td>
                    <div style="font-weight: 500;">
                        <?= sanitize($page['title'] ?: 'Untitled') ?>
                    </div>
                    <div style="font-size: 12px; color: var(--text-secondary);">
                        <?= sanitize($page['url']) ?>
                    </div>
                </td>
                <td>
                    <?= formatNumber($page['count']) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($view === 'countries' && !empty($stats['top_countries'])): ?>
<div class="table-card">
    <div class="chart-header">Top Countries</div>
    <table>
        <thead>
            <tr>
                <th>Country</th>
                <th>Visits</th>
                <th>%</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stats['top_countries'] as $country): ?>
            <tr>
                <td>
                    <span style="margin-right: 8px;">
                        <?= sanitize($country['code']) ?>
                    </span>
                    <?= sanitize($country['name']) ?>
                </td>
                <td>
                    <?= formatNumber($country['count']) ?>
                </td>
                <td>
                    <?= round(($country['count'] / $stats['total_visits']) * 100, 1) ?>%
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($view === 'referrers' && !empty($stats['top_referrers'])): ?>
<div class="table-card">
    <div class="chart-header">Top Referrers</div>
    <table>
        <thead>
            <tr>
                <th>Source</th>
                <th>Visits</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stats['top_referrers'] as $ref => $count): ?>
            <tr>
                <td>
                    <?= sanitize($ref) ?>
                </td>
                <td>
                    <?= formatNumber($count) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($view === 'searches' && !empty($stats['top_searches'])): ?>
<div class="table-card">
    <div class="chart-header">Top Searches</div>
    <table>
        <thead>
            <tr>
                <th>Search Query</th>
                <th>Count</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stats['top_searches'] as $query => $count): ?>
            <tr>
                <td>
                    <span style="font-family: monospace; background: var(--bg-secondary); padding: 2px 6px; border-radius: 4px;">
                        <?= sanitize($query) ?>
                    </span>
                </td>
                <td>
                    <?= formatNumber($count) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>