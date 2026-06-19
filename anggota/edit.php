<?php
/**
 * Cloud Team Management - Edit Anggota
 */

require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/../config/activity_helper.php';

$db = Database::getConnection();

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['flash_error'] = 'ID Anggota tidak valid.';
    header('Location: index.php');
    exit;
}

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
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

$userRole = $_SESSION['role'] ?? 'member';
$sessionAnggotaId = $_SESSION['anggota_id'] ?? null;

if ($userRole !== 'admin') {
    if (!$sessionAnggotaId) {
        $stmtSession = $db->prepare("SELECT id FROM anggota WHERE id_user = ?");
        $stmtSession->execute([$_SESSION['user_id']]);
        $sessionAnggotaId = $stmtSession->fetchColumn() ?: null;
        $_SESSION['anggota_id'] = $sessionAnggotaId;
    }

    if ((int) $id !== (int) $sessionAnggotaId) {
        $_SESSION['flash_error'] = 'Akses ditolak. Anda hanya dapat mengubah profil Anda sendiri.';
        header('Location: index.php');
        exit;
    }
}

$nama = $member['nama'];
$nim = $member['nim'];
$email = $member['email'];
$jabatan = $member['jabatan'] ?? 'Developer';
$system_role = $member['system_role'] ?? 'member';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $nim = trim($_POST['nim'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($userRole === 'admin') {
        $jabatanSelect = trim($_POST['jabatan'] ?? 'Developer');

        if ($jabatanSelect === 'Lainnya') {
            $jabatan = trim($_POST['custom_jabatan'] ?? '');

            if (empty($jabatan)) {
                $errors['custom_jabatan_err'] = 'Nama jabatan kustom wajib diisi jika memilih opsi Lainnya.';
            }
        } else {
            $jabatan = $jabatanSelect;
        }

        $system_role = trim($_POST['system_role'] ?? 'member');
    }

    if (empty($nama)) {
        $errors['nama'] = 'Nama lengkap wajib diisi.';
    } elseif (strlen($nama) < 3) {
        $errors['nama'] = 'Nama lengkap minimal harus 3 karakter.';
    }

    if (empty($nim)) {
        $errors['nim'] = 'NIM wajib diisi.';
    } elseif (!is_numeric($nim)) {
        $errors['nim'] = 'NIM harus berupa angka saja.';
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM anggota WHERE nim = ? AND id != ?");
        $stmt->execute([$nim, $id]);

        if ($stmt->fetchColumn() > 0) {
            $errors['nim'] = 'NIM sudah digunakan oleh anggota lain.';
        }
    }

    if (empty($email)) {
        $errors['email'] = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    } else {
        if (!empty($member['id_user'])) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $member['id_user']]);

            if ($stmt->fetchColumn() > 0) {
                $errors['email'] = 'Email sudah digunakan oleh pengguna lain.';
            }
        }

        if (empty($errors['email'])) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM anggota WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);

            if ($stmt->fetchColumn() > 0) {
                $errors['email'] = 'Email sudah digunakan oleh anggota lain.';
            }
        }
    }

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
        } elseif ($fileSize > 2 * 1024 * 1024) {
            $errors['foto'] = 'Ukuran foto maksimal adalah 2 MB.';
        } else {
            $newFotoFilename = 'anggota/' . uniqid('foto_', true) . '.' . $fileExt;
            $uploadTarget = __DIR__ . '/../uploads/' . $newFotoFilename;
            $hasNewFoto = true;
        }
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $oldFotoPath = $member['foto'];

            if ($hasNewFoto) {
                if (!move_uploaded_file($fileTmpName, $uploadTarget)) {
                    throw new Exception('Gagal memindahkan file foto ke direktori tujuan.');
                }

                if (!empty($oldFotoPath)) {
                    $oldFullFile = __DIR__ . '/../uploads/' . $oldFotoPath;

                    if (file_exists($oldFullFile) && is_file($oldFullFile)) {
                        @unlink($oldFullFile);
                    }
                }

                $query = "
                    UPDATE anggota 
                    SET nama = :nama, nim = :nim, email = :email, foto = :foto, jabatan = :jabatan, updated_at = NOW() 
                    WHERE id = :id
                ";

                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':nama' => $nama,
                    ':nim' => $nim,
                    ':email' => $email,
                    ':foto' => $newFotoFilename,
                    ':jabatan' => $jabatan,
                    ':id' => $id
                ]);
            } else {
                $query = "
                    UPDATE anggota 
                    SET nama = :nama, nim = :nim, email = :email, jabatan = :jabatan, updated_at = NOW() 
                    WHERE id = :id
                ";

                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':nama' => $nama,
                    ':nim' => $nim,
                    ':email' => $email,
                    ':jabatan' => $jabatan,
                    ':id' => $id
                ]);
            }

            if (!empty($member['id_user'])) {
                if ($userRole === 'admin' && $system_role === 'member' && ($member['system_role'] ?? '') === 'admin') {
                    $adminCountStmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                    $adminCount = (int) $adminCountStmt->fetchColumn();

                    if ($adminCount <= 1) {
                        throw new Exception('Perubahan role ditolak. Sistem harus memiliki minimal satu Admin.');
                    }
                }

                $userUpdateQuery = "UPDATE users SET email = :email";
                $userUpdateParams = [
                    ':email' => $email,
                    ':id_user' => $member['id_user']
                ];

                if ($userRole === 'admin') {
                    $userUpdateQuery .= ", role = :role";
                    $userUpdateParams[':role'] = $system_role;
                }

                $userUpdateQuery .= " WHERE id = :id_user";

                $stmtUser = $db->prepare($userUpdateQuery);
                $stmtUser->execute($userUpdateParams);
            }

            $db->commit();

            if (isset($_SESSION['user_id']) && !empty($member['id_user']) && (int) $member['id_user'] === (int) $_SESSION['user_id']) {
                $_SESSION['nama'] = $nama;
                $_SESSION['email'] = $email;

                if ($hasNewFoto) {
                    $_SESSION['foto'] = $newFotoFilename;
                }

                if ($userRole === 'admin') {
                    $_SESSION['role'] = $system_role;
                }
            }

            writeLog($db, $_SESSION['user_id'], 'UPDATE_MEMBER', 'Mengubah anggota: ' . $nama);

            $_SESSION['flash_success'] = 'Anggota berhasil diperbarui.';
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $errors['global'] = 'Gagal memperbarui data: ' . $e->getMessage();

            if ($hasNewFoto && file_exists(__DIR__ . '/../uploads/' . $newFotoFilename)) {
                @unlink(__DIR__ . '/../uploads/' . $newFotoFilename);
            }
        }
    }
}

