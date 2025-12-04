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
    } elseif ($campo === 'desligamentos_tipo') {
        return calcularDesligamentosPorTipo($pdo);
    } elseif ($campo === 'headcount_area_vinculo') {
        return calcularHeadcountAreaVinculo($pdo);
    } elseif ($campo === 'admitidos_desligados') {
        return calcularAdmitidosEDesligados($pdo);
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
            ],
            [
                'label' => 'Não Gestor',
                'data' => $dados['Não Gestor'],
                'borderColor' => '#ff7900',
                'backgroundColor' => '#ff7900',
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
    $ordemMeses = [
        'janeiro','fevereiro','marco','abril','maio','junho',
        'julho','agosto','setembro','outubro','novembro','dezembro'
    ];

    // Carregar tabelas reais na ordem correta
    $tabelas = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($t = $stmt->fetchColumn()) {
        if (in_array(strtolower($t), $ordemMeses)) {
            $tabelas[] = strtolower($t);
        }
    }
    usort($tabelas, fn($a,$b)=>array_search($a,$ordemMeses)<=>array_search($b,$ordemMeses));

    $labels = [];
    $valores = [];

    foreach ($tabelas as $tabela) {

        // HC FINAL — colaboradores ativos no mês (apenas informação auxiliar)
        $hcFinal = (int)$pdo->query("
            SELECT COUNT(*) 
            FROM `$tabela` 
            WHERE LOWER(statusmes) = 'ativo'
        ")->fetchColumn();

        // TOTAL desligados do mês
        $desligamentos = (int)$pdo->query("
            SELECT COUNT(*) 
            FROM `$tabela` 
            WHERE LOWER(statusmes) = 'desligado'
        ")->fetchColumn();

        /**
         * NOVA REGRA DE HC INICIAL (para qualquer mês):
         * HC Inicial(M) = COUNT(
         *   registros com statusmes = 'ativo' ou 'desligado'
         *   E (dataadmissao = '' OU dataadmissao <= último dia do mês ANTERIOR)
         * )
         * Ou seja, somente quem já estava na empresa antes do mês começar.
         */

        // Descobre o número do mês atual baseado na posição em $ordemMeses
        $numMes = array_search($tabela, $ordemMeses) + 1;

        // Último dia do mês ANTERIOR ao mês da tabela
        if ($numMes === 1) {
            // Janeiro: considera colaboradores admitidos até 31/12 do ano anterior
            $ultimoDiaMesAnterior = '2024-12-31';
        } else {
            $ultimoDiaMesAnterior = date(
                'Y-m-t',
                strtotime('2025-' . str_pad($numMes - 1, 2, '0', STR_PAD_LEFT) . '-01')
            );
        }

        // HC INICIAL com a nova regra do RH
        $sqlHCInicial = "
            SELECT COUNT(*)
            FROM `$tabela`
            WHERE 
                (LOWER(statusmes) = 'ativo' OR LOWER(statusmes) = 'desligado')
                AND (
                    dataadmissao IS NULL 
                    OR dataadmissao = ''
                    OR STR_TO_DATE(dataadmissao, '%d/%m/%Y') <= STR_TO_DATE('$ultimoDiaMesAnterior', '%Y-%m-%d')
                )
        ";
        $hcInicial = (int)$pdo->query($sqlHCInicial)->fetchColumn();
        if ($hcInicial <= 0) {
            $hcInicial = 1;
        }

        // Cálculo do turnover
        $turnover = $hcInicial > 0
            ? round(($desligamentos / $hcInicial) * 100, 2)
            : 0;

        $labels[] = ucfirst($tabela);
        $valores[] = $turnover;
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
        'janeiro','fevereiro','marco','abril','maio','junho',
        'julho','agosto','setembro','outubro','novembro','dezembro'
    ];

    // Capturar tabelas reais de meses
    $tabelas = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($t = $stmt->fetchColumn()) {
        $t = strtolower($t);
        if (in_array($t, $ordemMeses)) {
            $tabelas[] = $t;
        }
    }

    usort($tabelas, fn($a,$b)=>array_search($a,$ordemMeses)<=>array_search($b,$ordemMeses));

    $totalDesligamentos = 0;
    $somaHCInicial = 0;
    $numMeses = count($tabelas);

    foreach ($tabelas as $tabela) {

        // 1. Definir mês numérico
        $numMes = array_search($tabela, $ordemMeses) + 1;
        $primeiroDia = sprintf("2025-%02d-01", $numMes);

        // 2. HC Inicial
        $sqlHCInicial = "
            SELECT COUNT(*)
            FROM `$tabela`
            WHERE 
                dataadmissao = ''
                OR dataadmissao IS NULL
                OR STR_TO_DATE(dataadmissao,'%d/%m/%Y') < STR_TO_DATE('$primeiroDia','%Y-%m-%d')
        ";

        $hcInicial = (int)$pdo->query($sqlHCInicial)->fetchColumn();
        if ($hcInicial <= 0) $hcInicial = 1;

        $somaHCInicial += $hcInicial;

        // 3. Total de desligamentos (vol + inv)
        $sqlDesl = "
            SELECT COUNT(*)
            FROM `$tabela`
            WHERE LOWER(turnover) IN ('voluntário','involuntário')
        ";
        $totalDesligamentos += (int)$pdo->query($sqlDesl)->fetchColumn();
    }

    // 4. Média de HC Inicial
    $mediaHCInicial = $somaHCInicial / $numMeses;

    // 5. Turnover acumulado — sem divisão por 2
    $turnAcumulado = round(($totalDesligamentos / $mediaHCInicial) * 100, 2);

    return [
        'labels' => ['2025'],
        'datasets' => [[
            'label' => 'Turnover Acumulado (%)',
            'data' => [$turnAcumulado],
            'backgroundColor' => '#ff7f0e',
            'borderColor' => '#ff7f0e',
            'borderWidth' => 1
        ]]
    ];
}


function calcularTurnoverVoluntarioHeadcount(PDO $pdo): array
{
    $ordemMeses = [
        'janeiro','fevereiro','marco','abril','maio','junho',
        'julho','agosto','setembro','outubro','novembro','dezembro'
    ];

    // Encontrar tabelas dos meses
    $tabelas = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($t = $stmt->fetchColumn()) {
        $t = strtolower($t);
        if (in_array($t, $ordemMeses)) {
            $tabelas[] = $t;
        }
    }

    usort($tabelas, fn($a,$b)=>array_search($a,$ordemMeses)<=>array_search($b,$ordemMeses));

    $labels = [];
    $valores = [];

    foreach ($tabelas as $index => $tabela) {

        // ---------------------------------------------------------------
        // 1. DEFINIR PRIMEIRO DIA DO MÊS (2025)
        // ---------------------------------------------------------------
        $numMes = array_search($tabela, $ordemMeses) + 1;
        $primeiroDia = sprintf("2025-%02d-01", $numMes);

        // ---------------------------------------------------------------
        // 2. CALCULAR HC INICIAL DO MÊS
        //    Contar todos que já estavam antes do mês
        // ---------------------------------------------------------------
        $sqlHCInicial = "
    SELECT COUNT(*)
    FROM `$tabela`
    WHERE 
        (
            dataadmissao IS NULL
            OR dataadmissao = ''
            OR STR_TO_DATE(dataadmissao,'%d/%m/%Y') < STR_TO_DATE('$primeiroDia','%Y-%m-%d')
        )
        AND (statusmes IS NOT NULL AND statusmes <> '')
";


        $hcInicial = (int)$pdo->query($sqlHCInicial)->fetchColumn();
        if ($hcInicial <= 0) {
            $hcInicial = 1; // evita divisão por zero
        }

        // ---------------------------------------------------------------
        // 3. CONTAR DESLIGAMENTOS VOLUNTÁRIOS DO MÊS
        //    turnover = 'Voluntário'
        // ---------------------------------------------------------------
        $sqlVoluntarios = "
            SELECT COUNT(*)
            FROM `$tabela`
            WHERE LOWER(turnover) = 'voluntário'
        ";

        $voluntarios = (int)$pdo->query($sqlVoluntarios)->fetchColumn();

        // ---------------------------------------------------------------
        // 4. CALCULAR TURNOVER VOLUNTÁRIO
        // ---------------------------------------------------------------
        $turnVol = round(($voluntarios / $hcInicial) * 100, 2);

        $labels[] = ucfirst($tabela);
        $valores[] = $turnVol;
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
    $ordemMeses = [
        'janeiro','fevereiro','marco','abril','maio','junho',
        'julho','agosto','setembro','outubro','novembro','dezembro'
    ];

    // Encontrar tabelas de meses
    $tabelas = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($t = $stmt->fetchColumn()) {
        $t = strtolower($t);
        if (in_array($t, $ordemMeses)) {
            $tabelas[] = $t;
        }
    }

    usort($tabelas, fn($a,$b)=>array_search($a,$ordemMeses)<=>array_search($b,$ordemMeses));

    $labels = [];
    $valores = [];

    foreach ($tabelas as $index => $tabela) {

        // ---------------------------------------------------------------
        // 1. DEFINIR PRIMEIRO DIA DO MÊS (2025)
        // ---------------------------------------------------------------
        $numMes = array_search($tabela, $ordemMeses) + 1;
        $primeiroDia = sprintf("2025-%02d-01", $numMes);

        // ---------------------------------------------------------------
        // 2. CALCULAR HC INICIAL DO MÊS
        // ---------------------------------------------------------------
        $sqlHCInicial = "
            SELECT COUNT(*)
            FROM `$tabela`
            WHERE 
                (
                    dataadmissao = '' 
                    OR dataadmissao IS NULL
                    OR STR_TO_DATE(dataadmissao,'%d/%m/%Y') < STR_TO_DATE('$primeiroDia','%Y-%m-%d')
                )
        ";

        $hcInicial = (int)$pdo->query($sqlHCInicial)->fetchColumn();
        if ($hcInicial <= 0) {
            $hcInicial = 1; // evita divisão por zero
        }

        // ---------------------------------------------------------------
        // 3. CONTAR DESLIGAMENTOS INVOLUNTÁRIOS
        // ---------------------------------------------------------------
        $sqlInvoluntarios = "
            SELECT COUNT(*)
            FROM `$tabela`
            WHERE LOWER(turnover) = 'involuntário'
        ";

        $involuntarios = (int)$pdo->query($sqlInvoluntarios)->fetchColumn();

        // ---------------------------------------------------------------
        // 4. CALCULAR TURNOVER INVOLUNTÁRIO
        // ---------------------------------------------------------------
        $turnInv = round(($involuntarios / $hcInicial) * 100, 2);

        $labels[] = ucfirst($tabela);
        $valores[] = $turnInv;
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


function calcularDesligamentosPorTipo(PDO $pdo): array
{
    $ordemMeses = [
        'janeiro','fevereiro','marco','abril','maio','junho',
        'julho','agosto','setembro','outubro','novembro','dezembro'
    ];

    $tabelas = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($t = $stmt->fetchColumn()) {
        if (in_array(strtolower($t), $ordemMeses)) {
            $tabelas[] = strtolower($t);
        }
    }

    usort($tabelas, fn($a,$b)=>array_search($a,$ordemMeses)<=>array_search($b,$ordemMeses));

    $labels = [];
    $voluntarios = [];
    $involuntarios = [];

    foreach ($tabelas as $tabela) {

        // Nome amigável do mês
        $labels[] = ucfirst($tabela);

        // Voluntário
        $vol = (int)$pdo->query("
            SELECT COUNT(*)
            FROM `$tabela`
            WHERE LOWER(statusmes) = 'desligado'
              AND LOWER(turnover) = 'voluntário'
        ")->fetchColumn();

        // Involuntário
        $inv = (int)$pdo->query("
            SELECT COUNT(*)
            FROM `$tabela`
            WHERE LOWER(statusmes) = 'desligado'
              AND LOWER(turnover) = 'involuntário'
        ")->fetchColumn();

        $voluntarios[] = $vol;
        $involuntarios[] = $inv;
    }

    return [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Voluntário',
                'data' => $voluntarios,
                'backgroundColor' => '#22c55e'
            ],
            [
                'label' => 'Involuntário',
                'data' => $involuntarios,
                'backgroundColor' => '#ef4444'
            ]
        ]
    ];
}

function calcularHeadcountAreaVinculo(PDO $pdo): array
{
    $ordemMeses = [
        'janeiro','fevereiro','marco','abril','maio','junho',
        'julho','agosto','setembro','outubro','novembro','dezembro'
    ];

    // Localizar tabelas existentes
    $tabelas = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($t = $stmt->fetchColumn()) {
        if (in_array(strtolower($t), $ordemMeses)) {
            $tabelas[] = strtolower($t);
        }
    }

    usort($tabelas, fn($a,$b)=>array_search($a,$ordemMeses) <=> array_search($b,$ordemMeses));

    $labels = [];          // Diretoria
    $vinculos = [];        // Conjunto de vínculos existentes
    $dados = [];           // datasets por vínculo

    // Coletar todas as diretorias existentes no sistema
    $todasDiretorias = [];

    foreach ($tabelas as $tabela) {

        // Buscar apenas ativos no mês
        $res = $pdo->query("
            SELECT diretoria, vinculo, COUNT(*) AS total
            FROM `$tabela`
            WHERE LOWER(statusmes) = 'ativo'
            GROUP BY diretoria, vinculo
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($res as $row) {

            $dir = $row['diretoria'] ?: 'Não Informado';
            $vin = $row['vinculo']   ?: 'Não Informado';

            $todasDiretorias[$dir] = true;
            $vinculos[$vin] = true;
        }
    }

    // Ordenar listas
    $diretoriasOrdenadas = array_keys($todasDiretorias);
    sort($diretoriasOrdenadas, SORT_NATURAL | SORT_FLAG_CASE);

    $vinculosOrdenados = array_keys($vinculos);
    sort($vinculosOrdenados, SORT_NATURAL | SORT_FLAG_CASE);

    // Inicializar datasets
    foreach ($vinculosOrdenados as $vin) {
        $dados[$vin] = array_fill(0, count($diretoriasOrdenadas), 0);
    }

    // Preencher valores mês atual (último mês disponível)
    // Caso desejar usar algum mês específico, adapto facilmente.
    $tabelaAtual = end($tabelas);

    $res = $pdo->query("
        SELECT diretoria, vinculo, COUNT(*) AS total
        FROM `$tabelaAtual`
        WHERE LOWER(statusmes) = 'ativo'
        GROUP BY diretoria, vinculo
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($res as $row) {

        $dir = $row['diretoria'] ?: 'Não Informado';
        $vin = $row['vinculo']   ?: 'Não Informado';
        $total = (int)$row['total'];

        $indexDir = array_search($dir, $diretoriasOrdenadas);
        $dados[$vin][$indexDir] = $total;
    }

    // Preparar datasets finais
    $datasets = [];
    $cores = ['#1374a5','#ff7900','#23a550','#032e44','#d97706','#6b7280','#991b1b','#1e3a8a'];

    $c = 0;
    foreach ($dados as $vin => $valores) {
        $datasets[] = [
            'label' => $vin,
            'data'  => $valores,
            'backgroundColor' => $cores[$c % count($cores)]
        ];
        $c++;
    }

    return [
        'labels' => $diretoriasOrdenadas,
        'datasets' => $datasets
    ];
}

function calcularAdmitidosEDesligados(PDO $pdo): array
{
    $ordemMeses = [
        'janeiro','fevereiro','marco','abril','maio','junho',
        'julho','agosto','setembro','outubro','novembro','dezembro'
    ];

    // Localizar tabelas válidas
    $tabelas = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($t = $stmt->fetchColumn()) {
        if (in_array(strtolower($t), $ordemMeses)) {
            $tabelas[] = strtolower($t);
        }
    }

    usort($tabelas, fn($a,$b)=>array_search($a,$ordemMeses) <=> array_search($b,$ordemMeses));

    $labels = [];
    $admitidos = [];
    $desligados = [];

    foreach ($tabelas as $tabela) {

        // Nome do mês para exibição
        $labels[] = ucfirst($tabela);

        // 🟢 ADMITIDOS DO MÊS
        // Contar pessoas cuja data de admissão cai dentro do mês da tabela
        $numMes = array_search($tabela, $ordemMeses) + 1;
        $ano = date('Y');

        $inicioMes = sprintf('%04d-%02d-01', $ano, $numMes);
        $fimMes    = date('Y-m-t', strtotime($inicioMes));

        $admit = (int)$pdo->query("
            SELECT COUNT(*)
            FROM `$tabela`
            WHERE 
                dataadmissao <> '' 
                AND dataadmissao IS NOT NULL
                AND STR_TO_DATE(dataadmissao, '%d/%m/%Y')
                    BETWEEN STR_TO_DATE('$inicioMes', '%Y-%m-%d')
                    AND STR_TO_DATE('$fimMes', '%Y-%m-%d')
        ")->fetchColumn();

        // 🔴 DESLIGADOS DO MÊS
        // Aqui usamos statusmes = desligado pois a tabela já é separada por mês
        $desl = (int)$pdo->query("
            SELECT COUNT(*)
            FROM `$tabela`
            WHERE LOWER(statusmes) = 'desligado'
        ")->fetchColumn();

        $admitidos[]  = $admit;
        $desligados[] = $desl;
    }

    return [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Admitidos',
                'data' => $admitidos,
                'borderColor' => '#22c55e',
                'backgroundColor' => 'rgba(34,197,94,0.25)',
                'borderWidth' => 3,
                'fill' => false,
                'tension' => 0.3
            ],
            [
                'label' => 'Desligados',
                'data' => $desligados,
                'borderColor' => '#ef4444',
                'backgroundColor' => 'rgba(239,68,68,0.25)',
                'borderWidth' => 3,
                'fill' => false,
                'tension' => 0.3
            ]
        ]
    ];
}





if (!function_exists('gerarDados')) {
    function gerarDados(PDO $pdo, string $campo, array $filtros, string $tipoCalculo = 'quantidade'): array
    {
        return gerarDadosHeadcount($pdo, $campo, $filtros, $tipoCalculo);
    }
}
