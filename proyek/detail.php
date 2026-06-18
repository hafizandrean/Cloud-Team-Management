<?php
/**
 * Cloud Team Management - Detail Proyek
 */

require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/helper.php';

$db = Database::getConnection();

// Get and validate Project ID
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['flash_error'] = 'ID Proyek tidak valid.';
    header('Location: index.php');
    exit;
}

// Fetch project details
try {
    $stmt = $db->prepare("SELECT * FROM proyek WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        $_SESSION['flash_error'] = 'Proyek tidak ditemukan.';
        header('Location: index.php');
        exit;
    }

    // If member, verify that they are assigned to this project
    if (($_SESSION['role'] ?? '') !== 'admin') {
        $anggotaId = $_SESSION['anggota_id'] ?? null;
        if (!$anggotaId) {
            $stmtAnggota = $db->prepare("SELECT id FROM anggota WHERE id_user = ?");
            $stmtAnggota->execute([$_SESSION['user_id']]);
            $anggotaId = $stmtAnggota->fetchColumn() ?: null;
            $_SESSION['anggota_id'] = $anggotaId;
        }
        
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM anggota_proyek WHERE anggota_id = ? AND proyek_id = ?");
        $checkStmt->execute([$anggotaId, $id]);
        if ($checkStmt->fetchColumn() == 0) {
            $_SESSION['flash_error'] = 'Akses ditolak. Anda tidak ditugaskan pada proyek ini.';
            header('Location: index.php');
            exit;
        }
    }

    // Fetch members assigned to this project
    $memberQuery = "
        SELECT a.id, a.nama, a.nim, a.email, a.foto 
        FROM anggota a 
        JOIN anggota_proyek ap ON a.id = ap.anggota_id 
        WHERE ap.proyek_id = ?
        ORDER BY a.nama ASC
    ";
    $memberStmt = $db->prepare($memberQuery);
    $memberStmt->execute([$id]);
    $assignedMembers = $memberStmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

// Render Header using layout helper
renderHeader('Detail Proyek', 'proyek', '../');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold welcome-title" id="page-title">Detail Proyek</h1>
        <p class="welcome-subtitle text-muted">Detail informasi proyek dan kolaborator tim.</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 gap-2">
        <a href="index.php" class="btn btn-outline-secondary d-flex align-items-center gap-2 py-2 px-3">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            <span>Kembali ke Daftar</span>
        </a>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <a href="edit.php?id=<?php echo $project['id']; ?>" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3 border-0" style="background-color: var(--primary-color);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            <span>Edit Proyek</span>
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Project Description Card (Left Column) -->
    <div class="col-12 col-md-5 col-lg-5">
        <div class="card border-0 shadow-sm p-4 h-100">
            <div class="d-flex flex-column h-100">
                <div class="mb-3">
                    <span class="text-muted d-block small fw-bold text-uppercase tracking-wider">Status Proyek</span>
                    <div class="mt-1">
                        <?php echo getProjectStatusBadge($project['status'], $project['deadline']); ?>
                    </div>
                </div>
                
                <h3 class="fw-bold text-dark mb-1 text-wrap mt-2"><?php echo htmlspecialchars($project['nama_proyek']); ?></h3>
                <span class="text-muted small d-block mb-4">Deadline: <?php echo date('d F Y', strtotime($project['deadline'])); ?></span>
                
                <div class="border-top pt-3 flex-grow-1">
                    <span class="text-muted d-block small fw-bold text-uppercase tracking-wider mb-2">Deskripsi Proyek</span>
                    <p class="text-dark small style-description text-wrap" style="line-height: 1.6; white-space: pre-wrap;"><?php echo !empty($project['deskripsi']) ? htmlspecialchars($project['deskripsi']) : '<em>Tidak ada deskripsi untuk proyek ini.</em>'; ?></p>
                </div>
                
                <div class="border-top pt-3 mt-auto small text-muted">
                    <span>Dibuat pada: <?php echo date('d M Y, H:i', strtotime($project['created_at'])); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Assigned Team Members (Right Column) -->
    <div class="col-12 col-md-7 col-lg-7">
        <div class="card border-0 shadow-sm p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold text-dark mb-0">Anggota Tim Yang Terlibat</h5>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <a href="../assignment/create.php?project_id=<?php echo $project['id']; ?>" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1 py-1 px-2 shadow-sm" style="font-size: 13px; font-weight: 500; border-radius: 6px;">
                    <span>+</span> <span>Tambah Anggota</span>
                </a>
                <?php endif; ?>
            </div>
            
            <?php if (empty($assignedMembers)): ?>
                <div class="text-center text-muted py-5 my-auto">
                    <div class="fs-1 mb-2">👥</div>
                    <p class="mb-0">Belum ada anggota tim yang ditugaskan ke proyek ini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-custom mb-0">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Foto</th>
                                <th>Nama Lengkap</th>
                                <th>NIM</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignedMembers as $m): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        $avatarPath = '../uploads/' . $m['foto'];
                                        if (!empty($m['foto']) && file_exists(dirname(__DIR__) . '/uploads/' . $m['foto'])): 
                                        ?>
                                            <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar" class="avatar-mini" style="width: 36px; height: 36px;">
                                        <?php else: ?>
                                            <div class="avatar-mini text-uppercase fw-bold" style="width: 36px; height: 36px; font-size: 12px; background-color: var(--primary-color); color: #ffffff;">
                                                <?php echo htmlspecialchars(substr($m['nama'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold text-dark"><?php echo htmlspecialchars($m['nama']); ?></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($m['nim']); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($m['email']); ?></td>
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
