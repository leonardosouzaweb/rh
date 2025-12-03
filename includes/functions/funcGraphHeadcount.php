<?php

require_once __DIR__ . '../../conexao.php';

function parseMesBanco(string $mesRaw): ?array
{
    // Formato esperado: MM/YYYY
    if (!preg_match('/^(\d{2})\/(\d{4})$/', trim($mesRaw), $m)) {
        return null;
    }

    $mm = $m[1];
    $yyyy = $m[2];

    $nomes = [
        '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março',
        '04' => 'Abril', '05' => 'Maio', '06' => 'Junho',
        '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro',
        '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
    ];

    $nomeMes = $nomes[$mm] ?? null;
    if (!$nomeMes) return null;

    return [
        'key'   => $yyyy . $mm,            // 202501
        'mes'   => $nomeMes,               // Janeiro
        'ano'   => $yyyy,                  // 2025
        'label' => "$nomeMes"        // Janeiro/2025
    ];
}


function normalizarMes($valor)
{
    $valor = strtolower(trim($valor));
    $mapa = [
        'jan.' => 'Janeiro',
        'janeiro' => 'Janeiro',
        'fev.' => 'Fevereiro',
        'fevereiro' => 'Fevereiro',
        'mar.' => 'Março',
        'marco' => 'Março',
        'março' => 'Março',
        'abr.' => 'Abril',
        'abril' => 'Abril',
        'mai.' => 'Maio',
        'maio' => 'Maio',
        'jun.' => 'Junho',
        'junho' => 'Junho',
        'jul.' => 'Julho',
        'julho' => 'Julho',
        'ago.' => 'Agosto',
        'agosto' => 'Agosto',
        'set.' => 'Setembro',
        'setembro' => 'Setembro',
        'out.' => 'Outubro',
        'outubro' => 'Outubro',
        'nov.' => 'Novembro',
        'novembro' => 'Novembro',
        'dez.' => 'Dezembro',
        'dezembro' => 'Dezembro'
    ];
    return $mapa[$valor] ?? ucfirst($valor);
}


function getDistinct(PDO $pdo, string $campo, array $filtros = []): array
{
    $valores = [];
    $stmt = $pdo->query("SHOW TABLES");

    while ($t = $stmt->fetchColumn()) {
        if (preg_match('/^(janeiro|fevereiro|marco|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)$/i', $t)) {
            $q = "SELECT DISTINCT `$campo` FROM `$t` WHERE `$campo` IS NOT NULL AND `$campo` <> ''";
            foreach ($pdo->query($q)->fetchAll(PDO::FETCH_COLUMN) as $valor) {
                $valores[] = trim($valor);
            }
        }
    }

    $valores = array_unique($valores);
    sort($valores, SORT_NATURAL | SORT_FLAG_CASE);
    return $valores;
}

function gerarDadosHeadcount(PDO $pdo, string $campo, array $filtros, string $tipoCalculo = 'quantidade'): array
{
    if ($campo === 'headcount_evolucao') {
        return calcularEvolucaoHeadcount($pdo, $filtros);
    } elseif ($campo === 'headcount_lideranca') {
        return calcularHeadcountLideranca($pdo, $filtros);
    } elseif ($campo === 'headcount_admissoes') {
        return calcularAdmissoesHeadcount($pdo, $filtros);
    } elseif ($campo === 'headcount_desligamentos') {
        return calcularDesligamentosHeadcount($pdo, $filtros);
    } elseif ($campo === 'headcount_turnover') {
        return calcularTurnoverHeadcount($pdo, $filtros);
    } elseif ($campo === 'headcount_turnover_acumulado') {
        return calcularTurnoverAcumuladoHeadcount($pdo, $filtros);
    } elseif ($campo === 'headcount_turnover_voluntario') {
        return calcularTurnoverVoluntarioHeadcount($pdo, $filtros);
    } elseif ($campo === 'headcount_turnover_involuntario') {
        return calcularTurnoverInvoluntarioHeadcount($pdo, $filtros);
    }
    return ['labels' => [], 'datasets' => []];
}

