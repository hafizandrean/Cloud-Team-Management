<?php
/**
 * Cloud Team Management - Tambah Assignment Baru (Penugasan)
 */

require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../config/activity_helper.php';

// Protect page (Admin only)
requireRole('admin');

$db = Database::getConnection();

// Default selected project from Quick Assign parameter
$selectedProjectId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

$anggota_id = 0;
$proyek_id = $selectedProjectId;
$errors = [];

try {
    // Fetch dropdown data
    $membersDropdown = $db->query("SELECT id, nama, nim FROM anggota ORDER BY nama ASC")->fetchAll();
    $projectsDropdown = $db->query("SELECT id, nama_proyek FROM proyek ORDER BY nama_proyek ASC")->fetchAll();
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $anggota_id = isset($_POST['anggota_id']) && is_numeric($_POST['anggota_id']) ? (int)$_POST['anggota_id'] : 0;
    $proyek_id = isset($_POST['proyek_id']) && is_numeric($_POST['proyek_id']) ? (int)$_POST['proyek_id'] : 0;
    
    // 1. Validate Dropdown Selections
    if ($anggota_id <= 0) {
        $errors['anggota_id'] = 'Pilihlah salah satu anggota tim.';
    }
    if ($proyek_id <= 0) {
        $errors['proyek_id'] = 'Pilihlah salah satu proyek.';
    }

    // 2. Duplicate Protection Check
    if (empty($errors)) {
        try {
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM anggota_proyek WHERE anggota_id = ? AND proyek_id = ?");
            $checkStmt->execute([$anggota_id, $proyek_id]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $errors['global'] = 'Assignment sudah ada.';
            }
        } catch (PDOException $e) {
            $errors['global'] = 'Kesalahan saat memeriksa duplikasi: ' . $e->getMessage();
        }
    }

    // Insert if no errors
    if (empty($errors)) {
        try {
            $insertQuery = "INSERT INTO anggota_proyek (anggota_id, proyek_id, created_at) VALUES (?, ?, NOW())";
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->execute([$anggota_id, $proyek_id]);
            
            // Fetch names for log description
            $mStmt = $db->prepare("SELECT nama FROM anggota WHERE id = ?");
            $mStmt->execute([$anggota_id]);
            $memberName = $mStmt->fetchColumn();

            $pStmt = $db->prepare("SELECT nama_proyek FROM proyek WHERE id = ?");
            $pStmt->execute([$proyek_id]);
            $projectName = $pStmt->fetchColumn();

            // Log activity
            writeLog($db, $_SESSION['user_id'], 'CREATE_ASSIGNMENT', 'Menambahkan ' . $memberName . ' ke proyek ' . $projectName);

            $_SESSION['flash_success'] = 'Anggota berhasil ditugaskan ke proyek.';
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors['global'] = 'Gagal menyimpan penugasan: ' . $e->getMessage();
        }
    }
}

// Render Header using layout helper
renderHeader('Tambah Assignment Baru', 'assignment', '../');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold welcome-title" id="page-title">Tambah Assignment Baru</h1>
        <p class="welcome-subtitle text-muted">Tugaskan anggota tim Anda ke proyek kerja kolaboratif.</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="index.php" class="btn btn-outline-secondary d-flex align-items-center gap-2 py-2 px-3">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            <span>Kembali ke Daftar</span>
        </a>
    </div>
</div>

<?php if (isset($errors['global'])): ?>
    <div class="alert alert-danger shadow-sm border-0 mb-4" role="alert" style="border-left: 4px solid #ef4444 !important;">
        <div class="d-flex align-items-center gap-2">
            <span>⚠</span>
            <span><?php echo htmlspecialchars($errors['global']); ?></span>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form Card (Left Column) -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm p-4 h-100">
            <form method="POST" action="create.php" class="needs-validation">
                
                <!-- Pilih Anggota Dropdown -->
                <div class="mb-4">
                    <label for="anggota_id" class="form-label fw-semibold text-dark">Pilih Anggota Tim <span class="text-danger">*</span></label>
                    <select name="anggota_id" id="anggota_id" class="form-select <?php echo isset($errors['anggota_id']) ? 'is-invalid' : ''; ?>" required>
                        <option value="">-- Pilih Anggota --</option>
                        <?php foreach ($membersDropdown as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo $anggota_id === (int)$m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['nama']); ?> (NIM: <?php echo htmlspecialchars($m['nim']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['anggota_id'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['anggota_id']; ?></div>
                    <?php endif; ?>
                </div>

                <!-- Pilih Proyek Dropdown -->
                <div class="mb-4">
                    <label for="proyek_id" class="form-label fw-semibold text-dark">Pilih Proyek Kerja <span class="text-danger">*</span></label>
                    <select name="proyek_id" id="proyek_id" class="form-select <?php echo isset($errors['proyek_id']) ? 'is-invalid' : ''; ?>" required>
                        <option value="">-- Pilih Proyek --</option>
                        <?php foreach ($projectsDropdown as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $proyek_id === (int)$p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['nama_proyek']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['proyek_id'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['proyek_id']; ?></div>
                    <?php endif; ?>
                </div>

                <!-- Submit buttons -->
                <div class="pt-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 py-2 border-0" style="background-color: var(--primary-color);">Tugaskan Anggota</button>
                    <a href="index.php" class="btn btn-light border px-4 py-2">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Guide Sidebar Card (Right Column) -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm p-4 h-100">
            <h5 class="fw-bold text-dark mb-3">Panduan Penugasan</h5>
            <ul class="text-muted small ps-0" style="list-style-type: none;">
                <li class="mb-3 d-flex gap-2">
                    <span>📌</span>
                    <span>Modul ini merepresentasikan tabel relasi <strong>Many-to-Many</strong> antara tabel Anggota dan tabel Proyek.</span>
                </li>
                <li class="mb-3 d-flex gap-2">
                    <span>🔒</span>
                    <span>Sistem memiliki proteksi duplikasi, satu anggota hanya bisa ditugaskan <strong>satu kali</strong> pada proyek yang sama.</span>
                </li>
                <li class="mb-3 d-flex gap-2">
                    <span>⚡</span>
                    <span>Penugasan yang sukses akan langsung terintegrasi secara real-time pada halaman rincian Detail Anggota dan Detail Proyek.</span>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php
// Render Footer using layout helper
renderFooter('../');
?>
