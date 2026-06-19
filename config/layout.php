<?php
/**
 * Cloud Team Management - Layout Template Helper
 * Renders Header, Navbar, Sidebar, and Footer consistently across pages.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';

requireLogin();

/**
 * Renders the top page header, CSS links, navbar, sidebar, and opens the main content area.
 *
 * @param string $title Page title.
 * @param string $activeMenu Key of the active menu item.
 * @param string $basePath Relative path prefix to root folder. Default is '../'.
 */
function renderHeader($title, $activeMenu = 'dashboard', $basePath = '../') {
    requireLogin();

    $currentUser = getCurrentUser();

    if (!empty($basePath) && substr($basePath, -1) !== '/') {
        $basePath .= '/';
    }

    $foto = $currentUser['foto'] ?? '';
    $absoluteFotoPath = dirname(__DIR__) . '/uploads/' . $foto;
    $navAvatarPath = $basePath . 'uploads/' . $foto;

    $layoutDb = Database::getConnection();
    $layoutStmt = $layoutDb->prepare("SELECT id FROM anggota WHERE id_user = ?");
    $layoutStmt->execute([$currentUser['id']]);
    $layoutAnggotaId = $layoutStmt->fetchColumn() ?: null;

    if ($layoutAnggotaId) {
        $profileUrl = $basePath . 'anggota/detail.php?id=' . $layoutAnggotaId;
        $profileTooltip = 'Lihat Detail Profil Anda';
    } else {
        $profileUrl = '#';
        $profileTooltip = 'Akun Anda tidak tertaut dengan data anggota.';
    }
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - Cloud Team Management</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?php echo $basePath; ?>assets/css/style.css" rel="stylesheet">
</head>

<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-md navbar-custom">
    <div class="container-fluid navbar-inner">
        <a class="navbar-brand navbar-brand-custom" href="<?php echo $basePath; ?>dashboard/index.php">
            <span class="navbar-brand-icon">C</span>
            <span>Cloud Team Management</span>
        </a>

        <button class="navbar-toggler d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="ms-auto d-none d-md-flex align-items-center gap-3">
            <a href="<?php echo htmlspecialchars($profileUrl); ?>" class="nav-profile-link" title="<?php echo htmlspecialchars($profileTooltip); ?>">
                <?php if (!empty($foto) && file_exists($absoluteFotoPath)): ?>
                    <?php $version = filemtime($absoluteFotoPath); ?>
                    <img src="<?php echo htmlspecialchars($navAvatarPath . '?v=' . $version); ?>" alt="Avatar" class="avatar-mini navbar-avatar" loading="lazy">
                <?php else: ?>
                    <div class="avatar-mini navbar-avatar text-uppercase">
                        <?php echo htmlspecialchars(substr($currentUser['nama'] ?? $currentUser['username'], 0, 1)); ?>
                    </div>
                <?php endif; ?>

                <span class="navbar-greeting">
                    Halo, <strong><?php echo htmlspecialchars($currentUser['nama'] ?? $currentUser['username']); ?></strong>
                </span>
            </a>

            <form action="<?php echo $basePath; ?>auth/logout.php" method="POST" class="m-0">
                <button type="submit" class="btn btn-outline-danger btn-sm btn-navbar-logout">Keluar</button>
            </form>
        </div>
    </div>
</nav>

<!-- App Layout -->
<div class="app-shell">
    <div class="app-row">

        <!-- Sidebar -->
        <nav id="sidebarMenu" class="sidebar app-sidebar collapse d-md-block">
            <div class="sidebar-sticky-wrapper">
                <ul class="sidebar-menu">

                    <li class="sidebar-item <?php echo $activeMenu === 'dashboard' ? 'active' : ''; ?>">
                        <a href="<?php echo $basePath; ?>dashboard/index.php" id="nav-dashboard">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            <span>Dashboard</span>
                        </a>
                    </li>

                    <li class="sidebar-item <?php echo $activeMenu === 'anggota' ? 'active' : ''; ?>">
                        <a href="<?php echo $basePath; ?>anggota/index.php" id="nav-anggota">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                            </svg>
                            <span>Anggota</span>
                        </a>
                    </li>

                    <li class="sidebar-item <?php echo $activeMenu === 'proyek' ? 'active' : ''; ?>">
                        <a href="<?php echo $basePath; ?>proyek/index.php" id="nav-proyek">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                            </svg>
                            <span>Proyek</span>
                        </a>
                    </li>

                    <li class="sidebar-item <?php echo $activeMenu === 'assignment' ? 'active' : ''; ?>">
                        <a href="<?php echo $basePath; ?>assignment/index.php" id="nav-assignment">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <polyline points="16 11 18 13 22 9"></polyline>
                            </svg>
                            <span>Assignment</span>
                        </a>
                    </li>

                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <li class="sidebar-item <?php echo $activeMenu === 'activity' ? 'active' : ''; ?>">
                            <a href="<?php echo $basePath; ?>activity-log/index.php" id="nav-activity">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                                <span>Activity Log</span>
                            </a>
                        </li>

                        <li class="sidebar-item <?php echo $activeMenu === 'reports' ? 'active' : ''; ?>">
                            <a href="<?php echo $basePath; ?>reports/index.php" id="nav-reports">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="20" x2="18" y2="10"></line>
                                    <line x1="12" y1="20" x2="12" y2="4"></line>
                                    <line x1="6" y1="20" x2="6" y2="14"></line>
                                </svg>
                                <span>Reports</span>
                            </a>
                        </li>
                    <?php endif; ?>

                </ul>

                <div class="sidebar-logout-wrapper">
                    <form action="<?php echo $basePath; ?>auth/logout.php" method="POST" class="w-100">
                        <button type="submit" class="btn-sidebar-logout" id="btn-logout-sidebar">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            <span>Keluar</span>
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="app-main">

            <?php if (isset($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold">✓</span>
                        <span><?php echo htmlspecialchars($_SESSION['flash_success']); ?></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
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
 * Renders footer and closes HTML tags.
 *
 * @param string $basePath Relative path prefix to root folder. Default is '../'.
 */
function renderFooter($basePath = '../') {
    if (!empty($basePath) && substr($basePath, -1) !== '/') {
        $basePath .= '/';
    }
    ?>
            <footer class="footer text-center">
                Cloud Team Management v1.3 &copy; 2026 Kelompok 2
            </footer>
        </main>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }

    function resetAppScroll() {
        window.scrollTo(0, 0);
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;

        const main = document.querySelector('.app-main');
        if (main) {
            main.scrollTop = 0;
        }

        const sidebar = document.querySelector('.app-sidebar');
        if (sidebar) {
            sidebar.scrollTop = 0;
        }
    }

    window.addEventListener('DOMContentLoaded', resetAppScroll);
    window.addEventListener('load', resetAppScroll);
    window.addEventListener('pageshow', resetAppScroll);
</script>

</body>
</html>
    <?php
}