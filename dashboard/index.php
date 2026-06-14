<?php
/**
 * Cloud Team Management - Dashboard Page (Protected)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
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
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        
        if (in_array($fileExt, $allowedExtensions) && $fileSize <= 2 * 1024 * 1024) {
            $newFotoFilename = 'anggota/' . uniqid() . '.' . $fileExt;
            $uploadTarget = __DIR__ . '/../uploads/' . $newFotoFilename;
            
            // Ensure uploads/anggota directory exists
            if (!is_dir(__DIR__ . '/../uploads/anggota')) {
                mkdir(__DIR__ . '/../uploads/anggota', 0777, true);
            }
            
            if (move_uploaded_file($fileTmpName, $uploadTarget)) {
                try {
                    $db = Database::getConnection();
                    
                    // Check if user has an anggota record
                    $stmt = $db->prepare("SELECT id, foto FROM anggota WHERE id_user = ?");
                    $stmt->execute([$currentUser['id']]);
                    $member = $stmt->fetch();
                    
                    if ($member) {
                        // Delete old photo if exists
                        if (!empty($member['foto'])) {
                            $oldFile = __DIR__ . '/../uploads/' . $member['foto'];
                            if (file_exists($oldFile) && is_file($oldFile)) {
                                unlink($oldFile);
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
            $errorMsg = 'Berkas harus berupa JPG, JPEG, atau PNG dengan ukuran maksimal 2MB.';
        }
    } else {
        $errorMsg = 'Terjadi kesalahan saat mengunggah berkas.';
    }
}

try {
    $db = Database::getConnection();
    
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
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Cloud Team Management</title>
    <!-- Bootstrap 5 CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Style -->
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-md navbar-custom sticky-top py-2 px-3">
    <div class="container-fluid">
        <a class="navbar-brand navbar-brand-custom" href="#">
            <span class="navbar-brand-icon">C</span>
            <span>Cloud Team Management</span>
        </a>
        <button class="navbar-toggler d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="ms-auto d-none d-md-flex align-items-center gap-3">
                <span class="text-muted small">Halo, <strong><?php echo htmlspecialchars($currentUser['nama']); ?></strong></span>
                <form action="../auth/logout.php" method="POST" class="m-0">
                    <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-3">Keluar</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
            <div class="sidebar-sticky-wrapper">
                <ul class="sidebar-menu">
                    <li class="sidebar-item active">
                        <a href="index.php" id="nav-dashboard">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../anggota/index.php" id="nav-anggota">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                            <span>Anggota</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../proyek/index.php" id="nav-proyek">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                            <span>Proyek</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../assignment/index.php" id="nav-assignment">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg>
                            <span>Assignment</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../activity-log/index.php" id="nav-activity">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            <span>Activity Log</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../reports/index.php" id="nav-reports">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                            <span>Reports</span>
                        </a>
                    </li>
                </ul>
                <div class="sidebar-item border-top pt-3 mb-2">
                    <form action="../auth/logout.php" method="POST" class="w-100">
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100 d-flex align-items-center justify-content-center gap-2 py-2" id="btn-logout-sidebar">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                            <span>Keluar</span>
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        <!-- Main Content Workspace -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
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
            </div>

            <!-- Widgets Grid -->
            <div class="row g-4 mb-4">
                <!-- Project Status Summary -->
                <div class="col-12 col-md-4">
                    <div class="card h-100 p-4">
                        <h5 class="card-title fw-bold mb-3">Ringkasan Status Proyek</h5>
                        
                        <div class="mb-3">
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

                        <div class="mb-3">
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

                        <div class="mb-3">
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

                <!-- Account Information Widget -->
                <div class="col-12 col-md-4">
                    <div class="card h-100 p-4">
                        <h5 class="card-title fw-bold mb-3 text-center">Informasi Akun</h5>
                        
                        <!-- Profile Form for Avatar Upload -->
                        <form id="profile-photo-form" action="index.php" method="POST" enctype="multipart/form-data" class="d-flex flex-column align-items-center mb-4">
                            <input type="file" name="profile_photo" id="profile-photo-input" accept="image/png, image/jpeg, image/jpg" style="display:none;" onchange="document.getElementById('profile-photo-form').submit();">
                            
                            <div class="profile-avatar-container cursor-pointer" onclick="document.getElementById('profile-photo-input').click();" title="Klik untuk mengubah foto profil">
                                <?php 
                                $photoPath = '../uploads/' . $currentUser['foto'];
                                if (!empty($currentUser['foto']) && file_exists(dirname(__DIR__) . '/uploads/' . $currentUser['foto'])): 
                                ?>
                                    <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="Avatar" class="profile-avatar-img">
                                <?php else: ?>
                                    <div class="profile-avatar-placeholder text-uppercase fw-bold">
                                        <?php echo htmlspecialchars(substr($currentUser['nama'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="profile-avatar-overlay d-flex align-items-center justify-content-center">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white mb-1"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                                    <span class="text-white fw-semibold" style="font-size: 10px; letter-spacing: 0.5px; text-transform: uppercase;">Ubah Foto</span>
                                </div>
                            </div>
                            
                            <h6 class="fw-bold mt-3 mb-1 text-dark" style="font-size: 15px;"><?php echo htmlspecialchars($currentUser['nama']); ?></h6>
                            <span class="badge bg-primary px-3 py-1 text-white" style="font-size: 9px; font-weight: 600; border-radius: 4px;"><?php echo strtoupper(htmlspecialchars($currentUser['role'])); ?></span>
                        </form>

                        <div class="small">
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Username:</span>
                                <span class="fw-bold text-dark"><?php echo htmlspecialchars($currentUser['username']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Role Akses:</span>
                                <span class="fw-bold text-dark text-uppercase"><?php echo htmlspecialchars($currentUser['role']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-2">
                                <span class="text-muted">Terakhir Masuk:</span>
                                <span class="fw-bold text-dark">Hari Ini</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Access Shortcut Widget -->
                <div class="col-12 col-md-4">
                    <div class="card h-100 p-4">
                        <h5 class="card-title fw-bold mb-3">Aksi Cepat</h5>
                        <div class="d-flex flex-column gap-3">
                            <a href="../anggota/index.php" class="btn btn-light border-0 w-100 text-start py-3 px-3 shadow-sm d-flex align-items-center justify-content-between" style="border-radius: 10px; background: rgba(255, 255, 255, 0.4); border: 1px solid rgba(255,255,255,0.3); transition: all 0.2s ease;" onmouseover="this.style.background='rgba(99, 102, 241, 0.08)';" onmouseout="this.style.background='rgba(255, 255, 255, 0.4)';">
                                <div class="d-flex align-items-center gap-2">
                                    <span style="font-size: 18px;">👥</span>
                                    <span class="fw-semibold small text-dark">Kelola Anggota</span>
                                </div>
                                <span class="text-muted small">→</span>
                            </a>
                            <a href="../proyek/index.php" class="btn btn-light border-0 w-100 text-start py-3 px-3 shadow-sm d-flex align-items-center justify-content-between" style="border-radius: 10px; background: rgba(255, 255, 255, 0.4); border: 1px solid rgba(255,255,255,0.3); transition: all 0.2s ease;" onmouseover="this.style.background='rgba(99, 102, 241, 0.08)';" onmouseout="this.style.background='rgba(255, 255, 255, 0.4)';">
                                <div class="d-flex align-items-center gap-2">
                                    <span style="font-size: 18px;">📂</span>
                                    <span class="fw-semibold small text-dark">Kelola Proyek</span>
                                </div>
                                <span class="text-muted small">→</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Row -->
            <div class="row g-4">
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
            </div>
            <footer class="text-center text-muted small mt-5 pt-3 border-top">
                Cloud Team Management v1.3 &copy; 2026 Kelompok 2
            </footer>
        </main>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS via CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Counter Animation Script -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    const counters = [
        { id: "val-users", target: <?php echo (int)$totalUsers; ?> },
        { id: "val-anggota", target: <?php echo (int)$totalAnggota; ?> },
        { id: "val-proyek", target: <?php echo (int)$totalProyek; ?> },
        { id: "val-assignments", target: <?php echo (int)$totalAssignments; ?> }
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
</body>
</html>
