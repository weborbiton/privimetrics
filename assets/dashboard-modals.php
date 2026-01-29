<?php
// ===============================================================================
// PriviMetrics - Dashboard Modals Component
// ===============================================================================
?>

<!-- Add Site Modal -->
<div id="addSiteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">Add New Site</div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="add_site">

            <div class="form-group">
                <label>Site Name</label>
                <input type="text" name="site_name" placeholder="My Website" required>
            </div>

            <div class="form-group">
                <label>Domain</label>
                <input type="text" name="domain" placeholder="example.com" required>
            </div>

            <div class="form-group">
                <label>Domain Restriction</label>
                <select name="restriction_mode" style="width: 100%;">
                    <option value="full">Full (main domain + all subdomains)</option>
                    <option value="main">Main domain only (no subdomains)</option>
                    <option value="none">No restrictions (all domains)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Storage Type</label>
                <select name="storage_type" id="storageTypeSelect" style="width: 100%;">
                    <option value="xml">XML Files (Default)</option>
                    <option value="mysql">MySQL Database</option>
                </select>
                <p id="storageInfo" style="font-size: 12px; color: var(--text-secondary); margin-top: 8px;">
                    XML is fast and requires no database. MySQL is better for high-traffic sites.
                </p>
                <p id="storageWarning" style="display: none; font-size: 12px; color: var(--accent); margin-top: 8px;">
                    ⚠️ MySQL requires database configuration in config.php
                </p>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Add Site</button>
                <button type="button" class="btn btn-secondary" onclick="closeAddSiteModal()" style="flex: 1;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Tracking Code Modal -->
<div id="trackingModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">Tracking Code</div>
        <?php if ($currentSite): 
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $baseUrl = rtrim(
                $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']),
                '/'
            ) . '/';
            $tracking_code = (string)$currentSite->tracking_code;
        ?>
        <p style="color: var(--text-secondary); margin-bottom: 16px;">
            Copy and paste this code before the closing &lt;/body&gt; tag:
        </p>

        <div class="tracking-code" id="trackingCodeText" style="white-space: pre-wrap; word-break: break-all; background: #1a1a1a; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 13px; color: #dcdcdc;">
&lt;!-- PriviMetrics Analytics --&gt;
&lt;script src="<?= $baseUrl ?>privimetrics-div.js" data-privimetrics-code="<?= $tracking_code ?>"&gt;&lt;/script&gt;
&lt;!-- End PriviMetrics Analytics --&gt;
        </div>

        <p style="color: var(--text-tertiary); margin-top: 12px; font-size: 13px;">
            The system has automatically detected your domain: <strong><?= $baseUrl ?></strong>
        </p>

        <p style="color: var(--text-tertiary); margin-top: 12px; font-size: 13px;">
            Don't want to track IP? See how to do it:
            <a href="https://docs.weborbiton.com/privimetrics/?page=without-ip" target="_blank" style="color: var(--accent); text-decoration: underline;">
                Documentation
            </a>
        </p>

        <button class="btn btn-primary" onclick="copyTrackingCode()" style="width: 100%; margin-top: 16px;">Copy Code</button>
        <button class="btn btn-secondary" onclick="closeTrackingModal()" style="width: 100%; margin-top: 8px;">Close</button>
        <?php endif; ?>
    </div>
</div>

<!-- Manage Site Modal -->
<div id="manageModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">Manage Site</div>
        <?php if ($currentSite): ?>
        <div style="margin-bottom: 24px;">
            <div class="form-group">
                <label>Site Name</label>
                <div style="padding: 10px; background: var(--bg-primary); border-radius: 8px;">
                    <?= sanitize((string)$currentSite->name) ?>
                </div>
            </div>

            <div class="form-group">
                <label>Domain</label>
                <div style="padding: 10px; background: var(--bg-primary); border-radius: 8px;">
                    <?= sanitize((string)$currentSite->domain) ?>
                </div>
            </div>

            <div class="form-group">
                <label>Storage & Limits</label>
                <div style="padding: 10px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="margin-bottom: 4px;">
                        <strong>Type:</strong> <?= strtoupper((string)($currentSite->storage ?? 'xml')) ?>
                    </div>
                    <div style="font-size: 12px; color: var(--text-secondary);">
                        <?php 
                        $storageType = strtolower((string)($currentSite->storage ?? 'xml'));
                        $limit = isset($chosenLimits[$storageType]) ? $chosenLimits[$storageType] : ['requests' => 0, 'window' => 1];
                        ?>
                        Limit: <?= $limit['requests'] ?> req/<?= $limit['window'] ?>s
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Status</label>
                <div>
                    <span class="badge badge-<?= (string)$currentSite->active === 'true' ? 'success' : 'danger' ?>">
                        <?= (string)$currentSite->active === 'true' ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
            </div>
        </div>

        <form method="post" style="margin-bottom: 12px;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="toggle_site">
            <input type="hidden" name="site_id" value="<?= (string)$currentSite->id ?>">
            <button type="submit" class="btn btn-secondary" style="width: 100%;">
                <?= (string)$currentSite->active === 'true' ? 'Deactivate' : 'Activate' ?>
            </button>
        </form>

        <form method="post" onsubmit="return confirm('Delete this site and all its data?')">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="delete_site">
            <input type="hidden" name="site_id" value="<?= (string)$currentSite->id ?>">
            <button type="submit" class="btn btn-danger" style="width: 100%;">Delete Site</button>
        </form>

        <button class="btn btn-secondary" onclick="closeManageModal()" style="width: 100%; margin-top: 8px;">Close</button>
        <?php endif; ?>
    </div>
</div>

<script>
    function openAddSiteModal() {
        document.getElementById('addSiteModal').classList.add('active');
    }

    function closeAddSiteModal() {
        document.getElementById('addSiteModal').classList.remove('active');
    }

    function openTrackingModal() {
        document.getElementById('trackingModal').classList.add('active');
    }

    function closeTrackingModal() {
        document.getElementById('trackingModal').classList.remove('active');
    }

    function openManageModal() {
        document.getElementById('manageModal').classList.add('active');
    }

    function closeManageModal() {
        document.getElementById('manageModal').classList.remove('active');
    }

    function copyTrackingCode() {
        var code = document.querySelector('.tracking-code').innerText;
        navigator.clipboard.writeText(code).then(function () {
            alert('Tracking code copied to clipboard!');
        });
    }

    document.querySelectorAll('.modal').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    });

    // Storage type warning
    var selectElem = document.getElementById('storageTypeSelect');
    if (selectElem) {
        selectElem.addEventListener('change', function () {
            const warning = document.getElementById('storageWarning');
            const info = document.getElementById('storageInfo');

            if (this.value === 'mysql') {
                warning.style.display = 'block';
                info.style.display = 'none';
            } else {
                warning.style.display = 'none';
                info.style.display = 'block';
            }
        });
    }
</script>