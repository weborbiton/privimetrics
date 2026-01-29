<?php
// ===============================================================================
// PriviMetrics - Extensions Manager (Security Enhanced)
// ===============================================================================
require_once 'config.php';
require_once 'functions.php';

startSecureSession();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

$extensionsFile = 'extensions.xml';
$extensionsDir = __DIR__ . '/extensions';
$extensionsToggleFile = __DIR__ . '/extensions/extensions_off.txt';

$success = false;
$error = false;

$currentValue = 'false';
if (file_exists($extensionsToggleFile)) {
    $currentValue = trim(file_get_contents($extensionsToggleFile));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_global') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $newValue = isset($_POST['toggle_extensions']) ? 'false' : 'true';

        if (file_put_contents($extensionsToggleFile, $newValue, LOCK_EX) !== false) {
            $currentValue = $newValue;
            $success = ($newValue === 'false') ? "Extensions are now ACTIVE" : "Extensions are now DISABLED";
        } else {
            $error = 'Failed to update configuration';
        }
    }
}

// Ensure directories exist
if (!is_dir($extensionsDir)) {
    mkdir($extensionsDir, 0755, true);
}

// Load extensions XML
if (!file_exists($extensionsFile)) {
    $xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8'?><extensions></extensions>");
    $xml->asXML($extensionsFile);
}
$extensions = loadXMLFile($extensionsFile, 'extensions');

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['extension_zip'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $result = handleExtensionUpload($_FILES['extension_zip'], $extensionsDir, $extensions, $extensionsFile);
        $success = $result['success'] ?? null;
        $error = $result['error'] ?? null;
        if ($success) {
            $extensions = loadXMLFile($extensionsFile, 'extensions');
        }
    }
}

// Handle toggle extension
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_extension'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $extId = sanitize($_POST['extension_id'] ?? '');
        foreach ($extensions->extension as $ext) {
            if ((string)$ext->id === $extId) {
                $ext->enabled = (string)$ext->enabled === 'true' ? 'false' : 'true';
                saveXMLFile($extensions, $extensionsFile);
                $success = 'Extension status updated';
                break;
            }
        }
        $extensions = loadXMLFile($extensionsFile, 'extensions');
    }
}

// Handle delete extension
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_extension'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $extId = sanitize($_POST['extension_id'] ?? '');
        $result = deleteExtension($extId, $extensions, $extensionsDir, $extensionsFile);
        $success = $result['success'] ?? null;
        $error = $result['error'] ?? null;
        if ($success) {
            $extensions = loadXMLFile($extensionsFile, 'extensions');
        }
    }
}

