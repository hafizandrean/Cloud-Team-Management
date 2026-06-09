<?php

require_once 'config/database.php';

$db = Database::getConnection();

$stmt = $db->query("SELECT * FROM anggota");

echo "<h1>Daftar Anggota</h1>";

while ($row = $stmt->fetch()) {
    echo $row['nama'] . "<br>";
}