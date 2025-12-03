<?php
require_once __DIR__ . '../../conexao.php';
function gerarDadosAbsenteismo(PDO $pdo, string $campo, array $filtros): array
{
    switch ($campo) {
        case 'horas_mensais_previstas':
            return calcularHorasMensaisPrevistas($pdo, $filtros);

        case 'horas_afastamento_por_mes':
            return gerarHorasAfastamentoPorMes($pdo, $filtros);

        case 'tipo_afastamento':
            return gerarTipoAfastamento($pdo, $filtros);

        case 'top10_horas_abonadas':
            return gerarTop10HorasAbonadas($pdo, $filtros);

        default:
            return ['labels' => [], 'datasets' => []];
    }
}

function montarWhereFiltros(array $filtros): array
{
    $condicoes = [];
    $params = [];

    foreach ($filtros as $campo => $valores) {
        if (!empty($valores)) {
            $placeholders = implode(',', array_fill(0, count($valores), '?'));
            $condicoes[] = "$campo IN ($placeholders)";
            $params = array_merge($params, $valores);
        }
    }

    $where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';
    return [$where, $params];
}

function calcularHorasMensaisPrevistas(PDO $pdo, array $filtros): array
{
    $mesesValidos = [
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

    $mesTabela = $filtros['mesreferencia'][0] ?? null;
    if (!$mesTabela || !in_array(strtolower($mesTabela), $mesesValidos)) {
        return [
            'labels' => [],
            'datasets' => [[
                'label' => 'Horas Mensais Previstas',
                'data'  => [],
                'backgroundColor' => '#0b6fa4'
            ]]
        ];
    }

    $tabela = strtolower(trim($mesTabela));

    $filtrosSemMes = $filtros;
    unset($filtrosSemMes['mesreferencia']);
    [$where, $params] = montarWhereFiltros($filtrosSemMes);

    $sql = "SELECT diretoria,
                   SUM(
                       (CAST(SUBSTRING_INDEX(horasmensais, ':', 1) AS DECIMAL(10,2)) * 3600) +
                       (CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(horasmensais, ':', 2), ':', -1) AS DECIMAL(10,2)) * 60) +
                       (CAST(SUBSTRING_INDEX(horasmensais, ':', -1) AS DECIMAL(10,2)))
                   ) AS total_segundos
            FROM `$tabela`
            $where
            GROUP BY diretoria
            ORDER BY total_segundos DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$dados) {
        return [
            'labels' => [],
            'datasets' => [[
                'label' => 'Horas Mensais Previstas',
                'data'  => [],
                'backgroundColor' => '#0b6fa4'
            ]]
        ];
    }

    $labels = [];
    $valores = [];
    $valoresFormatados = [];

    foreach ($dados as $d) {
        $labels[] = $d['diretoria'] ?: 'Não informado';
        $segundos = (float)$d['total_segundos'];

        $horasDecimais = round($segundos / 3600, 2);
        $valores[] = $horasDecimais;

        $h = floor($segundos / 3600);
        $m = floor(($segundos % 3600) / 60);
        $s = $segundos % 60;
        $valoresFormatados[] = sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    return [
        'labels' => $labels,
        'datasets' => [[
            'label' => 'Horas Mensais Previstas',
            'data'  => $valores,
            'backgroundColor' => '#0b6fa4'
        ]],
        'labelsFormatados' => $valoresFormatados
    ];
}

function gerarHorasAfastamentoPorMes(PDO $pdo, array $filtros): array
{
    $filtrosSemMes = $filtros;
    unset($filtrosSemMes['mesreferencia']);
    [$where, $params] = montarWhereFiltros($filtrosSemMes);

    $sql = "SELECT 
                MONTH(STR_TO_DATE(mesreferencia, '%d/%m/%Y')) AS mes_numero,
                SUM(
                    (CAST(SUBSTRING_INDEX(totalhorasafastamento, ':', 1) AS DECIMAL(10,2)) * 3600) +
                    (CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(totalhorasafastamento, ':', 2), ':', -1) AS DECIMAL(10,2)) * 60) +
                    (CAST(SUBSTRING_INDEX(totalhorasafastamento, ':', -1) AS DECIMAL(10,2)))
                ) AS total_segundos
            FROM absenteismo
            $where
            GROUP BY mes_numero
            HAVING mes_numero IS NOT NULL
            ORDER BY mes_numero ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$dados) {
        return [
            'labels' => [],
            'datasets' => [[
                'label' => 'Total Horas Afastamento',
                'data'  => [],
                'borderColor' => '#0b6fa4',
                'backgroundColor' => 'rgba(11, 111, 164, 0.2)',
                'fill' => true,
                'tension' => 0.4
            ]]
        ];
    }

    $nomesMeses = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro'
    ];

    $labels = [];
    $valores = [];
    $valoresFormatados = [];

    foreach ($dados as $d) {
        $mesNumero = (int)$d['mes_numero'];
        $labels[] = $nomesMeses[$mesNumero] ?? "Mês $mesNumero";

        $segundos = (float)$d['total_segundos'];
        $horasDecimais = round($segundos / 3600, 2);
        $valores[] = $horasDecimais;

        $h = floor($segundos / 3600);
        $m = floor(($segundos % 3600) / 60);
        $valoresFormatados[] = sprintf('%02d:%02d', $h, $m);
    }

    return [
        'labels' => $labels,
        'datasets' => [[
            'label' => 'Total Horas Afastamento',
            'data' => $valores,
            'borderColor' => '#0b6fa4',
            'backgroundColor' => 'rgba(11, 111, 164, 0.2)',
            'fill' => true,
            'tension' => 0.4
        ]],
        'labelsFormatados' => $valoresFormatados
    ];
}

