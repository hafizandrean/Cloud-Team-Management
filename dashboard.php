<?php
/**
 * Cloud Team Management - Dashboard Page (Protected)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Enforce authentication
requireLogin();

// Get current user information
$currentUser = getCurrentUser();

// Fetch summary metrics from database
$totalMembers = 0;
$totalProjects = 0;
$errorMsg = '';

try {
    $db = Database::getConnection();
    
    // Count total members
    $stmt = $db->query("SELECT COUNT(*) FROM anggota");
    $totalMembers = $stmt->fetchColumn();

    // Count total projects
    $stmt = $db->query("SELECT COUNT(*) FROM proyek");
    $totalProjects = $stmt->fetchColumn();
} catch (PDOException $e) {
    $errorMsg = 'Gagal memuat beberapa data statistik.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Cloud Team Management</title>
    <meta name="description" content="Dashboard utama aplikasi Cloud Team Management.">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --primary-color: #6366f1;
            --primary-hover: #4f46e5;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-body: #334155;
            --border-color: #e2e8f0;
            --sidebar-width: 260px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-body);
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar Styling */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--card-bg);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
        }

        .sidebar-brand {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            background-color: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
        }

        .brand-name {
            font-weight: 700;
            font-size: 16px;
            color: var(--text-main);
            letter-spacing: -0.3px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-grow: 1;
        }

        .menu-item a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .menu-item a:hover {
            color: var(--primary-color);
            background-color: rgba(99, 102, 241, 0.05);
        }

        .menu-item.active a {
            color: var(--primary-color);
            background-color: rgba(99, 102, 241, 0.08);
            font-weight: 600;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            overflow: hidden;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }

        .user-details {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        .user-role {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: capitalize;
        }

        .btn-logout-icon {
            color: var(--text-muted);
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-logout-icon:hover {
            color: #ef4444;
            background-color: #fef2f2;
        }

        /* Main Content Styling */
        .main-content {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            padding: 40px;
            max-width: 1200px;
        }

        .welcome-header {
            margin-bottom: 36px;
        }

        .welcome-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .welcome-subtitle {
            font-size: 15px;
            color: var(--text-muted);
        }

        /* Cards Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .metric-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .metric-info h3 {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .metric-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-main);
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .metric-card.members .metric-icon {
            background-color: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
        }

        .metric-card.projects .metric-icon {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        /* Actions Section */
        .actions-section {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .section-desc {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .action-button {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 20px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: var(--bg-color);
            text-decoration: none;
            color: var(--text-body);
            transition: all 0.2s ease;
        }

        .action-button:hover {
            border-color: var(--primary-color);
            background-color: #ffffff;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.05);
        }

        .action-icon {
            font-size: 24px;
            margin-bottom: 12px;
        }

        .action-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 4px;
        }

        .action-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.4;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                position: relative;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }

            .sidebar-brand {
                padding: 16px 20px;
            }

            .sidebar-menu {
                padding: 12px 20px;
                flex-direction: row;
                overflow-x: auto;
                flex-grow: 0;
            }

            .sidebar-footer {
                display: none;
            }

            .main-content {
                margin-left: 0;
                padding: 24px;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar Navigation -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">C</div>
        <span class="brand-name">Cloud Team</span>
    </div>
    
    <ul class="sidebar-menu">
        <li class="menu-item active">
            <a href="dashboard.php" id="nav-dashboard">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="anggota/index.php" id="nav-anggota">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <span>Anggota Tim</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="proyek/index.php" id="nav-proyek">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path><line x1="12" y1="11" x2="12" y2="17"></line><line x1="9" y1="14" x2="15" y2="14"></line></svg>
                <span>Kelola Proyek</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <div class="user-info">
            <?php 
            $photoPath = 'uploads/' . $currentUser['foto'];
            if (!empty($currentUser['foto']) && file_exists(__DIR__ . '/' . $photoPath)): 
            ?>
                <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="Avatar" class="user-avatar" id="user-avatar">
            <?php else: ?>
                <div class="user-avatar" id="user-avatar">
                    <?php 
                    $initial = strtoupper(substr($currentUser['nama'], 0, 1));
                    echo htmlspecialchars($initial);
                    ?>
                </div>
            <?php endif; ?>
            <div class="user-details">
                <span class="user-name" id="user-name"><?php echo htmlspecialchars($currentUser['nama']); ?></span>
                <span class="user-role" id="user-role"><?php echo htmlspecialchars($currentUser['role']); ?></span>
            </div>
        </div>
        
        <form action="logout.php" method="POST" style="display:inline;">
            <button type="submit" class="btn-logout-icon" title="Logout" id="btn-logout-sidebar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </button>
        </form>
    </div>
</aside>

<!-- Main Workspace -->
<main class="main-content">
    <header class="welcome-header">
        <h2 class="welcome-title" id="welcome-title">Halo, <?php echo htmlspecialchars($currentUser['nama']); ?>!</h2>
        <p class="welcome-subtitle">Selamat datang di panel kontrol Cloud Team Management. Kelola proyek dan kolaborator tim dengan mudah.</p>
    </header>

    <!-- Metrics Cards -->
    <section class="metrics-grid">
        <div class="metric-card members" id="card-members">
            <div class="metric-info">
                <h3>Total Anggota Tim</h3>
                <span class="metric-value"><?php echo $totalMembers; ?></span>
            </div>
            <div class="metric-icon">👥</div>
        </div>
        
        <div class="metric-card projects" id="card-projects">
            <div class="metric-info">
                <h3>Total Proyek Aktif</h3>
                <span class="metric-value"><?php echo $totalProjects; ?></span>
            </div>
            <div class="metric-icon">📂</div>
        </div>
    </section>

    <!-- Quick Actions -->
    <section class="actions-section">
        <h3 class="section-title">Aksi Cepat</h3>
        <p class="section-desc">Pilih modul di bawah ini untuk mulai melakukan pengelolaan data tim Anda.</p>
        
        <div class="actions-grid">
            <a href="anggota/index.php" class="action-button" id="action-anggota">
                <span class="action-icon">👥</span>
                <span class="action-title">Kelola Anggota</span>
                <span class="action-desc">Tambah, edit, hapus, dan lihat anggota tim beserta data personalnya.</span>
            </a>
            
            <a href="proyek/index.php" class="action-button" id="action-proyek">
                <span class="action-icon">💼</span>
                <span class="action-title">Kelola Proyek</span>
                <span class="action-desc">Lihat daftar proyek berjalan, status progres, dan pengaturan durasi.</span>
            </a>
        </div>
    </section>
</main>

</body>
</html>
