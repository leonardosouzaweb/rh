<?php
require_once __DIR__ . '/../includes/conexao.php';
header('Content-Type: application/json');

$id = $_GET['id'] ?? '';
if (!$id) {
  echo json_encode(['erro' => 'ID inválido']);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT id, nome, tabela, eixo_x AS eixoX, eixo_y AS eixoY, tipo, criado_em FROM graficos_salvos WHERE id = ?");
  $stmt->execute([$id]);
  $grafico = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$grafico) {
    echo json_encode(['erro' => 'Gráfico não encontrado']);
    exit;
  }

  echo json_encode($grafico);
} catch (Exception $e) {
  echo json_encode(['erro' => $e->getMessage()]);
}