$predefinedGroups = [
    'Management' => ['Project Manager', 'Product Manager', 'Scrum Master', 'Team Lead', 'System Administrator'],
    'Development' => ['Frontend Developer', 'Backend Developer', 'Full Stack Developer', 'Mobile Developer', 'Developer'],
    'Design' => ['UI Designer', 'UX Designer', 'UI/UX Designer', 'Graphic Designer'],
    'Quality Assurance' => ['QA Tester', 'QA Engineer', 'Software Tester'],
    'Analysis' => ['Business Analyst', 'System Analyst', 'Data Analyst'],
    'Data & Cloud' => ['Database Administrator', 'Data Engineer', 'Cloud Engineer', 'DevOps Engineer'],
    'Security' => ['Cyber Security Analyst', 'Security Engineer'],
    'Support' => ['Technical Support', 'IT Support', 'Documentation Specialist']
];

$flatPredefined = [];

foreach ($predefinedGroups as $group => $options) {
    $flatPredefined = array_merge($flatPredefined, $options);
}

$isCustom = !empty($jabatan) && !in_array($jabatan, $flatPredefined);
$selectedVal = $isCustom ? 'Lainnya' : $jabatan;

renderHeader('Edit Anggota', 'anggota', '../');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold welcome-title" id="page-title">Edit Anggota</h1>
        <p class="welcome-subtitle text-muted">Perbarui data anggota tim CTM.</p>
    </div>

    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="index.php" class="btn btn-outline-secondary d-flex align-items-center gap-2 py-2 px-3">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
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

