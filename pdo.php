<?php
// pdo.php
$host = 'localhost';
$dbName = 'DBNAME';
$user = 'DBUSERNAME';
$pass = 'DBPASSWORD';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbName};charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("DB 연결 실패: " . $e->getMessage());
}

