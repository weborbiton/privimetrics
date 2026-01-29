<?php
// ===============================================================================
// PriviMetrics - Installation Wizard
// ===============================================================================

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$theme = $_GET['theme'] ?? 'dark';
$storageType = $_GET['storage'] ?? '';

// Check if already installed
$isInstalled = file_exists('config.php') && file_exists('sites.xml');

if ($isInstalled && $step === 1) {
    $alreadyInstalled = true;
}

if (isset($_GET['complete'])) {
    $selectedStorage = $_GET['complete'];
    
    // Create config.php
    $mysqlConfig = '';
    if ($selectedStorage === 'mysql' && isset($_POST['mysql_host'])) {
        $mysqlConfig = "
    // MySQL Configuration (if using MySQL storage)
    'mysql_host' => '" . addslashes($_POST['mysql_host']) . "',
    'mysql_database' => '" . addslashes($_POST['mysql_database']) . "',
    'mysql_username' => '" . addslashes($_POST['mysql_username']) . "',
    'mysql_password' => '" . addslashes($_POST['mysql_password']) . "',";
    } else {
        $mysqlConfig = "
    // MySQL Configuration (if using MySQL storage)
    'mysql_host' => 'localhost',
    'mysql_database' => 'privimetrics',
    'mysql_username' => 'root',
    'mysql_password' => '',";
    }
    
    $configContent = "<?php
// ===============================================================================
// PriviMetrics Configuration
// ===============================================================================

\$config = [
    'site_name' => 'PriviMetrics',
    'site_logo' => 'img:logo.svg', // Text Logo Example: PA | Image Logo Example: img:logo.svg
    
    // Data directories
    'data_dir' => __DIR__ . '/data',
    'sites_file' => __DIR__ . '/sites.xml',
    
    // Default storage type for new sites
    'default_storage' => '$selectedStorage',
    $mysqlConfig
    
    // Session settings
    'session_lifetime' => 3600, // 1 hour
];
";
    
    file_put_contents('config.php', $configContent);
    
    // Create sites.xml
    $sitesContent = '<?xml version="1.0" encoding="UTF-8"?>
<sites>
</sites>';
    file_put_contents('sites.xml', $sitesContent);
    
    // Create data directory
    if (!file_exists('data')) {
        mkdir('data', 0755, true);
    }
    
    // Create .htaccess for data directory
    $htaccessContent = 'Order deny,allow
Deny from all';
    file_put_contents('data/.htaccess', $htaccessContent);
    
    header('Location: signup.php?installed=1');
    exit;
}

// System requirements check
function checkRequirements() {
    return [
        'php_version' => [
            'name' => 'PHP Version >= 8.0',
            'status' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'value' => PHP_VERSION
        ],
        'xml_support' => [
            'name' => 'XML Extension',
            'status' => extension_loaded('xml') && extension_loaded('simplexml'),
            'value' => extension_loaded('xml') ? 'Enabled' : 'Disabled'
        ],
        'curl_support' => [
            'name' => 'cURL Extension',
            'status' => extension_loaded('curl'),
            'value' => extension_loaded('curl') ? 'Enabled' : 'Disabled'
        ],
        'data_writable' => [
            'name' => 'Data Directory Writable',
            'status' => is_writable(__DIR__),
            'value' => is_writable(__DIR__) ? 'Yes' : 'No'
        ],
        'mysql_support' => [
            'name' => 'MySQL/PDO Extension (Optional)',
            'status' => extension_loaded('pdo') && extension_loaded('pdo_mysql'),
            'value' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled'
        ]
    ];
}

$requirements = checkRequirements();
$allRequired = $requirements['php_version']['status'] && 
               $requirements['xml_support']['status'] && 
               $requirements['curl_support']['status'] && 
               $requirements['data_writable']['status'];

