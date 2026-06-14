<?php
/**
 * Cloud Team Management - Reporting & Export Center
 */

require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/helper.php';

$db = Database::getConnection();

// Fetch summary metrics
$summary = getDashboardSummary($db);

// Check if database is empty (no members and no projects)
$isEmpty = ($summary['total_members'] === 0 && $summary['total_projects'] === 0);

// Fetch active projects for dropdown
$projectsDropdown = [];
try {
    $projectsDropdown = $db->query("SELECT id, nama_proyek FROM proyek ORDER BY nama_proyek ASC")->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to load projects dropdown: " . $e->getMessage());
}

// Render header
renderHeader('Report Center', 'reports', '../');
?>

<!-- Title Block -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold welcome-title" id="page-title">Reporting & Export Center</h1>
        <p class="welcome-subtitle text-muted">Pusat pelaporan manajemen, ekspor data, dan ringkasan eksekutif.</p>
    </div>
</div>

<?php if ($isEmpty): ?>
    <!-- Empty State -->
    <div class="card border-0 shadow-sm p-5 text-center mb-4">
        <div class="my-5">
            <span style="font-size: 56px;">📝</span>
            <h4 class="fw-bold mt-3 text-dark">Belum Ada Data Untuk Dilaporkan</h4>
            <p class="text-muted">Silakan tambahkan anggota, proyek, atau assignment terlebih dahulu sebelum mengunduh laporan.</p>
            <div class="mt-4 gap-2 d-flex justify-content-center">
                <a href="../anggota/create.php" class="btn btn-primary px-4 py-2">Tambah Anggota</a>
                <a href="../proyek/create.php" class="btn btn-outline-primary px-4 py-2">Tambah Proyek</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Metrics Cards Grid -->
    <div class="row g-4 mb-4">
        <!-- Members Count -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card-summary d-flex align-items-center justify-content-between">
                <div class="metric-info">
                    <div class="card-summary-title">Total Members</div>
                    <div class="card-summary-value" id="val-total-members"><?php echo $summary['total_members']; ?></div>
                </div>
                <div class="card-summary-icon" style="background-color: rgba(99, 102, 241, 0.1); color: var(--primary-color);">👥</div>
            </div>
        </div>
        <!-- Projects Count -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card-summary d-flex align-items-center justify-content-between">
                <div class="metric-info">
                    <div class="card-summary-title">Total Projects</div>
                    <div class="card-summary-value" id="val-total-projects"><?php echo $summary['total_projects']; ?></div>
                </div>
                <div class="card-summary-icon" style="background-color: rgba(14, 165, 233, 0.1); color: #0ea5e9;">📂</div>
            </div>
        </div>
        <!-- Assignments Count -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card-summary d-flex align-items-center justify-content-between">
                <div class="metric-info">
                    <div class="card-summary-title">Total Assignments</div>
                    <div class="card-summary-value" id="val-total-assignments"><?php echo $summary['total_assignments']; ?></div>
                </div>
                <div class="card-summary-icon" style="background-color: rgba(245, 158, 11, 0.1); color: #f59e0b;">🔄</div>
            </div>
        </div>
        <!-- Completed Projects Count -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card-summary d-flex align-items-center justify-content-between">
                <div class="metric-info">
                    <div class="card-summary-title">Completed Projects</div>
                    <div class="card-summary-value" id="val-completed-projects"><?php echo $summary['completed_projects']; ?></div>
                </div>
                <div class="card-summary-icon" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981;">✅</div>
            </div>
        </div>
    </div>

    <!-- Export Actions Grid -->
    <div class="row g-4 mb-4">
        <!-- 1. Member Report -->
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span style="font-size: 24px;">👥</span>
                        <h5 class="fw-bold mb-0 text-dark">Member Report</h5>
                    </div>
                    <p class="text-muted small">Mengekspor daftar seluruh anggota CTM terdaftar beserta NIM dan email ke berkas PDF atau Excel.</p>
                </div>
                <div class="d-flex gap-2 mt-4">
                    <a href="pdf/members.php" target="_blank" class="btn btn-primary w-100 py-2 d-flex align-items-center justify-content-center gap-2">
                        📄 Export PDF
                    </a>
                    <a href="excel/members.php" target="_blank" class="btn btn-success w-100 py-2 d-flex align-items-center justify-content-center gap-2">
                        📊 Export Excel
                    </a>
                </div>
            </div>
        </div>

        <!-- 2. Project Report -->
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span style="font-size: 24px;">📂</span>
                        <h5 class="fw-bold mb-0 text-dark">Project Report</h5>
                    </div>
                    <p class="text-muted small">Mengekspor daftar proyek beserta status, deadline, dan jumlah kontributor. Filter status opsional sebelum ekspor.</p>
                    
                    <div class="mb-3 mt-3">
                        <label for="project-status" class="form-label small fw-bold text-muted">Filter Status</label>
                        <select class="form-select" id="project-status">
                            <option value="all">Semua Status</option>
                            <option value="direncanakan">Planning (Direncanakan)</option>
                            <option value="berjalan">On Progress (Berjalan)</option>
                            <option value="selesai">Completed (Selesai)</option>
                            <option value="tertunda">Suspended (Tertunda)</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button onclick="exportProject('pdf')" class="btn btn-primary w-100 py-2 d-flex align-items-center justify-content-center gap-2">
                        📄 Export PDF
                    </button>
                    <button onclick="exportProject('excel')" class="btn btn-success w-100 py-2 d-flex align-items-center justify-content-center gap-2">
                        📊 Export Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- 3. Assignment Report -->
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span style="font-size: 24px;">🔄</span>
                        <h5 class="fw-bold mb-0 text-dark">Assignment Report</h5>
                    </div>
                    <p class="text-muted small">Mengekspor daftar penugasan kolaborasi antar anggota dan proyek. Filter berdasarkan proyek spesifik sebelum ekspor.</p>
                    
                    <div class="mb-3 mt-3">
                        <label for="assignment-project" class="form-label small fw-bold text-muted">Filter Proyek</label>
                        <select class="form-select" id="assignment-project">
                            <option value="all">Semua Proyek</option>
                            <?php foreach ($projectsDropdown as $proj): ?>
                                <option value="<?php echo $proj['id']; ?>"><?php echo htmlspecialchars($proj['nama_proyek']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button onclick="exportAssignment('pdf')" class="btn btn-primary w-100 py-2 d-flex align-items-center justify-content-center gap-2">
                        📄 Export PDF
                    </button>
                    <button onclick="exportAssignment('excel')" class="btn btn-success w-100 py-2 d-flex align-items-center justify-content-center gap-2">
                        📊 Export Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- 4. Executive Summary -->
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span style="font-size: 24px;">📊</span>
                        <h5 class="fw-bold mb-0 text-dark">Executive Summary</h5>
                    </div>
                    <p class="text-muted small">Mengekspor laporan ikhtisar eksekutif yang memuat KPI performa tim, grafik distribusi status proyek, dan proyek teratas.</p>
                </div>
                <div class="mt-4">
                    <a href="pdf/summary.php" target="_blank" class="btn btn-primary w-100 py-2 d-flex align-items-center justify-content-center gap-2">
                        📄 Export Executive Summary (PDF)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript For Filters Routing -->
    <script>
    function exportProject(format) {
        const status = document.getElementById('project-status').value;
        window.open(format === 'pdf' ? 'pdf/projects.php?status=' + status : 'excel/projects.php?status=' + status, '_blank');
    }

    function exportAssignment(format) {
        const projectId = document.getElementById('assignment-project').value;
        window.open(format === 'pdf' ? 'pdf/assignments.php?project_id=' + projectId : 'excel/assignments.php?project_id=' + projectId, '_blank');
    }
    </script>
<?php endif; ?>

<?php
// Render footer
renderFooter('../');
?>
