<?php
/**
 * Cloud Team Management - Export Executive Summary to PDF
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

// Fetch summary metrics
$summary = getDashboardSummary($db);

// Write activity log
writeLog($db, $_SESSION['user_id'], 'EXPORT_SUMMARY_PDF', 'Mengunduh laporan ringkasan dashboard (PDF)');

// Setup DomPDF options
$options = new Options();
$options->set('defaultFont', 'Helvetica');
$dompdf = new Dompdf($options);

// Build Executive Summary HTML template
$html = '
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Ringkasan Eksekutif - Executive Summary</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.5; }
        .header { text-align: center; border-bottom: 2px solid #6366f1; padding-bottom: 12px; margin-bottom: 25px; }
        .header h2 { margin: 0 0 5px 0; font-size: 20px; color: #1e1b4b; text-transform: uppercase; letter-spacing: 0.5px; }
        .header p { margin: 0; font-size: 10px; color: #64748b; }
        .report-title { text-align: center; margin-bottom: 25px; }
        .report-title h3 { margin: 0; font-size: 14px; color: #312e81; text-transform: uppercase; letter-spacing: 0.3px; }
        .report-title p { margin: 4px 0 0 0; font-size: 10px; color: #64748b; }
        
        .section-title { font-size: 12px; font-weight: bold; color: #1e1b4b; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-top: 25px; margin-bottom: 12px; text-transform: uppercase; }
        
        /* KPI Cards Styling */
        .kpi-container { width: 100%; margin-bottom: 20px; }
        .kpi-table { width: 100%; border-collapse: collapse; }
        .kpi-card { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; text-align: center; width: 23%; }
        .kpi-title { font-size: 8px; color: #64748b; text-transform: uppercase; margin-bottom: 4px; font-weight: bold; letter-spacing: 0.3px; }
        .kpi-value { font-size: 20px; font-weight: bold; color: #1e293b; }
        
        /* Stats Table Grid */
        .stats-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .stats-table td { padding: 6px 10px; border: 1px solid #e2e8f0; }
        .stats-table td.label { width: 40%; background-color: #f8fafc; font-weight: bold; color: #475569; }
        .stats-table td.value { width: 60%; color: #1e293b; }
        
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
    
    <div class="report-title">
        <h3>Executive Summary Report</h3>
        <p>Tanggal Cetak: ' . formatExportDate('now') . ' &bull; Dicetak Oleh: ' . htmlspecialchars($currentUser['nama'] ?? $currentUser['username']) . '</p>
    </div>

    <!-- 1. KPI Section -->
    <div class="section-title">Indikator Kinerja Utama (KPI)</div>
    <table class="kpi-table" style="width: 100%;">
        <tr>
            <td class="kpi-card">
                <div class="kpi-title">Total Members</div>
                <div class="kpi-value">' . $summary['total_members'] . '</div>
            </td>
            <td style="width: 2%;"></td>
            <td class="kpi-card">
                <div class="kpi-title">Total Projects</div>
                <div class="kpi-value">' . $summary['total_projects'] . '</div>
            </td>
            <td style="width: 2%;"></td>
            <td class="kpi-card">
                <div class="kpi-title">Total Assignments</div>
                <div class="kpi-value">' . $summary['total_assignments'] . '</div>
            </td>
            <td style="width: 2%;"></td>
            <td class="kpi-card">
                <div class="kpi-title">Completed Projects</div>
                <div class="kpi-value" style="color: #10b981;">' . $summary['completed_projects'] . '</div>
            </td>
        </tr>
    </table>

    <!-- 2. System Overview -->
    <div class="section-title">Ikhtisar & Statistik Kolaborasi</div>
    <table class="stats-table">
        <tr>
            <td class="label">Rata-rata Assignment / Member</td>
            <td class="value">' . number_format($summary['avg_assignments_per_member'], 2) . ' penugasan per anggota tim</td>
        </tr>
        <tr>
            <td class="label">Proyek Teratas (Top Project)</td>
            <td class="value">
                <strong>' . htmlspecialchars($summary['top_project_name']) . '</strong> 
                (' . $summary['top_project_members'] . ' Kontributor)
            </td>
        </tr>
    </table>

    <!-- 3. Status Distribution -->
    <div class="section-title">Distribusi Status Proyek</div>
    <table class="stats-table">
        <tr>
            <td class="label">Planning (Direncanakan)</td>
            <td class="value">' . $summary['status_distribution']['planning'] . ' Proyek</td>
        </tr>
        <tr>
            <td class="label">On Progress (Berjalan)</td>
            <td class="value">' . $summary['status_distribution']['progress'] . ' Proyek</td>
        </tr>
        <tr>
            <td class="label">Completed (Selesai)</td>
            <td class="value" style="font-weight: bold; color: #10b981;">' . $summary['status_distribution']['completed'] . ' Proyek</td>
        </tr>
        <tr>
            <td class="label">Suspended (Tertunda)</td>
            <td class="value" style="color: #ef4444;">' . $summary['status_distribution']['suspended'] . ' Proyek</td>
        </tr>
    </table>

    <div class="signature-box">
        <div class="signature-title">Disahkan & Diterbitkan Oleh,</div>
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

$dompdf->stream('Executive_Summary_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
exit;
