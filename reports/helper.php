<?php
/**
 * Cloud Team Management - Reports Helper
 * Centralized queries and utility functions for generating reports.
 */

if (!function_exists('getDashboardSummary')) {
    /**
     * Get dashboard summary statistics.
     *
     * @param PDO $db The database connection
     * @return array Summary statistics
     */
    function getDashboardSummary(PDO $db) {
        $summary = [
            'total_members' => 0,
            'total_projects' => 0,
            'total_assignments' => 0,
            'completed_projects' => 0,
            'avg_assignments_per_member' => 0.0,
            'top_project_name' => '-',
            'top_project_members' => 0,
            'status_distribution' => [
                'planning' => 0,
                'progress' => 0,
                'completed' => 0,
                'suspended' => 0
            ]
        ];

        try {
            // 1. Total Members
            $summary['total_members'] = (int)$db->query("SELECT COUNT(*) FROM anggota")->fetchColumn();

            // 2. Total Projects
            $summary['total_projects'] = (int)$db->query("SELECT COUNT(*) FROM proyek")->fetchColumn();

            // 3. Total Assignments
            $summary['total_assignments'] = (int)$db->query("SELECT COUNT(*) FROM anggota_proyek")->fetchColumn();

            // 4. Completed Projects
            $summary['completed_projects'] = (int)$db->query("
                SELECT COUNT(*) FROM proyek WHERE status = 'selesai'
            ")->fetchColumn();

            // 5. Average Assignments / Member
            if ($summary['total_members'] > 0) {
                $summary['avg_assignments_per_member'] = round($summary['total_assignments'] / $summary['total_members'], 2);
            }

            // 6. Top Project (project with most members assigned)
            $topProj = $db->query("
                SELECT p.nama_proyek, COUNT(ap.id) as total_members
                FROM proyek p
                LEFT JOIN anggota_proyek ap ON p.id = ap.proyek_id
                GROUP BY p.id
                ORDER BY total_members DESC, p.nama_proyek ASC
                LIMIT 1
            ")->fetch();
            
            if ($topProj && (int)$topProj['total_members'] > 0) {
                $summary['top_project_name'] = $topProj['nama_proyek'];
                $summary['top_project_members'] = (int)$topProj['total_members'];
            }

            // 7. Status Distribution breakdown
            $statusCounts = $db->query("
                SELECT status, COUNT(*) as count 
                FROM proyek 
                GROUP BY status
            ")->fetchAll();

            foreach ($statusCounts as $row) {
                switch ($row['status']) {
                    case 'direncanakan':
                        $summary['status_distribution']['planning'] = (int)$row['count'];
                        break;
                    case 'berjalan':
                        $summary['status_distribution']['progress'] = (int)$row['count'];
                        break;
                    case 'selesai':
                        $summary['status_distribution']['completed'] = (int)$row['count'];
                        break;
                    case 'tertunda':
                        $summary['status_distribution']['suspended'] = (int)$row['count'];
                        break;
                }
            }
        } catch (PDOException $e) {
            error_log("Failed to fetch dashboard summary: " . $e->getMessage());
        }

        return $summary;
    }
}

if (!function_exists('getProjectStatusText')) {
    /**
     * Map database ENUM status to UI English status.
     *
     * @param string $status Database status
     * @return string English UI status
     */
    function getProjectStatusText(string $status) {
        switch ($status) {
            case 'direncanakan':
                return 'Planning';
            case 'berjalan':
                return 'On Progress';
            case 'selesai':
                return 'Completed';
            case 'tertunda':
                return 'Suspended';
            default:
                return ucfirst($status);
        }
    }
}

if (!function_exists('formatExportDate')) {
    /**
     * Format timestamp to WIB reporting timezone format.
     *
     * @param string $dateString Date string, default is 'now'
     * @return string Formatted date string
     */
    function formatExportDate(string $dateString = 'now') {
        try {
            $date = new DateTime($dateString, new DateTimeZone('Asia/Jakarta'));
            return $date->format('d M Y H:i') . ' WIB';
        } catch (Exception $e) {
            return date('d M Y H:i') . ' WIB';
        }
    }
}
