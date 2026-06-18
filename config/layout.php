<?php
/**
 * Cloud Team Management - Layout Template Helper
 * Merenders Header, Navbar, Sidebar, and Footer consistently across pages.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';

// Enforce authentication and start session immediately for all pages using the layout helper
requireLogin();

/**
 * Renders the top page header, CSS links, navbar, sidebar, and opens the main content area.
 * 
 * @param string $title Page title.
 * @param string $activeMenu Key of the active menu item (dashboard, anggota, proyek, etc.).
 * @param string $basePath Relative path prefix to root folder. Default is '../'.
 */
function renderHeader($title, $activeMenu = 'dashboard', $basePath = '../') {
    // Enforce authentication
    requireLogin();
    
    // Get currently authenticated user
    $currentUser = getCurrentUser();
    
    // Normalize basePath (ensure it ends with a slash)
    if (!empty($basePath) && substr($basePath, -1) !== '/') {
        $basePath .= '/';
    }
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - Cloud Team Management</title>
    <!-- Bootstrap 5 CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Style -->
    <link href="<?php echo $basePath; ?>assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-md navbar-custom sticky-top py-2 px-3">
    <div class="container-fluid">
        <a class="navbar-brand navbar-brand-custom" href="<?php echo $basePath; ?>dashboard/index.php">
            <span class="navbar-brand-icon">C</span>
            <span>Cloud Team Management</span>
        </a>
        <button class="navbar-toggler d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="ms-auto d-none d-md-flex align-items-center gap-3">
                <?php 
                $foto = $currentUser['foto'] ?? '';
                $absoluteFotoPath = dirname(__DIR__) . '/uploads/' . $foto;
                $navAvatarPath = $basePath . 'uploads/' . $foto;
                
                // Get the associated anggota ID for profile link
                $layoutDb = Database::getConnection();
                $layoutStmt = $layoutDb->prepare("SELECT id FROM anggota WHERE id_user = ?");
                $layoutStmt->execute([$currentUser['id']]);
                $layoutAnggotaId = $layoutStmt->fetchColumn() ?: null;
                
                // Determine target link
                if ($layoutAnggotaId) {
                    $profileUrl = $basePath . 'anggota/detail.php?id=' . $layoutAnggotaId;
                    $profileTooltip = 'Lihat Detail Profil Anda';
                } else {
                    $profileUrl = '#';
                    $profileTooltip = 'Akun Anda tidak tertaut dengan data anggota.';
                }
                ?>
                <a href="<?php echo htmlspecialchars($profileUrl); ?>" class="d-flex align-items-center gap-2 text-decoration-none nav-profile-link" title="<?php echo htmlspecialchars($profileTooltip); ?>">
                    <?php if (!empty($foto) && file_exists($absoluteFotoPath)): 
                        $version = filemtime($absoluteFotoPath);
                    ?>
                        <img src="<?php echo htmlspecialchars($navAvatarPath . '?v=' . $version); ?>" alt="Avatar" class="avatar-mini rounded-circle" style="width: 30px; height: 30px; object-fit: cover;" loading="lazy">
                    <?php else: ?>
                        <div class="avatar-mini rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center text-uppercase fw-bold" style="width: 30px; height: 30px; font-size: 12px; border: 2px solid rgba(255, 255, 255, 0.8);">
                            <?php echo htmlspecialchars(substr($currentUser['nama'] ?? $currentUser['username'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <span class="text-muted small">Halo, <strong class="text-dark"><?php echo htmlspecialchars($currentUser['nama'] ?? $currentUser['username']); ?></strong></span>
                </a>
                <form action="<?php echo $basePath; ?>auth/logout.php" method="POST" class="m-0">
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
                    <!-- Dashboard -->
                    <li class="sidebar-item <?php echo $activeMenu === 'dashboard' ? 'active' : ''; ?>">
                        <a href="<?php echo $basePath; ?>dashboard/index.php" id="nav-dashboard">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <!-- Anggota -->
                    <li class="sidebar-item <?php echo $activeMenu === 'anggota' ? 'active' : ''; ?>">
                        <a href="<?php echo $basePath; ?>anggota/index.php" id="nav-anggota">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                            <span>Anggota</span>
                        </a>
                    </li>
                    <!-- Proyek -->
                    <li class="sidebar-item <?php echo $activeMenu === 'proyek' ? 'active' : ''; ?>">
                        <a href="<?php echo $basePath; ?>proyek/index.php" id="nav-proyek">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                            <span>Proyek</span>
                        </a>
                    </li>
                    <!-- Assignment -->
                    <li class="sidebar-item <?php echo $activeMenu === 'assignment' ? 'active' : ''; ?>">
                        <a href="<?php echo $basePath; ?>assignment/index.php" id="nav-assignment">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg>
                            <span>Assignment</span>
                        </a>
                    </li>
                    <!-- Activity Log -->
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li class="sidebar-item <?php echo $activeMenu === 'activity' ? 'active' : ''; ?>">
                        <a href="<?php echo $basePath; ?>activity-log/index.php" id="nav-activity">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            <span>Activity Log</span>
                        </a>
                    </li>
                    <!-- Reports -->
                    <li class="sidebar-item <?php echo $activeMenu === 'reports' ? 'active' : ''; ?>">
                        <a href="<?php echo $basePath; ?>reports/index.php" id="nav-reports">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                            <span>Reports</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="sidebar-item border-top pt-3 mb-2">
                    <form action="<?php echo $basePath; ?>auth/logout.php" method="POST" class="w-100">
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
            
            <!-- Global Flash Messages Display -->
            <?php if (isset($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4" role="alert" style="border-left: 4px solid #10b981 !important;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold">✓</span>
                        <span><?php echo htmlspecialchars($_SESSION['flash_success']); ?></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert" style="border-left: 4px solid #ef4444 !important;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold">⚠</span>
                        <span><?php echo htmlspecialchars($_SESSION['flash_error']); ?></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>
    <?php
}

/**
 * Renders the page footer and closes HTML tags.
 * 
 * @param string $basePath Relative path prefix to root folder. Default is '../'.
 */
function renderFooter($basePath = '../') {
    // Normalize basePath (ensure it ends with a slash)
    if (!empty($basePath) && substr($basePath, -1) !== '/') {
        $basePath .= '/';
    }
    ?>
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
    <?php
}
