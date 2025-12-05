<?php
require_once __DIR__ . '/../includes/conexao.php';
header('Content-Type: application/json');

$logFile = __DIR__ . '/../logs/debug.log';
function logDebug($msg)
{
  global $logFile;
  $time = date('Y-m-d H:i:s');
  file_put_contents($logFile, "[$time] get_dados.php: $msg\n", FILE_APPEND);
}

$tabela = $_GET['tabela'] ?? '';
$x = $_GET['x'] ?? '';
$y = $_GET['y'] ?? '';
$dataset = $_GET['dataset'] ?? '';

$campoCond = $_GET['campoCond'] ?? '';
$tipoCond  = $_GET['tipoCond'] ?? '';
$valorCond = $_GET['valorCond'] ?? '';

if (!$tabela || !$x || !$y || !$dataset) {
  echo json_encode(["erro" => "Parâmetros insuficientes."]);
  exit;
}

try {
  // Detectar se Y é numérico
  $amostra = $pdo->query("
    SELECT `$y` FROM `$tabela` 
    WHERE `$y` IS NOT NULL AND `$y` <> '' 
    LIMIT 10
  ")->fetchAll(PDO::FETCH_COLUMN);

  $isNumeric = true;
  foreach ($amostra as $val) {
    if (!is_numeric(str_replace(',', '.', $val))) {
      $isNumeric = false;
      break;
    }
  }

  // ============================
  // MONTAGEM DE FILTROS
  // ============================
  $where = [];
  $params = [];

  if ($campoCond && $tipoCond && $valorCond) {
    switch ($tipoCond) {
      case 'contém':
        $where[] = "`$campoCond` LIKE :valor";
        $params[':valor'] = "%$valorCond%";
        break;

      case 'igual':
        $where[] = "`$campoCond` = :valor";
        $params[':valor'] = $valorCond;
        break;

      case 'maior':
        $where[] = "CAST(`$campoCond` AS DECIMAL(10,2)) > :valor";
        $params[':valor'] = $valorCond;
        break;

      case 'menor':
        $where[] = "CAST(`$campoCond` AS DECIMAL(10,2)) < :valor";
        $params[':valor'] = $valorCond;
        break;

      case 'diferente':
        $where[] = "`$campoCond` <> :valor";
        $params[':valor'] = $valorCond;
        break;
    }
  }

  $whereSQL = $where ? ("WHERE " . implode(" AND ", $where)) : "";

  // ============================
  // QUERY PRINCIPAL
  // ============================

  // Se é numérico -> média dos valores
  if ($isNumeric) {
    $yExpr = "AVG(CAST(REPLACE(REPLACE(REPLACE(REPLACE(`$y`, 'R$', ''), ' ', ''), ',', '.'), '.', '') AS DECIMAL(10,2)))";
  } else {
    // Se não numérico -> contagem por dataset
    $yExpr = "COUNT(`$y`)";
  }

  $sql = "
    SELECT 
      `$x` AS eixoX,
      `$dataset` AS ds,
      $yExpr AS valor
    FROM `$tabela`
    $whereSQL
    GROUP BY `$x`, `$dataset`
    ORDER BY `$x`
  ";

  $stmt = $pdo->prepare($sql);
  foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
  }
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // ============================
  // GERAR labels e datasets
  // ============================
  $labels = array_values(array_unique(array_column($rows, 'eixoX')));
  $datasetNomes = array_values(array_unique(array_column($rows, 'ds')));

  $datasets = [];

  foreach ($datasetNomes as $dsNome) {
    $data = [];
    foreach ($labels as $label) {
      $match = array_filter($rows, fn($r) => $r['eixoX'] == $label && $r['ds'] == $dsNome);
      $value = $match ? floatval(array_values($match)[0]['valor']) : 0;
      $data[] = $value;
    }

    $datasets[] = [
      "label" => $dsNome ?: "Sem valor",
      "data"  => $data
    ];
  }

  echo json_encode([
    "labels"   => $labels,
    "datasets" => $datasets
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  logDebug("ERRO: " . $e->getMessage());
  echo json_encode(["erro" => "Falha interna"]);
}
