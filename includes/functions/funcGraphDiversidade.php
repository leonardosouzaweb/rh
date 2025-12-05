<?php

require_once __DIR__ . '../../conexao.php';


/*
|--------------------------------------------------------------------------
| Função auxiliar: retorna a tabela mais recente de headcount
|--------------------------------------------------------------------------
*/
function obterUltimaTabelaMes(PDO $pdo): ?string
{
    $meses = [
        'janeiro','fevereiro','marco','abril','maio','junho',
        'julho','agosto','setembro','outubro','novembro','dezembro'
    ];

    $tabelas = [];
    $stmt = $pdo->query("SHOW TABLES");

    while ($t = $stmt->fetchColumn()) {
        $lower = strtolower($t);
        if (in_array($lower, $meses)) {
            $tabelas[] = $lower;
        }
    }

    if (empty($tabelas)) return null;

    usort($tabelas, fn($a,$b)=>array_search($a,$meses)<=>array_search($b,$meses));

    return end($tabelas); // Último mês disponível
}


/*
|--------------------------------------------------------------------------
| Função auxiliar: aplica filtros (diretoria, time, empresa)
|--------------------------------------------------------------------------
*/
function montarFiltroWhere(array $filtros): string
{
    $condicoes = [];

    foreach (['diretoria','time','empresa'] as $campo) {
        if (!empty($filtros[$campo])) {
            $valores = array_map(fn($v)=>"'" . addslashes($v) . "'", $filtros[$campo]);
            $condicoes[] = "$campo IN (" . implode(',', $valores) . ")";
        }
    }

    return $condicoes ? "WHERE " . implode(" AND ", $condicoes) : "";
}


/*
|--------------------------------------------------------------------------
| Função auxiliar: retorna valores únicos de um campo
|--------------------------------------------------------------------------
*/
function getDistinctDiv(PDO $pdo, string $campo): array
{
    $valores = [];
    $stmt = $pdo->query("SHOW TABLES");

    while ($t = $stmt->fetchColumn()) {
        if (preg_match('/^(janeiro|fevereiro|marco|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)$/i', $t)) {

            $q = "SELECT DISTINCT `$campo` FROM `$t` WHERE `$campo` <> '' AND `$campo` IS NOT NULL";
            foreach ($pdo->query($q)->fetchAll(PDO::FETCH_COLUMN) as $v) {
                $valores[] = trim($v);
            }
        }
    }

    $valores = array_unique($valores);
    sort($valores, SORT_NATURAL | SORT_FLAG_CASE);

    return $valores;
}


/*
|--------------------------------------------------------------------------
| Função principal chamada pelo arquivo graphs/diversidade.php
|--------------------------------------------------------------------------
*/
function gerarDadosDiversidade(PDO $pdo, string $campo, array $filtros, string $tipoCalculo): array
{
    return match ($campo) {
        'div_genero'            => graficoGenero($pdo, $filtros),
        'div_raca'              => graficoRaca($pdo, $filtros),
        'div_orientacao'        => graficoOrientacao($pdo, $filtros),
        'div_pcd'               => graficoPCD($pdo, $filtros),
        'div_geracao'           => graficoGeracao($pdo, $filtros),
        'div_escolaridade'      => graficoEscolaridade($pdo, $filtros),
        'div_estado_civil'      => graficoEstadoCivil($pdo, $filtros),
        'div_diretoria_genero'  => graficoDiversidadePorDiretoria($pdo, $filtros),
        default                 => ['labels'=>[],'datasets'=>[]]
    };
}


/*
|--------------------------------------------------------------------------
| 1. Gráfico por Gênero
|--------------------------------------------------------------------------
*/
function graficoGenero(PDO $pdo, array $filtros): array
{
    $tabela = obterUltimaTabelaMes($pdo);
    if (!$tabela) return [];

    $where = montarFiltroWhere($filtros);

    $sql = "
        SELECT genero, COUNT(*) AS total
        FROM `$tabela`
        WHERE LOWER(statusmes) = 'ativo'
        " . ($where ? " AND " . substr($where, 6) : "") . "
        GROUP BY genero
    ";

    $res = $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'labels'   => array_keys($res),
        'datasets' => [
            ['label' => 'Gênero', 'data' => array_values($res)]
        ]
    ];
}


/*
|--------------------------------------------------------------------------
| 2. Gráfico por Raça/Cor
|--------------------------------------------------------------------------
*/
function graficoRaca(PDO $pdo, array $filtros): array
{
    $tabela = obterUltimaTabelaMes($pdo);
    if (!$tabela) return [];

    $where = montarFiltroWhere($filtros);

    $sql = "
        SELECT corraca, COUNT(*) AS total
        FROM `$tabela`
        WHERE LOWER(statusmes) = 'ativo'
        " . ($where ? " AND " . substr($where, 6) : "") . "
        GROUP BY corraca
    ";

    $res = $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'labels' => array_keys($res),
        'datasets' => [
            ['label' => 'Raça/Cor', 'data' => array_values($res)]
        ]
    ];
}


