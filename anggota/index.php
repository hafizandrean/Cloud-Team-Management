<?php
/**
 * Cloud Team Management - Daftar Anggota
 */

require_once __DIR__ . '/../config/layout.php';

// Check database connection
$db = Database::getConnection();

// Search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination variables
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

// Prepare query for total records count
try {
    if (!empty($search)) {
        $countQuery = "
            SELECT COUNT(*) 
            FROM anggota a
            LEFT JOIN users u ON a.id_user = u.id
            WHERE a.nama LIKE :search1 
               OR a.nim LIKE :search2 
               OR a.email LIKE :search3
               OR u.username LIKE :search4
        ";
        $stmt = $db->prepare($countQuery);
        $searchParam = "%$search%";
        $stmt->bindValue(':search1', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search2', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search3', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search4', $searchParam, PDO::PARAM_STR);
        $stmt->execute();
        $totalRecords = $stmt->fetchColumn();
    } else {
        $totalRecords = $db->query("SELECT COUNT(*) FROM anggota")->fetchColumn();
    }

    $totalPages = ceil($totalRecords / $limit);
    if ($totalPages < 1) {
        $totalPages = 1;
    }
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    // Fetch members with linked login account
    if (!empty($search)) {
        $query = "
            SELECT 
                a.*,
                u.username
            FROM anggota a
            LEFT JOIN users u ON a.id_user = u.id
            WHERE a.nama LIKE :search1 
               OR a.nim LIKE :search2 
               OR a.email LIKE :search3
               OR u.username LIKE :search4
            ORDER BY a.nama ASC 
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':search1', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search2', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search3', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search4', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $members = $stmt->fetchAll();
    } else {
        $query = "
            SELECT 
                a.*,
                u.username
            FROM anggota a
            LEFT JOIN users u ON a.id_user = u.id
            ORDER BY a.nama ASC 
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $members = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
    $members = [];
    $totalRecords = 0;
    $totalPages = 1;
}

// Render Header using layout helper
renderHeader('Kelola Anggota', 'anggota', '../');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold welcome-title" id="page-title">Kelola Anggota</h1>
        <p class="welcome-subtitle text-muted">Kelola data anggota tim, termasuk menambahkan, memperbarui, dan menghapus anggota.</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="create.php" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3 shadow-sm border-0" style="background-color: var(--primary-color);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>Tambah Anggota</span>
        </a>
    </div>
</div>

<!-- Search Form & Filters -->
<div class="card border-0 shadow-sm p-4 mb-4">
    <form method="GET" action="index.php" class="row g-3 align-items-center">
        <div class="col-12 col-md-8 col-lg-9">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </span>
                <input 
                    type="text" 
                    name="search" 
                    class="form-control border-start-0 ps-0" 
                    placeholder="Cari berdasarkan Nama, Username, NIM, atau Email..." 
                    value="<?php echo htmlspecialchars($search); ?>"
                >
            </div>
        </div>
        <div class="col-12 col-md-4 col-lg-3 d-flex gap-2">
            <button type="submit" class="btn btn-light border w-100 py-2">Filter</button>
            <?php if (!empty($search)): ?>
                <a href="index.php" class="btn btn-outline-secondary w-100 py-2">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Member List Table -->
<div class="card border-0 shadow-sm p-0 mb-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover table-custom mb-0">
            <thead>
                <tr>
                    <th style="width: 80px;">Foto</th>
                    <th>Nama Lengkap</th>
                    <th>Akun Login</th>
                    <th>NIM</th>
                    <th>Email</th>
                    <th style="width: 200px;" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <div class="mb-2">👥</div>
                            <div>Tidak ada data anggota ditemukan.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td>
                                <?php 
                                $avatarPath = '../uploads/' . $m['foto'];
                                if (!empty($m['foto']) && file_exists(dirname(__DIR__) . '/uploads/' . $m['foto'])): 
                                ?>
                                    <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar" class="avatar-mini" style="width: 40px; height: 40px;">
                                <?php else: ?>
                                    <div class="avatar-mini text-uppercase fw-bold" style="width: 40px; height: 40px; font-size: 14px; background-color: var(--primary-color); color: #ffffff;">
                                        <?php echo htmlspecialchars(substr($m['nama'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="fw-semibold text-dark">
                                <?php echo htmlspecialchars($m['nama']); ?>
                            </td>

                            <td>
                                <?php if (!empty($m['username'])): ?>
                                    <span class="badge bg-light text-dark border px-3 py-2">
                                        <?php echo htmlspecialchars($m['username']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning border px-3 py-2">
                                        Belum terhubung
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="text-muted">
                                <?php echo htmlspecialchars($m['nim']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($m['email']); ?>
                            </td>

                            <td>
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="detail.php?id=<?php echo $m['id']; ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Detail">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </a>

                                    <a href="edit.php?id=<?php echo $m['id']; ?>" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Edit">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </a>

                                    <a href="delete.php?id=<?php echo $m['id']; ?>" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus anggota <?php echo htmlspecialchars($m['nama']); ?>?');">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            <line x1="10" y1="11" x2="10" y2="17"></line>
                                            <line x1="14" y1="11" x2="14" y2="17"></line>
                                        </svg>
                                    </a>
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
                <a class="page-link rounded border shadow-sm px-3 py-2 text-dark" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Sebelumnya</a>
            </li>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                    <a class="page-link rounded border shadow-sm px-3 py-2 <?php echo $page === $i ? 'bg-primary border-primary text-white' : 'text-dark'; ?>" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link rounded border shadow-sm px-3 py-2 text-dark" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Selanjutnya</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php
// Render Footer using layout helper
renderFooter('../');
?>