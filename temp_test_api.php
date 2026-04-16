<?php
$ch = curl_init('http://localhost:1317/api/move.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['dx' => 1, 'dy' => 0]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, 'fw_ssid=test');
$result = curl_exec($ch);
curl_close($ch);
echo $result;
