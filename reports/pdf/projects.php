<?php
/**
 * Cloud Team Management - Export Projects to PDF
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/activity_helper.php';
require_once __DIR__ . '/../helper.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Enforce login
requireLogin();
$currentUser = getCurrentUser();

$db = Database::getConnection();

// Get status filter
$statusFilter = trim($_GET['status'] ?? 'all');
$allowedStatuses = ['direncanakan', 'berjalan', 'selesai', 'tertunda'];

$where = '';
$params = [];

if (in_array($statusFilter, $allowedStatuses)) {
    $where = ' WHERE p.status = :status ';
    $params['status'] = $statusFilter;
}

// Fetch projects
try {
    $sql = "
        SELECT p.*, COUNT(ap.id) as total_anggota
        FROM proyek p
        LEFT JOIN anggota_proyek ap ON p.id = ap.proyek_id
        " . $where . "
        GROUP BY p.id
        ORDER BY p.nama_proyek ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Write activity log with detail filter
$statusLabel = 'Semua Status';
if (in_array($statusFilter, $allowedStatuses)) {
    $statusLabel = getProjectStatusText($statusFilter);
}
writeLog($db, $_SESSION['user_id'], 'EXPORT_PROJECT_PDF', 'Mengunduh laporan proyek dengan filter status: ' . $statusLabel);

// Setup DomPDF options
$options = new Options();
$options->set('defaultFont', 'Helvetica');
$dompdf = new Dompdf($options);

// Build printable HTML template
$html = '
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Daftar Proyek</title>
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
        .badge { padding: 4px 8px; border-radius: 4px; font-weight: 500; font-size: 9px; text-transform: uppercase; display: inline-block; }
        .badge-planning { background-color: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }
        .badge-progress { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .badge-completed { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-suspended { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
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
                <td style="width: 45%;">Daftar Proyek CTM</td>
                <td style="width: 20%; text-align: right;"><strong>Tanggal Cetak:</strong></td>
                <td style="width: 20%; text-align: right;">' . formatExportDate('now') . '</td>
            </tr>
            <tr>
                <td><strong>Dicetak Oleh:</strong></td>
                <td>' . htmlspecialchars($currentUser['nama'] ?? $currentUser['username']) . '</td>
                <td style="text-align: right;"><strong>Filter Status:</strong></td>
                <td style="text-align: right; font-weight: bold;">' . htmlspecialchars($statusLabel) . '</td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 6%; text-align: center;">No</th>
                <th style="width: 34%;">Nama Proyek</th>
                <th style="width: 22%; text-align: center;">Status</th>
                <th style="width: 20%;">Deadline</th>
                <th style="width: 18%; text-align: center;">Anggota</th>
            </tr>
        </thead>
        <tbody>';

$no = 1;
if (!empty($projects)) {
    foreach ($projects as $p) {
        $statusClass = 'badge-planning';
        if ($p['status'] === 'berjalan') {
            $statusClass = 'badge-progress';
        } elseif ($p['status'] === 'selesai') {
            $statusClass = 'badge-completed';
        } elseif ($p['status'] === 'tertunda') {
            $statusClass = 'badge-suspended';
        }

        $html .= '
            <tr>
                <td style="text-align: center;">' . $no++ . '</td>
                <td><strong>' . htmlspecialchars($p['nama_proyek']) . '</strong></td>
                <td style="text-align: center;">
                    <span class="badge ' . $statusClass . '">' . getProjectStatusText($p['status']) . '</span>
                </td>
                <td>' . date('d M Y', strtotime($p['deadline'])) . '</td>
                <td style="text-align: center;">' . $p['total_anggota'] . ' Org</td>
            </tr>';
    }
} else {
    $html .= '
            <tr>
                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 20px;">Tidak ada data proyek terdaftar.</td>
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

$dompdf->stream('Laporan_Proyek_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
exit;