function gerarTipoAfastamento(PDO $pdo, array $filtros): array
{
    unset($filtros['mesreferencia']);
    [$where, $params] = montarWhereFiltros($filtros);

    if (stripos($where, 'WHERE') === 0) $where = trim(substr($where, 5));

    $sql = "SELECT 
                TRIM(COALESCE(tipoausencia, 'Não informado')) AS tipoausencia,
                COUNT(*) AS total
            FROM absenteismo
            WHERE tipoausencia IS NOT NULL AND tipoausencia <> ''
            " . ($where ? " AND $where" : "") . "
            GROUP BY tipoausencia
            ORDER BY total DESC";

    $logPath = __DIR__ . '/../../logs/debug.log';
    if (!file_exists(dirname($logPath))) mkdir(dirname($logPath), 0777, true);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$dados) {
        return ['labels' => [], 'datasets' => [['label' => 'Tipo de Afastamento', 'data' => [], 'backgroundColor' => '#0b6fa4']]];
    }

    return [
        'labels' => array_column($dados, 'tipoausencia'),
        'datasets' => [[
            'label' => 'Qtd. de Colaboradores',
            'data'  => array_column($dados, 'total'),
            'backgroundColor' => '#0b6fa4'
        ]]
    ];
}

function gerarTop10HorasAbonadas(PDO $pdo, array $filtros): array
{
    unset($filtros['mesreferencia']);
    [$where, $params] = montarWhereFiltros($filtros);
    if (stripos($where, 'WHERE') === 0) $where = trim(substr($where, 5));

    $sql = "SELECT 
                TRIM(COALESCE(nomecolaborador, 'Não informado')) AS nomecolaborador,
                SUM(
                    (CAST(SUBSTRING_INDEX(totalhorasafastamento, ':', 1) AS DECIMAL(10,2)) * 3600) +
                    (CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(totalhorasafastamento, ':', 2), ':', -1) AS DECIMAL(10,2)) * 60) +
                    (CAST(SUBSTRING_INDEX(totalhorasafastamento, ':', -1) AS DECIMAL(10,2)))
                ) AS total_segundos
            FROM absenteismo
            WHERE totalhorasafastamento IS NOT NULL AND totalhorasafastamento <> ''
            " . ($where ? " AND $where" : "") . "
            GROUP BY nomecolaborador
            HAVING total_segundos > 0
            ORDER BY total_segundos DESC
            LIMIT 10";

    $logPath = __DIR__ . '/../../logs/debug.log';
    if (!file_exists(dirname($logPath))) mkdir(dirname($logPath), 0777, true);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$dados) {
        return ['labels' => [], 'datasets' => [['label' => 'Horas Abonadas', 'data' => [], 'backgroundColor' => '#0b6fa4']]];
    }

    $labels = [];
    $valores = [];
    $valoresFormatados = [];
    foreach ($dados as $d) {
        $labels[] = $d['nomecolaborador'];
        $segundos = (float)$d['total_segundos'];
        $valores[] = round($segundos / 3600, 2);
        $h = floor($segundos / 3600);
        $m = floor(($segundos % 3600) / 60);
        $valoresFormatados[] = sprintf('%02d:%02d', $h, $m);
    }

    return [
        'labels' => $labels,
        'datasets' => [[
            'label' => 'Horas Abonadas',
            'data'  => $valores,
            'backgroundColor' => '#0b6fa4'
        ]],
        'labelsFormatados' => $valoresFormatados
    ];
}
