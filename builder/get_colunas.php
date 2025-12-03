<?php
require_once __DIR__ . '/../includes/conexao.php';
header('Content-Type: application/json');

$tabela = $_GET['tabela'] ?? '';
if (!$tabela) {
  echo json_encode([]);
  exit;
}

$stmt = $pdo->prepare("SHOW COLUMNS FROM `$tabela`");
$stmt->execute();
$colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);

$resultado = [];
foreach ($colunas as $col) {
  try {
    $check = $pdo->query("SELECT COUNT(DISTINCT `$col`) AS qtd FROM `$tabela`")->fetch(PDO::FETCH_ASSOC);
    $temDados = ($check['qtd'] ?? 0) > 0;
    $resultado[] = [
      'nome' => $col,
      'temDados' => $temDados
    ];
  } catch (Exception $e) {
    $resultado[] = ['nome' => $col, 'temDados' => false];
  }
}

echo json_encode($resultado);
