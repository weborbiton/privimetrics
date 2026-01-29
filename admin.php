<?php
// ===============================================================================
// PriviMetrics - Admin Configuration
// ===============================================================================

// Admin Authentication System
define('ADMIN_USERNAME', 'admin'); // @user-config
// This is hash for password: "admin123" - generate your own using signup.php
define('ADMIN_PASSWORD_HASH', '$2y$10$MGwiiWTPtH6rLi0qMxdKae1VdMMzQxlsFqasRfSGsDFkcXJNeqrVK'); // @user-config
define('ADMIN_EMAIL', ''); // @user-config // Optional email for notifications 

if (!file_exists('config.php')) {
    die('Configuration file not found. Please run install.php first.');
}
require_once 'config.php';

if (!file_exists('functions.php')) {
    die('Functions file not found. Please check your installation.');
}
require_once 'functions.php';

startSecureSession();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Check rate limiting
        if (!checkRateLimit('login_' . getClientIP(), 5, 900)) {
            $error = 'Too many login attempts. Please try again later.';
        } else {
            if ($username === ADMIN_USERNAME && verifyPassword($password, ADMIN_PASSWORD_HASH)) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
                $_SESSION['login_time'] = time();
                $_SESSION['created'] = time();
                session_write_close();
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}

$csrfToken = generateCSRFToken();
$theme = sanitize($_GET['theme'] ?? ($config['default_theme'] ?? 'dark'));
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>" data-font="<?= htmlspecialchars($settings['ui_font'] ?? 'sora') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?= sanitize($config['site_name'] ?? 'PriviMetrics') ?></title>
    <style>
        :root[data-theme="dark"] {
            --bg-primary: #0a0a0a;
            --bg-secondary: #151515;
            --border-color: #252525;
            --text-primary: #e5e5e5;
            --text-secondary: #a0a0a0;
            --accent: #f1484e;
            --accent-hover: #d53b40ff;
            --error-bg: #7f1d1d;
            --error-border: #991b1b;
            --error-text: #fecaca;
        }
        
        :root[data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f9fafb;
            --border-color: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --accent: #f1484e;
            --accent-hover: #d53b40ff;
            --error-bg: #fee2e2;
            --error-border: #fecaca;
            --error-text: #991b1b;
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 40px;
            max-width: 400px;
            width: 100%;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .logo {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 20px;
            color: white;
            background: var(--accent);
        }
        .theme-toggle {
            width: 40px;
            height: 40px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 18px;
        }
        .theme-toggle:hover {
            background: var(--bg-secondary);
        }
        h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        p {
            color: var(--text-secondary);
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
        }
        input {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: var(--accent);
        }
        button {
            width: 100%;
            padding: 12px 16px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover {
            background: var(--accent-hover);
        }
        .error {
            padding: 12px 16px;
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            border-radius: 8px;
            color: var(--error-text);
            font-size: 14px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="header">
            <div class="logo">
                <?php 
                $logo = $config['site_logo'] ?? 'PM';
                if (str_starts_with($logo, 'img:')) {
                    $logoFile = substr($logo, 4);
                    echo '<img src="' . htmlspecialchars($logoFile) . '" alt="' . htmlspecialchars($config['site_name'] ?? 'PriviMetrics') . '" style="height:40px;">';
                } else {
                    echo '<span>' . htmlspecialchars($logo) . '</span>';
                }
                ?>
            </div>
            <a href="?theme=<?= $theme === 'dark' ? 'light' : 'dark' ?>" class="theme-toggle">
                <?= $theme === 'dark' ? '<svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"> <circle cx="12" cy="12" r="5" /> <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" /></svg>' : '<svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"> <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" /></svg>' ?>
            </a>
        </div>
        <h1>Welcome back</h1>
        <p>Sign in to your analytics dashboard</p>
        
        <?php if ($error): ?>
            <div class="error"><?= sanitize($error) ?></div>
        <?php endif; ?>
        
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Sign In</button>
        </form>
    </div>
</body>
</html>
