<?php
function getTabelaAtual($filtros)
{
    $meses = [];
    $mapa = [
        'jan.' => 'janeiro',
        'fev.' => 'fevereiro',
        'mar.' => 'marco',
        'abr.' => 'abril',
        'mai.' => 'maio',
        'jun.' => 'junho',
        'jul.' => 'julho',
        'ago.' => 'agosto',
        'set.' => 'setembro',
        'out.' => 'outubro',
        'nov.' => 'novembro',
        'dez.' => 'dezembro'
    ];

    if (!empty($filtros['mes'])) {
        foreach ($filtros['mes'] as $valor) {
            $valor = strtolower($valor);
            $valor = preg_replace('/-[0-9]{2}$/', '', $valor);
            foreach ($mapa as $k => $mesNome) {
                if (str_starts_with($valor, $k)) {
                    $meses[] = $mesNome;
                    break;
                }
            }
        }
    }

    if (empty($meses)) {
        global $pdo;
        $stmt = $pdo->query("SHOW TABLES");
        while ($t = $stmt->fetchColumn()) {
            if (preg_match('/^(janeiro|fevereiro|marco|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)$/i', $t)) {
                $meses[] = strtolower($t);
            }
        }
    }

    return $meses;
}

function getDistinct($pdo, $coluna, $filtros)
{
    $tabelas = getTabelaAtual($filtros);
    $valores = [];

    foreach ($tabelas as $tabela) {
        $tabela = preg_replace('/[^a-z0-9_]/', '', strtolower($tabela));
        $check = $pdo->prepare("SHOW TABLES LIKE ?");
        $check->execute([$tabela]);
        if (!$check->fetchColumn()) continue;

        $sql = "SELECT DISTINCT TRIM(`$coluna`) AS valor
                FROM `$tabela`
                WHERE `$coluna` IS NOT NULL AND TRIM(`$coluna`) <> ''";
        try {
            $stmt = $pdo->query($sql);
            $dados = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($dados) $valores = array_merge($valores, $dados);
        } catch (PDOException $e) {
            continue;
        }
    }

    $valores = array_unique(array_filter($valores));
    sort($valores, SORT_NATURAL | SORT_FLAG_CASE);
    return $valores;
}

function gerarDadosStackedVinculoPorDiretoria($pdo, $filtros)
{
    $tabelas = getTabelaAtual($filtros);
    $resultado = [];

    foreach ($tabelas as $tabela) {
        $tabela = preg_replace('/[^a-z0-9_]/', '', strtolower($tabela));
        $check = $pdo->prepare("SHOW TABLES LIKE ?");
        $check->execute([$tabela]);
        if (!$check->fetchColumn()) continue;

        $where = [];
        $params = [];

        foreach ($filtros as $col => $valores) {
            if (!empty($valores) && $col !== 'diretoria' && $col !== 'vinculo' && $col !== 'mes') {
                $valoresLimpos = array_filter($valores, fn($v) => $v !== 'Não informado');
                $placeholders = implode(',', array_fill(0, count($valoresLimpos), '?'));
                $condicoes = [];

                if ($valoresLimpos) {
                    $condicoes[] = "TRIM(`$col`) IN ($placeholders)";
                    $params = array_merge($params, $valoresLimpos);
                }

                if (in_array('Não informado', $valores)) {
                    $condicoes[] = "`$col` IS NULL";
                    $condicoes[] = "TRIM(`$col`) = ''";
                }

                if ($condicoes) $where[] = '(' . implode(' OR ', $condicoes) . ')';
            }
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT 
                COALESCE(NULLIF(TRIM(`diretoria`), ''), 'Não informado') AS diretoria,
                COALESCE(NULLIF(TRIM(`vinculo`), ''), 'Não informado') AS vinculo,
                COUNT(*) AS total
            FROM `$tabela`
            $whereSQL
            GROUP BY diretoria, vinculo
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($dados as $d) {
                $dir = $d['diretoria'];
                $vin = $d['vinculo'];
                $tot = (int)$d['total'];

                if (!isset($resultado[$dir])) $resultado[$dir] = [];
                if (!isset($resultado[$dir][$vin])) $resultado[$dir][$vin] = 0;

                $resultado[$dir][$vin] += $tot;
            }

        } catch (PDOException $e) {
            continue;
        }
    }

    if (empty($resultado)) return ['labels' => [], 'datasets' => []];

    $labels = array_keys($resultado);

    // Normalizar categorias (CLT, PJ, Estágio, etc.)
    $tiposVinculo = [];
    foreach ($resultado as $dir => $tipos) {
        foreach ($tipos as $v => $t) {
            $tiposVinculo[$v] = true;
        }
    }
    $tiposVinculo = array_keys($tiposVinculo);

    $datasets = [];
    foreach ($tiposVinculo as $tipo) {
        $dataset = [
            'label' => $tipo,
            'data' => []
        ];
        foreach ($labels as $dir) {
            $dataset['data'][] = $resultado[$dir][$tipo] ?? 0;
        }
        $datasets[] = $dataset;
    }

    return [
        'labels' => $labels,
        'datasets' => $datasets
    ];
}

