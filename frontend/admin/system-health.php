<?php
$page_title = 'System Health - Admin';
require_once __DIR__ . '/../../shared/templates/header.php';
require_once __DIR__ . '/../../backend/includes/system-check.php';

// Check if user is admin
if (!Auth::isAuthenticated() || Auth::getUserRole() !== 'admin') {
    header('Location: /frontend/login.php');
    exit;
}

$health_check = SystemCheck::checkAll();
$system_info = SystemCheck::getSystemInfo();
?>

<div class="admin-container">
    <div class="admin-sidebar">
        <?php include __DIR__ . '/../../shared/templates/admin-nav.php'; ?>
    </div>
    
    <div class="admin-content">
        <div class="page-header">
            <h1>System Health Check</h1>
            <p>Monitor your LMS system status and requirements</p>
        </div>
        
        <div class="health-overview">
            <div class="health-card <?php echo $health_check['overall'] ? 'healthy' : 'unhealthy'; ?>">
                <div class="health-icon">
                    <?php echo $health_check['overall'] ? '✓' : '✗'; ?>
                </div>
                <h2>System Status</h2>
                <p class="health-status">
                    <?php echo $health_check['overall'] ? 'All Systems Operational' : 'Issues Detected'; ?>
                </p>
            </div>
        </div>
        
        <div class="health-section">
            <h3>PHP Version</h3>
            <div class="requirement-item <?php echo $health_check['php_version']['status'] ? 'pass' : 'fail'; ?>">
                <div class="requirement-info">
                    <strong>Current: <?php echo $health_check['php_version']['current']; ?></strong>
                    <span>Required: <?php echo $health_check['php_version']['required']; ?>+</span>
                </div>
                <div class="requirement-status">
                    <?php echo $health_check['php_version']['status'] ? '✓' : '✗'; ?>
                </div>
            </div>
        </div>
        
        <div class="health-section">
            <h3>PHP Extensions</h3>
            <?php foreach ($health_check['extensions'] as $ext): ?>
                <div class="requirement-item <?php echo $ext['status'] ? 'pass' : 'fail'; ?>">
                    <div class="requirement-info">
                        <strong><?php echo $ext['name']; ?></strong>
                        <span><?php echo $ext['message']; ?></span>
                    </div>
                    <div class="requirement-status">
                        <?php echo $ext['status'] ? '✓' : '✗'; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="health-section">
            <h3>Directory Permissions</h3>
            <?php foreach ($health_check['directories'] as $dir): ?>
                <div class="requirement-item <?php echo $dir['status'] ? 'pass' : 'fail'; ?>">
                    <div class="requirement-info">
                        <strong><?php echo $dir['name']; ?></strong>
                        <span><?php echo $dir['path']; ?></span>
                    </div>
                    <div class="requirement-status">
                        <?php echo $dir['status'] ? '✓' : '✗'; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="health-section">
            <h3>Database Connection</h3>
            <div class="requirement-item <?php echo $health_check['database']['status'] ? 'pass' : 'fail'; ?>">
                <div class="requirement-info">
                    <strong>Database Status</strong>
                    <span><?php echo $health_check['database']['message']; ?></span>
                </div>
                <div class="requirement-status">
                    <?php echo $health_check['database']['status'] ? '✓' : '✗'; ?>
                </div>
            </div>
        </div>
        
        <div class="health-section">
            <h3>System Information</h3>
            <table class="info-table">
                <tr>
                    <td><strong>PHP Version</strong></td>
                    <td><?php echo $system_info['php_version']; ?></td>
                </tr>
                <tr>
                    <td><strong>Server Software</strong></td>
                    <td><?php echo $system_info['server_software']; ?></td>
                </tr>
                <tr>
                    <td><strong>Operating System</strong></td>
                    <td><?php echo $system_info['os']; ?></td>
                </tr>
                <tr>
                    <td><strong>Max Execution Time</strong></td>
                    <td><?php echo $system_info['max_execution_time']; ?>s</td>
                </tr>
                <tr>
                    <td><strong>Memory Limit</strong></td>
                    <td><?php echo $system_info['memory_limit']; ?></td>
                </tr>
                <tr>
                    <td><strong>Upload Max Filesize</strong></td>
                    <td><?php echo $system_info['upload_max_filesize']; ?></td>
                </tr>
                <tr>
                    <td><strong>Post Max Size</strong></td>
                    <td><?php echo $system_info['post_max_size']; ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<style>
.health-overview {
    margin-bottom: 30px;
}

.health-card {
    background: white;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.health-card.healthy {
    border-left: 5px solid #10b981;
}

.health-card.unhealthy {
    border-left: 5px solid #ef4444;
}

.health-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    font-weight: bold;
}

.health-card.healthy .health-icon {
    background: #d1fae5;
    color: #10b981;
}

.health-card.unhealthy .health-icon {
    background: #fee2e2;
    color: #ef4444;
}

.health-status {
    font-size: 18px;
    color: #666;
    margin-top: 10px;
}

.health-section {
    background: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.health-section h3 {
    margin-bottom: 20px;
    color: #333;
    font-size: 20px;
}

.requirement-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 8px;
    background: #f9fafb;
}

.requirement-item.pass {
    border-left: 4px solid #10b981;
}

.requirement-item.fail {
    border-left: 4px solid #ef4444;
}

.requirement-info strong {
    display: block;
    color: #333;
    margin-bottom: 5px;
}

.requirement-info span {
    color: #666;
    font-size: 14px;
}

.requirement-status {
    font-size: 24px;
    font-weight: bold;
}

.requirement-item.pass .requirement-status {
    color: #10b981;
}

.requirement-item.fail .requirement-status {
    color: #ef4444;
}

.info-table {
    width: 100%;
    border-collapse: collapse;
}

.info-table tr {
    border-bottom: 1px solid #e5e7eb;
}

.info-table td {
    padding: 12px 0;
}

.info-table td:first-child {
    width: 40%;
    color: #666;
}

.info-table td:last-child {
    color: #333;
}
</style>

<?php require_once __DIR__ . '/../../shared/templates/footer.php'; ?>
