<?php
$isLocal = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) || strpos($_SERVER['HTTP_HOST'], '.test') !== false;

if ($isLocal) {
    $host = 'localhost';
    $dbname = 'artesanal';
    $user = 'root';
    $pass = 'root';
} else {
    $host = 'localhost';
    $dbname = 'leona497_rh';
    $user = 'leona497_rh';
    $pass = 'Leo2248228@';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar: " . $e->getMessage());
}

$logDir = $_SERVER['DOCUMENT_ROOT'] . '/rh/logs';
if (!is_dir($logDir)) mkdir($logDir, 0777, true);
ini_set('error_log', $logDir . '/debug.log');

setlocale(LC_TIME, 'pt_BR.utf8', 'pt_BR', 'Portuguese_Brazil');
date_default_timezone_set('America/Sao_Paulo');
