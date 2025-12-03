<?php
require_once __DIR__ . '/funcGraphDemografico.php';
$tabelas = getTabelaAtual($filtros);
$tabela = null;
foreach ($tabelas as $t) {
    $check = $pdo->prepare("SHOW TABLES LIKE ?");
    $check->execute([$t]);
    if ($check->fetchColumn()) {
        $tabela = $t;
        break;
    }
}

if (!$tabela) {
    $headcount = $lideranca = $liderados = $mediaIdade = $mediaTempo = $mediaPorGestor = 0;
    return;
}

$sqlBase = "FROM `$tabela` WHERE statusmes = 'Ativo'";

$headcount = (int)$pdo->query("SELECT COUNT(*) $sqlBase")->fetchColumn();
$lideranca = (int)$pdo->query("SELECT COUNT(*) $sqlBase AND LOWER(TRIM(lideranca)) = 'gestor'")->fetchColumn();
$liderados = (int)$pdo->query("SELECT COUNT(*) $sqlBase AND LOWER(TRIM(lideranca)) = 'não gestor'")->fetchColumn();
$mediaIdade = (float)$pdo->query("SELECT ROUND(AVG(CAST(idade AS DECIMAL(10,2))),0) $sqlBase")->fetchColumn();
$mediaTempo = (float)$pdo->query("SELECT ROUND(AVG(CAST(tempocasa AS DECIMAL(10,2))),1) $sqlBase")->fetchColumn();
$mediaPorGestor = $lideranca > 0 ? round($headcount / $lideranca, 1) : 0;

$fmt = new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'America/Sao_Paulo', IntlDateFormatter::GREGORIAN, 'MMMM');
$mesAtual = ucfirst($fmt->format(new DateTime()));
$anoAtual = date('Y');
$labelMesAtual = "$mesAtual de $anoAtual";

if (basename($_SERVER['PHP_SELF']) === 'headcount.php') {
    $mesSelecionado = isset($filtros['mes'][0]) ? $filtros['mes'][0] : null;

    $headcountTotal = (int)$pdo->query("SELECT COUNT(*) FROM admissoes WHERE statusmes='Ativo'")->fetchColumn();

    $admissoesMes = $mesSelecionado
        ? (int)$pdo->query("SELECT COUNT(*) FROM admissoes WHERE mes = '$mesSelecionado'")->fetchColumn()
        : 0;

    $desligamentosMes = $mesSelecionado
        ? (int)$pdo->query("SELECT COUNT(*) FROM desligamentos WHERE mesdesligamento = '$mesSelecionado'")->fetchColumn()
        : 0;

    $turnoverMes = $headcountTotal > 0
        ? round(($desligamentosMes / $headcountTotal) * 100, 2)
        : 0;
}