function handleExtensionUpload($file, $extensionsDir, $extensions, $extensionsFile) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Upload failed'];
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        return ['error' => 'File too large (max 10MB)'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ['application/zip', 'application/x-zip-compressed'])) {
        return ['error' => 'Invalid file type. Only ZIP files allowed'];
    }

    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true) {
        return ['error' => 'Failed to open ZIP file'];
    }

    $manifestJson = $zip->getFromName('manifest.json');
    if (!$manifestJson) {
        $zip->close();
        return ['error' => 'Missing manifest.json in extension'];
    }

    $manifest = json_decode($manifestJson, true);
    if (!$manifest || !isset($manifest['id'], $manifest['name'], $manifest['version'])) {
        $zip->close();
        return ['error' => 'Invalid manifest.json format'];
    }

    $isUpdate = false;
    $existingExtNode = null;

    foreach ($extensions->extension as $ext) {
        if ((string)$ext->id === $manifest['id']) {
            if (version_compare($manifest['version'], (string)$ext->version, '>')) {
                $isUpdate = true;
                $existingExtNode = $ext;
                break;
            } else {
                $zip->close();
                return ['error' => 'Extension installed. New version (' . $manifest['version'] . ') must be greater than current (' . (string)$ext->version . ')'];
            }
        }
    }

    // --- SECURITY SCAN START ---
    $securityWarnings = [];
    $isCritical = false;
    
    // Critical functions that trigger Red Alert
    $criticalPatterns = ['exec(', 'shell_exec(', 'system(', 'passthru(', 'proc_open(', 'popen(', 'eval('];
    
    $dangerPatterns = [
        'exec(' => 'Executes system commands (exec)',
        'shell_exec(' => 'Shell access (shell_exec)',
        'system(' => 'Executes system commands (system)',
        'passthru(' => 'Executes system commands (passthru)',
        'proc_open(' => 'Opens system processes',
        'popen(' => 'Opens system processes',
        'eval(' => 'Executes arbitrary PHP code (eval)',
        'base64_decode(' => 'Possible code obfuscation (base64)',
        'file_put_contents(' => 'Modifies server files',
        'fwrite(' => 'Writes to files',
        'unlink(' => 'Deletes server files',
        'chmod(' => 'Changes file permissions',
        'chown(' => 'Changes file owner'
    ];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (strpos($filename, '..') !== false || strpos($filename, '/') === 0) {
            $zip->close();
            return ['error' => 'Security Critical: Invalid file path (.. or /) in ZIP'];
        }

        if (preg_match('/\.(php|phtml|inc|js)$/i', $filename)) {
            $content = $zip->getFromIndex($i);
            foreach ($dangerPatterns as $pattern => $desc) {
                if (stripos($content, $pattern) !== false) {
                    $securityWarnings[] = "$desc in file: $filename";
                    if (in_array($pattern, $criticalPatterns)) {
                        $isCritical = true;
                    }
                }
            }
        }
    }
    
    $securityWarnings = array_unique($securityWarnings);
    $safetyStatus = empty($securityWarnings) ? 'safe' : ($isCritical ? 'critical' : 'suspicious');
    $warningsString = implode('|', $securityWarnings);
    // --- SECURITY SCAN END ---

    $extDir = $extensionsDir . '/' . $manifest['id'];
    if ($isUpdate && is_dir($extDir)) {
        deleteDirectory($extDir);
    }

    if (!is_dir($extDir)) {
        mkdir($extDir, 0755, true);
    }

    if (!$zip->extractTo($extDir)) {
        $zip->close();
        return ['error' => 'Failed to extract extension'];
    }
    $zip->close();

    if ($isUpdate && $existingExtNode) {
        $existingExtNode->name = $manifest['name'];
        $existingExtNode->version = $manifest['version'];
        $existingExtNode->description = $manifest['description'] ?? '';
        $existingExtNode->author = $manifest['author'] ?? 'Unknown';
        $existingExtNode->safety_status = $safetyStatus;
        $existingExtNode->warnings = $warningsString;
        $successMsg = 'Extension updated to version ' . $manifest['version'];
    } else {
        $ext = $extensions->addChild('extension');
        $ext->addChild('id', $manifest['id']);
        $ext->addChild('name', $manifest['name']);
        $ext->addChild('version', $manifest['version']);
        $ext->addChild('description', $manifest['description'] ?? '');
        $ext->addChild('author', $manifest['author'] ?? 'Unknown');
        $ext->addChild('enabled', 'false');
        $ext->addChild('installed', gmdate('Y-m-d H:i:s'));
        $ext->addChild('safety_status', $safetyStatus);
        $ext->addChild('warnings', $warningsString);
        $successMsg = 'Extension installed successfully';
    }

    saveXMLFile($extensions, $extensionsFile);
    return ['success' => $successMsg];
}

function deleteExtension($extId, $extensions, $extensionsDir, $extensionsFile) {
    $index = 0;
    $found = false;
    foreach ($extensions->extension as $ext) {
        if ((string)$ext->id === $extId) {
            $extDir = $extensionsDir . '/' . $extId;
            if (is_dir($extDir)) {
                deleteDirectory($extDir);
            }
            unset($extensions->extension[$index]);
            saveXMLFile($extensions, $extensionsFile);
            $found = true;
            break;
        }
        $index++;
    }
    return $found ? ['success' => 'Extension deleted successfully'] : ['error' => 'Extension not found'];
}

