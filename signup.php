<?php
// ===============================================================================
// PriviMetrics - Admin Signup & Self-Destruct
// ===============================================================================

$error = '';
$success = '';
$admin_file = 'admin.php';
$self = basename(__FILE__);

// Handle manual deletion request
if (isset($_GET['delete_self'])) {
    @unlink($self);
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = $_POST['username'] ?? '';
    $new_password = $_POST['password'] ?? '';
    
    if (empty($new_username) || empty($new_password)) {
        $error = 'Please fill in both fields.';
    } elseif (!file_exists($admin_file)) {
        $error = "Error: File '$admin_file' not found.";
    } elseif (!is_writable($admin_file)) {
        $error = "Error: No write permissions for '$admin_file'.";
    } else {
        // 1. Generate correct bcrypt hash
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
        
        // 2. Get content of admin.php
        $content = file_get_contents($admin_file);
        
        // 3. Prepare hash for PHP file injection (escaping $ for preg_replace)
        $safe_hash = str_replace('$', '\$', $new_hash);

        // 4. Update ADMIN_USERNAME
        $content = preg_replace(
            "/define\('ADMIN_USERNAME',\s*'.*?'\);/",
            "define('ADMIN_USERNAME', '" . addslashes($new_username) . "');",
            $content
        );
        
        // 5. Update ADMIN_PASSWORD_HASH
        $content = preg_replace(
            "/define\('ADMIN_PASSWORD_HASH',\s*'.*?'\);/",
            "define('ADMIN_PASSWORD_HASH', '" . $safe_hash . "');",
            $content
        );

        // 6. Save the updated file
        if (file_put_contents($admin_file, $content)) {
            $success = "Success! Admin credentials updated.<br>The system will now attempt to remove this file for security.";
            
            // 7. Auto-delete attempt
            @unlink($self); 
        } else {
            $error = "Failed to write changes to the file.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin - PriviMetrics</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #0a0a0a; color: #e5e5e5; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .container { background: #151515; padding: 30px; border-radius: 12px; border: 1px solid #252525; width: 100%; max-width: 400px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        h2 { margin-top: 0; font-size: 22px; text-align: center; color: #fff; }
        label { display: block; margin-top: 15px; font-size: 14px; color: #a0a0a0; }
        input { width: 100%; padding: 12px; background: #0a0a0a; border: 1px solid #252525; border-radius: 8px; color: #fff; margin-top: 5px; box-sizing: border-box; }
        input:focus { border-color: #f1484e; outline: none; }
        button { width: 100%; padding: 12px; background: #f1484e; border: none; border-radius: 8px; color: white; font-weight: bold; cursor: pointer; margin-top: 20px; font-size: 16px; transition: background 0.2s; }
        button:hover { background: #d53b40; }
        .remove-btn { background: #333; margin-top: 10px; font-size: 13px; }
        .remove-btn:hover { background: #444; }
        .success { background: #064e3b; color: #a7f3d0; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; border: 1px solid #065f46; }
        .error { background: #7f1d1d; color: #fecaca; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; border: 1px solid #991b1b; }
        .footer-link { text-align: center; margin-top: 20px; }
        .footer-link a { color: #a0a0a0; font-size: 13px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Create Admin</h2>
        
        <?php if ($error): ?> <div class="error"><?= $error ?></div> <?php endif; ?>
        
        <?php if ($success): ?> 
            <div class="success"><?= $success ?></div> 
            <form method="get">
                <input type="hidden" name="delete_self" value="1">
                <button type="submit" class="remove-btn">Remove this file & Go to Login</button>
            </form>
        <?php else: ?>
            <form method="post">
                <label for="username">Admin Username</label>
                <input type="text" id="username" name="username" required value="admin">
                
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter secure password">
                
                <button type="submit">Signup</button>
            </form>
            <div class="footer-link">
                <a href="admin.php">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>