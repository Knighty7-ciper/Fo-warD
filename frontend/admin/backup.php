<?php
require_once '../../backend/config/auth.php';
requireLogin();
requireRole(['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore - FowarD LMS</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/backup.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="backup-header">
            <h1>Backup & Restore</h1>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="showSettings()">Settings</button>
                <button class="btn btn-primary" onclick="createBackup()">Create Backup Now</button>
            </div>
        </div>

        <div class="backup-stats" id="backupStats">
            <div class="stat-card">
                <div class="stat-icon">💾</div>
                <div class="stat-info">
                    <div class="stat-value" id="totalBackups">-</div>
                    <div class="stat-label">Total Backups</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-info">
                    <div class="stat-value" id="lastBackup">-</div>
                    <div class="stat-label">Last Backup</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-info">
                    <div class="stat-value" id="totalSize">-</div>
                    <div class="stat-label">Total Size</div>
                </div>
            </div>
        </div>

        <div class="backups-list">
            <h2>Backup History</h2>
            <div class="table-container">
                <table class="backups-table" id="backupsTable">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="backupsTableBody">
                        <tr>
                            <td colspan="7" class="loading">Loading backups...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Backup Settings</h2>
                <button class="close-btn" onclick="closeModal('settingsModal')">&times;</button>
            </div>
            <form id="settingsForm">
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="autoBackupEnabled" checked>
                        Enable Automatic Backups
                    </label>
                </div>

                <div class="form-group">
                    <label for="backupFrequency">Backup Frequency</label>
                    <select id="backupFrequency">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="backupTime">Backup Time</label>
                    <input type="time" id="backupTime" value="02:00">
                    <small>Time when automatic backups will run</small>
                </div>

                <div class="form-group">
                    <label for="retentionDays">Retention Period (days)</label>
                    <input type="number" id="retentionDays" value="30" min="1" max="365">
                    <small>Backups older than this will be automatically deleted</small>
                </div>

                <div class="form-group">
                    <label for="maxBackups">Maximum Backups to Keep</label>
                    <input type="number" id="maxBackups" value="10" min="1" max="100">
                    <small>Only keep this many most recent backups</small>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="includeUploads">
                        Include Uploaded Files
                    </label>
                    <small>Include user uploads in backups (increases backup size)</small>
                </div>

                <div class="form-group">
                    <label for="notificationEmail">Notification Email</label>
                    <input type="email" id="notificationEmail" placeholder="admin@example.com">
                    <small>Receive notifications when automatic backups complete</small>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('settingsModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div id="restoreModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Restore Database</h2>
                <button class="close-btn" onclick="closeModal('restoreModal')">&times;</button>
            </div>
            <div class="restore-warning">
                <div class="warning-icon">⚠️</div>
                <h3>Warning: This action cannot be undone!</h3>
                <p>Restoring this backup will replace all current data in the database. Make sure you have a recent backup before proceeding.</p>
                <p><strong>Backup to restore:</strong> <span id="restoreFilename"></span></p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('restoreModal')">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmRestore()">Restore Database</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/backup.js"></script>
</body>
</html>
