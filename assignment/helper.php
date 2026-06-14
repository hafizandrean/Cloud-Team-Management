<?php
/**
 * Cloud Team Management - Assignment Helpers
 */

/**
 * Calculates and returns stats for assignments.
 * 
 * @param PDO $db Connection instance.
 * @return array Array of stats.
 */
function getAssignmentStats($db) {
    try {
        $totalAssignments = $db->query("SELECT COUNT(*) FROM anggota_proyek")->fetchColumn();
        $totalActiveMembers = $db->query("SELECT COUNT(DISTINCT anggota_id) FROM anggota_proyek")->fetchColumn();
        $totalActiveProjects = $db->query("SELECT COUNT(DISTINCT proyek_id) FROM anggota_proyek")->fetchColumn();
        
        $avg = 0;
        if ($totalActiveMembers > 0) {
            $avg = round($totalAssignments / $totalActiveMembers, 2);
        }
        
        return [
            'total_assignments'   => $totalAssignments,
            'total_active_members' => $totalActiveMembers,
            'total_active_projects' => $totalActiveProjects,
            'average_assignments'  => $avg
        ];
    } catch (PDOException $e) {
        return [
            'total_assignments'   => 0,
            'total_active_members' => 0,
            'total_active_projects' => 0,
            'average_assignments'  => 0
        ];
    }
}

/**
 * Fetches project member distribution for progress bars.
 * 
 * @param PDO $db Connection instance.
 * @return array Array of projects and member counts.
 */
function getProjectDistribution($db) {
    try {
        // Get total members count to act as 100% basis
        $totalMembers = $db->query("SELECT COUNT(*) FROM anggota")->fetchColumn();
        if ($totalMembers < 1) {
            $totalMembers = 1;
        }

        $query = "
            SELECT p.id, p.nama_proyek, COUNT(ap.id) AS member_count
            FROM proyek p
            LEFT JOIN anggota_proyek ap ON p.id = ap.proyek_id
            GROUP BY p.id, p.nama_proyek
            ORDER BY member_count DESC, p.nama_proyek ASC
        ";
        $projects = $db->query($query)->fetchAll();
        
        $distribution = [];
        foreach ($projects as $p) {
            $count = (int)$p['member_count'];
            $percentage = round(($count / $totalMembers) * 100);
            
            $distribution[] = [
                'nama_proyek' => $p['nama_proyek'],
                'count' => $count,
                'percentage' => $percentage
            ];
        }
        
        return $distribution;
    } catch (PDOException $e) {
        return [];
    }
}
