<?php
/**
 * Cloud Team Management - Project Helpers
 */

/**
 * Maps database ENUM status values to English labels.
 * 
 * @param string $status
 * @return string Mapped label.
 */
function getProjectStatusLabel($status) {
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
            return 'Unknown';
    }
}

/**
 * Returns HTML badge elements for the project status.
 * Detects if a project is overdue (deadline passed and status is not completed).
 * 
 * @param string $status
 * @param string $deadline Date string (Y-m-d).
 * @return string HTML span elements.
 */
function getProjectStatusBadge($status, $deadline) {
    $statusLabel = getProjectStatusLabel($status);
    $badgeClass = 'badge-planning';
    
    switch ($status) {
        case 'berjalan':
            $badgeClass = 'badge-progress';
            break;
        case 'selesai':
            $badgeClass = 'badge-completed';
            break;
        case 'tertunda':
            $badgeClass = 'badge-suspended';
            break;
    }
    
    $html = '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($statusLabel) . '</span>';
    
    // Check if overdue: deadline passed and status is NOT completed ('selesai')
    $today = date('Y-m-d');
    if ($status !== 'selesai' && strtotime($deadline) < strtotime($today)) {
        $html = '<span class="badge bg-danger shadow-sm me-1" style="border: 1px solid rgba(239, 68, 68, 0.15) !important;">Overdue</span> ' . $html;
    }
    
    return $html;
}