function calcularEvolucaoHeadcount(PDO $pdo, array $filtros): array
{
    $labels = [];
    $vinculos = [];
    $dadosPorMes = [];

    $ordemMeses = [
        'janeiro',
        'fevereiro',
        'marco',
        'abril',
        'maio',
        'junho',
        'julho',
        'agosto',
        'setembro',
        'outubro',
        'novembro',
        'dezembro'
    ];

    $stmt = $pdo->query("SHOW TABLES");
    $tabelas = [];
    while ($t = $stmt->fetchColumn()) {
        if (in_array(strtolower($t), $ordemMeses)) $tabelas[] = strtolower($t);
    }
    usort($tabelas, fn($a, $b) => array_search($a, $ordemMeses) <=> array_search($b, $ordemMeses));

    foreach ($tabelas as $tabela) {
        $cols = $pdo->query("SHOW COLUMNS FROM `$tabela`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('statusmes', $cols) || !in_array('vinculo', $cols)) continue;

        $q = "
            SELECT vinculo, COUNT(*) AS total
            FROM `$tabela`
            WHERE LOWER(statusmes) = 'ativo'
            GROUP BY vinculo
        ";
        $res = $pdo->query($q)->fetchAll(PDO::FETCH_ASSOC);

        $labels[] = normalizarMes($tabela);
        foreach ($res as $r) {
            $vinc = $r['vinculo'] ?: 'Não informado';
            $vinculos[$vinc] = true;
            $dadosPorMes[$vinc][$tabela] = (int)$r['total'];
        }
    }

    $vinculos = array_keys($vinculos);
    sort($vinculos, SORT_NATURAL | SORT_FLAG_CASE);

    $datasets = [];
    foreach ($vinculos as $vinculo) {
        $valores = [];
        foreach ($tabelas as $tabelaMes) {
            $valores[] = $dadosPorMes[$vinculo][$tabelaMes] ?? 0;
        }
        $datasets[] = [
            'label' => $vinculo,
            'data' => $valores
        ];
    }

    return [
        'labels'   => $labels,
        'datasets' => $datasets
    ];
}

function calcularHeadcountLideranca(PDO $pdo, array $filtros): array
{
    $labels = [];
    $dados = ['Gestor' => [], 'Não Gestor' => []];

    $ordemMeses = [
        'janeiro',
        'fevereiro',
        'marco',
        'abril',
        'maio',
        'junho',
        'julho',
        'agosto',
        'setembro',
        'outubro',
        'novembro',
        'dezembro'
    ];

    $stmt = $pdo->query("SHOW TABLES");
    $tabelas = [];
    while ($t = $stmt->fetchColumn()) {
        if (in_array(strtolower($t), $ordemMeses)) $tabelas[] = strtolower($t);
    }
    usort($tabelas, fn($a, $b) => array_search($a, $ordemMeses) <=> array_search($b, $ordemMeses));

    foreach ($tabelas as $tabela) {
        $cols = $pdo->query("SHOW COLUMNS FROM `$tabela`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('lideranca', $cols) || !in_array('statusmes', $cols)) continue;

        $sql = "
            SELECT 
                CASE 
                    WHEN LOWER(TRIM(lideranca)) = 'gestor' THEN 'Gestor'
                    WHEN LOWER(TRIM(lideranca)) = 'não gestor' THEN 'Não Gestor'
                    ELSE 'Não Gestor'
                END AS categoria,
                COUNT(*) AS total
            FROM `$tabela`
            WHERE LOWER(statusmes) = 'ativo'
            GROUP BY categoria
        ";
        $res = $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

        $labels[] = normalizarMes($tabela);
        $dados['Gestor'][] = (int)($res['Gestor'] ?? 0);
        $dados['Não Gestor'][] = (int)($res['Não Gestor'] ?? 0);
    }

    foreach (['Gestor', 'Não Gestor'] as $tipo) {
        $dados[$tipo] = array_pad($dados[$tipo], count($labels), 0);
    }

    return [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Gestor',
                'data' => $dados['Gestor'],
                'borderColor' => '#1374a5',
                'backgroundColor' => '#1374a5',
                'tension' => 0.3,
                'fill' => false,
                'yAxisID' => 'y1'
            ],
            [
                'label' => 'Não Gestor',
                'data' => $dados['Não Gestor'],
                'borderColor' => '#ff7900',
                'backgroundColor' => '#ff7900',
                'tension' => 0.3,
                'fill' => false,
                'yAxisID' => 'y2'
            ]
        ]
    ];
}

