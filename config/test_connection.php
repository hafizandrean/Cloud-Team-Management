<?php
/**
 * Test Connection and Query Seeded Data
 * Run this from the root directory: php config/test_connection.php
 */

require_once __DIR__ . '/database.php';

echo "============================================\n";
echo "Database Connection & Schema Verification\n";
echo "============================================\n\n";

try {
    // 1. Get PDO Connection
    $db = Database::getConnection();
    echo "✔ PDO Connection: SUCCESS\n\n";

    // 2. Query Users Table
    echo "--- Users Table ---\n";
    $stmt = $db->query("SELECT id, username, email, role, created_at FROM users");
    $users = $stmt->fetchAll();
    foreach ($users as $user) {
        echo sprintf("ID: %d | User: %-10s | Email: %-25s | Role: %-8s | Created: %s\n", 
            $user['id'], $user['username'], $user['email'], $user['role'], $user['created_at']);
    }
    echo "\n";

    // 3. Query Proyek Table
    echo "--- Proyek Table ---\n";
    $stmt = $db->query("SELECT id, nama_proyek, deadline, status FROM proyek");
    $proyekList = $stmt->fetchAll();
    foreach ($proyekList as $p) {
        echo sprintf("ID: %d | Project: %-25s | Deadline: %s | Status: %s\n", 
            $p['id'], $p['nama_proyek'], $p['deadline'], $p['status']);
    }
    echo "\n";

    // 4. Query Anggota Table
    echo "--- Anggota Table ---\n";
    $stmt = $db->query("
        SELECT a.id, a.nama, a.nim, a.foto, u.username, GROUP_CONCAT(p.nama_proyek SEPARATOR ', ') AS proyek_assigned
        FROM anggota a
        LEFT JOIN users u ON a.id_user = u.id
        LEFT JOIN anggota_proyek ap ON a.id = ap.anggota_id
        LEFT JOIN proyek p ON ap.proyek_id = p.id
        GROUP BY a.id
    ");
    $anggotaList = $stmt->fetchAll();
    foreach ($anggotaList as $a) {
        echo sprintf("ID: %d | Name: %-18s | NIM: %-11s | Photo: %-10s | User: %-8s | Projects: %s\n", 
            $a['id'], $a['nama'], $a['nim'], $a['foto'] ?? 'None', $a['username'] ?? 'None', $a['proyek_assigned'] ?? 'None');
    }
    echo "\n";
    echo "============================================\n";
    echo "✔ Verification completed successfully!\n";
    echo "============================================\n";

} catch (PDOException $e) {
    echo "❌ Verification FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
