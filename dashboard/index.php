<?php
/**
 * Cloud Team Management - Dashboard Page (Protected)
 */

require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/../config/activity_helper.php';

// Enforce authentication
requireLogin();

// Get current user information
$currentUser = getCurrentUser();

$totalUsers = 0;
$totalAnggota = 0;
$totalProyek = 0;
$totalAssignments = 0;
$recentProjects = [];
$recentMembers = [];
$statusCounts = [
    'direncanakan' => 0,
    'berjalan' => 0,
    'selesai' => 0,
    'tertunda' => 0
];
$errorMsg = '';

// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $file = $_FILES['profile_photo'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        // 1. Validate Extension
        if (in_array($fileExt, $allowedExtensions)) {
            // 2. Validate Size (Max 5 MB)
            if ($fileSize <= 5 * 1024 * 1024) {
                // 3. Validate MIME Type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $fileTmpName);
                finfo_close($finfo);
                
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                if (in_array($mimeType, $allowedMimeTypes)) {
                    // Generate secure random filename
                    $randomName = bin2hex(random_bytes(16));
                    $newFotoFilename = 'anggota/' . $randomName . '.' . $fileExt;
                    
                    // Target directories
                    $uploadTargetDir = dirname(__DIR__) . '/uploads/anggota';
                    if (!is_dir($uploadTargetDir)) {
                        mkdir($uploadTargetDir, 0777, true);
                    }
                    $uploadTarget = dirname(__DIR__) . '/uploads/' . $newFotoFilename;
                    
                    $uploadSuccess = false;
                    
                    // 4. GD Resizing & Compression (with Fallback)
                    if (extension_loaded('gd')) {
                        list($width, $height, $type) = getimagesize($fileTmpName);
                        if ($width && $height) {
                            $maxDim = 800;
                            $newWidth = $width;
                            $newHeight = $height;
                            
                            // Scale down keeping aspect ratio
                            if ($width > $maxDim || $height > $maxDim) {
                                $ratio = $width / $height;
                                if ($ratio > 1) {
                                    $newWidth = $maxDim;
                                    $newHeight = round($maxDim / $ratio);
                                } else {
                                    $newHeight = $maxDim;
                                    $newWidth = round($maxDim * $ratio);
                                }
                            }
                            
                            $dst = imagecreatetruecolor($newWidth, $newHeight);
                            
                            // Load image source based on type
                            $src = null;
                            switch ($type) {
                                case IMAGETYPE_JPEG:
                                    $src = @imagecreatefromjpeg($fileTmpName);
                                    break;
                                case IMAGETYPE_PNG:
                                    $src = @imagecreatefrompng($fileTmpName);
                                    if ($src) {
                                        imagealphablending($dst, false);
                                        imagesavealpha($dst, true);
                                    }
                                    break;
                                case IMAGETYPE_WEBP:
                                    $src = @imagecreatefromwebp($fileTmpName);
                                    break;
                            }
                            
                            if ($src) {
                                imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                                
                                // Save and compress
                                switch ($type) {
                                    case IMAGETYPE_JPEG:
                                        $uploadSuccess = imagejpeg($dst, $uploadTarget, 85);
                                        break;
                                    case IMAGETYPE_PNG:
                                        $uploadSuccess = imagepng($dst, $uploadTarget, 6);
                                        break;
                                    case IMAGETYPE_WEBP:
                                        $uploadSuccess = imagewebp($dst, $uploadTarget, 80);
                                        break;
                                }
                                
                                imagedestroy($src);
                                imagedestroy($dst);
                            }
                        }
                    }
                    
                    // Fallback to move_uploaded_file if GD is disabled or failed
                    if (!$uploadSuccess) {
                        $uploadSuccess = move_uploaded_file($fileTmpName, $uploadTarget);
                    }
                    
                    if ($uploadSuccess) {
                        try {
                            $db = Database::getConnection();
                            
                            // Check if user has an anggota record
                            $stmt = $db->prepare("SELECT id, foto FROM anggota WHERE id_user = ?");
                            $stmt->execute([$currentUser['id']]);
                            $member = $stmt->fetch();
                            
                            if ($member) {
                                // Delete old photo if exists
                                if (!empty($member['foto'])) {
                                    $oldFile = dirname(__DIR__) . '/uploads/' . $member['foto'];
                                    if (file_exists($oldFile) && is_file($oldFile)) {
                                        @unlink($oldFile);
                                    }
                                }
                                
                                // Update existing anggota record
                                $updateStmt = $db->prepare("UPDATE anggota SET foto = ?, updated_at = NOW() WHERE id = ?");
                                $updateStmt->execute([$newFotoFilename, $member['id']]);
                            } else {
                                // Create a new anggota record for the user
                                $uStmt = $db->prepare("SELECT email, username FROM users WHERE id = ?");
                                $uStmt->execute([$currentUser['id']]);
                                $userObj = $uStmt->fetch();
                                
                                $userEmail = $userObj['email'] ?? ($currentUser['username'] . '@cloudteam.com');
                                $userNama = $currentUser['nama'] ?: $currentUser['username'];
                                $dummyNim = '22' . str_pad($currentUser['id'], 8, '0', STR_PAD_LEFT);
                                
                                $insertStmt = $db->prepare("
                                    INSERT INTO anggota (nama, nim, email, foto, id_user, created_at, updated_at)
                                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                                ");
                                $insertStmt->execute([$userNama, $dummyNim, $userEmail, $newFotoFilename, $currentUser['id']]);
                            }
                            
                            // Update session variable
                            startSession();
                            $_SESSION['foto'] = $newFotoFilename;
                            
                            // Log activity
                            writeLog($db, $currentUser['id'], 'UPDATE_MEMBER', 'Mengubah foto profil');
                            
                            $_SESSION['flash_success'] = 'Foto profil berhasil diperbarui.';
                            header("Location: index.php");
                            exit;
                        } catch (Exception $e) {
                            $errorMsg = 'Gagal menyimpan foto profil ke database: ' . $e->getMessage();
                        }
                    } else {
                        $errorMsg = 'Gagal memindahkan berkas foto.';
                    }
                } else {
                    $errorMsg = 'Tipe MIME berkas tidak diizinkan. Harap unggah gambar JPG, JPEG, PNG, atau WEBP yang valid.';
                }
            } else {
                $errorMsg = 'Ukuran berkas melebihi batas maksimal 5MB.';
            }
        } else {
            $errorMsg = 'Ekstensi berkas tidak diizinkan. Hanya JPG, JPEG, PNG, dan WEBP yang didukung.';
        }
    } else {
        $errorMsg = 'Terjadi kesalahan saat mengunggah berkas.';
    }
}

