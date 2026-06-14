<?php
/**
 * Cloud Team Management - Edit Anggota
 */

require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/../config/activity_helper.php';

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
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

// Variables for form values
$nama = $member['nama'];
$nim = $member['nim'];
$email = $member['email'];
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $nim = trim($_POST['nim'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // 1. Validation Name
    if (empty($nama)) {
        $errors['nama'] = 'Nama lengkap wajib diisi.';
    } elseif (strlen($nama) < 3) {
        $errors['nama'] = 'Nama lengkap minimal harus 3 karakter.';
    }

    // 2. Validation NIM
    if (empty($nim)) {
        $errors['nim'] = 'NIM wajib diisi.';
    } elseif (!is_numeric($nim)) {
        $errors['nim'] = 'NIM harus berupa angka saja.';
    } else {
        // Check uniqueness of NIM excluding current member
        $stmt = $db->prepare("SELECT COUNT(*) FROM anggota WHERE nim = ? AND id != ?");
        $stmt->execute([$nim, $id]);
        if ($stmt->fetchColumn() > 0) {
            $errors['nim'] = 'NIM sudah digunakan oleh anggota lain.';
        }
    }

    // 3. Validation Email
    if (empty($email)) {
        $errors['email'] = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    } else {
        // Check uniqueness of Email excluding current member
        $stmt = $db->prepare("SELECT COUNT(*) FROM anggota WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'Email sudah digunakan oleh anggota lain.';
        }
    }

    // 4. Validation & Upload Foto
    $newFotoFilename = null;
    $hasNewFoto = false;
    
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['foto'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];
        
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        
        if ($fileError !== UPLOAD_ERR_OK) {
            $errors['foto'] = 'Terjadi kesalahan saat mengunggah foto.';
        } elseif (!in_array($fileExt, $allowedExtensions)) {
            $errors['foto'] = 'Format foto tidak valid. Gunakan format JPG, JPEG, atau PNG.';
        } elseif ($fileSize > 2 * 1024 * 1024) { // 2MB
            $errors['foto'] = 'Ukuran foto maksimal adalah 2 MB.';
        } else {
            // Generate unique filename
            $newFotoFilename = 'anggota/' . uniqid() . '.' . $fileExt;
            $uploadTarget = __DIR__ . '/../uploads/' . $newFotoFilename;
            $hasNewFoto = true;
        }
    }

    // Update if no errors
    if (empty($errors)) {
        try {
            $oldFotoPath = $member['foto'];
            
            // Upload new photo if provided
            if ($hasNewFoto) {
                if (!move_uploaded_file($fileTmpName, $uploadTarget)) {
                    throw new Exception('Gagal memindahkan file foto ke direktori tujuan.');
                }
                
                // Delete old physical file if it exists
                if (!empty($oldFotoPath)) {
                    $oldFullFile = __DIR__ . '/../uploads/' . $oldFotoPath;
                    if (file_exists($oldFullFile) && is_file($oldFullFile)) {
                        unlink($oldFullFile);
                    }
                }
                
                // DB Update query (with new photo)
                $query = "UPDATE anggota SET nama = :nama, nim = :nim, email = :email, foto = :foto, updated_at = NOW() WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':nama' => $nama,
                    ':nim' => $nim,
                    ':email' => $email,
                    ':foto' => $newFotoFilename,
                    ':id' => $id
                ]);
            } else {
                // DB Update query (keep existing photo)
                $query = "UPDATE anggota SET nama = :nama, nim = :nim, email = :email, updated_at = NOW() WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':nama' => $nama,
                    ':nim' => $nim,
                    ':email' => $email,
                    ':id' => $id
                ]);
            }

            // Sync session variables if editing own profile
            if (isset($_SESSION['user_id']) && !empty($member['id_user']) && (int)$member['id_user'] === (int)$_SESSION['user_id']) {
                $_SESSION['nama'] = $nama;
                $_SESSION['email'] = $email;
                if ($hasNewFoto) {
                    $_SESSION['foto'] = $newFotoFilename;
                }
            }

            // Write activity log
            writeLog($db, $_SESSION['user_id'], 'UPDATE_MEMBER', 'Mengubah anggota: ' . $nama);

            $_SESSION['flash_success'] = 'Anggota berhasil diperbarui.';
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $errors['global'] = 'Gagal memperbarui data: ' . $e->getMessage();
            // Clean uploaded photo if DB query fails
            if ($hasNewFoto && file_exists(__DIR__ . '/../uploads/' . $newFotoFilename)) {
                unlink(__DIR__ . '/../uploads/' . $newFotoFilename);
            }
        }
    }
}