<div class="edit-member-page">
    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card edit-member-card p-4 h-100">
                <form method="POST" action="edit.php?id=<?php echo (int) $id; ?>" enctype="multipart/form-data" class="needs-validation">

                    <div class="mb-5">
                        <div class="d-flex flex-column align-items-center">
                            <input type="file" name="foto" id="foto-input" accept="image/png, image/jpeg, image/jpg" style="display: none;">

                            <div class="edit-photo-preview-wrapper" onclick="document.getElementById('foto-input').click();" title="Klik untuk mengubah foto profil" id="avatar-preview-container">
                                <div class="edit-photo-preview-inner">
                                    <?php
                                    $avatarPath = '../uploads/' . ($member['foto'] ?? '');
                                    $avatarFile = dirname(__DIR__) . '/uploads/' . ($member['foto'] ?? '');
                                    ?>

                                    <?php if (!empty($member['foto']) && file_exists($avatarFile)): ?>
                                        <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar" class="edit-photo-preview-img" id="avatar-preview-img">
                                    <?php else: ?>
                                        <div class="edit-photo-preview-placeholder text-uppercase fw-bold" id="avatar-preview-placeholder">
                                            <?php echo htmlspecialchars(substr($member['nama'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="edit-photo-preview-overlay">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="text-white">
                                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                            <circle cx="12" cy="13" r="4"></circle>
                                        </svg>
                                        <span class="text-white fw-bold" style="font-size: 9px; letter-spacing: 0.8px; text-transform: uppercase;">Ubah Foto</span>
                                    </div>
                                </div>
                            </div>

                            <span class="text-muted small mt-2 fw-semibold">Foto Profil</span>
                            <span class="text-muted text-center" style="font-size: 11px;">Format: JPG, JPEG, PNG (Maks 2MB)</span>

                            <?php if (isset($errors['foto'])): ?>
                                <div class="text-danger small mt-1"><?php echo htmlspecialchars($errors['foto']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="nama" class="form-label fw-semibold text-dark">
                                Nama Lengkap <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="nama" id="nama" class="form-control <?php echo isset($errors['nama']) ? 'is-invalid' : ''; ?>" placeholder="Masukkan nama lengkap" value="<?php echo htmlspecialchars($nama); ?>" required>

                            <?php if (isset($errors['nama'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['nama']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="nim" class="form-label fw-semibold text-dark">
                                NIM <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="nim" id="nim" class="form-control <?php echo isset($errors['nim']) ? 'is-invalid' : ''; ?>" placeholder="Masukkan NIM" value="<?php echo htmlspecialchars($nim); ?>" required>

                            <?php if (isset($errors['nim'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['nim']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label for="email" class="form-label fw-semibold text-dark">
                                Alamat Email <span class="text-danger">*</span>
                            </label>
                            <input type="email" name="email" id="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" placeholder="Masukkan alamat email" value="<?php echo htmlspecialchars($email); ?>" required>

                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="jabatan" class="form-label fw-semibold text-dark">Peran Tim / Jabatan</label>
                            <select name="jabatan" id="jabatan" class="form-select <?php echo isset($errors['custom_jabatan_err']) ? 'is-invalid' : ''; ?>" <?php echo ($userRole !== 'admin') ? 'disabled' : ''; ?> onchange="toggleCustomJabatan()">
                                <?php foreach ($predefinedGroups as $group => $options): ?>
                                    <optgroup label="<?php echo htmlspecialchars($group); ?>">
                                        <?php foreach ($options as $opt): ?>
                                            <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($selectedVal === $opt) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($opt); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>

                                <optgroup label="Lainnya">
                                    <option value="Lainnya" <?php echo ($selectedVal === 'Lainnya') ? 'selected' : ''; ?>>
                                        Lainnya (Custom)
                                    </option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="col-md-6" id="custom-jabatan-container" style="<?php echo ($selectedVal === 'Lainnya') ? '' : 'display: none;'; ?>">
                            <label for="custom_jabatan" class="form-label fw-semibold text-dark">Masukkan Jabatan Kustom</label>
                            <input type="text" name="custom_jabatan" id="custom_jabatan" class="form-control <?php echo isset($errors['custom_jabatan_err']) ? 'is-invalid' : ''; ?>" placeholder="Contoh: AI Engineer, Machine Learning Engineer" value="<?php echo htmlspecialchars($isCustom ? $jabatan : ''); ?>" <?php echo ($userRole !== 'admin') ? 'disabled' : ''; ?>>

                            <?php if (isset($errors['custom_jabatan_err'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['custom_jabatan_err']); ?></div>
                            <?php endif; ?>
                        </div>

                        <?php if ($userRole === 'admin' && !empty($member['id_user'])): ?>
                            <div class="col-md-6">
                                <label for="system_role" class="form-label fw-semibold text-dark">Hak Akses Sistem</label>
                                <select name="system_role" id="system_role" class="form-select">
                                    <option value="member" <?php echo ($system_role === 'member') ? 'selected' : ''; ?>>
                                        Member (Akses Terbatas)
                                    </option>
                                    <option value="admin" <?php echo ($system_role === 'admin') ? 'selected' : ''; ?>>
                                        Admin (Akses Penuh)
                                    </option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="pt-4 mt-2 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary px-4 py-2 border-0">
                            Simpan Perubahan
                        </button>
                        <a href="index.php" class="btn btn-light border px-4 py-2">
                            Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card edit-guide-card border-0 shadow-sm p-4 h-100">
                <h5 class="fw-bold text-dark mb-3">Panduan Pengeditan</h5>

                <ul class="text-muted small ps-0" style="list-style-type: none;">
                    <li class="mb-3 d-flex gap-2">
                        <span>💡</span>
                        <span>Jika Anda mengganti foto profil dengan yang baru, foto profil lama otomatis dihapus dari sistem penyimpanan server.</span>
                    </li>
                    <li class="mb-3 d-flex gap-2">
                        <span>🔒</span>
                        <span>NIM dan alamat email harus tetap unik di dalam sistem.</span>
                    </li>
                    <li class="mb-3 d-flex gap-2">
                        <span>📌</span>
                        <span>Kolom dengan tanda bintang (<span class="text-danger">*</span>) harus diisi dengan benar.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
const fotoInput = document.getElementById('foto-input');

if (fotoInput) {
    fotoInput.addEventListener('change', function(event) {
        const file = event.target.files[0];

        if (!file) {
            return;
        }

        const reader = new FileReader();

        reader.onload = function(e) {
            let img = document.getElementById('avatar-preview-img');
            const placeholder = document.getElementById('avatar-preview-placeholder');
            const inner = document.querySelector('#avatar-preview-container .edit-photo-preview-inner');
            const overlay = document.querySelector('#avatar-preview-container .edit-photo-preview-overlay');

            if (!img && inner) {
                img = document.createElement('img');
                img.id = 'avatar-preview-img';
                img.className = 'edit-photo-preview-img';
                img.alt = 'Avatar';

                inner.insertBefore(img, overlay);
            }

            if (img) {
                img.src = e.target.result;
                img.style.display = 'block';
            }

            if (placeholder) {
                placeholder.style.display = 'none';
            }
        };

        reader.readAsDataURL(file);
    });
}

function toggleCustomJabatan() {
    const jabatanSelect = document.getElementById('jabatan');
    const container = document.getElementById('custom-jabatan-container');
    const customInput = document.getElementById('custom_jabatan');

    if (!jabatanSelect || !container) {
        return;
    }

    if (jabatanSelect.value === 'Lainnya') {
        container.style.display = 'block';

        if (customInput) {
            customInput.focus();
        }
    } else {
        container.style.display = 'none';

        if (customInput) {
            customInput.value = '';
        }
    }
}
</script>

<?php
renderFooter('../');
?>