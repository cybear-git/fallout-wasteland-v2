<?php
$pdo = new PDO("mysql:host=localhost;dbname=fallout_wastelands_v2;charset=utf8mb4", "root", "12345678");
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) { echo $row[0] . "\n"; }
