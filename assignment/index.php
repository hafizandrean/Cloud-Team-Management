<?php
/**
 * Cloud Team Management - Daftar Penugasan (Assignment)
 */

require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../proyek/helper.php'; // For project status labels/badges

$db = Database::getConnection();

// Search & Filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$proyekFilter = isset($_GET['proyek_id']) && is_numeric($_GET['proyek_id']) ? (int)$_GET['proyek_id'] : 0;

// Pagination variables
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

try {
    // 1. Fetch metrics & distribution
    $stats = getAssignmentStats($db);
    $distribution = getProjectDistribution($db);
    
    // 2. Fetch Projects for dropdown filter
    $projectsDropdown = $db->query("SELECT id, nama_proyek FROM proyek ORDER BY nama_proyek ASC")->fetchAll();

    // 3. Build query for table
    $whereClauses = [];
    $params = [];

    // Filter assignments if the user is a Member
    if (($_SESSION['role'] ?? '') !== 'admin') {
        $anggotaId = $_SESSION['anggota_id'] ?? null;
        if (!$anggotaId) {
            $stmtAnggota = $db->prepare("SELECT id FROM anggota WHERE id_user = ?");
            $stmtAnggota->execute([$_SESSION['user_id']]);
            $anggotaId = $stmtAnggota->fetchColumn() ?: null;
            $_SESSION['anggota_id'] = $anggotaId;
        }
        $whereClauses[] = "ap.anggota_id = :session_anggota_id";
        $params[':session_anggota_id'] = $anggotaId ? (int)$anggotaId : 0;
    }

    if (!empty($search)) {
        $whereClauses[] = "(a.nama LIKE :search1 OR p.nama_proyek LIKE :search2)";
        $params[':search1'] = "%$search%";
        $params[':search2'] = "%$search%";
    }

    if ($proyekFilter > 0) {
        $whereClauses[] = "ap.proyek_id = :proyek_id";
        $params[':proyek_id'] = $proyekFilter;
    }

    $whereSql = '';
    if (!empty($whereClauses)) {
        $whereSql = "WHERE " . implode(" AND ", $whereClauses);
    }

    // Get filtered total count
    $countQuery = "
        SELECT COUNT(*) 
        FROM anggota_proyek ap 
        JOIN anggota a ON ap.anggota_id = a.id 
        JOIN proyek p ON ap.proyek_id = p.id 
        $whereSql
    ";
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

    // Fetch paginated filtered assignments sorted by created_at DESC
    $query = "
        SELECT ap.id, ap.created_at, a.nama, a.nim, a.foto, p.nama_proyek, p.status, p.deadline 
        FROM anggota_proyek ap 
        JOIN anggota a ON ap.anggota_id = a.id 
        JOIN proyek p ON ap.proyek_id = p.id 
        $whereSql 
        ORDER BY ap.created_at DESC 
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $assignments = $stmt->fetchAll();

} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
    $assignments = [];
    $stats = ['total_assignments' => 0, 'total_active_members' => 0, 'total_active_projects' => 0, 'average_assignments' => 0];
    $distribution = [];
    $projectsDropdown = [];
    $filteredTotal = 0;
    $totalPages = 1;
}

