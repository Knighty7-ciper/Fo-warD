<?php
// Prevent access if already installed
if (file_exists(__DIR__ . '/../config/installed.lock')) {
    header('Location: /frontend/index.php');
    exit;
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // System requirements check
        header('Location: ?step=2');
        exit;
    } elseif ($step === 2) {
        // Database configuration
        $db_host = $_POST['db_host'] ?? '';
        $db_name = $_POST['db_name'] ?? '';
        $db_user = $_POST['db_user'] ?? '';
        $db_pass = $_POST['db_pass'] ?? '';
        
        // Test database connection
        try {
            $pdo = new PDO(
                "mysql:host=$db_host;charset=utf8mb4",
                $db_user,
                $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Save database configuration
            $config_content = "<?php\n";
            $config_content .= "// Database configuration\n";
            $config_content .= "\$host = '$db_host';\n";
            $config_content .= "\$dbname = '$db_name';\n";
            $config_content .= "\$username = '$db_user';\n";
            $config_content .= "\$password = '$db_pass';\n";
            $config_content .= "\n// Connection\n";
            $config_content .= "try {\n";
            $config_content .= "    \$pdo = new PDO(\n";
            $config_content .= "        \"mysql:host=\$host;dbname=\$dbname;charset=utf8mb4\",\n";
            $config_content .= "        \$username,\n";
            $config_content .= "        \$password,\n";
            $config_content .= "        [\n";
            $config_content .= "            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
            $config_content .= "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
            $config_content .= "            PDO::ATTR_EMULATE_PREPARES => false\n";
            $config_content .= "        ]\n";
            $config_content .= "    );\n";
            $config_content .= "} catch(PDOException \$e) {\n";
            $config_content .= "    error_log(\"Database connection failed: \" . \$e->getMessage());\n";
            $config_content .= "    die(\"Database connection failed. Please check your configuration.\");\n";
            $config_content .= "}\n";
            
            file_put_contents(__DIR__ . '/../backend/config/db.php', $config_content);
            
            // Store DB info in session for next step
            session_start();
            $_SESSION['install_db'] = [
                'host' => $db_host,
                'name' => $db_name,
                'user' => $db_user,
                'pass' => $db_pass
            ];
            
            header('Location: ?step=3');
            exit;
        } catch (PDOException $e) {
            $error = "Database connection failed: " . $e->getMessage();
        }
    } elseif ($step === 3) {
        // Import database schema
        session_start();
        $db_info = $_SESSION['install_db'];
        
        try {
            $pdo = new PDO(
                "mysql:host={$db_info['host']};dbname={$db_info['name']};charset=utf8mb4",
                $db_info['user'],
                $db_info['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Read and execute schema
            $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
            $pdo->exec($schema);
            
            header('Location: ?step=4');
            exit;
        } catch (PDOException $e) {
            $error = "Database setup failed: " . $e->getMessage();
        }
    } elseif ($step === 4) {
        // Site configuration
        $site_name = $_POST['site_name'] ?? 'FowarD LMS';
        $site_url = $_POST['site_url'] ?? '';
        $admin_email = $_POST['admin_email'] ?? '';
        
        session_start();
        $_SESSION['install_config'] = [
            'site_name' => $site_name,
            'site_url' => $site_url,
            'admin_email' => $admin_email
        ];
        
        header('Location: ?step=5');
        exit;
    } elseif ($step === 5) {
        // Create admin account
        session_start();
        $db_info = $_SESSION['install_db'];
        $config = $_SESSION['install_config'];
        
        $admin_name = $_POST['admin_name'] ?? '';
        $admin_email = $_POST['admin_email'] ?? '';
        $admin_password = $_POST['admin_password'] ?? '';
        $admin_password_confirm = $_POST['admin_password_confirm'] ?? '';
        
        if ($admin_password !== $admin_password_confirm) {
            $error = "Passwords do not match";
        } else {
            try {
                $pdo = new PDO(
                    "mysql:host={$db_info['host']};dbname={$db_info['name']};charset=utf8mb4",
                    $db_info['user'],
                    $db_info['pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                
                // Create admin user
                $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')");
                $stmt->execute([$admin_name, $admin_email, $hashed_password]);
                
                // Update system settings
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type) VALUES (?, ?, 'string') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute(['site_name', $config['site_name']]);
                $stmt->execute(['site_url', $config['site_url']]);
                $stmt->execute(['admin_email', $admin_email]);
                
                // Create installation lock file
                file_put_contents(__DIR__ . '/../config/installed.lock', date('Y-m-d H:i:s'));
                
                // Clear session
                unset($_SESSION['install_db']);
                unset($_SESSION['install_config']);
                
                header('Location: ?step=6');
                exit;
            } catch (PDOException $e) {
                $error = "Admin account creation failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FowarD LMS Installation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .install-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 700px;
            width: 100%;
            overflow: hidden;
        }
        
        .install-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .install-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .install-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .progress-bar {
            display: flex;
            background: rgba(255,255,255,0.2);
            padding: 0;
            margin-top: 20px;
        }
        
        .progress-step {
            flex: 1;
            height: 4px;
            background: rgba(255,255,255,0.3);
            transition: background 0.3s;
        }
        
        .progress-step.active {
            background: white;
        }
        
        .install-body {
            padding: 40px;
        }
        
        .step-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .step-description {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #999;
            font-size: 12px;
        }
        
        .requirements-list {
            list-style: none;
            margin: 20px 0;
        }
        
        .requirements-list li {
            padding: 12px;
            margin-bottom: 10px;
            background: #f5f5f5;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .requirement-status {
            font-weight: 600;
        }
        
        .requirement-status.pass {
            color: #10b981;
        }
        
        .requirement-status.fail {
            color: #ef4444;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
        }
        
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>FowarD LMS Installation</h1>
            <p>Welcome! Let's set up your learning management system</p>
            <div class="progress-bar">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <div class="progress-step <?php echo $i <= $step ? 'active' : ''; ?>"></div>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="install-body">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
                <h2 class="step-title">System Requirements</h2>
                <p class="step-description">Let's check if your server meets the requirements for FowarD LMS.</p>
                
                <ul class="requirements-list">
                    <li>
                        <span>PHP Version (>= 7.4)</span>
                        <span class="requirement-status <?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? 'pass' : 'fail'; ?>">
                            <?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? '✓ ' . PHP_VERSION : '✗ ' . PHP_VERSION; ?>
                        </span>
                    </li>
                    <li>
                        <span>PDO Extension</span>
                        <span class="requirement-status <?php echo extension_loaded('pdo') ? 'pass' : 'fail'; ?>">
                            <?php echo extension_loaded('pdo') ? '✓ Installed' : '✗ Not Installed'; ?>
                        </span>
                    </li>
                    <li>
                        <span>PDO MySQL Extension</span>
                        <span class="requirement-status <?php echo extension_loaded('pdo_mysql') ? 'pass' : 'fail'; ?>">
                            <?php echo extension_loaded('pdo_mysql') ? '✓ Installed' : '✗ Not Installed'; ?>
                        </span>
                    </li>
                    <li>
                        <span>JSON Extension</span>
                        <span class="requirement-status <?php echo extension_loaded('json') ? 'pass' : 'fail'; ?>">
                            <?php echo extension_loaded('json') ? '✓ Installed' : '✗ Not Installed'; ?>
                        </span>
                    </li>
                    <li>
                        <span>Config Directory Writable</span>
                        <span class="requirement-status <?php echo is_writable(__DIR__ . '/../backend/config') ? 'pass' : 'fail'; ?>">
                            <?php echo is_writable(__DIR__ . '/../backend/config') ? '✓ Writable' : '✗ Not Writable'; ?>
                        </span>
                    </li>
                </ul>
                
                <form method="POST">
                    <div class="btn-group">
                        <span></span>
                        <button type="submit" class="btn btn-primary">Continue →</button>
                    </div>
                </form>
                
            <?php elseif ($step === 2): ?>
                <h2 class="step-title">Database Configuration</h2>
                <p class="step-description">Enter your MySQL database credentials. The installer will create the database if it doesn't exist.</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Database Host</label>
                        <input type="text" name="db_host" value="localhost" required>
                        <small>Usually "localhost" or "127.0.0.1"</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Database Name</label>
                        <input type="text" name="db_name" value="forward_lms" required>
                        <small>The database will be created if it doesn't exist</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Database Username</label>
                        <input type="text" name="db_user" value="root" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Database Password</label>
                        <input type="password" name="db_pass">
                        <small>Leave blank if no password is set</small>
                    </div>
                    
                    <div class="btn-group">
                        <a href="?step=1" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn btn-primary">Test & Continue →</button>
                    </div>
                </form>
                
            <?php elseif ($step === 3): ?>
                <h2 class="step-title">Database Setup</h2>
                <p class="step-description">Click the button below to create all necessary database tables and initial data.</p>
                
                <form method="POST">
                    <div class="btn-group">
                        <a href="?step=2" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn btn-primary">Install Database →</button>
                    </div>
                </form>
                
            <?php elseif ($step === 4): ?>
                <h2 class="step-title">Site Configuration</h2>
                <p class="step-description">Configure your LMS site settings.</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Site Name</label>
                        <input type="text" name="site_name" value="FowarD LMS" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Site URL</label>
                        <input type="url" name="site_url" value="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>" required>
                        <small>Your site's full URL (including http:// or https://)</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Admin Email</label>
                        <input type="email" name="admin_email" required>
                        <small>This email will receive system notifications</small>
                    </div>
                    
                    <div class="btn-group">
                        <a href="?step=3" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn btn-primary">Continue →</button>
                    </div>
                </form>
                
            <?php elseif ($step === 5): ?>
                <h2 class="step-title">Create Admin Account</h2>
                <p class="step-description">Create your administrator account to manage the LMS.</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Admin Name</label>
                        <input type="text" name="admin_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Admin Email</label>
                        <input type="email" name="admin_email" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="admin_password" required minlength="8">
                        <small>Minimum 8 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="admin_password_confirm" required minlength="8">
                    </div>
                    
                    <div class="btn-group">
                        <a href="?step=4" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn btn-primary">Complete Installation →</button>
                    </div>
                </form>
                
            <?php elseif ($step === 6): ?>
                <div class="text-center">
                    <div class="success-icon">✓</div>
                    <h2 class="step-title">Installation Complete!</h2>
                    <p class="step-description">Your FowarD LMS has been successfully installed and configured.</p>
                    
                    <div style="margin: 30px 0;">
                        <a href="/frontend/index.php" class="btn btn-primary">Go to Homepage</a>
                        <a href="/frontend/login.php" class="btn btn-secondary" style="margin-left: 10px;">Admin Login</a>
                    </div>
                    
                    <div style="background: #f5f5f5; padding: 20px; border-radius: 6px; text-align: left; margin-top: 20px;">
                        <strong>Important Security Note:</strong>
                        <p style="margin-top: 10px; color: #666; font-size: 14px;">
                            For security reasons, please delete or rename the <code>/install</code> directory to prevent unauthorized access.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
