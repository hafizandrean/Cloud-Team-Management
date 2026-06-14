<?php
/**
 * Cloud Team Management - Detail Anggota
 */

require_once __DIR__ . '/../config/layout.php';

$db = Database::getConnection();

// Get and validate Member ID
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['flash_error'] = 'ID Anggota tidak valid.';
    header('Location: index.php');
    exit;
}

// Fetch member data
try {
    $stmt = $db->prepare("SELECT * FROM anggota WHERE id = ?");
    $stmt->execute([$id]);
    $member = $stmt->fetch();
    
    if (!$member) {
        $_SESSION['flash_error'] = 'Anggota tidak ditemukan.';
        header('Location: index.php');
        exit;
    }

    // Fetch projects assigned to this member
    $projectQuery = "
        SELECT p.id, p.nama_proyek, p.status, p.deadline 
        FROM anggota_proyek ap 
        JOIN proyek p ON ap.proyek_id = p.id 
        WHERE ap.anggota_id = ?
        ORDER BY p.deadline ASC
    ";
    $projStmt = $db->prepare($projectQuery);
    $projStmt->execute([$id]);
    $assignedProjects = $projStmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

// Render Header using layout helper
renderHeader('Detail Anggota', 'anggota', '../');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold welcome-title" id="page-title">Profil Anggota</h1>
        <p class="welcome-subtitle text-muted">Detail lengkap profil anggota tim CTM.</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 gap-2">
        <a href="index.php" class="btn btn-outline-secondary d-flex align-items-center gap-2 py-2 px-3">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            <span>Kembali ke Daftar</span>
        </a>
        <a href="edit.php?id=<?php echo $member['id']; ?>" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3 border-0" style="background-color: var(--primary-color);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            <span>Edit Profil</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Profile Card (Left Column) -->
    <div class="col-12 col-md-5 col-lg-4">
        <div class="card border-0 shadow-sm p-4 text-center h-100">
            <div class="my-4 d-flex justify-content-center">
                <?php 
                $avatarPath = '../uploads/' . $member['foto'];
                if (!empty($member['foto']) && file_exists(dirname(__DIR__) . '/uploads/' . $member['foto'])): 
                ?>
                    <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar" class="rounded-circle shadow-sm" style="width: 140px; height: 140px; object-fit: cover; border: 4px solid var(--border-color);">
                <?php else: ?>
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-uppercase fw-bold shadow-sm" style="width: 140px; height: 140px; font-size: 52px; background-color: var(--primary-color); color: #ffffff; border: 4px solid var(--border-color);">
                        <?php echo htmlspecialchars(substr($member['nama'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($member['nama']); ?></h4>
            <p class="text-muted small mb-3">NIM: <?php echo htmlspecialchars($member['nim']); ?></p>
            
            <div class="badge bg-light text-muted border py-2 px-3 text-capitalize rounded-pill">
                <?php echo !empty($member['id_user']) ? 'Terkait Akun CTM' : 'Tanpa Akun User'; ?>
            </div>
            
            <div class="mt-4 pt-3 border-top text-start">
                <div class="mb-3">
                    <span class="text-muted d-block small">Alamat Email</span>
                    <strong class="text-dark small d-block text-truncate"><?php echo htmlspecialchars($member['email'] ?? '-'); ?></strong>
                </div>
                <div>
                    <span class="text-muted d-block small">Bergabung Sejak</span>
                    <strong class="text-dark small"><?php echo date('d F Y', strtotime($member['created_at'])); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Assigned Projects (Right Column) -->
    <div class="col-12 col-md-7 col-lg-8">
        <div class="card border-0 shadow-sm p-4 h-100">
            <h5 class="fw-bold text-dark mb-4">Proyek Yang Ditugaskan</h5>
            
            <?php if (empty($assignedProjects)): ?>
                <div class="text-center text-muted py-5 my-auto">
                    <div class="fs-1 mb-2">📂</div>
                    <p class="mb-0">Anggota ini belum ditugaskan ke proyek apa pun saat ini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Nama Proyek</th>
                                <th>Deadline</th>
                                <th>Status Proyek</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignedProjects as $p): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($p['nama_proyek']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($p['deadline'])); ?></td>
                                    <td>
                                        <?php 
                                        $badgeClass = 'badge-planning';
                                        $statusText = 'Planning';
                                        if ($p['status'] === 'berjalan') { $badgeClass = 'badge-progress'; $statusText = 'On Progress'; }
                                        elseif ($p['status'] === 'selesai') { $badgeClass = 'badge-completed'; $statusText = 'Completed'; }
                                        elseif ($p['status'] === 'tertunda') { $badgeClass = 'badge-suspended'; $statusText = 'Suspended'; }
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Render Footer using layout helper
renderFooter('../');
?>
