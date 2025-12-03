<?php
session_start();
require_once __DIR__ . '/../includes/conexao.php';
header('Content-Type: application/json');

$logFile = __DIR__ . '/../logs/debug.log';
function logDebug($msg)
{
  global $logFile;
  $time = date('Y-m-d H:i:s');
  file_put_contents($logFile, "[$time] salvar_grafico.php: $msg\n", FILE_APPEND);
}

$input = json_decode(file_get_contents('php://input'), true);

$nome   = $input['nome'] ?? '';
$tabela = $input['tabela'] ?? '';
$eixoX  = $input['eixoX'] ?? '';
$eixoY  = $input['eixoY'] ?? '';
$tipo   = $input['tipo'] ?? '';
$usuario = $_SESSION['usuario_id'] ?? null;

if (!$nome || !$tabela || !$eixoX || !$eixoY || !$tipo) {
  http_response_code(400);
  echo json_encode(['erro' => 'Dados incompletos.']);
  exit;
}

try {
  $stmt = $pdo->prepare("
    INSERT INTO graficos_salvos (usuario_id, nome, tabela, eixo_x, eixo_y, tipo, criado_em)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
  ");
  $stmt->execute([$usuario, $nome, $tabela, $eixoX, $eixoY, $tipo]);
  echo json_encode(['sucesso' => true]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['erro' => 'Erro ao salvar gráfico.']);
}
