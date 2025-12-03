<?php
require_once __DIR__ . '/../includes/conexao.php';
session_start();
header('Content-Type: application/json');

$logFile = __DIR__ . '/../logs/debug.log';
function logDebug($msg)
{
  global $logFile;
  $time = date('Y-m-d H:i:s');
  file_put_contents($logFile, "[$time] del_grafico.php: $msg\n", FILE_APPEND);
}

if (!isset($_SESSION['usuario_id'])) {
  echo json_encode(['sucesso' => false, 'erro' => 'Usuário não autenticado']);
  exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
  echo json_encode(['sucesso' => false, 'erro' => 'ID inválido']);
  exit;
}

try {
  $stmt = $pdo->prepare("DELETE FROM graficos_salvos WHERE id = ?");
  $ok = $stmt->execute([$id]);

  if ($ok && $stmt->rowCount() > 0) {
    echo json_encode(['sucesso' => true]);
  } else {
    echo json_encode(['sucesso' => false, 'erro' => 'Gráfico não encontrado']);
  }
} catch (Exception $e) {
  echo json_encode(['sucesso' => false, 'erro' => 'Erro interno']);
}
