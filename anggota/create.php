<?php
/**
 * Cloud Team Management - Tambah Anggota
 */

require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/../config/activity_helper.php';

$db = Database::getConnection();

// Variables for form values
$nama = '';
$nim = '';
$email = '';
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
        // Check uniqueness of NIM
        $stmt = $db->prepare("SELECT COUNT(*) FROM anggota WHERE nim = ?");
        $stmt->execute([$nim]);
        if ($stmt->fetchColumn() > 0) {
            $errors['nim'] = 'NIM sudah terdaftar.';
        }
    }

    // 3. Validation Email
    if (empty($email)) {
        $errors['email'] = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    } else {
        // Check uniqueness of Email
        $stmt = $db->prepare("SELECT COUNT(*) FROM anggota WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'Email sudah terdaftar.';
        }
    }

    // 4. Validation & Upload Foto
    $fotoFilename = null;
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
            $fotoFilename = 'anggota/' . uniqid() . '.' . $fileExt;
            $uploadTarget = __DIR__ . '/../uploads/' . $fotoFilename;
        }
    }

    // Insert if no errors
    if (empty($errors)) {
        try {
            // Check if photo upload succeeded
            if ($fotoFilename !== null && isset($uploadTarget)) {
                if (!move_uploaded_file($fileTmpName, $uploadTarget)) {
                    throw new Exception('Gagal memindahkan file foto ke direktori tujuan.');
                }
            }

            // Insert query
            $query = "INSERT INTO anggota (nama, nim, email, foto, created_at, updated_at) VALUES (:nama, :nim, :email, :foto, NOW(), NOW())";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':nama' => $nama,
                ':nim' => $nim,
                ':email' => $email,
                ':foto' => $fotoFilename
            ]);

            // Write activity log
            writeLog($db, $_SESSION['user_id'], 'CREATE_MEMBER', 'Menambahkan anggota baru: ' . $nama);

            $_SESSION['flash_success'] = 'Anggota berhasil ditambahkan.';
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $errors['global'] = 'Gagal menyimpan data: ' . $e->getMessage();
            // Clean uploaded photo if DB query fails
            if ($fotoFilename !== null && file_exists(__DIR__ . '/../uploads/' . $fotoFilename)) {
                unlink(__DIR__ . '/../uploads/' . $fotoFilename);
            }
        }
    }
}

// Render Header using layout helper
renderHeader('Tambah Anggota Baru', 'anggota', '../');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold welcome-title" id="page-title">Tambah Anggota Baru</h1>
        <p class="welcome-subtitle text-muted">Tambahkan anggota tim baru ke dalam pangkalan data CTM.</p>
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
            <form method="POST" action="create.php" enctype="multipart/form-data" class="needs-validation">
                
                <!-- Nama -->
                <div class="mb-4">
                    <label for="nama" class="form-label fw-semibold text-dark">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama" id="nama" class="form-control <?php echo isset($errors['nama']) ? 'is-invalid' : ''; ?>" placeholder="Masukkan nama lengkap" value="<?php echo htmlspecialchars($nama); ?>" required>
                    <?php if (isset($errors['nama'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['nama']; ?></div>
                    <?php else: ?>
                        <div class="form-text text-muted small">Minimal 3 karakter.</div>
                    <?php endif; ?>
                </div>

                <!-- NIM -->
                <div class="mb-4">
                    <label for="nim" class="form-label fw-semibold text-dark">NIM <span class="text-danger">*</span></label>
                    <input type="text" name="nim" id="nim" class="form-control <?php echo isset($errors['nim']) ? 'is-invalid' : ''; ?>" placeholder="Masukkan NIM (Nomor Induk Mahasiswa)" value="<?php echo htmlspecialchars($nim); ?>" required>
                    <?php if (isset($errors['nim'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['nim']; ?></div>
                    <?php else: ?>
                        <div class="form-text text-muted small">Hanya angka dan harus unik.</div>
                    <?php endif; ?>
                </div>

                <!-- Email -->
                <div class="mb-4">
                    <label for="email" class="form-label fw-semibold text-dark">Alamat Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" placeholder="Masukkan alamat email" value="<?php echo htmlspecialchars($email); ?>" required>
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                    <?php else: ?>
                        <div class="form-text text-muted small">Format email valid dan harus unik.</div>
                    <?php endif; ?>
                </div>

                <!-- Foto Profil -->
                <div class="mb-4">
                    <label for="foto" class="form-label fw-semibold text-dark">Foto Profil</label>
                    <input type="file" name="foto" id="foto" class="form-control <?php echo isset($errors['foto']) ? 'is-invalid' : ''; ?>" accept=".jpg,.jpeg,.png">
                    <?php if (isset($errors['foto'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['foto']; ?></div>
                    <?php else: ?>
                        <div class="form-text text-muted small">Tipe file yang diperbolehkan: JPG, JPEG, PNG. Ukuran maksimal 2 MB.</div>
                    <?php endif; ?>
                </div>

                <!-- Submit buttons -->
                <div class="pt-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 py-2 border-0" style="background-color: var(--primary-color);">Simpan Anggota</button>
                    <a href="index.php" class="btn btn-light border px-4 py-2">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview/Informasi Sidebar Card -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm p-4 h-100">
            <h5 class="fw-bold text-dark mb-3">Panduan Pengisian</h5>
            <ul class="text-muted small ps-0" style="list-style-type: none;">
                <li class="mb-3 d-flex gap-2">
                    <span>📌</span>
                    <span>Kolom dengan tanda bintang (<span class="text-danger">*</span>) wajib diisi dengan benar.</span>
                </li>
                <li class="mb-3 d-flex gap-2">
                    <span>🔒</span>
                    <span>Pastikan NIM yang diinput adalah unik dan tidak terduplikasi dalam sistem.</span>
                </li>
                <li class="mb-3 d-flex gap-2">
                    <span>✉</span>
                    <span>Gunakan format penulisan email yang benar seperti <code>nama@domain.com</code>.</span>
                </li>
                <li class="mb-3 d-flex gap-2">
                    <span>👤</span>
                    <span>Jika Anda tidak mengunggah foto profil, sistem akan otomatis menghasilkan inisial huruf dari nama anggota sebagai avatar default.</span>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php
// Render Footer using layout helper
renderFooter('../');
?>
