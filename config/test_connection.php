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
    $stmt = $db->query("SELECT id, nama_proyek, tanggal_mulai, tanggal_selesai, status FROM proyek");
    $proyekList = $stmt->fetchAll();
    foreach ($proyekList as $p) {
        echo sprintf("ID: %d | Project: %-25s | Range: %s to %s | Status: %s\n", 
            $p['id'], $p['nama_proyek'], $p['tanggal_mulai'], $p['tanggal_selesai'], $p['status']);
    }
    echo "\n";

    // 4. Query Anggota Table
    echo "--- Anggota Table ---\n";
    $stmt = $db->query("
        SELECT a.id, a.nama, a.nip_nim, a.jabatan, a.foto, p.nama_proyek, u.username 
        FROM anggota a
        LEFT JOIN proyek p ON a.id_proyek = p.id
        LEFT JOIN users u ON a.id_user = u.id
    ");
    $anggotaList = $stmt->fetchAll();
    foreach ($anggotaList as $a) {
        echo sprintf("ID: %d | Name: %-18s | NIP/NIM: %-11s | Job: %-15s | Photo: %-10s | Project: %-22s | User: %s\n", 
            $a['id'], $a['nama'], $a['nip_nim'], $a['jabatan'], $a['foto'] ?? 'None', $a['nama_proyek'] ?? 'None', $a['username'] ?? 'None');
    }
    echo "\n";
    echo "============================================\n";
    echo "✔ Verification completed successfully!\n";
    echo "============================================\n";

} catch (PDOException $e) {
    echo "❌ Verification FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
