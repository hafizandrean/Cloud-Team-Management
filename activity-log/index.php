<?php
/**
 * Cloud Team Management - Activity Log (Audit Trail Dashboard)
 */

require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/../config/activity_helper.php';

$db = Database::getConnection();

// Get filter inputs
$search = trim($_GET['search'] ?? '');
$activityFilter = trim($_GET['activity_type'] ?? 'all');
$dateRange = trim($_GET['date_range'] ?? 'all');

// 1. Fetch metrics summary counts
$metricTotalLogs = 0;
$metricTodayLogins = 0;
$metricDataChanges = 0;
$metricActiveUsers = 0;

try {
    // Total Activities
    $metricTotalLogs = $db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
    
    // Logins Today
    $metricTodayLogins = $db->query("
        SELECT COUNT(*) 
        FROM activity_logs 
        WHERE activity_type = 'LOGIN' 
        AND DATE(created_at) = CURDATE()
    ")->fetchColumn();
    
    // Data Changes (CREATE, UPDATE, DELETE)
    $metricDataChanges = $db->query("
        SELECT COUNT(*) 
        FROM activity_logs 
        WHERE activity_type LIKE '%CREATE%' 
           OR activity_type LIKE '%UPDATE%' 
           OR activity_type LIKE '%DELETE%'
    ")->fetchColumn();
    
    // Active Users Today
    $metricActiveUsers = $db->query("
        SELECT COUNT(DISTINCT user_id) 
        FROM activity_logs 
        WHERE DATE(created_at) = CURDATE()
    ")->fetchColumn();
} catch (PDOException $e) {
    error_log("Failed to load activity metrics: " . $e->getMessage());
}

// 2. Build dynamic queries for the audit table list
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(u.username LIKE :search OR a.activity_type LIKE :search OR a.description LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if ($activityFilter !== 'all') {
    if (in_array($activityFilter, ['CREATE', 'UPDATE', 'DELETE', 'ASSIGNMENT'])) {
        $where[] = "a.activity_type LIKE :activity_type";
        $params['activity_type'] = '%' . $activityFilter . '%';
    } else {
        $where[] = "a.activity_type = :activity_type";
        $params['activity_type'] = $activityFilter;
    }
}

if ($dateRange !== 'all') {
    if ($dateRange === 'today') {
        $where[] = "a.created_at >= CURDATE()";
    } elseif ($dateRange === '7days') {
        $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($dateRange === '30days') {
        $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
}

$whereClause = '';
if (!empty($where)) {
    $whereClause = ' WHERE ' . implode(' AND ', $where);
}

// 3. Setup Pagination
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

try {
    // Count total filtered records
    $countSql = "
        SELECT COUNT(*) 
        FROM activity_logs a 
        JOIN users u ON a.user_id = u.id
    " . $whereClause;
    
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    
    $totalPages = ceil($totalRecords / $limit);
    if ($totalPages < 1) {
        $totalPages = 1;
    }
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $limit;

    // Fetch filtered audit logs sorted by newest first
    $sql = "
        SELECT a.*, u.username 
        FROM activity_logs a 
        JOIN users u ON a.user_id = u.id
        " . $whereClause . " 
        ORDER BY a.created_at DESC, a.id DESC 
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $db->prepare($sql);
    
    // Bind parameters
    foreach ($params as $key => $val) {
        $stmt->bindValue(':' . $key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Gagal memuat log aktivitas: ' . $e->getMessage();
    $logs = [];
    $totalPages = 1;
    $totalRecords = 0;
}

// Render layout header
renderHeader('Activity Log', 'activity', '../');
?>

<!-- Title Block -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold welcome-title" id="page-title">Activity Log</h1>
        <p class="welcome-subtitle text-muted">Jejak aktivitas dan perubahan sistem (Audit Trail).</p>
    </div>
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="clear.php" class="btn btn-danger d-flex align-items-center gap-2 py-2 px-3 shadow-sm" onclick="return confirm('Apakah Anda yakin ingin menghapus seluruh log aktivitas? Tindakan ini tidak dapat dibatalkan.');" id="btn-clear-logs">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                <span>Bersihkan Log</span>
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Metrics Cards Grid -->
<div class="row g-4 mb-4">
    <!-- Card 1: Total Aktivitas -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card-summary d-flex align-items-center justify-content-between">
            <div class="metric-info">
                <div class="card-summary-title">Total Aktivitas</div>
                <div class="card-summary-value" id="val-total-logs"><?php echo $metricTotalLogs; ?></div>
            </div>
            <div class="card-summary-icon" style="background-color: rgba(99, 102, 241, 0.1); color: var(--primary-color);">📊</div>
        </div>
    </div>
    <!-- Card 2: Login Hari Ini -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card-summary d-flex align-items-center justify-content-between">
            <div class="metric-info">
                <div class="card-summary-title">Login Hari Ini</div>
                <div class="card-summary-value" id="val-today-logins"><?php echo $metricTodayLogins; ?></div>
            </div>
            <div class="card-summary-icon" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981;">🔑</div>
        </div>
    </div>
    <!-- Card 3: Perubahan Data -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card-summary d-flex align-items-center justify-content-between">
            <div class="metric-info">
                <div class="card-summary-title">Perubahan Data</div>
                <div class="card-summary-value" id="val-data-changes"><?php echo $metricDataChanges; ?></div>
            </div>
            <div class="card-summary-icon" style="background-color: rgba(245, 158, 11, 0.1); color: #f59e0b;">🔄</div>
        </div>
    </div>
    <!-- Card 4: User Aktif -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card-summary d-flex align-items-center justify-content-between">
            <div class="metric-info">
                <div class="card-summary-title">User Aktif</div>
                <div class="card-summary-value" id="val-active-users"><?php echo $metricActiveUsers; ?></div>
            </div>
            <div class="card-summary-icon" style="background-color: rgba(14, 165, 233, 0.1); color: #0ea5e9;">⚡</div>
        </div>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-sm p-4 mb-4">
    <form action="index.php" method="GET" class="row g-3">
        <!-- Search Query -->
        <div class="col-12 col-md-4">
            <label for="search" class="form-label small fw-bold text-muted">Cari Log</label>
            <input type="text" class="form-control" id="search" name="search" placeholder="Cari username, aktivitas, deskripsi..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <!-- Type Filter -->
        <div class="col-12 col-sm-6 col-md-3">
            <label for="activity_type" class="form-label small fw-bold text-muted">Kategori Aktivitas</label>
            <select class="form-select" id="activity_type" name="activity_type">
                <option value="all" <?php echo $activityFilter === 'all' ? 'selected' : ''; ?>>Semua</option>
                <option value="LOGIN" <?php echo $activityFilter === 'LOGIN' ? 'selected' : ''; ?>>LOGIN</option>
                <option value="LOGOUT" <?php echo $activityFilter === 'LOGOUT' ? 'selected' : ''; ?>>LOGOUT</option>
                <option value="CREATE" <?php echo $activityFilter === 'CREATE' ? 'selected' : ''; ?>>CREATE (Tambah)</option>
                <option value="UPDATE" <?php echo $activityFilter === 'UPDATE' ? 'selected' : ''; ?>>UPDATE (Edit)</option>
                <option value="DELETE" <?php echo $activityFilter === 'DELETE' ? 'selected' : ''; ?>>DELETE (Hapus)</option>
                <option value="ASSIGNMENT" <?php echo $activityFilter === 'ASSIGNMENT' ? 'selected' : ''; ?>>ASSIGNMENT</option>
            </select>
        </div>
        <!-- Date Range Filter -->
        <div class="col-12 col-sm-6 col-md-3">
            <label for="date_range" class="form-label small fw-bold text-muted">Rentang Waktu</label>
            <select class="form-select" id="date_range" name="date_range">
                <option value="all" <?php echo $dateRange === 'all' ? 'selected' : ''; ?>>Semua</option>
                <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                <option value="7days" <?php echo $dateRange === '7days' ? 'selected' : ''; ?>>7 Hari Terakhir</option>
                <option value="30days" <?php echo $dateRange === '30days' ? 'selected' : ''; ?>>30 Hari Terakhir</option>
            </select>
        </div>
        <!-- Filter Action Button -->
        <div class="col-12 col-md-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary w-100 py-2">Filter</button>
            <?php if (!empty($search) || $activityFilter !== 'all' || $dateRange !== 'all'): ?>
                <a href="index.php" class="btn btn-outline-secondary py-2 px-3 d-flex align-items-center justify-content-center" title="Reset Filter">✕</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Logs Content -->
<?php if (!empty($logs)): ?>
    <div class="card border-0 shadow-sm p-0 mb-4">
        <div class="table-responsive">
            <table class="table table-hover table-custom mb-0">
                <thead>
                    <tr>
                        <th style="width: 20%">Waktu</th>
                        <th style="width: 15%">User</th>
                        <th style="width: 20%">Aktivitas</th>
                        <th style="width: 45%">Deskripsi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-muted"><?php echo date('d M Y H:i', strtotime($log['created_at'])); ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-mini bg-primary text-white text-uppercase" style="width: 28px; height: 28px; font-size: 11px;">
                                        <?php echo substr(htmlspecialchars($log['username']), 0, 2); ?>
                                    </div>
                                    <span class="fw-medium text-dark"><?php echo htmlspecialchars($log['username']); ?></span>
                                </div>
                            </td>
                            <td><?php echo getActivityBadge($log['activity_type']); ?></td>
                            <td><?php echo htmlspecialchars($log['description']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Halaman log">
            <ul class="pagination justify-content-center mt-4">
                <!-- Previous Page -->
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $activityFilter !== 'all' ? '&activity_type=' . urlencode($activityFilter) : ''; ?><?php echo $dateRange !== 'all' ? '&date_range=' . urlencode($dateRange) : ''; ?>" tabindex="-1">Sebelumnya</a>
                </li>
                
                <!-- Page Numbers -->
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $activityFilter !== 'all' ? '&activity_type=' . urlencode($activityFilter) : ''; ?><?php echo $dateRange !== 'all' ? '&date_range=' . urlencode($dateRange) : ''; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <!-- Next Page -->
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $activityFilter !== 'all' ? '&activity_type=' . urlencode($activityFilter) : ''; ?><?php echo $dateRange !== 'all' ? '&date_range=' . urlencode($dateRange) : ''; ?>">Selanjutnya</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

<?php else: ?>
    <!-- Empty State -->
    <div class="card border-0 shadow-sm p-5 text-center mb-4">
        <div class="my-4">
            <span style="font-size: 48px;">📝</span>
            <h4 class="fw-bold mt-3 text-dark">Belum Ada Aktivitas</h4>
            <p class="text-muted">Aktivitas pengguna akan muncul di sini setelah sistem digunakan.</p>
            <?php if (!empty($search) || $activityFilter !== 'all' || $dateRange !== 'all'): ?>
                <a href="index.php" class="btn btn-outline-primary btn-sm mt-2">Reset Semua Filter</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php
// Render layout footer
renderFooter('../');
?>