function calcularAdmissoesHeadcount(PDO $pdo, array $filtros): array
{
    $labels = [];
    $vinculos = [];
    $dadosPorMes = [];

    $res = $pdo->query("
        SELECT mes, vinculo, COUNT(*) AS total
        FROM admissoes
        GROUP BY mes, vinculo
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($res as $r) {

        $p = parseMesBanco($r['mes']);
        if (!$p) continue;

        // Apenas ano atual (ajuste conforme quiser)
        if ((int)$p['ano'] < date('Y')) continue;

        $labels[$p['key']] = $p['label'];

        $v = $r['vinculo'] ?: 'Não informado';
        $vinculos[$v] = true;

        $dadosPorMes[$v][$p['key']] = (int)$r['total'];
    }

    ksort($labels); // ordenação perfeita ano+mes
    $keysOrdenados = array_keys($labels);
    $labelsOrdenados = array_values($labels);

    $vinculos = array_keys($vinculos);
    sort($vinculos);

    $datasets = [];
    foreach ($vinculos as $v) {
        $valores = [];
        foreach ($keysOrdenados as $key) {
            $valores[] = $dadosPorMes[$v][$key] ?? 0;
        }
        $datasets[] = ['label' => $v, 'data' => $valores];
    }

    return [
        'labels' => $labelsOrdenados,
        'datasets' => $datasets
    ];
}

function calcularDesligamentosHeadcount(PDO $pdo, array $filtros): array
{
    $labels = [];
    $vinculos = [];
    $dadosPorMes = [];

    $res = $pdo->query("
        SELECT mes, vinculo, COUNT(*) AS total
        FROM desligamentos
        GROUP BY mes, vinculo
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($res as $r) {

        $p = parseMesBanco($r['mes']);
        if (!$p) continue;

        if ((int)$p['ano'] < date('Y')) continue;

        $labels[$p['key']] = $p['label'];

        $v = $r['vinculo'] ?: 'Não informado';
        $vinculos[$v] = true;

        $dadosPorMes[$v][$p['key']] = (int)$r['total'];
    }

    ksort($labels);
    $keysOrdenados = array_keys($labels);
    $labelsOrdenados = array_values($labels);

    $vinculos = array_keys($vinculos);
    sort($vinculos);

    $datasets = [];
    foreach ($vinculos as $v) {
        $valores = [];
        foreach ($keysOrdenados as $key) {
            $valores[] = $dadosPorMes[$v][$key] ?? 0;
        }
        $datasets[] = ['label' => $v, 'data' => $valores];
    }

    return [
        'labels' => $labelsOrdenados,
        'datasets' => $datasets
    ];
}







function calcularTurnoverHeadcount(PDO $pdo): array
{
    $labels = [];
    $valores = [];

    $ordemMeses = [
        'janeiro',
        'fevereiro',
        'marco',
        'abril',
        'maio',
        'junho',
        'julho',
        'agosto',
        'setembro',
        'outubro',
        'novembro',
        'dezembro'
    ];

    $tabelas = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($t = $stmt->fetchColumn()) {
        if (in_array(strtolower($t), $ordemMeses)) {
            $tabelas[] = strtolower($t);
        }
    }
    usort($tabelas, fn($a, $b) => array_search($a, $ordemMeses) <=> array_search($b, $ordemMeses));

    $hcAnterior = null;

    foreach ($tabelas as $tabela) {
        $sqlHcFinal = "SELECT COUNT(*) FROM `$tabela` WHERE LOWER(statusmes) = 'ativo'";
        $hcFinal = (int)$pdo->query($sqlHcFinal)->fetchColumn();

        $sqlDesl = "SELECT COUNT(*) FROM `$tabela` WHERE LOWER(statusmes) = 'desligado'";
        $desligamentos = (int)$pdo->query($sqlDesl)->fetchColumn();

        if ($hcAnterior === null) {
            $sqlAdmissoes = "
                SELECT COUNT(*) FROM `$tabela`
                WHERE (
                    (STR_TO_DATE(dataadmissao, '%d/%m/%Y') BETWEEN '2025-01-01' AND '2025-01-31')
                    OR
                    (STR_TO_DATE(dataadmissao, '%c/%e/%Y') BETWEEN '2025-01-01' AND '2025-01-31')
                )
            ";
            $admissoes = (int)$pdo->query($sqlAdmissoes)->fetchColumn();

            $hcInicial = max($hcFinal - $admissoes, 1);
        } else {
            $hcInicial = $hcAnterior;
        }

        $turnover = $hcInicial > 0 ? round(($desligamentos / $hcInicial) * 100, 2) : 0;
        $labels[] = ucfirst($tabela);
        $valores[] = $turnover;
        $hcAnterior = $hcFinal;
    }

    return [
        'labels' => $labels,
        'datasets' => [[
            'label' => 'Turnover Geral (%)',
            'data' => $valores,
            'borderColor' => '#0b6fa4',
            'backgroundColor' => '#0b6fa4',
            'fill' => false,
            'tension' => 0.3
        ]]
    ];
}

function calcularTurnoverAcumuladoHeadcount(PDO $pdo): array
{
    $ordemMeses = [
        'janeiro',
        'fevereiro',
        'marco',
        'abril',
        'maio',
        'junho',
        'julho',
        'agosto',
        'setembro',
        'outubro',
        'novembro',
        'dezembro'
    ];

    $tabelas = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($t = $stmt->fetchColumn()) {
        if (in_array(strtolower($t), $ordemMeses)) {
            $tabelas[] = strtolower($t);
        }
    }
    usort($tabelas, fn($a, $b) => array_search($a, $ordemMeses) <=> array_search($b, $ordemMeses));

    $hcAnterior = null;
    $totalDesligamentos = 0;
    $totalHCInicial = 0;

    foreach ($tabelas as $tabela) {
        $sqlHcFinal = "SELECT COUNT(*) FROM `$tabela` WHERE LOWER(statusmes) = 'ativo'";
        $hcFinal = (int)$pdo->query($sqlHcFinal)->fetchColumn();

        $sqlDesl = "SELECT COUNT(*) FROM `$tabela` WHERE LOWER(statusmes) = 'desligado'";
        $desligamentos = (int)$pdo->query($sqlDesl)->fetchColumn();

        if ($hcAnterior === null) {
            $sqlAdmissoes = "
                SELECT COUNT(*) FROM `$tabela`
                WHERE (
                    (STR_TO_DATE(dataadmissao, '%d/%m/%Y') BETWEEN '2025-01-01' AND '2025-01-31')
                    OR
                    (STR_TO_DATE(dataadmissao, '%c/%e/%Y') BETWEEN '2025-01-01' AND '2025-01-31')
                )
            ";
            $admissoes = (int)$pdo->query($sqlAdmissoes)->fetchColumn();
            $hcInicial = max($hcFinal - $admissoes, 1);
        } else {
            $hcInicial = $hcAnterior;
        }

        $totalHCInicial += $hcInicial;
        $totalDesligamentos += $desligamentos;

        $hcAnterior = $hcFinal;
    }

    $turnoverAcumulado = $totalHCInicial > 0
        ? round(($totalDesligamentos / $totalHCInicial) * 100, 2)
        : 0;

    return [
        'labels' => ['2025'],
        'datasets' => [[
            'label' => 'Turnover Acumulado (%)',
            'data' => [$turnoverAcumulado],
            'backgroundColor' => '#ff7f0e',
            'borderColor' => '#ff7f0e',
            'borderWidth' => 1
        ]]
    ];
}

function calcularTurnoverVoluntarioHeadcount(PDO $pdo): array
{
    $labels = [];
    $valores = [];

    $ordemMeses = [
        'janeiro',
        'fevereiro',
        'marco',
        'abril',
        'maio',
        'junho',
        'julho',
        'agosto',
        'setembro',
        'outubro',
        'novembro',
        'dezembro'
    ];

    $tabelas = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($t = $stmt->fetchColumn()) {
        if (in_array(strtolower($t), $ordemMeses)) $tabelas[] = strtolower($t);
    }
    usort($tabelas, fn($a, $b) => array_search($a, $ordemMeses) <=> array_search($b, $ordemMeses));

    $hcAnterior = null;

    foreach ($tabelas as $tabela) {
        $numMes = array_search($tabela, $ordemMeses) + 1;
        $inicioMes = sprintf('2025-%02d-01', $numMes);
        $fimMes = date('Y-m-t', strtotime($inicioMes));

        $hcFinal = (int)$pdo->query("
            SELECT COUNT(*) FROM `$tabela`
            WHERE LOWER(statusmes) = 'ativo'
        ")->fetchColumn();

        $deslVol = (int)$pdo->query("
            SELECT COUNT(*) FROM `$tabela`
            WHERE LOWER(statusmes) = 'desligado'
              AND REPLACE(REPLACE(LOWER(turnover),'á','a'),'ã','a') LIKE '%volunt%'
        ")->fetchColumn();

        $admissoes = (int)$pdo->query("
            SELECT COUNT(*) FROM `$tabela`
            WHERE dataadmissao IS NOT NULL AND dataadmissao <> ''
              AND STR_TO_DATE(dataadmissao, '%d/%m/%Y')
                  BETWEEN STR_TO_DATE('$inicioMes', '%Y-%m-%d')
                  AND STR_TO_DATE('$fimMes', '%Y-%m-%d')
        ")->fetchColumn();

        if (strtolower($tabela) === 'janeiro') {
            $hcInicial = max($hcFinal - $admissoes, 1);
        } else {
            $hcInicial = $hcAnterior ?? 1;
        }

        $turnVol = $hcInicial > 0 ? round(($deslVol / $hcInicial) * 100, 2) : 0;

        $labels[] = ucfirst($tabela);
        $valores[] = $turnVol;
        $hcAnterior = $hcFinal;
    }

    return [
        'labels' => $labels,
        'datasets' => [[
            'label' => 'Turnover Voluntário (%)',
            'data' => $valores,
            'borderColor' => '#22c55e',
            'backgroundColor' => 'rgba(34,197,94,0.25)',
            'fill' => false,
            'tension' => 0.3
        ]]
    ];
}



function calcularTurnoverInvoluntarioHeadcount(PDO $pdo): array
{
    $labels = [];
    $valores = [];

    $ordemMeses = [
        'janeiro',
        'fevereiro',
        'marco',
        'abril',
        'maio',
        'junho',
        'julho',
        'agosto',
        'setembro',
        'outubro',
        'novembro',
        'dezembro'
    ];

    $tabelas = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($t = $stmt->fetchColumn()) {
        if (in_array(strtolower($t), $ordemMeses)) $tabelas[] = strtolower($t);
    }
    usort($tabelas, fn($a, $b) => array_search($a, $ordemMeses) <=> array_search($b, $ordemMeses));

    $hcAnterior = null;

    foreach ($tabelas as $tabela) {
        $numMes = array_search($tabela, $ordemMeses) + 1;
        $inicioMes = sprintf('2025-%02d-01', $numMes);
        $fimMes = date('Y-m-t', strtotime($inicioMes));

        $hcFinal = (int)$pdo->query("
            SELECT COUNT(*) FROM `$tabela`
            WHERE LOWER(statusmes) = 'ativo'
        ")->fetchColumn();

        $deslInv = (int)$pdo->query("
            SELECT COUNT(*) FROM `$tabela`
            WHERE LOWER(statusmes) = 'desligado'
              AND REPLACE(REPLACE(LOWER(turnover),'á','a'),'ã','a') LIKE '%involunt%'
        ")->fetchColumn();

        $admissoes = (int)$pdo->query("
            SELECT COUNT(*) FROM `$tabela`
            WHERE dataadmissao IS NOT NULL AND dataadmissao <> ''
              AND STR_TO_DATE(dataadmissao, '%d/%m/%Y')
                  BETWEEN STR_TO_DATE('$inicioMes', '%Y-%m-%d')
                  AND STR_TO_DATE('$fimMes', '%Y-%m-%d')
        ")->fetchColumn();

        if (strtolower($tabela) === 'janeiro') {
            $hcInicial = 133; 
        } else {
            $hcInicial = $hcAnterior ?? 1;
        }

        $turnInv = $hcInicial > 0 ? round(($deslInv / $hcInicial) * 100, 2) : 0;

        $labels[] = ucfirst($tabela);
        $valores[] = $turnInv;

        $hcAnterior = $hcFinal;
    }

    return [
        'labels' => $labels,
        'datasets' => [[
            'label' => 'Turnover Involuntário (%)',
            'data' => $valores,
            'borderColor' => '#0b6fa4',
            'backgroundColor' => 'rgba(11,111,164,0.25)',
            'fill' => false,
            'tension' => 0.3
        ]]
    ];
}

if (!function_exists('gerarDados')) {
    function gerarDados(PDO $pdo, string $campo, array $filtros, string $tipoCalculo = 'quantidade'): array
    {
        return gerarDadosHeadcount($pdo, $campo, $filtros, $tipoCalculo);
    }
}
