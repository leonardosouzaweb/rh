<?php
require_once __DIR__ . '/includes/conexao.php';

$ordemMeses = [
    'janeiro','fevereiro','marco','abril','maio','junho',
    'julho','agosto','setembro','outubro','novembro'
];

$excel = [
    'janeiro'   => ['geral'=>0.75, 'vol'=>0.00, 'inv'=>0.75],
    'fevereiro' => ['geral'=>5.84, 'vol'=>5.11, 'inv'=>0.73],
    'marco'     => ['geral'=>2.27, 'vol'=>2.27, 'inv'=>0.00],
    'abril'     => ['geral'=>2.27, 'vol'=>1.52, 'inv'=>0.76],
    'maio'      => ['geral'=>4.41, 'vol'=>2.94, 'inv'=>1.47],
    'junho'     => ['geral'=>2.17, 'vol'=>2.17, 'inv'=>0.00],
    'julho'     => ['geral'=>2.86, 'vol'=>1.43, 'inv'=>1.43],
    'agosto'    => ['geral'=>4.20, 'vol'=>2.10, 'inv'=>2.10],
    'setembro'  => ['geral'=>2.08, 'vol'=>1.39, 'inv'=>0.69],
    'outubro'   => ['geral'=>3.55, 'vol'=>2.84, 'inv'=>0.71],
    'novembro'  => ['geral'=>2.92, 'vol'=>2.19, 'inv'=>0.73]
];

echo "<pre style='font-size:15px;background:#111;color:#0f0;padding:20px;'>";

foreach ($ordemMeses as $tabela) {

    // HC Final
    $hcFinal = (int)$pdo->query("
        SELECT COUNT(*) FROM `$tabela`
        WHERE LOWER(statusmes) = 'ativo'
    ")->fetchColumn();

    // Admissões (para HC inicial de janeiro)
    $numMes = array_search($tabela, $ordemMeses)+1;
    $inicioMes = sprintf('2025-%02d-01', $numMes);
    $fimMes = date('Y-m-t', strtotime($inicioMes));

    $admissoes = (int)$pdo->query("
        SELECT COUNT(*)
        FROM `$tabela`
        WHERE dataadmissao <> '' AND dataadmissao IS NOT NULL
          AND STR_TO_DATE(dataadmissao, '%d/%m/%Y')
              BETWEEN STR_TO_DATE('$inicioMes', '%Y-%m-%d')
              AND STR_TO_DATE('$fimMes', '%Y-%m-%d')
    ")->fetchColumn();

    // HC Inicial
    if ($tabela === 'janeiro') {
        $hcInicial = max($hcFinal - $admissoes, 1);
    } else {
        static $hcAnterior = null;
        $hcInicial = $hcAnterior ?? 1;
    }

    // Desligados
    $desligados = $pdo->query("
        SELECT nomecolaborador, desligadostipo, desligadosmotivo
        FROM `$tabela`
        WHERE LOWER(statusmes) = 'desligado'
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Voluntários
    $vol = (int)$pdo->query("
        SELECT COUNT(*) 
        FROM `$tabela`
        WHERE LOWER(statusmes) = 'desligado'
          AND (
                LOWER(desligadostipo) LIKE '%antecipado pelo empregado%'
             OR LOWER(desligadostipo) LIKE '%término de contrato%'
             OR LOWER(desligadostipo) LIKE '%estágio%'
          )
    ")->fetchColumn();

    // Involuntários
    $inv = (int)$pdo->query("
        SELECT COUNT(*) 
        FROM `$tabela`
        WHERE LOWER(statusmes) = 'desligado'
          AND (
                LOWER(desligadostipo) LIKE '%justa causa%'
             OR LOWER(desligadostipo) LIKE '%fora do contrato%'
             OR LOWER(desligadostipo) LIKE '%pedido da empresa%'
             OR LOWER(desligadostipo) LIKE '%rescisão contrat%'
             OR LOWER(desligadostipo) LIKE '%quebra do contrato%'
             OR LOWER(desligadostipo) LIKE '%morte%'
          )
    ")->fetchColumn();

    // Geral
    $total = count($desligados);

    $turnGeral = $hcInicial > 0 ? round(($total / $hcInicial) * 100, 2) : 0;
    $turnVol   = $hcInicial > 0 ? round(($vol / $hcInicial) * 100, 2) : 0;
    $turnInv   = $hcInicial > 0 ? round(($inv / $hcInicial) * 100, 2) : 0;

    $hcAnterior = $hcFinal;

    echo "==================== $tabela ====================\n\n";
    echo "HC Inicial: $hcInicial\n";
    echo "HC Final:   $hcFinal\n\n";

    echo "--- Desligados ---\n";
    print_r($desligados);

    echo "\n--- Sistema ---\n";
    echo "Geral:      {$turnGeral}%\n";
    echo "Voluntário: {$turnVol}%\n";
    echo "Invol.:     {$turnInv}%\n\n";

    echo "--- Excel ---\n";
    echo "Geral:      " . $excel[$tabela]['geral'] . "%\n";
    echo "Voluntário: " . $excel[$tabela]['vol'] . "%\n";
    echo "Invol.:     " . $excel[$tabela]['inv'] . "%\n\n";

    echo "--- Diferença ---\n";
    echo "Dif Geral: " . round($turnGeral - $excel[$tabela]['geral'], 2) . "\n";
    echo "Dif Vol:   " . round($turnVol - $excel[$tabela]['vol'], 2) . "\n";
    echo "Dif Inv:   " . round($turnInv - $excel[$tabela]['inv'], 2) . "\n\n";
}

echo "</pre>";
