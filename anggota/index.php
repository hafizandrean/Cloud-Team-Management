<?php
/**
 * Cloud Team Management - Daftar Anggota
 */

require_once __DIR__ . '/../config/layout.php';

$db = Database::getConnection();

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;

if ($page < 1) {
    $page = 1;
}

$offset = ($page - 1) * $limit;

try {
    // Total semua anggota untuk card kanan atas
    $totalAnggotaAll = (int) $db->query("SELECT COUNT(*) FROM anggota")->fetchColumn();

    // Total data sesuai pencarian untuk pagination
    if (!empty($search)) {
        $searchParam = "%{$search}%";

        $countQuery = "
            SELECT COUNT(*) 
            FROM anggota 
            WHERE nama LIKE :search1 
               OR nim LIKE :search2 
               OR email LIKE :search3
        ";

        $stmt = $db->prepare($countQuery);
        $stmt->bindValue(':search1', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search2', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search3', $searchParam, PDO::PARAM_STR);
        $stmt->execute();

        $totalRecords = (int) $stmt->fetchColumn();

        $query = "
            SELECT a.*, u.role AS system_role 
            FROM anggota a 
            LEFT JOIN users u ON a.id_user = u.id 
            WHERE a.nama LIKE :search1 
               OR a.nim LIKE :search2 
               OR a.email LIKE :search3 
            ORDER BY a.nama ASC 
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':search1', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search2', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search3', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $members = $stmt->fetchAll();
    } else {
        $totalRecords = $totalAnggotaAll;

        $query = "
            SELECT a.*, u.role AS system_role 
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

    $totalPages = max(1, (int) ceil($totalRecords / $limit));

    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();

    $members = [];
    $totalRecords = 0;
    $totalAnggotaAll = 0;
    $totalPages = 1;
}

/**
 * Badge role sistem
 */
if (!function_exists('getRoleBadge')) {
    function getRoleBadge($role)
    {
        $role = strtolower(trim($role ?? 'member'));

        if ($role === 'admin') {
            return '<span class="badge rounded-pill text-white" style="background-color: #8b5cf6;">Admin</span>';
        }

        return '<span class="badge rounded-pill text-white" style="background-color: #64748b;">Member</span>';
    }
}

/**
 * Badge jabatan tim
 */
if (!function_exists('getJabatanBadge')) {
    function getJabatanBadge($jabatan)
    {
        $jabatan = trim($jabatan ?? 'Developer');

        $colors = [
            'Project Manager' => '#10b981',
            'Product Manager' => '#10b981',
            'Scrum Master' => '#10b981',
            'Team Lead' => '#10b981',

            'Developer' => '#3b82f6',
            'Frontend Developer' => '#3b82f6',
            'Backend Developer' => '#3b82f6',
            'Full Stack Developer' => '#3b82f6',
            'Mobile Developer' => '#3b82f6',

            'UI Designer' => '#ec4899',
            'UX Designer' => '#ec4899',
            'UI/UX Designer' => '#ec4899',
            'Graphic Designer' => '#ec4899',

            'Business Analyst' => '#06b6d4',
            'System Analyst' => '#06b6d4',
            'Data Analyst' => '#06b6d4',

            'System Administrator' => '#4b5563',
            'Database Administrator' => '#4b5563',
            'Data Engineer' => '#4b5563',
            'Cloud Engineer' => '#4b5563',
            'DevOps Engineer' => '#4b5563',

            'Cyber Security Analyst' => '#f97316',
            'Security Engineer' => '#f97316',

            'Technical Support' => '#14b8a6',
            'IT Support' => '#14b8a6',
            'Documentation Specialist' => '#14b8a6',
        ];

        if (in_array($jabatan, ['QA Tester', 'QA Engineer', 'Software Tester'], true)) {
            return '<span class="badge rounded-pill text-dark" style="background-color: #fbbf24;">' . htmlspecialchars($jabatan) . '</span>';
        }

        $color = $colors[$jabatan] ?? '#6b7280';

        return '<span class="badge rounded-pill text-white" style="background-color: ' . $color . ';">' . htmlspecialchars($jabatan) . '</span>';
    }
}

renderHeader('Kelola Anggota', 'anggota', '../');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div class="me-3">
        <h1 class="h2 fw-bold welcome-title mb-1" id="page-title">Kelola Anggota</h1>

        <p class="welcome-subtitle text-muted mb-2">
            Pantau dan kelola data anggota yang terdaftar dalam tim CTM.
        </p>

        <p class="member-page-note">
            <span>Alur Registrasi</span>
            Anggota baru mendaftar secara mandiri, lalu admin dapat mengatur jabatan dan hak aksesnya.
        </p>
    </div>

    <div class="my-2 flex-shrink-0">
        <a href="index.php" class="total-anggota-card text-decoration-none" title="Lihat Semua Anggota">
            <div class="total-anggota-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>

            <div class="total-anggota-text">
                <span class="total-anggota-label">Total Anggota</span>
                <span class="total-anggota-number">
                    <?php echo (int) $totalAnggotaAll; ?>
                    <span>Orang</span>
                </span>
            </div>
        </a>
    </div>
</div>

<!-- Search Form -->
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
                    placeholder="Cari berdasarkan nama, NIM, atau email..." 
                    value="<?php echo htmlspecialchars($search); ?>"
                >
            </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3 d-flex gap-2">
            <button type="submit" class="btn btn-light border w-100">Filter</button>

            <?php if (!empty($search)): ?>
                <a href="index.php" class="btn btn-outline-secondary w-100">Reset</a>
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
                    <th>NIM</th>
                    <th>Jabatan</th>
                    <th>Role Sistem</th>
                    <th>Email</th>
                    <th style="width: 200px;" class="text-center">Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <div class="mb-2">👥</div>
                            <div>Tidak ada data anggota ditemukan.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($members as $m): ?>
                        <?php
                        $memberId = (int) $m['id'];
                        $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
                        $isOwnProfile = $memberId === (int) ($_SESSION['anggota_id'] ?? 0);

                        $avatarPath = '../uploads/' . ($m['foto'] ?? '');
                        $avatarFile = dirname(__DIR__) . '/uploads/' . ($m['foto'] ?? '');
                        ?>
                        
                        <tr>
                            <td>
                                <?php if (!empty($m['foto']) && file_exists($avatarFile)): ?>
                                    <img 
                                        src="<?php echo htmlspecialchars($avatarPath); ?>" 
                                        alt="Avatar <?php echo htmlspecialchars($m['nama']); ?>" 
                                        class="avatar-mini"
                                    >
                                <?php else: ?>
                                    <div class="avatar-mini text-uppercase fw-bold">
                                        <?php echo htmlspecialchars(substr($m['nama'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="fw-semibold text-dark">
                                <?php echo htmlspecialchars($m['nama']); ?>
                            </td>

                            <td class="text-muted">
                                <?php echo htmlspecialchars($m['nim']); ?>
                            </td>

                            <td>
                                <?php echo getJabatanBadge($m['jabatan']); ?>
                            </td>

                            <td>
                                <?php echo getRoleBadge($m['system_role'] ?? 'member'); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($m['email']); ?>
                            </td>

                            <td>
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="detail.php?id=<?php echo $memberId; ?>" class="btn btn-outline-secondary btn-sm" title="Detail">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </a>

                                    <?php if ($isAdmin || $isOwnProfile): ?>
                                        <a href="edit.php?id=<?php echo $memberId; ?>" class="btn btn-outline-primary btn-sm" title="Edit">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($isAdmin): ?>
                                        <a 
                                            href="delete.php?id=<?php echo $memberId; ?>" 
                                            class="btn btn-outline-danger btn-sm" 
                                            title="Hapus"
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus anggota <?php echo htmlspecialchars($m['nama']); ?>?');"
                                        >
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                                <line x1="14" y1="11" x2="14" y2="17"></line>
                                            </svg>
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
                <a 
                    class="page-link rounded border shadow-sm px-3 py-2 text-dark" 
                    href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                >
                    Sebelumnya
                </a>
            </li>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                    <a 
                        class="page-link rounded border shadow-sm px-3 py-2 <?php echo $page === $i ? 'bg-primary border-primary text-white' : 'text-dark'; ?>" 
                        href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                    >
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                <a 
                    class="page-link rounded border shadow-sm px-3 py-2 text-dark" 
                    href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                >
                    Selanjutnya
                </a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php
renderFooter('../');
?>