$userRole = $currentUser['role'] ?? 'member';
$anggotaId = $_SESSION['anggota_id'] ?? null;

try {
    $db = Database::getConnection();
    
    if ($userRole !== 'admin' && !$anggotaId) {
        $stmtAnggota = $db->prepare("SELECT id FROM anggota WHERE id_user = ?");
        $stmtAnggota->execute([$currentUser['id']]);
        $anggotaId = $stmtAnggota->fetchColumn() ?: null;
        $_SESSION['anggota_id'] = $anggotaId;
    }
    
    if ($userRole === 'admin') {
        // 1. Fetch Metrics counts
        $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalAnggota = $db->query("SELECT COUNT(*) FROM anggota")->fetchColumn();
        $totalProyek = $db->query("SELECT COUNT(*) FROM proyek")->fetchColumn();
        $totalAssignments = $db->query("SELECT COUNT(*) FROM anggota_proyek")->fetchColumn();

        // 2. Fetch Project status breakdown
        $stmt = $db->query("SELECT status, COUNT(*) as count FROM proyek GROUP BY status");
        while ($row = $stmt->fetch()) {
            if (isset($statusCounts[$row['status']])) {
                $statusCounts[$row['status']] = (int)$row['count'];
            }
        }

        // 3. Fetch 5 Recent Projects
        $recentProjects = $db->query("SELECT * FROM proyek ORDER BY created_at DESC LIMIT 5")->fetchAll();
    } else {
        $anggotaIdValue = $anggotaId ? (int)$anggotaId : 0;

        // 1. Fetch member metrics
        $pStmt = $db->prepare("SELECT COUNT(*) FROM anggota_proyek WHERE anggota_id = ?");
        $pStmt->execute([$anggotaIdValue]);
        $totalProyekSaya = $pStmt->fetchColumn();

        $tugasSaya = $totalProyekSaya;

        $pbStmt = $db->prepare("
            SELECT COUNT(*) 
            FROM proyek p
            JOIN anggota_proyek ap ON p.id = ap.proyek_id
            WHERE ap.anggota_id = ? AND p.status = 'berjalan'
        ");
        $pbStmt->execute([$anggotaIdValue]);
        $proyekBerjalanSaya = $pbStmt->fetchColumn();

        $totalRekanTim = max(0, (int)$db->query("SELECT COUNT(*) FROM anggota")->fetchColumn() - 1);

        // 2. Fetch Project status breakdown for member's projects
        $psStmt = $db->prepare("
            SELECT p.status, COUNT(*) as count 
            FROM proyek p
            JOIN anggota_proyek ap ON p.id = ap.proyek_id
            WHERE ap.anggota_id = ?
            GROUP BY p.status
        ");
        $psStmt->execute([$anggotaIdValue]);
        while ($row = $psStmt->fetch()) {
            if (isset($statusCounts[$row['status']])) {
                $statusCounts[$row['status']] = (int)$row['count'];
            }
        }

        // 3. Fetch 5 Recent Projects assigned to member
        $rpStmt = $db->prepare("
            SELECT p.* 
            FROM proyek p
            JOIN anggota_proyek ap ON p.id = ap.proyek_id
            WHERE ap.anggota_id = ?
            ORDER BY p.created_at DESC 
            LIMIT 5
        ");
        $rpStmt->execute([$anggotaIdValue]);
        $recentProjects = $rpStmt->fetchAll();
    }

    // 4. Fetch 5 Recent Members
    $recentMembers = $db->query("SELECT * FROM anggota ORDER BY created_at DESC LIMIT 5")->fetchAll();

} catch (PDOException $e) {
    $errorMsg = 'Gagal memuat data dari database: ' . $e->getMessage();
}

// Calculate percentages for progress bars
$totalProyekSum = array_sum($statusCounts);
$percentages = [
    'direncanakan' => $totalProyekSum > 0 ? round(($statusCounts['direncanakan'] / $totalProyekSum) * 100) : 0,
    'berjalan' => $totalProyekSum > 0 ? round(($statusCounts['berjalan'] / $totalProyekSum) * 100) : 0,
    'selesai' => $totalProyekSum > 0 ? round(($statusCounts['selesai'] / $totalProyekSum) * 100) : 0,
    'tertunda' => $totalProyekSum > 0 ? round(($statusCounts['tertunda'] / $totalProyekSum) * 100) : 0,
];
?>
<?php
// Render Header using layout helper
renderHeader('Dashboard', 'dashboard', '../');
?>
            <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>

            <!-- Welcome Header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <div>
                    <h1 class="h2 welcome-title" id="welcome-title">Selamat Datang, <?php echo htmlspecialchars($currentUser['nama']); ?>!</h1>
                    <p class="welcome-subtitle text-muted">Aplikasi Cloud Team Management siap membantu Anda berkolaborasi.</p>
                </div>
            </div>

            <!-- Summary Cards Grid -->
            <div class="row g-4 mb-4">
                <?php if ($userRole === 'admin'): ?>
                <div class="col-12 col-sm-6 col-lg-3">
                    <a href="../activity-log/index.php" class="card-summary d-flex align-items-center justify-content-between text-decoration-none" title="Lihat Log Aktivitas User">
                        <div class="metric-info">
                            <div class="card-summary-title text-muted">Total User</div>
                            <div class="card-summary-value text-dark" id="val-users">0</div>
                        </div>
                        <div class="card-summary-icon users">👤</div>
                    </a>
                </div>
                
                <div class="col-12 col-sm-6 col-lg-3">
                    <a href="../anggota/index.php" class="card-summary d-flex align-items-center justify-content-between text-decoration-none" title="Kelola Anggota">
                        <div class="metric-info">
                            <div class="card-summary-title text-muted">Total Anggota</div>
                            <div class="card-summary-value text-dark" id="val-anggota">0</div>
                        </div>
                        <div class="card-summary-icon anggota">👥</div>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <a href="../proyek/index.php" class="card-summary d-flex align-items-center justify-content-between text-decoration-none" title="Kelola Proyek">
                        <div class="metric-info">
                            <div class="card-summary-title text-muted">Total Proyek</div>
                            <div class="card-summary-value text-dark" id="val-proyek">0</div>
                        </div>
                        <div class="card-summary-icon proyek">📂</div>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <a href="../assignment/index.php" class="card-summary d-flex align-items-center justify-content-between text-decoration-none" title="Kelola Assignment">
                        <div class="metric-info">
                            <div class="card-summary-title text-muted">Assignment</div>
                            <div class="card-summary-value text-dark" id="val-assignments">0</div>
                        </div>
                        <div class="card-summary-icon assignment">🔗</div>
                    </a>
                </div>
                <?php else: ?>
                <div class="col-12 col-sm-6 col-lg-3">
                    <a href="../proyek/index.php" class="card-summary d-flex align-items-center justify-content-between text-decoration-none" title="Lihat Proyek Saya">
                        <div class="metric-info">
                            <div class="card-summary-title text-muted">Proyek Saya</div>
                            <div class="card-summary-value text-dark" id="val-proyek-saya">0</div>
                        </div>
                        <div class="card-summary-icon proyek">📂</div>
                    </a>
                </div>
                
                <div class="col-12 col-sm-6 col-lg-3">
                    <a href="../assignment/index.php" class="card-summary d-flex align-items-center justify-content-between text-decoration-none" title="Lihat Tugas Saya">
                        <div class="metric-info">
                            <div class="card-summary-title text-muted">Tugas Saya</div>
                            <div class="card-summary-value text-dark" id="val-tugas-saya">0</div>
                        </div>
                        <div class="card-summary-icon assignment">🔗</div>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <a href="../proyek/index.php?status=berjalan" class="card-summary d-flex align-items-center justify-content-between text-decoration-none" title="Lihat Proyek Berjalan Saya">
                        <div class="metric-info">
                            <div class="card-summary-title text-muted">Proyek Berjalan Saya</div>
                            <div class="card-summary-value text-dark" id="val-proyek-berjalan">0</div>
                        </div>
                        <div class="card-summary-icon users">⚡</div>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <a href="../anggota/index.php" class="card-summary d-flex align-items-center justify-content-between text-decoration-none" title="Lihat Rekan Tim">
                        <div class="metric-info">
                            <div class="card-summary-title text-muted">Rekan Tim</div>
                            <div class="card-summary-value text-dark" id="val-rekan-tim">0</div>
                        </div>
                        <div class="card-summary-icon anggota">👥</div>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Widgets Grid -->
            <div class="row g-4 mb-4">
                <!-- Status & Quick Actions -->
                <div class="col-12">
                    <div class="row g-4 h-100">
                        <!-- Project Status Summary -->
                        <div class="col-12 col-md-6 d-flex">
                            <div class="card w-100 p-4 d-flex flex-column justify-content-between">
                                <h5 class="card-title fw-bold mb-3">Ringkasan Status Proyek</h5>
                                
                                <div class="d-flex flex-column gap-3 justify-content-center h-100">
                                    <div>
                                        <a href="../proyek/index.php?status=direncanakan" class="clickable-status-item" title="<?php echo $statusCounts['direncanakan']; ?> proyek dalam tahap perencanaan">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span class="fw-medium">Planning</span>
                                                <span class="fw-bold text-muted"><?php echo $statusCounts['direncanakan']; ?></span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-secondary" role="progressbar" style="width: <?php echo $percentages['direncanakan']; ?>%" aria-valuenow="<?php echo $percentages['direncanakan']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </a>
                                    </div>

                                    <div>
                                        <a href="../proyek/index.php?status=berjalan" class="clickable-status-item" title="<?php echo $statusCounts['berjalan']; ?> proyek sedang berjalan">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span class="fw-medium">On Progress</span>
                                                <span class="fw-bold text-primary"><?php echo $statusCounts['berjalan']; ?></span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $percentages['berjalan']; ?>%" aria-valuenow="<?php echo $percentages['berjalan']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </a>
                                    </div>

                                    <div>
                                        <a href="../proyek/index.php?status=selesai" class="clickable-status-item" title="<?php echo $statusCounts['selesai']; ?> proyek telah selesai">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span class="fw-medium">Completed</span>
                                                <span class="fw-bold text-success"><?php echo $statusCounts['selesai']; ?></span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentages['selesai']; ?>%" aria-valuenow="<?php echo $percentages['selesai']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </a>
                                    </div>

                                    <div>
                                        <a href="../proyek/index.php?status=tertunda" class="clickable-status-item" title="<?php echo $statusCounts['tertunda']; ?> proyek ditangguhkan">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span class="fw-medium">Suspended</span>
                                                <span class="fw-bold text-danger"><?php echo $statusCounts['tertunda']; ?></span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $percentages['tertunda']; ?>%" aria-valuenow="<?php echo $percentages['tertunda']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Access Shortcut Widget -->
                        <div class="col-12 col-md-6 d-flex">
                            <div class="card w-100 p-4 d-flex flex-column justify-content-between">
                                <h5 class="card-title fw-bold mb-3">Aksi Cepat</h5>
                                <div class="d-flex flex-column gap-3 justify-content-center h-100">
                                    <?php if ($userRole === 'admin'): ?>
                                    <a href="../anggota/index.php" class="btn btn-light btn-quick-access w-100 text-start py-3 px-3 shadow-sm d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <span style="font-size: 18px;">👥</span>
                                            <span class="fw-semibold small text-dark">Kelola Anggota</span>
                                        </div>
                                        <span class="text-muted small">→</span>
                                    </a>
                                    <a href="../proyek/index.php" class="btn btn-light btn-quick-access w-100 text-start py-3 px-3 shadow-sm d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <span style="font-size: 18px;">📂</span>
                                            <span class="fw-semibold small text-dark">Kelola Proyek</span>
                                        </div>
                                        <span class="text-muted small">→</span>
                                    </a>
                                    <?php else: ?>
                                    <a href="../anggota/index.php" class="btn btn-light btn-quick-access w-100 text-start py-3 px-3 shadow-sm d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <span style="font-size: 18px;">👥</span>
                                            <span class="fw-semibold small text-dark">Lihat Anggota</span>
                                        </div>
                                        <span class="text-muted small">→</span>
                                    </a>
                                    <a href="../proyek/index.php" class="btn btn-light btn-quick-access w-100 text-start py-3 px-3 shadow-sm d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <span style="font-size: 18px;">📂</span>
                                            <span class="fw-semibold small text-dark">Lihat Proyek Saya</span>
                                        </div>
                                        <span class="text-muted small">→</span>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Row -->
            <div class="row g-4">
                <?php if ($userRole === 'admin'): ?>
                <!-- Recent Projects Table -->
                <div class="col-12 col-xl-6">
                    <div class="card p-4 h-100">
                        <h5 class="card-title fw-bold mb-3">5 Proyek Terbaru</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>Nama Proyek</th>
                                        <th>Deadline</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentProjects)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-5">
                                                <div class="empty-state-container">
                                                    <span class="empty-state-icon">📋</span>
                                                    <h6 class="fw-bold text-dark mb-1">Belum Ada Proyek</h6>
                                                    <p class="empty-state-text small">Mulai dengan membuat proyek pertama Anda.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentProjects as $p): ?>
                                            <tr class="clickable-row" onclick="window.location.href='../proyek/detail.php?id=<?php echo $p['id']; ?>';" title="Klik untuk melihat detail proyek <?php echo htmlspecialchars($p['nama_proyek']); ?>">
                                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($p['nama_proyek']); ?></td>
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
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Members Table -->
                <div class="col-12 col-xl-6">
                    <div class="card p-4 h-100">
                        <h5 class="card-title fw-bold mb-3">5 Anggota Terbaru</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>NIM</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentMembers)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-5">
                                                <div class="empty-state-container">
                                                    <span class="empty-state-icon">👥</span>
                                                    <h6 class="fw-bold text-dark mb-1">Belum Ada Anggota</h6>
                                                    <p class="empty-state-text small">Mulai dengan menambahkan anggota pertama Anda.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentMembers as $m): ?>
                                            <tr class="clickable-row" onclick="window.location.href='../anggota/detail.php?id=<?php echo $m['id']; ?>';" title="Klik untuk melihat detail anggota <?php echo htmlspecialchars($m['nama']); ?>">
                                                <td class="d-flex align-items-center gap-2 fw-semibold text-dark">
                                                    <?php 
                                                    $avatarPath = '../uploads/' . $m['foto'];
                                                    if (!empty($m['foto']) && file_exists(dirname(__DIR__) . '/uploads/' . $m['foto'])): 
                                                    ?>
                                                        <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar" class="avatar-mini">
                                                    <?php else: ?>
                                                        <div class="avatar-mini text-uppercase">
                                                            <?php echo htmlspecialchars(substr($m['nama'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span><?php echo htmlspecialchars($m['nama']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($m['nim']); ?></td>
                                                <td><?php echo htmlspecialchars($m['email'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Member Dashboard: Only show personal projects spanning full width -->
                <div class="col-12">
                    <div class="card p-4 h-100">
                        <h5 class="card-title fw-bold mb-3">Proyek Saya</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>Nama Proyek</th>
                                        <th>Deadline</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentProjects)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-5">
                                                <div class="empty-state-container">
                                                    <span class="empty-state-icon">📋</span>
                                                    <h6 class="fw-bold text-dark mb-1">Belum Ada Proyek</h6>
                                                    <p class="empty-state-text small">Anda tidak sedang ditugaskan dalam proyek apa pun.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentProjects as $p): ?>
                                            <tr class="clickable-row" onclick="window.location.href='../proyek/detail.php?id=<?php echo $p['id']; ?>';" title="Klik untuk melihat detail proyek <?php echo htmlspecialchars($p['nama_proyek']); ?>">
                                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($p['nama_proyek']); ?></td>
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
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
<!-- Counter Animation Script -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    const counters = [
        <?php if ($userRole === 'admin'): ?>
        { id: "val-users", target: <?php echo (int)$totalUsers; ?> },
        { id: "val-anggota", target: <?php echo (int)$totalAnggota; ?> },
        { id: "val-proyek", target: <?php echo (int)$totalProyek; ?> },
        { id: "val-assignments", target: <?php echo (int)$totalAssignments; ?> }
        <?php else: ?>
        { id: "val-proyek-saya", target: <?php echo (int)$totalProyekSaya; ?> },
        { id: "val-tugas-saya", target: <?php echo (int)$tugasSaya; ?> },
        { id: "val-proyek-berjalan", target: <?php echo (int)$proyekBerjalanSaya; ?> },
        { id: "val-rekan-tim", target: <?php echo (int)$totalRekanTim; ?> }
        <?php endif; ?>
    ];

    counters.forEach(c => {
        const el = document.getElementById(c.id);
        if (!el) return;
        
        const target = c.target;
        if (target === 0) {
            el.textContent = "0";
            return;
        }

        let current = 0;
        const duration = 1200; // Animation duration in ms
        const stepTime = 20; // 50 fps
        const increment = target / (duration / stepTime);

        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                el.textContent = target;
                clearInterval(timer);
            } else {
                el.textContent = Math.floor(current);
            }
        }, stepTime);
    });
});
</script>

<?php
// Render Footer using layout helper
renderFooter('../');
?>
