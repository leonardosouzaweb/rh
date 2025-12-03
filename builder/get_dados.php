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
$campoCond = $_GET['campoCond'] ?? '';
$tipoCond = $_GET['tipoCond'] ?? '';
$valorCond = $_GET['valorCond'] ?? '';

if (!$tabela || !$x || !$y) {
  echo json_encode([]);
  exit;
}

try {
  $amostra = $pdo->query("SELECT `$y` FROM `$tabela` WHERE `$y` IS NOT NULL AND `$y` <> '' LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
  $isNumeric = true;
  foreach ($amostra as $val) {
    if (!is_numeric(str_replace(',', '.', $val))) {
      $isNumeric = false;
      break;
    }
  }

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

  $whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

  if ($isNumeric) {
    $sql = "SELECT `$x` AS x,
                 AVG(CAST(
                   REPLACE(
                     REPLACE(
                       REPLACE(
                         REPLACE(`$y`, 'R$', ''),
                       ',', '.'),
                     ' ', ''),
                   '.', '') AS DECIMAL(10,2))
                 ) AS y
          FROM `$tabela`
          $whereSQL
          GROUP BY `$x`
          ORDER BY `$x`";
  } else {
    $sql = "SELECT `$x` AS x, COUNT(`$y`) AS y
          FROM `$tabela`
          $whereSQL
          GROUP BY `$x`
          ORDER BY `$x`";
  }

  $stmt = $pdo->prepare($sql);
  foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
  }
  $stmt->execute();
  $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode($dados);
} catch (Exception $e) {
  echo json_encode([]);
}