// Render Header using layout helper
renderHeader('Kelola Assignment', 'assignment', '../');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold welcome-title" id="page-title">
            <?php echo (($_SESSION['role'] ?? '') === 'admin') ? 'Penugasan & Kolaborasi' : 'Tugas Saya'; ?>
        </h1>
        <p class="welcome-subtitle text-muted">
            <?php echo (($_SESSION['role'] ?? '') === 'admin') ? 'Kelola relasi kolaborasi antara anggota tim dengan proyek.' : 'Daftar penugasan dan proyek yang Anda ikuti.'; ?>
        </p>
    </div>
    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="create.php" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3 shadow-sm border-0" style="background-color: var(--primary-color);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            <span>Tambah Assignment</span>
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- If no assignments exist at all in system, display a beautiful Empty State Card -->
<?php if ($stats['total_assignments'] == 0 && empty($search) && $proyekFilter == 0): ?>
    <div class="card border-0 shadow-sm p-5 text-center my-4">
        <div class="py-5">
            <div class="display-3 mb-3">🔗</div>
            <h3 class="fw-bold text-dark">Belum Ada Assignment</h3>
            <p class="text-muted mx-auto" style="max-width: 480px;">Sistem belum mencatat penugasan anggota ke proyek apa pun. Hubungkan anggota tim Anda ke proyek untuk mulai berkolaborasi.</p>
            <a href="create.php" class="btn btn-primary px-4 py-2 mt-3 border-0" style="background-color: var(--primary-color);">
                Tambah Assignment Pertama
            </a>
        </div>
    </div>