// Handle MySQL setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_mysql'])) {
    $host = $_POST['mysql_host'] ?? 'localhost';
    $database = $_POST['mysql_database'] ?? '';
    $username = $_POST['mysql_username'] ?? '';
    $password = $_POST['mysql_password'] ?? '';
    
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$database`");
        
        // Create tables
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS analytics (
                id VARCHAR(50) PRIMARY KEY,
                site_id VARCHAR(50) NOT NULL,
                ip VARCHAR(50) NOT NULL,
                country VARCHAR(100) DEFAULT 'Unknown',
                country_code VARCHAR(10) DEFAULT 'XX',
                user_agent TEXT,
                user_hash VARCHAR(100) NOT NULL,
                timestamp INT NOT NULL,
                date DATE NOT NULL,
                hour TINYINT NOT NULL,
                page_url TEXT NOT NULL,
                page_title VARCHAR(500),
                referrer VARCHAR(500),
                search_query VARCHAR(500),
                INDEX idx_site_date (site_id, date),
                INDEX idx_user_hash (user_hash),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $mysqlSuccess = true;
        $mysqlMessage = "MySQL connection successful! Database and tables created.";
    } catch (PDOException $e) {
        $mysqlSuccess = false;
        $mysqlMessage = "MySQL Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PriviMetrics Installation</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .install-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }
        .install-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 24px;
        }
        .install-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .install-header h1 {
            font-size: 32px;
            margin-bottom: 12px;
        }
        .install-header p {
            color: var(--text-secondary);
            font-size: 16px;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-bottom: 40px;
        }
        .step-dot {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bg-tertiary);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .step-dot.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }
        .step-dot.completed {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }
        .req-list {
            list-style: none;
            padding: 0;
        }
        .req-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
        }
        .req-item:last-child {
            border-bottom: none;
        }
        .req-status {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .status-pass {
            background: var(--success);
            color: white;
        }
        .status-fail {
            background: var(--danger);
            color: white;
        }
        .storage-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 24px 0;
        }
        .storage-card {
            background: var(--bg-tertiary);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: var(--text-primary);
        }
        .storage-card:hover {
            border-color: var(--accent);
            background: var(--bg-secondary);
        }
        .storage-card.selected {
            border-color: var(--accent);
            background: var(--bg-secondary);
        }
        .storage-card h3 {
            font-size: 20px;
            margin-bottom: 12px;
        }
        .storage-card ul {
            margin: 12px 0;
            padding-left: 20px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        .storage-card li {
            margin: 6px 0;
        }
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }
        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid #3b82f6;
            color: #3b82f6;
        }
        .code-block {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin: 16px 0;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 32px;
        }
        .theme-toggle-fixed {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none;
            font-size: 20px;
            z-index: 1000;
        }
        @media (max-width: 768px) {
            .storage-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <a href="?step=<?= $step ?>&theme=<?= $theme === 'dark' ? 'light' : 'dark' ?><?= $storageType ? '&storage=' . $storageType : '' ?>" class="theme-toggle-fixed">
        <?= $theme === 'dark' ? '<svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"> <circle cx="12" cy="12" r="5" /> <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" /></svg>' : '<svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"> <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" /></svg>' ?>
    </a>

    <div class="install-container">
        <div class="install-header">
            <h1>PriviMetrics Installation</h1>
            <p>Privacy-focused web analytics platform</p>
        </div>

        <?php if (isset($alreadyInstalled)): ?>
        <div class="install-card">
            <div class="alert alert-info">
                <strong>Already Installed!</strong><br>
                PriviMetrics is already configured. Delete or rename <code>config.php</code> and <code>sites.xml</code> to reinstall.
            </div>
            <div class="form-actions">
                <a href="signup.php" class="btn btn-primary" style="flex: 1;">Create a new account</a>
            </div>
        </div>
        <?php else: ?>

        <div class="step-indicator">
            <div class="step-dot <?= $step === 1 ? 'active' : ($step > 1 ? 'completed' : '') ?>">1</div>
            <div class="step-dot <?= $step === 2 ? 'active' : ($step > 2 ? 'completed' : '') ?>">2</div>
            <div class="step-dot <?= $step === 3 ? 'active' : ($step > 3 ? 'completed' : '') ?>">3</div>
        </div>

        <?php if ($step === 1): ?>
        <!-- Step 1: System Requirements -->
        <div class="install-card">
            <h2 style="margin-bottom: 24px;">System Requirements</h2>
            
            <ul class="req-list">
                <?php foreach ($requirements as $key => $req): ?>
                <li class="req-item">
                    <div>
                        <strong><?= $req['name'] ?></strong><br>
                        <small style="color: var(--text-secondary);"><?= $req['value'] ?></small>
                    </div>
                    <div class="req-status">
                        <span class="status-icon <?= $req['status'] ? 'status-pass' : 'status-fail' ?>">
                            <?= $req['status'] ? '✓' : '✗' ?>
                        </span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>

            <?php if (!$allRequired): ?>
            <div class="alert alert-danger" style="margin-top: 24px;">
                <strong>Requirements Not Met!</strong><br>
                Please fix the failed requirements before continuing.
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <a href="?step=2&theme=<?= $theme ?>" class="btn btn-primary" style="flex: 1;" <?= !$allRequired ? 'onclick="return false;" style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
                    Continue
                </a>
            </div>
        </div>

        <?php elseif ($step === 2): ?>
        <!-- Step 2: Choose Storage Type -->
        <div class="install-card">
            <h2 style="margin-bottom: 24px;">Choose Storage Type</h2>
            
            <p style="color: var(--text-secondary); margin-bottom: 24px;">
                Select how you want to store analytics data. You can mix both types for different sites.
            </p>

            <div class="storage-options">
                <a href="?step=3&storage=xml&theme=<?= $theme ?>" class="storage-card <?= $storageType === 'xml' ? 'selected' : '' ?>">
                    <h3>📄 XML Version</h3>
                    <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 16px;">
                        File-based storage using XML files
                    </p>
                    <strong style="color: var(--success);">Recommended for:</strong>
                    <ul>
                        <li>Small to medium websites</li>
                        <li>Quick setup (no database required)</li>
                        <li>Shared hosting environments</li>
                        <li>Up to 50K visitors/month</li>
                        <li>Up to 2K visits/day per site</li>
                    </ul>
                    <strong style="color: var(--text-secondary); display: block; margin-top: 16px;">Requirements:</strong>
                    <ul>
                        <li>PHP XML extension</li>
                        <li>Write permissions</li>
                    </ul>
                </a>

                <a href="?step=3&storage=mysql&theme=<?= $theme ?>" class="storage-card <?= $storageType === 'mysql' ? 'selected' : '' ?>">
                    <h3>🗄️ MySQL Version</h3>
                    <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 16px;">
                        Database storage using MySQL
                    </p>
                    <strong style="color: var(--success);">Recommended for:</strong>
                    <ul>
                        <li>High-traffic websites</li>
                        <li>Better performance at scale</li>
                        <li>Advanced querying capabilities</li>
                        <li>50+ visitors/month</li>
                        <li>Up to 2K+ visits/day per site</li>
                    </ul>
                    <strong style="color: var(--text-secondary); display: block; margin-top: 16px;">Requirements:</strong>
                    <ul>
                        <li>MySQL 5.7+ or MariaDB</li>
                        <li>PDO extension</li>
                    </ul>
                </a>
            </div>

            <div class="alert alert-info">
                <strong>💡 Pro Tip:</strong> You can use both! Configure MySQL in settings and choose storage type per site when adding them.
            </div>
        </div>

        <?php elseif ($step === 3): ?>
        <!-- Step 3: Configuration -->
        <div class="install-card">
            <h2 style="margin-bottom: 24px;">
                <?= $storageType === 'mysql' ? 'MySQL Configuration' : 'Final Setup' ?>
            </h2>

            <?php if ($storageType === 'mysql'): ?>
                <?php if (isset($mysqlSuccess) && $mysqlSuccess): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($mysqlMessage) ?>
                </div>
                <?php elseif (isset($mysqlSuccess) && !$mysqlSuccess): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($mysqlMessage) ?>
                </div>
                <?php endif; ?>

                <form method="post" action="?step=3&storage=mysql&theme=<?= $theme ?>">
                    <div class="form-group">
                        <label>MySQL Host</label>
                        <input type="text" name="mysql_host" value="<?= $_POST['mysql_host'] ?? 'localhost' ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Database Name</label>
                        <input type="text" name="mysql_database" value="<?= $_POST['mysql_database'] ?? 'privimetrics' ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="mysql_username" value="<?= $_POST['mysql_username'] ?? 'root' ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="mysql_password" placeholder="Enter database password" value="<?= $_POST['mysql_password'] ?? '' ?>">
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="test_mysql" class="btn btn-primary" style="flex: 1;">
                            Test Connection & Create Tables
                        </button>
                    </div>
                </form>

                <?php if (isset($mysqlSuccess) && $mysqlSuccess): ?>
                <div class="form-actions" style="margin-top: 24px;">
                    <form method="post" action="?complete=mysql&theme=<?= $theme ?>">
                        <input type="hidden" name="mysql_host" value="<?= htmlspecialchars($_POST['mysql_host'] ?? 'localhost') ?>">
                        <input type="hidden" name="mysql_database" value="<?= htmlspecialchars($_POST['mysql_database'] ?? 'privimetrics') ?>">
                        <input type="hidden" name="mysql_username" value="<?= htmlspecialchars($_POST['mysql_username'] ?? 'root') ?>">
                        <input type="hidden" name="mysql_password" value="<?= htmlspecialchars($_POST['mysql_password'] ?? '') ?>">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Complete Installation</button>
                    </form>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-success">
                    <strong>✓ XML Storage Selected</strong><br>
                    No additional configuration needed. The system will create XML files automatically.
                </div>

                <h3 style="margin: 32px 0 16px;">Next Steps:</h3>
                <ol style="color: var(--text-secondary); line-height: 1.8;">
                    <li>Click "Complete Installation" below</li>
                    <li>Log in with default credentials:<br>
                        <div class="code-block" style="margin: 8px 0;">
Username: admin<br>
Password: admin123
                        </div>
                    </li>
                    <li>Open <a href="signup.php"><code>signup.php</code></a> and create a new account (default account will be deleted)</li>
                    <li>Add your first website in the dashboard</li>
                    <li>Copy the tracking code and add it to your website</li>
                </ol>

                <div class="form-actions">
                    <a href="?complete=xml&theme=<?= $theme ?>" class="btn btn-primary" style="flex: 1;">Complete Installation</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="install-card">
            <h3 style="margin-bottom: 16px;">📚 Important Information</h3>
            <ul style="color: var(--text-secondary); line-height: 1.8;">
                <li><strong>Default Login:</strong> admin / admin123</li>
                <li><strong>Security:</strong> After installation, open <a href="signup.php"><code>signup.php</code></a> and create a new account.</li>
                <li><strong>Files Created:</strong> 
                    <ul style="margin-top: 8px;">
                        <li><code>sites.xml</code> - Stores your website configurations</li>
                        <li><code>data/</code> - Directory for analytics data</li>
                        <?php if ($storageType === 'mysql'): ?>
                        <li><code>analytics</code> table in MySQL database</li>
                        <?php endif; ?>
                    </ul>
                </li>
                <li><strong>Privacy:</strong> IPs are automatically anonymized, no cookies used</li>
                <li><strong>Documentation:</strong> Check README files for detailed usage instructions</li>
            </ul>
        </div>

        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>

<!--                                                                                     
                                                                                    :--.            
                                                                                  +******-          
                                                                                 =********:         
                                                                                 *********-         
                                                                                =********+          
                                                                               .*******+-           
                                                                               *****+:...           
                                                                              +****......           
                                                                             ****+.......           
                                                                            +***+........           
                                                                          =***+..........           
                                                                         -****:..........           
                                                                        :****:...........            
                                                                      :****=.............           
                            .+*********+=                            .****=..............           
                          -****************=                         ****=...............           
                        -******-:....:-*******=                    :****=................           
                       *****=............-+******:                -****+.................           
                     :****=.................-*******-           -*****-..................           
                    =***+......................=********+:..:+*******:...................           
                   +***+..........................-****************:.....................           
                  ****-...............................=+*******+=........................           
                :****-...................................................................           
               -****:...................................................................             
            .*****:....................................................................             
           -****+.....................................................................              
          -****+......................................................................              
         .****=......................................................................               
         :***.   ..................................................................                      
                      ........................................................                      
                          .................................................                         
                             ..........................................                             
-->