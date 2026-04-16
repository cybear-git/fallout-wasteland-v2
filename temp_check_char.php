<?php
$pdo = new PDO("mysql:host=localhost;dbname=fallout_wastelands_v2;charset=utf8mb4", "root", "12345678");

echo "=== Characters ===\n";
$stmt = $pdo->query("DESCRIBE characters");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\n=== Sample character ===\n";
$stmt = $pdo->query("SELECT * FROM characters LIMIT 1");
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
