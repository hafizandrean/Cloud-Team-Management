<?php
/**
 * Cloud Team Management - Daftar Proyek
 */

require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/helper.php';

$db = Database::getConnection();

// Search & Filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Pagination variables
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

try {
    // 1. Fetch overall Project Metrics (unfiltered)
    $totalProjects = $db->query("SELECT COUNT(*) FROM proyek")->fetchColumn();
    $planningProjects = $db->query("SELECT COUNT(*) FROM proyek WHERE status = 'direncanakan'")->fetchColumn();
    $progressProjects = $db->query("SELECT COUNT(*) FROM proyek WHERE status = 'berjalan'")->fetchColumn();
    $completedProjects = $db->query("SELECT COUNT(*) FROM proyek WHERE status = 'selesai'")->fetchColumn();

    // 2. Build Query with filters
    $whereClauses = [];
    $params = [];

    // If not admin, only show projects they are assigned to
    if (($_SESSION['role'] ?? '') !== 'admin') {
        $anggotaId = $_SESSION['anggota_id'] ?? null;
        if (!$anggotaId) {
            $stmtAnggota = $db->prepare("SELECT id FROM anggota WHERE id_user = ?");
            $stmtAnggota->execute([$_SESSION['user_id']]);
            $anggotaId = $stmtAnggota->fetchColumn() ?: null;
            $_SESSION['anggota_id'] = $anggotaId;
        }
        $whereClauses[] = "p.id IN (SELECT proyek_id FROM anggota_proyek WHERE anggota_id = :session_anggota_id)";
        $params[':session_anggota_id'] = $anggotaId ? (int)$anggotaId : 0;
    }

    if (!empty($search)) {
        $whereClauses[] = "p.nama_proyek LIKE :search";
        $params[':search'] = "%$search%";
    }

    if (!empty($statusFilter)) {
        $whereClauses[] = "p.status = :status";
        $params[':status'] = $statusFilter;
    }

    $whereSql = '';
    if (!empty($whereClauses)) {
        $whereSql = "WHERE " . implode(" AND ", $whereClauses);
    }

    // Get filtered total count
    $countQuery = "SELECT COUNT(*) FROM proyek p $whereSql";
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $val) {
        $countStmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $filteredTotal = $countStmt->fetchColumn();

    $totalPages = ceil($filteredTotal / $limit);
    if ($totalPages < 1) {
        $totalPages = 1;
    }
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    // Fetch paginated filtered projects with member counts
    $query = "
        SELECT p.*, (SELECT COUNT(*) FROM anggota_proyek WHERE proyek_id = p.id) AS member_count 
        FROM proyek p 
        $whereSql 
        ORDER BY p.deadline ASC 
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $projects = $stmt->fetchAll();

} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
    $projects = [];
    $totalProjects = 0;
    $planningProjects = 0;
    $progressProjects = 0;
    $completedProjects = 0;
    $filteredTotal = 0;
    $totalPages = 1;
}

// Render Header using layout helper
renderHeader('Kelola Proyek', 'proyek', '../');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold welcome-title" id="page-title">Kelola Proyek</h1>
        <p class="welcome-subtitle text-muted">Kelola daur hidup proyek dan penugasan tim di lingkungan CTM.</p>
    </div>
    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="create.php" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3 shadow-sm border-0" style="background-color: var(--primary-color);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            <span>Tambah Proyek</span>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