function deleteDirectory($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

$csrfToken = generateCSRFToken();
$settings = file_exists('settings-config.php') ? include 'settings-config.php' : [];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $_SESSION['theme'] ?? 'dark' ?>" data-font="<?= htmlspecialchars($settings['ui_font'] ?? 'sora') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extensions - PriviMetrics</title>
    <link rel="stylesheet" href="styles-mobile.css?v=<?= time(); ?>" media="screen and (max-width: 900px)">
    <link rel="stylesheet" href="styles.css?v=<?= time(); ?>" media="screen and (min-width: 901px)">
    <style>
        .upload-zone {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            background: var(--bg-secondary);
            transition: all 0.3s;
            cursor: pointer;
            margin-bottom: 32px;
        }
        .upload-zone:hover, .upload-zone.dragover {
            border-color: var(--accent);
            background: var(--bg-tertiary);
        }
        .extensions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .extension-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
        }
        .extension-card:hover { border-color: var(--accent); }
        .extension-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }
        .extension-title { font-size: 18px; font-weight: 600; margin-bottom: 4px; }
        .extension-version { font-size: 12px; color: var(--text-tertiary); }
        .extension-description {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 16px;
            line-height: 1.5;
            flex-grow: 1;
        }
        .extension-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }
        .extension-author { font-size: 12px; color: var(--text-tertiary); }
        .extension-actions { display: flex; gap: 8px; }

        /* Security Alert Styles */
        .safety-alert {
            margin: 12px 0;
            padding: 12px;
            border-radius: 4px;
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-left: 3px solid #ffc107;
            background: rgba(255, 193, 7, 0.05);
        }
        .safety-alert.critical {
            border-color: rgba(255, 68, 68, 0.3);
            border-left-color: #ff4444;
            background: rgba(255, 68, 68, 0.05);
        }
        .safety-alert-title {
            font-weight: 700;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .safety-alert.suspicious .safety-alert-title { color: #ffc107; }
        .safety-alert.critical .safety-alert-title { color: #ff4444; }
        
        .safety-list { margin: 0; padding-left: 18px; font-size: 11px; color: var(--text-secondary); }
        .safety-list li { margin-bottom: 2px; }
        
        .safety-safe {
            margin: 12px 0;
            font-size: 12px;
            color: #00c851;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px;
            background: rgba(0, 200, 81, 0.05);
            border-radius: 4px;
        }

        /* Toggle UI */
        .toggle-container { display: flex; align-items: center; gap: 8px; }
        .toggle-switch { position: relative; display: inline-block; width: 40px; height: 20px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: var(--border-color, #ccc);
            transition: 0.4s; border-radius: 20px;
        }
        .slider:before {
            position: absolute; content: ""; height: 16px; width: 16px;
            left: 2px; bottom: 2px; background-color: white;
            transition: 0.4s; border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--accent, #4CAF50); }
        input:checked + .slider:before { transform: translateX(20px); }
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
            <div class="logo-text"><span style="font-weight: 300;">| Extensions</span></div>
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

        <form method="POST" id="globalToggleForm" style="margin-bottom: 24px;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="toggle_global">
            <div class="toggle-container">
                <label class="toggle-switch">
                    <input type="checkbox" name="toggle_extensions" onchange="this.form.submit()" <?= ($currentValue === 'false') ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
                <span style="font-weight:500;">Extensions Enabled</span>
            </div>
        </form>

        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <div class="upload-zone" id="uploadZone">
                <input type="file" name="extension_zip" id="fileInput" accept=".zip" style="display: none;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 16px; opacity: 0.5;">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                <h3 style="margin-bottom: 8px;">Drop extension here or click to browse</h3>
                <p style="color: var(--text-secondary); font-size: 14px;">ZIP file only (max 10MB)</p>
            </div>
        </form>

        <div class="chart-header" style="margin-bottom: 20px;">Installed Extensions</div>

        <?php if (count($extensions->extension) > 0): ?>
        <div class="extensions-grid">
            <?php foreach ($extensions->extension as $ext): ?>
            <div class="extension-card">
                <div class="extension-header">
                    <div>
                        <div class="extension-title"><?= htmlspecialchars((string)$ext->name) ?></div>
                        <div class="extension-version">v<?= htmlspecialchars((string)$ext->version) ?></div>
                    </div>
                    <span class="badge badge-<?= (string)$ext->enabled === 'true' ? 'success' : 'secondary' ?>">
                        <?= (string)$ext->enabled === 'true' ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
                
                <div class="extension-description">
                    <?= htmlspecialchars((string)$ext->description ?: 'No description provided') ?>
                </div>

                <?php 
                $status = isset($ext->safety_status) ? (string)$ext->safety_status : 'unknown';
                $warnings = isset($ext->warnings) && !empty((string)$ext->warnings) ? explode('|', (string)$ext->warnings) : [];
                ?>

                <?php if ($status === 'suspicious' || $status === 'critical'): ?>
                    <div class="safety-alert <?= $status ?>">
                        <div class="safety-alert-title">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                <line x1="12" y1="9" x2="12" y2="13"></line>
                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                            <?= $status === 'critical' ? 'Security Threat Detected' : 'Security Warnings' ?>
                        </div>
                        <ul class="safety-list">
                            <?php foreach ($warnings as $w): if(!empty($w)): ?>
                                <li><?= htmlspecialchars($w) ?></li>
                            <?php endif; endforeach; ?>
                        </ul>
                    </div>
                <?php elseif ($status === 'safe'): ?>
                    <div class="safety-safe">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        Safe Code
                    </div>
                <?php endif; ?>

                <div class="extension-meta">
                    <div class="extension-author">by <?= htmlspecialchars((string)$ext->author) ?></div>
                    <div class="extension-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="extension_id" value="<?= (string)$ext->id ?>">
                            <button type="submit" name="toggle_extension" class="btn btn-sm btn-secondary">
                                <?= (string)$ext->enabled === 'true' ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this extension?')">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="extension_id" value="<?= (string)$ext->id ?>">
                            <button type="submit" name="delete_extension" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-data">
            <h3>No extensions installed</h3>
            <p>Upload your first extension to extend functionality.</p>
        </div>
        <?php endif; ?>
    </main>

    <script>
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const uploadForm = document.getElementById('uploadForm');

        uploadZone.addEventListener('click', () => fileInput.click());
        uploadZone.addEventListener('dragover', (e) => { e.preventDefault(); uploadZone.classList.add('dragover'); });
        uploadZone.addEventListener('dragleave', () => { uploadZone.classList.remove('dragover'); });
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) { fileInput.files = files; uploadForm.submit(); }
        });
        fileInput.addEventListener('change', () => { if (fileInput.files.length > 0) { uploadForm.submit(); } });
    </script>
</body>
</html>