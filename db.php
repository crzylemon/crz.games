<?php
$host = 'localhost';
$dbname = 'crz_network';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo_videos = new PDO("mysql:host=$host;dbname=crz_videos;charset=utf8mb4", $username, $password);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>