<?php
session_start();
require_once '../../backend/config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Audit Logs';
include '../../shared/templates/header.php';

$db = getDBConnection();

// Get audit logs
$sql = "SELECT al.*, u.first_name, u.last_name, u.email
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 500";

$stmt = $db->query($sql);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="page-header">
        <h1>Audit Logs</h1>
        <p>System activity and security logs</p>
    </div>
    
    <div class="filters">
        <input type="text" id="searchLogs" placeholder="Search logs..." class="search-input">
        <select id="actionFilter" class="filter-select">
            <option value="">All Actions</option>
            <option value="login">Login</option>
            <option value="logout">Logout</option>
            <option value="create">Create</option>
            <option value="update">Update</option>
            <option value="delete">Delete</option>
        </select>
        <input type="date" id="dateFilter" class="filter-select">
    </div>
    
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Resource</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody id="logsTableBody">
                <?php foreach ($logs as $log): ?>
                    <tr data-action="<?= $log['action'] ?>" data-date="<?= date('Y-m-d', strtotime($log['created_at'])) ?>">
                        <td><?= date('M j, Y H:i:s', strtotime($log['created_at'])) ?></td>
                        <td>
                            <?php if ($log['user_id']): ?>
                                <?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?>
                                <br><small><?= htmlspecialchars($log['email']) ?></small>
                            <?php else: ?>
                                System
                            <?php endif; ?>
                        </td>
                        <td><span class="action-badge action-<?= $log['action'] ?>"><?= ucfirst($log['action']) ?></span></td>
                        <td><?= htmlspecialchars($log['resource_type'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($log['details'] ?? '') ?></td>
                        <td><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.action-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.action-login { background: #d4edda; color: #155724; }
.action-logout { background: #d1ecf1; color: #0c5460; }
.action-create { background: #d1ecf1; color: #0c5460; }
.action-update { background: #fff3cd; color: #856404; }
.action-delete { background: #f8d7da; color: #721c24; }
</style>

<script>
document.getElementById('searchLogs').addEventListener('input', filterLogs);
document.getElementById('actionFilter').addEventListener('change', filterLogs);
document.getElementById('dateFilter').addEventListener('change', filterLogs);

function filterLogs() {
    const search = document.getElementById('searchLogs').value.toLowerCase();
    const action = document.getElementById('actionFilter').value;
    const date = document.getElementById('dateFilter').value;
    
    const rows = document.querySelectorAll('#logsTableBody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const rowAction = row.dataset.action;
        const rowDate = row.dataset.date;
        
        const matchesSearch = text.includes(search);
        const matchesAction = !action || rowAction === action;
        const matchesDate = !date || rowDate === date;
        
        row.style.display = matchesSearch && matchesAction && matchesDate ? '' : 'none';
    });
}
</script>

<?php include '../../shared/templates/footer.php'; ?>
