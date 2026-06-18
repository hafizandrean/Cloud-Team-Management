<?php
/**
 * Cloud Team Management - Export Assignments to PDF
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/activity_helper.php';
require_once __DIR__ . '/../helper.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Protect page (Admin only)
requireRole('admin');
$currentUser = getCurrentUser();

$db = Database::getConnection();

// Get project filter
$projectIdFilter = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

$where = '';
$params = [];

if ($projectIdFilter > 0) {
    $where = ' WHERE p.id = :project_id ';
    $params['project_id'] = $projectIdFilter;
}

// Fetch assignments
try {
    $sql = "
        SELECT a.nama, a.nim, p.nama_proyek, ap.created_at
        FROM anggota_proyek ap
        JOIN anggota a ON ap.anggota_id = a.id
        JOIN proyek p ON ap.proyek_id = p.id
        " . $where . "
        ORDER BY p.nama_proyek ASC, a.nama ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $assignments = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Fetch project name for log & title
$projectNameLabel = 'Semua Proyek';
if ($projectIdFilter > 0) {
    try {
        $pStmt = $db->prepare("SELECT nama_proyek FROM proyek WHERE id = ?");
        $pStmt->execute([$projectIdFilter]);
        $fetchedName = $pStmt->fetchColumn();
        if ($fetchedName) {
            $projectNameLabel = $fetchedName;
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch project name: " . $e->getMessage());
    }
}

// Write activity log with detailed description
if ($projectIdFilter > 0) {
    writeLog($db, $_SESSION['user_id'], 'EXPORT_ASSIGNMENT_PDF', 'Mengunduh laporan assignment proyek: ' . $projectNameLabel);
} else {
    writeLog($db, $_SESSION['user_id'], 'EXPORT_ASSIGNMENT_PDF', 'Mengunduh laporan assignment untuk semua proyek');
}

// Setup DomPDF options
$options = new Options();
$options->set('defaultFont', 'Helvetica');
$dompdf = new Dompdf($options);

// Build HTML template
$html = '
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penugasan Anggota (Assignment)</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0 0 5px 0; font-size: 18px; text-transform: uppercase; letter-spacing: 0.5px; }
        .header p { margin: 0; font-size: 10px; color: #666; }
        .meta-info { margin-bottom: 20px; }
        .meta-info table { width: 100%; font-size: 10px; }
        .meta-info td { padding: 3px 0; vertical-align: top; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        .data-table th, .data-table td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
        .data-table th { background-color: #f8fafc; font-weight: bold; font-size: 10px; text-transform: uppercase; color: #1e293b; }
        .data-table tr:nth-child(even) { background-color: #fcfdfe; }
        .footer { position: fixed; bottom: -10px; left: 0; right: 0; font-size: 8px; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 5px; color: #94a3b8; }
        .signature-box { margin-top: 40px; float: right; width: 220px; text-align: center; }
        .signature-title { margin-bottom: 55px; font-size: 10px; }
        .signature-name { border-top: 1px solid #333; padding-top: 4px; font-weight: bold; font-size: 10px; }
        .signature-role { font-size: 8px; color: #64748b; margin-top: 2px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Cloud Team Management</h2>
        <p>Laporan Resmi Internal &bull; Kelompok 2</p>
    </div>
    
    <div class="meta-info">
        <table cellspacing="0" cellpadding="0">
            <tr>
                <td style="width: 15%;"><strong>Jenis Laporan:</strong></td>
                <td style="width: 45%;">Daftar Penugasan Tim (Assignment)</td>
                <td style="width: 20%; text-align: right;"><strong>Tanggal Cetak:</strong></td>
                <td style="width: 20%; text-align: right;">' . formatExportDate('now') . '</td>
            </tr>
            <tr>
                <td><strong>Dicetak Oleh:</strong></td>
                <td>' . htmlspecialchars($currentUser['nama'] ?? $currentUser['username']) . '</td>
                <td style="text-align: right;"><strong>Filter Proyek:</strong></td>
                <td style="text-align: right; font-weight: bold;">' . htmlspecialchars($projectNameLabel) . '</td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 6%; text-align: center;">No</th>
                <th style="width: 29%;">Nama Anggota</th>
                <th style="width: 20%;">NIM</th>
                <th style="width: 25%;">Nama Proyek</th>
                <th style="width: 20%;">Ditugaskan Pada</th>
            </tr>
        </thead>
        <tbody>';

$no = 1;
if (!empty($assignments)) {
    foreach ($assignments as $a) {
        $html .= '
            <tr>
                <td style="text-align: center;">' . $no++ . '</td>
                <td><strong>' . htmlspecialchars($a['nama']) . '</strong></td>
                <td>' . htmlspecialchars($a['nim']) . '</td>
                <td>' . htmlspecialchars($a['nama_proyek']) . '</td>
                <td>' . date('d M Y, H:i', strtotime($a['created_at'])) . '</td>
            </tr>';
    }
} else {
    $html .= '
            <tr>
                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 20px;">Tidak ada data assignment terdaftar.</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="signature-box">
        <div class="signature-title">Disetujui & Disahkan Oleh,</div>
        <div class="signature-name">' . htmlspecialchars($currentUser['nama'] ?? $currentUser['username']) . '</div>
        <div class="signature-role">' . strtoupper(htmlspecialchars($currentUser['role'])) . ' CTM</div>
    </div>

    <div class="footer">
        Cloud Team Management &bull; Generated By: ' . htmlspecialchars($currentUser['username']) . ' &bull; Generated At: ' . formatExportDate('now') . '
    </div>
</body>
</html>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream('Laporan_Assignment_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
exit;