// Render Header using layout helper
renderHeader('Edit Anggota', 'anggota', '../');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold welcome-title" id="page-title">Edit Anggota</h1>
        <p class="welcome-subtitle text-muted">Perbarui data anggota tim CTM.</p>
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
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm p-4 h-100">
            <form method="POST" action="edit.php?id=<?php echo $id; ?>" enctype="multipart/form-data" class="needs-validation">
                
                <!-- Nama -->
                <div class="mb-4">
                    <label for="nama" class="form-label fw-semibold text-dark">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama" id="nama" class="form-control <?php echo isset($errors['nama']) ? 'is-invalid' : ''; ?>" placeholder="Masukkan nama lengkap" value="<?php echo htmlspecialchars($nama); ?>" required>
                    <?php if (isset($errors['nama'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['nama']; ?></div>
                    <?php endif; ?>
                </div>

                <!-- NIM -->
                <div class="mb-4">
                    <label for="nim" class="form-label fw-semibold text-dark">NIM <span class="text-danger">*</span></label>
                    <input type="text" name="nim" id="nim" class="form-control <?php echo isset($errors['nim']) ? 'is-invalid' : ''; ?>" placeholder="Masukkan NIM" value="<?php echo htmlspecialchars($nim); ?>" required>
                    <?php if (isset($errors['nim'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['nim']; ?></div>
                    <?php endif; ?>
                </div>

                <!-- Email -->
                <div class="mb-4">
                    <label for="email" class="form-label fw-semibold text-dark">Alamat Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" placeholder="Masukkan alamat email" value="<?php echo htmlspecialchars($email); ?>" required>
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>

                <!-- Foto Profil -->
                <div class="mb-4">
                    <label for="foto" class="form-label fw-semibold text-dark d-block">Foto Profil</label>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <?php 
                        $avatarPath = '../uploads/' . $member['foto'];
                        if (!empty($member['foto']) && file_exists(__DIR__ . '/../' . $avatarPath)): 
                        ?>
                            <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar" class="avatar-mini rounded" style="width: 60px; height: 60px;">
                        <?php else: ?>
                            <div class="avatar-mini text-uppercase fw-bold rounded" style="width: 60px; height: 60px; font-size: 22px; background-color: var(--primary-color); color: #ffffff;">
                                <?php echo htmlspecialchars(substr($member['nama'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <span class="text-muted small">Akan digantikan bila mengunggah foto baru.</span>
                        </div>
                    </div>
                    <input type="file" name="foto" id="foto" class="form-control <?php echo isset($errors['foto']) ? 'is-invalid' : ''; ?>" accept=".jpg,.jpeg,.png">
                    <?php if (isset($errors['foto'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['foto']; ?></div>
                    <?php else: ?>
                        <div class="form-text text-muted small">Format file: JPG, JPEG, PNG. Maksimal 2 MB. Kosongkan jika tidak ingin mengubah.</div>
                    <?php endif; ?>
                </div>

                <!-- Submit buttons -->
                <div class="pt-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 py-2 border-0" style="background-color: var(--primary-color);">Simpan Perubahan</button>
                    <a href="index.php" class="btn btn-light border px-4 py-2">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Informasi Sidebar Card -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm p-4 h-100">
            <h5 class="fw-bold text-dark mb-3">Panduan Pengeditan</h5>
            <ul class="text-muted small ps-0" style="list-style-type: none;">
                <li class="mb-3 d-flex gap-2">
                    <span>💡</span>
                    <span>Jika Anda mengganti foto profil dengan yang baru, foto profil lama otomatis dihapus dari sistem penyimpanan server.</span>
                </li>
                <li class="mb-3 d-flex gap-2">
                    <span>🔒</span>
                    <span>NIM dan alamat email harus tetap unik di dalam sistem (tidak boleh bentrok dengan milik anggota lain).</span>
                </li>
                <li class="mb-3 d-flex gap-2">
                    <span>📌</span>
                    <span>Kolom dengan tanda bintang (<span class="text-danger">*</span>) harus diisi dengan benar.</span>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php
// Render Footer using layout helper
renderFooter('../');
?>