function gerarDados($pdo, $campo, $filtros, $tipo = 'percentual')
{
    $tabelas = getTabelaAtual($filtros);
    $dadosTotais = [];

    foreach ($tabelas as $tabela) {
        $tabela = preg_replace('/[^a-z0-9_]/', '', strtolower($tabela));
        $check = $pdo->prepare("SHOW TABLES LIKE ?");
        $check->execute([$tabela]);
        if (!$check->fetchColumn()) continue;

        $where = [];
        $params = [];

        foreach ($filtros as $col => $valores) {
            if (!empty($valores) && $col !== $campo && $col !== 'mes') {
                $valoresLimpos = array_filter($valores, fn($v) => $v !== 'Não informado');
                $placeholders = implode(',', array_fill(0, count($valoresLimpos), '?'));
                $condicoes = [];

                if ($valoresLimpos) {
                    $condicoes[] = "TRIM(`$col`) IN ($placeholders)";
                    $params = array_merge($params, $valoresLimpos);
                }
                if (in_array('Não informado', $valores)) {
                    $condicoes[] = "`$col` IS NULL";
                    $condicoes[] = "TRIM(`$col`) = ''";
                }
                if ($condicoes) $where[] = '(' . implode(' OR ', $condicoes) . ')';
            }
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "
            SELECT 
                COALESCE(NULLIF(TRIM(`$campo`), ''), 'Não informado') AS categoria, 
                COUNT(*) AS total 
            FROM `$tabela`
            $whereSQL 
            GROUP BY categoria
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($dados as $d) {
                $cat = $d['categoria'] ?? 'Não informado';
                $dadosTotais[$cat] = ($dadosTotais[$cat] ?? 0) + (int)$d['total'];
            }
        } catch (PDOException $e) {
            continue;
        }
    }

    if (empty($dadosTotais)) {
        $dadosTotais = ['Não informado' => 0];
    }

    if ($tipo === 'percentual') {
        $totalGeral = array_sum($dadosTotais);
        if ($totalGeral > 0) {
            $percentuais = [];
            $soma = 0;
            $categorias = array_keys($dadosTotais);
            $ultima = end($categorias);

            foreach ($dadosTotais as $cat => $valor) {
                if ($cat !== $ultima) {
                    $p = round(($valor / $totalGeral) * 100, 1);
                    $percentuais[$cat] = $p;
                    $soma += $p;
                } else {
                    $percentuais[$cat] = round(100 - $soma, 1);
                }
            }
            $dadosTotais = $percentuais;
        }
    }

    $ordemNivel = [
        'Não informado',
        'Estágio',
        'Auxiliar',
        'Assistente',
        'Recepção/ Copeiros/ Zeladoria/ Motorista',
        'Analista Junior',
        'Analista Pleno',
        'Analista Sênior',
        'Especialista',
        'Coordenador',
        'Gerente',
        'Diretor',
        'Fundador'
    ];

    $ordemTempo = [
        'Até 3 meses',
        'Até 6 meses',
        'Até 1 ano',
        '1 a 3 anos',
        '3 a 5 anos',
        '5 a 10 anos',
        'Acima de 10 anos'
    ];

    $ordemGeracao = [
        'Baby Boomers',
        'Geração X',
        'Millennials (Geração Y)',
        'Geração Z'
    ];

    $ordemEscolaridade = [
        'Ensino Médio incompleto',
        'Ensino Médio completo',
        'Educação Superior incompleta',
        'Educação Superior completa',
        'Pós-graduação incompleta',
        'Pós-graduação completa',
        'Mestrado incompleto',
        'Mestrado completo',
        'Doutorado incompleto',
        'Doutorado completo',
        'Não informado'
    ];

    $mapas = [
        'nivel' => $ordemNivel,
        'tempocasa' => $ordemTempo,
        'geracao' => $ordemGeracao,
        'escolaridade' => $ordemEscolaridade
    ];

    if (isset($mapas[$campo])) {
        $ordem = $mapas[$campo];
        $dadosOrdenados = [];
        foreach ($ordem as $categoria) {
            $dadosOrdenados[$categoria] = $dadosTotais[$categoria] ?? 0;
        }
        $dadosTotais = $dadosOrdenados;
    }

    if ($campo === 'empresa') {
        arsort($dadosTotais, SORT_NUMERIC);
    }

    $labels = array_keys($dadosTotais);
    $valores = array_values($dadosTotais);

    return ['labels' => $labels, 'valores' => $valores];
}