/*
|--------------------------------------------------------------------------
| 3. Orientação Sexual
|--------------------------------------------------------------------------
*/
function graficoOrientacao(PDO $pdo, array $filtros): array
{
    $tabela = obterUltimaTabelaMes($pdo);
    if (!$tabela) return [];

    $where = montarFiltroWhere($filtros);

    $sql = "
        SELECT orientacaosexual, COUNT(*) AS total
        FROM `$tabela`
        WHERE LOWER(statusmes) = 'ativo'
        " . ($where ? " AND " . substr($where, 6) : "") . "
        GROUP BY orientacaosexual
    ";

    $res = $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'labels' => array_keys($res),
        'datasets' => [
            ['label' => 'Orientação Sexual', 'data' => array_values($res)]
        ]
    ];
}


/*
|--------------------------------------------------------------------------
| 4. PCD
|--------------------------------------------------------------------------
*/
function graficoPCD(PDO $pdo, array $filtros): array
{
    $tabela = obterUltimaTabelaMes($pdo);
    if (!$tabela) return [];

    $where = montarFiltroWhere($filtros);

    $sql = "
        SELECT 
            CASE 
                WHEN LOWER(TRIM(pcd)) IN ('sim','s','y','yes') THEN 'PCD'
                ELSE 'Não PCD'
            END AS categoria,
            COUNT(*) AS total
        FROM `$tabela`
        WHERE LOWER(statusmes) = 'ativo'
        " . ($where ? " AND " . substr($where, 6) : "") . "
        GROUP BY categoria
    ";

    $res = $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'labels' => array_keys($res),
        'datasets' => [
            ['label' => 'PCD', 'data' => array_values($res)]
        ]
    ];
}


/*
|--------------------------------------------------------------------------
| 5. Geração
|--------------------------------------------------------------------------
*/
function graficoGeracao(PDO $pdo, array $filtros): array
{
    $tabela = obterUltimaTabelaMes($pdo);
    if (!$tabela) return [];

    $where = montarFiltroWhere($filtros);

    $sql = "
        SELECT geracao, COUNT(*) AS total
        FROM `$tabela`
        WHERE LOWER(statusmes) = 'ativo'
        " . ($where ? " AND " . substr($where, 6) : "") . "
        GROUP BY geracao
    ";

    $res = $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'labels' => array_keys($res),
        'datasets' => [
            ['label' => 'Geração', 'data' => array_values($res)]
        ]
    ];
}


/*
|--------------------------------------------------------------------------
| 6. Escolaridade
|--------------------------------------------------------------------------
*/
function graficoEscolaridade(PDO $pdo, array $filtros): array
{
    $tabela = obterUltimaTabelaMes($pdo);
    if (!$tabela) return [];

    $where = montarFiltroWhere($filtros);

    $sql = "
        SELECT escolaridade, COUNT(*) AS total
        FROM `$tabela`
        WHERE LOWER(statusmes) = 'ativo'
        " . ($where ? " AND " . substr($where, 6) : "") . "
        GROUP BY escolaridade
    ";

    $res = $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'labels' => array_keys($res),
        'datasets' => [
            ['label' => 'Escolaridade', 'data' => array_values($res)]
        ]
    ];
}


/*
|--------------------------------------------------------------------------
| 7. Estado Civil
|--------------------------------------------------------------------------
*/
function graficoEstadoCivil(PDO $pdo, array $filtros): array
{
    $tabela = obterUltimaTabelaMes($pdo);
    if (!$tabela) return [];

    $where = montarFiltroWhere($filtros);

    $sql = "
        SELECT estadocivil, COUNT(*) AS total
        FROM `$tabela`
        WHERE LOWER(statusmes) = 'ativo'
        " . ($where ? " AND " . substr($where, 6) : "") . "
        GROUP BY estadocivil
    ";

    $res = $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'labels' => array_keys($res),
        'datasets' => [
            ['label' => 'Estado Civil', 'data' => array_values($res)]
        ]
    ];
}


/*
|--------------------------------------------------------------------------
| 8. Diversidade por Diretoria (exemplo: gênero por diretoria)
|--------------------------------------------------------------------------
*/
function graficoDiversidadePorDiretoria(PDO $pdo, array $filtros): array
{
    $tabela = obterUltimaTabelaMes($pdo);
    if (!$tabela) return [];

    $where = montarFiltroWhere($filtros);

    $sql = "
        SELECT diretoria, genero, COUNT(*) AS total
        FROM `$tabela`
        WHERE LOWER(statusmes) = 'ativo'
        " . ($where ? " AND " . substr($where, 6) : "") . "
        GROUP BY diretoria, genero
        ORDER BY diretoria, genero
    ";

    $res = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $diretorias = [];
    $generos    = [];
    $matriz     = [];

    foreach ($res as $row) {
        $d = $row['diretoria'] ?: 'Não informado';
        $g = $row['genero'] ?: 'Não informado';

        $diretorias[$d] = true;
        $generos[$g] = true;

        $matriz[$g][$d] = (int)$row['total'];
    }

    $diretorias = array_keys($diretorias);
    $generos    = array_keys($generos);

    $datasets = [];
    $cores = ['#1374a5','#ff7900','#23a550','#032e44','#d97706','#6b7280','#991b1b'];

    $c = 0;
    foreach ($generos as $g) {
        $linha = [];
        foreach ($diretorias as $d) {
            $linha[] = $matriz[$g][$d] ?? 0;
        }

        $datasets[] = [
            'label' => $g,
            'data'  => $linha,
            'backgroundColor' => $cores[$c % count($cores)]
        ];
        $c++;
    }

    return [
        'labels'   => $diretorias,
        'datasets' => $datasets
    ];
}