<?php else: ?>

    <!-- Assignment Statistics Header -->
    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
    <div class="row g-4 mb-4">
        <!-- Total Assignment -->
        <div class="col-6 col-lg-3">
            <a href="index.php" class="card p-3 border-0 shadow-sm d-flex flex-row align-items-center justify-content-between text-decoration-none" title="Lihat Daftar Penugasan" style="cursor: pointer;">
                <div>
                    <span class="text-muted d-block small fw-medium text-uppercase tracking-wider">Total Assignment</span>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo $stats['total_assignments']; ?></h3>
                </div>
                <div class="card-summary-icon assignment small">🔗</div>
            </a>
        </div>
        <!-- Anggota Aktif -->
        <div class="col-6 col-lg-3">
            <a href="../anggota/index.php" class="card p-3 border-0 shadow-sm d-flex flex-row align-items-center justify-content-between text-decoration-none" title="Lihat Daftar Anggota" style="cursor: pointer;">
                <div>
                    <span class="text-muted d-block small fw-medium text-uppercase tracking-wider">Anggota Aktif</span>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo $stats['total_active_members']; ?></h3>
                </div>
                <div class="card-summary-icon users small">👥</div>
            </a>
        </div>
        <!-- Proyek Aktif -->
        <div class="col-6 col-lg-3">
            <a href="../proyek/index.php" class="card p-3 border-0 shadow-sm d-flex flex-row align-items-center justify-content-between text-decoration-none" title="Lihat Daftar Proyek" style="cursor: pointer;">
                <div>
                    <span class="text-muted d-block small fw-medium text-uppercase tracking-wider">Proyek Aktif</span>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo $stats['total_active_projects']; ?></h3>
                </div>
                <div class="card-summary-icon proyek small">📂</div>
            </a>
        </div>
        <!-- Rata-rata Assignment -->
        <div class="col-6 col-lg-3">
            <a href="index.php" class="card p-3 border-0 shadow-sm d-flex flex-row align-items-center justify-content-between text-decoration-none" title="Rincian Rata-rata Penugasan" style="cursor: pointer;">
                <div>
                    <span class="text-muted d-block small fw-medium text-uppercase tracking-wider">Rata-rata Assignment</span>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['average_assignments'], 2); ?></h3>
                </div>
                <div class="card-summary-icon anggota small">📈</div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Search, Filters, Table and Distribution Grid -->
    <div class="row g-4">
        <!-- Main Table Area -->
        <div class="<?php echo (($_SESSION['role'] ?? '') === 'admin') ? 'col-12 col-xl-8' : 'col-12'; ?>">
            <!-- Search & Filters -->
            <div class="card border-0 shadow-sm p-4 mb-4">
                <form method="GET" action="index.php" class="row g-3 align-items-center">
                    <div class="col-12 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari Anggota atau Proyek..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-3">
                        <select name="proyek_id" class="form-select">
                            <option value="">-- Semua Proyek --</option>
                            <?php foreach ($projectsDropdown as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $proyekFilter === (int)$p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['nama_proyek']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-light border w-100 py-2">Filter</button>
                        <?php if (!empty($search) || $proyekFilter > 0): ?>
                            <a href="index.php" class="btn btn-outline-secondary w-100 py-2">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="card border-0 shadow-sm p-0 mb-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover table-custom mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th style="width: 70px;">Foto</th>
                                <th>Nama Anggota</th>
                                <th>Nama Proyek</th>
                                <th>Tanggal Assignment</th>
                                <th>Status Proyek</th>
                                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                <th style="width: 80px;" class="text-center">Aksi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assignments)): ?>
                                <tr>
                                    <td colspan="<?php echo (($_SESSION['role'] ?? '') === 'admin') ? 7 : 6; ?>" class="text-center text-muted py-4">
                                        Tidak ada data penugasan ditemukan.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $no = $offset + 1;
                                foreach ($assignments as $a): 
                                ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td>
                                            <?php 
                                            $avatarPath = '../uploads/' . $a['foto'];
                                            if (!empty($a['foto']) && file_exists(dirname(__DIR__) . '/uploads/' . $a['foto'])): 
                                            ?>
                                                <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar" class="avatar-mini" style="width: 36px; height: 36px;">
                                            <?php else: ?>
                                                <div class="avatar-mini text-uppercase fw-bold" style="width: 36px; height: 36px; font-size: 12px; background-color: var(--primary-color); color: #ffffff;">
                                                    <?php echo htmlspecialchars(substr($a['nama'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?php echo htmlspecialchars($a['nama']); ?></div>
                                            <div class="text-muted small">NIM: <?php echo htmlspecialchars($a['nim']); ?></div>
                                        </td>
                                        <td class="text-dark fw-medium text-wrap" style="max-width: 200px;">
                                            <?php echo htmlspecialchars($a['nama_proyek']); ?>
                                        </td>
                                        <td class="text-muted small">
                                            <?php echo date('d M Y', strtotime($a['created_at'])); ?>
                                        </td>
                                        <td>
                                            <?php echo getProjectStatusBadge($a['status'], $a['deadline']); ?>
                                        </td>
                                        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                        <td class="text-center">
                                            <a href="delete.php?id=<?php echo $a['id']; ?>" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus penugasan <?php echo htmlspecialchars($a['nama']); ?> dari proyek <?php echo htmlspecialchars($a['nama_proyek']); ?>?');">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                            </a>
                                        </td>
                                        <?php endif; ?>
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
                            <a class="page-link rounded border shadow-sm px-3 py-2 text-dark" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $proyekFilter > 0 ? '&proyek_id=' . $proyekFilter : ''; ?>">Sebelumnya</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                <a class="page-link rounded border shadow-sm px-3 py-2 <?php echo $page === $i ? 'bg-primary border-primary text-white' : 'text-dark'; ?>" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $proyekFilter > 0 ? '&proyek_id=' . $proyekFilter : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link rounded border shadow-sm px-3 py-2 text-dark" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $proyekFilter > 0 ? '&proyek_id=' . $proyekFilter : ''; ?>">Selanjutnya</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>

        <!-- Collaboration Widget (Right Column) -->
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm p-4 h-100">
                <h5 class="fw-bold text-dark mb-4">Distribusi Proyek (Collaboration)</h5>
                
                <?php if (empty($distribution)): ?>
                    <p class="text-muted small">Belum ada data distribusi.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($distribution as $d): ?>
                            <div>
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-medium text-dark" title="<?php echo htmlspecialchars($d['nama_proyek']); ?>">
                                        <?php echo htmlspecialchars($d['nama_proyek']); ?>
                                    </span>
                                    <span class="text-muted small fw-bold">
                                        <?php echo $d['count']; ?> Anggota
                                    </span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $d['percentage']; ?>%" aria-valuenow="<?php echo $d['percentage']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
// Render Footer using layout helper
renderFooter('../');
?>
