<?php
/**
 * Cloud Team Management - Dashboard Page (Protected)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

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
                    <div class="card-summary d-flex align-items-center justify-content-between">
                        <div class="metric-info">
                            <div class="card-summary-title">Total User</div>
                            <div class="card-summary-value" id="val-users"><?php echo $totalUsers; ?></div>
                        </div>
                        <div class="card-summary-icon users">👤</div>
                    </div>
                </div>
                
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card-summary d-flex align-items-center justify-content-between">
                        <div class="metric-info">
                            <div class="card-summary-title">Total Anggota</div>
                            <div class="card-summary-value" id="val-anggota"><?php echo $totalAnggota; ?></div>
                        </div>
                        <div class="card-summary-icon anggota">👥</div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card-summary d-flex align-items-center justify-content-between">
                        <div class="metric-info">
                            <div class="card-summary-title">Total Proyek</div>
                            <div class="card-summary-value" id="val-proyek"><?php echo $totalProyek; ?></div>
                        </div>
                        <div class="card-summary-icon proyek">📂</div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card-summary d-flex align-items-center justify-content-between">
                        <div class="metric-info">
                            <div class="card-summary-title">Assignment</div>
                            <div class="card-summary-value" id="val-assignments"><?php echo $totalAssignments; ?></div>
                        </div>
                        <div class="card-summary-icon assignment">🔗</div>
                    </div>
                </div>
            </div>

            <!-- Widgets Grid -->
            <div class="row g-4 mb-4">
                <!-- Project Status Summary -->
                <div class="col-12 col-md-4">
                    <div class="card h-100 shadow-sm border-0 p-4">
                        <h5 class="card-title fw-bold mb-3">Ringkasan Status Proyek</h5>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>Planning</span>
                                <span class="fw-bold"><?php echo $statusCounts['direncanakan']; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-secondary" role="progressbar" style="width: <?php echo $percentages['direncanakan']; ?>%" aria-valuenow="<?php echo $percentages['direncanakan']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>On Progress</span>
                                <span class="fw-bold"><?php echo $statusCounts['berjalan']; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $percentages['berjalan']; ?>%" aria-valuenow="<?php echo $percentages['berjalan']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>Completed</span>
                                <span class="fw-bold"><?php echo $statusCounts['selesai']; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentages['selesai']; ?>%" aria-valuenow="<?php echo $percentages['selesai']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>

                        <div>
                            <div class="d-flex justify-content-between small mb-1">
                                <span>Suspended</span>
                                <span class="fw-bold"><?php echo $statusCounts['tertunda']; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $percentages['tertunda']; ?>%" aria-valuenow="<?php echo $percentages['tertunda']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Information Widget -->
                <div class="col-12 col-md-4">
                    <div class="card h-100 shadow-sm border-0 p-4">
                        <h5 class="card-title fw-bold mb-3">Informasi Akun</h5>
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <?php 
                            $photoPath = '../uploads/' . $currentUser['foto'];
                            if (!empty($currentUser['foto']) && file_exists(__DIR__ . '/../' . $photoPath)): 
                            ?>
                                <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="Avatar" class="avatar-mini" style="width: 50px; height: 50px;">
                            <?php else: ?>
                                <div class="avatar-mini text-uppercase fw-bold" style="width: 50px; height: 50px; font-size: 18px;">
                                    <?php echo htmlspecialchars(substr($currentUser['nama'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($currentUser['nama']); ?></h6>
                                <span class="text-muted small text-capitalize"><?php echo htmlspecialchars($currentUser['role']); ?></span>
                            </div>
                        </div>
                        <div class="small">
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Username:</span>
                                <span class="fw-bold"><?php echo htmlspecialchars($currentUser['username']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Role Akses:</span>
                                <span class="fw-bold text-uppercase"><?php echo htmlspecialchars($currentUser['role']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-2">
                                <span class="text-muted">Terakhir Masuk:</span>
                                <span class="fw-bold">Hari Ini</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Access Shortcut Widget -->
                <div class="col-12 col-md-4">
                    <div class="card h-100 shadow-sm border-0 p-4">
                        <h5 class="card-title fw-bold mb-3">Aksi Cepat</h5>
                        <div class="d-flex flex-column gap-3">
                            <a href="../anggota/index.php" class="btn btn-light border text-start py-3 px-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>👥</span>
                                    <span class="fw-medium small">Kelola Anggota</span>
                                </div>
                            </a>
                            <a href="../proyek/index.php" class="btn btn-light border text-start py-3 px-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>📂</span>
                                    <span class="fw-medium small">Kelola Proyek</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Row -->
            <div class="row g-4">
                <!-- Recent Projects Table -->
                <div class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm p-4 h-100">
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
                                            <td colspan="3" class="text-center text-muted py-3">Belum ada proyek terdaftar.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentProjects as $p): ?>
                                            <tr>
                                                <td class="fw-medium"><?php echo htmlspecialchars($p['nama_proyek']); ?></td>
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
                    <div class="card border-0 shadow-sm p-4 h-100">
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
                                            <td colspan="3" class="text-center text-muted py-3">Belum ada anggota terdaftar.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentMembers as $m): ?>
                                            <tr>
                                                <td class="d-flex align-items-center gap-2 fw-medium">
                                                    <?php 
                                                    $avatarPath = '../uploads/' . $m['foto'];
                                                    if (!empty($m['foto']) && file_exists(__DIR__ . '/../' . $avatarPath)): 
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
</body>
</html>
