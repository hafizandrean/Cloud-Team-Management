<?php
/**
 * Cloud Team Management - Activity Log Helper
 * Provides global logging functionality and badge styling for audit trail.
 */

if (!function_exists('writeLog')) {
    /**
     * Write an activity log to the database.
     *
     * @param PDO $db The database connection instance
     * @param int $userId The ID of the user performing the activity
     * @param string $type The activity type (e.g., LOGIN, LOGOUT, CREATE_MEMBER, etc.)
     * @param string $description Detailed description of the activity
     * @return bool True on success, false on failure
     */
    function writeLog(PDO $db, int $userId, string $type, string $description) {
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, activity_type, description)
                VALUES (?, ?, ?)
            ");
            return $stmt->execute([$userId, $type, $description]);
        } catch (PDOException $e) {
            // Silently log the database exception to prevent application crash on log failure
            error_log("Failed to write activity log: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getActivityBadge')) {
    /**
     * Get HTML styled badge for activity type with glassmorphism style.
     *
     * @param string $type The activity type
     * @return string HTML span badge
     */
    function getActivityBadge(string $type) {
        $typeUpper = strtoupper($type);
        
        if (strpos($typeUpper, 'LOGIN') !== false) {
            // Green (Success)
            return '<span class="badge" style="background-color: rgba(220, 252, 231, 0.8) !important; color: #15803d !important; border: 1px solid rgba(34, 197, 94, 0.2) !important;">' . htmlspecialchars($type) . '</span>';
        } elseif (strpos($typeUpper, 'LOGOUT') !== false) {
            // Gray (Secondary)
            return '<span class="badge" style="background-color: rgba(241, 245, 249, 0.8) !important; color: #64748b !important; border: 1px solid rgba(148, 163, 184, 0.2) !important;">' . htmlspecialchars($type) . '</span>';
        } elseif (strpos($typeUpper, 'CREATE') !== false) {
            // Blue (Primary)
            return '<span class="badge" style="background-color: rgba(219, 234, 254, 0.8) !important; color: #1d4ed8 !important; border: 1px solid rgba(59, 130, 246, 0.2) !important;">' . htmlspecialchars($type) . '</span>';
        } elseif (strpos($typeUpper, 'UPDATE') !== false) {
            // Yellow (Warning)
            return '<span class="badge" style="background-color: rgba(254, 243, 199, 0.8) !important; color: #b45309 !important; border: 1px solid rgba(245, 158, 11, 0.2) !important;">' . htmlspecialchars($type) . '</span>';
        } elseif (strpos($typeUpper, 'DELETE') !== false) {
            // Red (Danger)
            return '<span class="badge" style="background-color: rgba(254, 226, 230, 0.8) !important; color: #b91c1c !important; border: 1px solid rgba(239, 68, 68, 0.2) !important;">' . htmlspecialchars($type) . '</span>';
        } elseif (strpos($typeUpper, 'ASSIGNMENT') !== false) {
            // Purple (Info/Purple)
            return '<span class="badge" style="background-color: rgba(243, 232, 255, 0.8) !important; color: #6d28d9 !important; border: 1px solid rgba(139, 92, 246, 0.2) !important;">' . htmlspecialchars($type) . '</span>';
        } else {
            // Default Gray
            return '<span class="badge bg-secondary">' . htmlspecialchars($type) . '</span>';
        }
    }
}