<!-- Project Summary Mini-Cards -->
<div class="row g-4 mb-4">
    <!-- Total Proyek -->
    <div class="col-6 col-md-3">
        <a href="index.php" class="card p-3 border-0 shadow-sm d-flex flex-row align-items-center justify-content-between text-decoration-none" title="Lihat Semua Proyek" style="cursor: pointer;">
            <div>
                <span class="text-muted d-block small fw-medium text-uppercase tracking-wider">Total Proyek</span>
                <h3 class="fw-bold mb-0 text-dark"><?php echo $totalProjects; ?></h3>
            </div>
            <div class="card-summary-icon proyek small">📂</div>
        </a>
    </div>
    <!-- Planning -->
    <div class="col-6 col-md-3">
        <a href="index.php?status=direncanakan" class="card p-3 border-0 shadow-sm d-flex flex-row align-items-center justify-content-between text-decoration-none" title="Lihat Proyek Planning" style="cursor: pointer;">
            <div>
                <span class="text-muted d-block small fw-medium text-uppercase tracking-wider">Planning</span>
                <h3 class="fw-bold mb-0 text-dark"><?php echo $planningProjects; ?></h3>
            </div>
            <div class="card-summary-icon assignment small">📋</div>
        </a>
    </div>
    <!-- On Progress -->
    <div class="col-6 col-md-3">
        <a href="index.php?status=berjalan" class="card p-3 border-0 shadow-sm d-flex flex-row align-items-center justify-content-between text-decoration-none" title="Lihat Proyek On Progress" style="cursor: pointer;">
            <div>
                <span class="text-muted d-block small fw-medium text-uppercase tracking-wider">On Progress</span>
                <h3 class="fw-bold mb-0 text-dark"><?php echo $progressProjects; ?></h3>
            </div>
            <div class="card-summary-icon users small">⚡</div>
        </a>
    </div>
    <!-- Completed -->
    <div class="col-6 col-md-3">
        <a href="index.php?status=selesai" class="card p-3 border-0 shadow-sm d-flex flex-row align-items-center justify-content-between text-decoration-none" title="Lihat Proyek Completed" style="cursor: pointer;">
            <div>
                <span class="text-muted d-block small fw-medium text-uppercase tracking-wider">Completed</span>
                <h3 class="fw-bold mb-0 text-dark"><?php echo $completedProjects; ?></h3>
            </div>
            <div class="card-summary-icon anggota small">✓</div>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Search & Filters -->
<div class="card border-0 shadow-sm p-4 mb-4">
    <form method="GET" action="index.php" class="row g-3 align-items-center">
        <!-- Search Input -->
        <div class="col-12 col-md-6 col-lg-7">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </span>
                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari berdasarkan nama proyek..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </div>
        <!-- Status Dropdown Filter -->
        <div class="col-12 col-sm-6 col-md-3 col-lg-3">
            <select name="status" class="form-select">
                <option value="">-- Semua Status --</option>
                <option value="direncanakan" <?php echo $statusFilter === 'direncanakan' ? 'selected' : ''; ?>>Planning</option>
                <option value="berjalan" <?php echo $statusFilter === 'berjalan' ? 'selected' : ''; ?>>On Progress</option>
                <option value="selesai" <?php echo $statusFilter === 'selesai' ? 'selected' : ''; ?>>Completed</option>
                <option value="tertunda" <?php echo $statusFilter === 'tertunda' ? 'selected' : ''; ?>>Suspended</option>
            </select>
        </div>
        <!-- Filter/Reset Buttons -->
        <div class="col-12 col-sm-6 col-md-3 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-light border w-100 py-2">Filter</button>
            <?php if (!empty($search) || !empty($statusFilter)): ?>
                <a href="index.php" class="btn btn-outline-secondary w-100 py-2">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Projects List Table -->
<div class="card border-0 shadow-sm p-0 mb-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover table-custom mb-0">
            <thead>
                <tr>
                    <th>Nama Proyek</th>
                    <th>Anggota Tim</th>
                    <th>Tenggat Waktu (Deadline)</th>
                    <th style="width: 180px;">Status</th>
                    <th style="width: 180px;" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <div class="mb-2">📂</div>
                            <div>Tidak ada proyek ditemukan.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $p): ?>
                        <tr>
                            <td class="fw-semibold text-dark text-wrap" style="max-width: 300px;">
                                <?php echo htmlspecialchars($p['nama_proyek']); ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-muted border px-2 py-1 small">
                                    👥 <?php echo (int)$p['member_count']; ?> Anggota
                                </span>
                            </td>
                            <td class="text-muted">
                                <?php echo date('d M Y', strtotime($p['deadline'])); ?>
                            </td>
                            <td>
                                <?php echo getProjectStatusBadge($p['status'], $p['deadline']); ?>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="detail.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Detail">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </a>
                                    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                    <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Edit">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </a>
                                    <a href="delete.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus proyek <?php echo htmlspecialchars($p['nama_proyek']); ?>?');">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <nav aria-label="Navigasi Halaman" class="d-flex justify-content-center">
        <ul class="pagination pagination-sm gap-1 border-0">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link rounded border shadow-sm px-3 py-2 text-dark" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?>">Sebelumnya</a>
            </li>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                    <a class="page-link rounded border shadow-sm px-3 py-2 <?php echo $page === $i ? 'bg-primary border-primary text-white' : 'text-dark'; ?>" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link rounded border shadow-sm px-3 py-2 text-dark" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?>">Selanjutnya</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php
// Render Footer using layout helper
renderFooter('../');
?>
