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
    $stmt = $db->prepare("
        SELECT a.*, u.role AS system_role 
        FROM anggota a 
        LEFT JOIN users u ON a.id_user = u.id 
        WHERE a.id = ?
    ");
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

// Helper functions for badges (Premium theme)
if (!function_exists('getRoleBadge')) {
    function getRoleBadge($role) {
        if ($role === 'admin') {
            return '<span class="badge px-2.5 py-1.5 rounded-pill text-white shadow-sm" style="background-color: #8b5cf6; font-size: 11px; font-weight: 600; letter-spacing: 0.3px;">Admin</span>';
        } else {
            return '<span class="badge px-2.5 py-1.5 rounded-pill text-white shadow-sm" style="background-color: #64748b; font-size: 11px; font-weight: 600; letter-spacing: 0.3px;">Member</span>';
        }
    }
}

if (!function_exists('getJabatanBadge')) {
    function getJabatanBadge($jabatan) {
        $jabatan = trim($jabatan ?? 'Developer');
        
        switch ($jabatan) {
            // Management: Green
            case 'Project Manager':
            case 'Product Manager':
            case 'Scrum Master':
            case 'Team Lead':
                return '<span class="badge px-2.5 py-1.5 rounded-pill text-white shadow-sm" style="background-color: #10b981; font-size: 11px; font-weight: 600;">' . htmlspecialchars($jabatan) . '</span>';
            
            // Development: Blue
            case 'Developer':
            case 'Frontend Developer':
            case 'Backend Developer':
            case 'Full Stack Developer':
            case 'Mobile Developer':
                return '<span class="badge px-2.5 py-1.5 rounded-pill text-white shadow-sm" style="background-color: #3b82f6; font-size: 11px; font-weight: 600;">' . htmlspecialchars($jabatan) . '</span>';
            
            // Design: Pink
            case 'UI Designer':
            case 'UX Designer':
            case 'UI/UX Designer':
            case 'Graphic Designer':
                return '<span class="badge px-2.5 py-1.5 rounded-pill text-white shadow-sm" style="background-color: #ec4899; font-size: 11px; font-weight: 600;">' . htmlspecialchars($jabatan) . '</span>';
            
            // QA: Amber/Yellow (dark text)
            case 'QA Tester':
            case 'QA Engineer':
            case 'Software Tester':
                return '<span class="badge px-2.5 py-1.5 text-dark shadow-sm" style="background-color: #fbbf24; font-size: 11px; font-weight: 600; border: 1px solid rgba(0,0,0,0.05);">' . htmlspecialchars($jabatan) . '</span>';
            
            // Analysis: Cyan
            case 'Business Analyst':
            case 'System Analyst':
            case 'Data Analyst':
                return '<span class="badge px-2.5 py-1.5 rounded-pill text-white shadow-sm" style="background-color: #06b6d4; font-size: 11px; font-weight: 600;">' . htmlspecialchars($jabatan) . '</span>';
            
            // Data & Cloud (and System Administrator): Charcoal/Dark Gray
            case 'System Administrator':
            case 'Database Administrator':
            case 'Data Engineer':
            case 'Cloud Engineer':
            case 'DevOps Engineer':
                return '<span class="badge px-2.5 py-1.5 rounded-pill text-white shadow-sm" style="background-color: #4b5563; font-size: 11px; font-weight: 600;">' . htmlspecialchars($jabatan) . '</span>';
            
            // Security: Orange
            case 'Cyber Security Analyst':
            case 'Security Engineer':
                return '<span class="badge px-2.5 py-1.5 rounded-pill text-white shadow-sm" style="background-color: #f97316; font-size: 11px; font-weight: 600;">' . htmlspecialchars($jabatan) . '</span>';
            
            // Support: Teal
            case 'Technical Support':
            case 'IT Support':
            case 'Documentation Specialist':
                return '<span class="badge px-2.5 py-1.5 rounded-pill text-white shadow-sm" style="background-color: #14b8a6; font-size: 11px; font-weight: 600;">' . htmlspecialchars($jabatan) . '</span>';
            
            // Custom / Fallback: Grey
            default:
                return '<span class="badge px-2.5 py-1.5 rounded-pill text-white shadow-sm" style="background-color: #6b7280; font-size: 11px; font-weight: 600;">' . htmlspecialchars($jabatan) . '</span>';
        }
    }
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
        <?php 
        $isOwnProfile = (int)$member['id'] === (int)($_SESSION['anggota_id'] ?? 0);
        if (($_SESSION['role'] ?? '') === 'admin' || $isOwnProfile): 
        ?>
        <a href="edit.php?id=<?php echo $member['id']; ?>" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3 border-0" style="background-color: var(--primary-color);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            <span>Edit Profil</span>
        </a>
        <?php endif; ?>
        <?php if ($isOwnProfile): ?>
        <button type="button" class="btn btn-danger d-flex align-items-center gap-2 py-2 px-3 border-0" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal1">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
            <span>Hapus Akun Saya</span>
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Profile Card (Left Column) -->
    <div class="col-12 col-md-5 col-lg-4">
        <div class="card p-4 text-center h-100">
            <div class="my-4 d-flex justify-content-center">
                <div class="profile-avatar-container" style="cursor: default; pointer-events: none;">
                    <div class="profile-avatar-inner">
                        <?php 
                        $avatarPath = '../uploads/' . $member['foto'];
                        if (!empty($member['foto']) && file_exists(dirname(__DIR__) . '/uploads/' . $member['foto'])): 
                        ?>
                            <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar" class="profile-avatar-img">
                        <?php else: ?>
                            <div class="profile-avatar-placeholder text-uppercase fw-bold">
                                <?php echo htmlspecialchars(substr($member['nama'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($member['nama']); ?></h4>
            <p class="text-muted small mb-3">NIM: <?php echo htmlspecialchars($member['nim']); ?></p>
            
            <div class="d-flex flex-column gap-2 align-items-center mb-4">
                <span class="badge bg-light text-muted border px-3 py-1 text-capitalize rounded-pill" style="font-size: 11px;">
                    <?php echo !empty($member['id_user']) ? 'Terkait Akun CTM' : 'Tanpa Akun User'; ?>
                </span>
                <?php if (!empty($member['id_user'])): ?>
                    <div class="small text-muted mb-2">
                        <strong class="me-1">Role Sistem:</strong> 
                        <?php echo getRoleBadge($member['system_role'] ?? 'member'); ?>
                    </div>
                <?php endif; ?>
                <div class="small text-muted">
                    <strong class="me-1">Jabatan:</strong> 
                    <?php echo getJabatanBadge($member['jabatan']); ?>
                </div>
            </div>
            
            <div class="user-stats-grid d-flex justify-content-around mt-auto pt-3 border-top w-100">
                <div class="stat-item text-center px-2">
                    <span class="text-muted d-block small mb-1" style="font-size: 11px;">Alamat Email</span>
                    <span class="fw-semibold text-dark small d-block" style="word-break: break-all;" title="<?php echo htmlspecialchars($member['email'] ?? '-'); ?>">
                        <?php echo htmlspecialchars($member['email'] ?? '-'); ?>
                    </span>
                </div>
                <div class="stat-item text-center px-2 border-start">
                    <span class="text-muted d-block small mb-1" style="font-size: 11px;">Bergabung Sejak</span>
                    <span class="fw-semibold text-dark small">
                        <?php echo date('d M Y', strtotime($member['created_at'])); ?>
                    </span>
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

<!-- Explanation Row (Role vs Jabatan) -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm p-4" style="background: rgba(255, 255, 255, 0.65); backdrop-filter: blur(10px); border-radius: 12px;">
            <h5 class="fw-bold text-dark mb-3">💡 Memahami Peran Sistem vs Jabatan Tim</h5>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="p-3 rounded bg-white border-start border-primary border-4 shadow-sm" style="border-radius: 8px;">
                        <h6 class="fw-bold text-primary mb-2">Role Sistem (Hak Akses)</h6>
                        <p class="text-muted small mb-0" style="line-height: 1.5;">Menentukan hak akses dan kontrol fungsionalitas di dalam aplikasi (<strong>Admin</strong> memiliki akses penuh ke Dashboard Global, CRUD Proyek/Anggota/Assignment, Laporan & Log. <strong>Member</strong> memiliki akses terbatas untuk melihat proyek, tugas, dan profil mereka sendiri).</p>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="p-3 rounded bg-white border-start border-success border-4 shadow-sm" style="border-radius: 8px;">
                        <h6 class="fw-bold text-success mb-2">Jabatan (Peran Tim)</h6>
                        <p class="text-muted small mb-0" style="line-height: 1.5;">Menentukan tanggung jawab profesional atau posisi fungsional anggota dalam tim proyek (seperti <em>Project Manager</em>, <em>Developer</em>, <em>QA Tester</em>, <em>UI/UX Designer</em>, dll.). Jabatan ini tidak membatasi hak akses sistem.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($isOwnProfile): ?>
<!-- Modal Konfirmasi Pertama -->
<div class="modal fade" id="confirmDeleteModal1" tabindex="-1" aria-labelledby="confirmDeleteModal1Label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold text-dark" id="confirmDeleteModal1Label">Konfirmasi Penghapusan Akun</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <span style="font-size: 48px;">⚠️</span>
        <h5 class="fw-bold text-danger mt-3">Apakah Anda yakin ingin menghapus akun Anda?</h5>
        <p class="text-muted small mb-0">Tindakan ini akan menghapus akun dan seluruh data profil Anda secara permanen dari organisasi CTM.</p>
      </div>
      <div class="modal-footer border-0 d-flex justify-content-center gap-2 pb-4">
        <button type="button" class="btn btn-secondary px-4 py-2" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
        <button type="button" class="btn btn-primary px-4 py-2 border-0" style="background-color: var(--primary-color); border-radius: 8px;" data-bs-target="#confirmDeleteModal2" data-bs-toggle="modal">Lanjutkan</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Konfirmasi Kedua -->
<div class="modal fade" id="confirmDeleteModal2" tabindex="-1" aria-labelledby="confirmDeleteModal2Label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form action="delete_account.php" method="POST" class="m-0">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title fw-bold" id="confirmDeleteModal2Label">Peringatan Penghapusan Permanen</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="alert alert-danger border-0 small mb-3 p-3" style="background-color: #fef2f2; color: #991b1b; border-radius: 8px;">
            <strong>Peringatan Keras!</strong> Tindakan ini sama sekali tidak dapat dibatalkan. Seluruh data penugasan proyek, data profil, dan data login Anda akan dihapus selamanya dari sistem.
          </div>
          <p class="text-dark small mb-2">Untuk melanjutkan, ketik kata kunci <strong class="text-danger">HAPUS AKUN</strong> pada kolom di bawah ini:</p>
          <input type="text" id="confirmText" class="form-control" placeholder="Ketik HAPUS AKUN..." autocomplete="off" style="border-radius: 8px;">
        </div>
        <div class="modal-footer border-0 d-flex justify-content-center gap-2 pb-4">
          <button type="button" class="btn btn-light border px-4 py-2" style="border-radius: 8px;" data-bs-target="#confirmDeleteModal1" data-bs-toggle="modal">Kembali</button>
          <button type="submit" id="btnDeleteAccountSubmit" class="btn btn-danger px-4 py-2" style="border-radius: 8px;" disabled>Ya, Hapus Akun Saya</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const confirmInput = document.getElementById('confirmText');
    const submitBtn = document.getElementById('btnDeleteAccountSubmit');
    if (confirmInput && submitBtn) {
        confirmInput.addEventListener('input', function() {
            if (this.value.trim() === 'HAPUS AKUN') {
                submitBtn.removeAttribute('disabled');
            } else {
                submitBtn.setAttribute('disabled', 'true');
            }
        });
    }
});
</script>
<?php endif; ?>

<?php
// Render Footer using layout helper
renderFooter('../');
?>
