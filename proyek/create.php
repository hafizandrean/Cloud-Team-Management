<?php
/**
 * Cloud Team Management - Tambah Proyek Baru
 */

require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../config/activity_helper.php';

// Protect page (Admin only)
requireRole('admin');

$db = Database::getConnection();

// Variables for form values
$nama_proyek = '';
$deskripsi = '';
$deadline = '';
$status = 'direncanakan';
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_proyek = trim($_POST['nama_proyek'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $status = trim($_POST['status'] ?? 'direncanakan');
    
    // 1. Validation Name
    if (empty($nama_proyek)) {
        $errors['nama_proyek'] = 'Nama proyek wajib diisi.';
    } elseif (strlen($nama_proyek) < 3) {
        $errors['nama_proyek'] = 'Nama proyek minimal harus 3 karakter.';
    }

    // 2. Validation Deadline
    if (empty($deadline)) {
        $errors['deadline'] = 'Tenggat waktu (deadline) wajib diisi.';
    } else {
        // Basic date validation
        $tempDate = explode('-', $deadline);
        if (count($tempDate) !== 3 || !checkdate((int)$tempDate[1], (int)$tempDate[2], (int)$tempDate[0])) {
            $errors['deadline'] = 'Format tanggal tenggat waktu tidak valid.';
        }
    }

    // 3. Validation Status
    $allowedStatus = ['direncanakan', 'berjalan', 'selesai', 'tertunda'];
    if (!in_array($status, $allowedStatus)) {
        $errors['status'] = 'Pilihan status tidak valid.';
    }

    // Insert if no errors
    if (empty($errors)) {
        try {
            // Insert query
            $query = "
                INSERT INTO proyek (nama_proyek, deskripsi, deadline, status, created_at, updated_at) 
                VALUES (:nama_proyek, :deskripsi, :deadline, :status, NOW(), NOW())
            ";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':nama_proyek' => $nama_proyek,
                ':deskripsi' => !empty($deskripsi) ? $deskripsi : null,
                ':deadline' => $deadline,
                ':status' => $status
            ]);

            // Write activity log
            writeLog($db, $_SESSION['user_id'], 'CREATE_PROJECT', 'Menambahkan proyek: ' . $nama_proyek);

            $_SESSION['flash_success'] = 'Proyek baru berhasil ditambahkan.';
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors['global'] = 'Gagal menyimpan data proyek: ' . $e->getMessage();
        }
    }
}

// Render Header using layout helper
renderHeader('Tambah Proyek Baru', 'proyek', '../');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold welcome-title" id="page-title">Tambah Proyek Baru</h1>
        <p class="welcome-subtitle text-muted">Buat proyek kolaboratif baru di bawah naungan CTM.</p>
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
                
                <!-- Nama Proyek -->
                <div class="mb-4">
                    <label for="nama_proyek" class="form-label fw-semibold text-dark">Nama Proyek <span class="text-danger">*</span></label>
                    <input type="text" name="nama_proyek" id="nama_proyek" class="form-control <?php echo isset($errors['nama_proyek']) ? 'is-invalid' : ''; ?>" placeholder="Masukkan nama proyek" value="<?php echo htmlspecialchars($nama_proyek); ?>" required>
                    <?php if (isset($errors['nama_proyek'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['nama_proyek']; ?></div>
                    <?php else: ?>
                        <div class="form-text text-muted small">Minimal 3 karakter.</div>
                    <?php endif; ?>
                </div>

                <!-- Deskripsi Proyek -->
                <div class="mb-4">
                    <label for="deskripsi" class="form-label fw-semibold text-dark">Deskripsi Proyek</label>
                    <textarea name="deskripsi" id="deskripsi" rows="4" class="form-control" placeholder="Tulis deskripsi atau ruang lingkup proyek..."><?php echo htmlspecialchars($deskripsi); ?></textarea>
                    <div class="form-text text-muted small">Tulis tujuan, lingkup kerja, atau batasan proyek secara singkat.</div>
                </div>

                <div class="row g-3 mb-4">
                    <!-- Deadline -->
                    <div class="col-12 col-sm-6">
                        <label for="deadline" class="form-label fw-semibold text-dark">Tenggat Waktu (Deadline) <span class="text-danger">*</span></label>
                        <input type="date" name="deadline" id="deadline" class="form-control <?php echo isset($errors['deadline']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($deadline); ?>" required>
                        <?php if (isset($errors['deadline'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['deadline']; ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Status -->
                    <div class="col-12 col-sm-6">
                        <label for="status" class="form-label fw-semibold text-dark">Status Awal <span class="text-danger">*</span></label>
                        <select name="status" id="status" class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" required>
                            <option value="direncanakan" <?php echo $status === 'direncanakan' ? 'selected' : ''; ?>>Planning</option>
                            <option value="berjalan" <?php echo $status === 'berjalan' ? 'selected' : ''; ?>>On Progress</option>
                            <option value="selesai" <?php echo $status === 'selesai' ? 'selected' : ''; ?>>Completed</option>
                            <option value="tertunda" <?php echo $status === 'tertunda' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                        <?php if (isset($errors['status'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['status']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Submit buttons -->
                <div class="pt-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 py-2 border-0" style="background-color: var(--primary-color);">Simpan Proyek</button>
                    <a href="index.php" class="btn btn-light border px-4 py-2">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Guide Sidebar Card (Right Column) -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm p-4 h-100">
            <h5 class="fw-bold text-dark mb-3">Panduan Pengisian</h5>
            <ul class="text-muted small ps-0" style="list-style-type: none;">
                <li class="mb-3 d-flex gap-2">
                    <span>📌</span>
                    <span>Kolom bertanda bintang (<span class="text-danger">*</span>) bersifat wajib.</span>
                </li>
                <li class="mb-3 d-flex gap-2">
                    <span>📅</span>
                    <span>Pilihlah tenggat waktu yang realistis untuk pengerjaan proyek cloud ini.</span>
                </li>
                <li class="mb-3 d-flex gap-2">
                    <span>⚡</span>
                    <span>Status "Planning" digunakan untuk proyek yang sedang dikonsep, dan "On Progress" untuk pengerjaan aktif.</span>
                </li>
                <li class="mb-3 d-flex gap-2">
                    <span>👥</span>
                    <span>Penugasan anggota tim ke proyek ini dapat dilakukan secara mendetail pada modul <strong>Assignment</strong> setelah proyek tersimpan.</span>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php
// Render Footer using layout helper
renderFooter('../');
?>
