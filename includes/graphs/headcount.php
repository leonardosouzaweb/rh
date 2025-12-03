<?php
$graficosHeadcount = [
    [
        'titulo'     => 'Evolução do Headcount por Vínculo',
        'campo'      => 'headcount_evolucao',
        'tipo'       => 'bar',
        'formato'    => 'numero',
        'orientacao' => 'horizontal'
    ],
    [
        'titulo'     => 'Headcount - Gestor vs Não Gestor',
        'campo'      => 'headcount_lideranca',
        'tipo'       => 'line',
        'formato'    => 'numero',
        'orientacao' => 'horizontal'
    ],
    [
        'titulo'     => 'Admissões por Mês e Vínculo',
        'campo'      => 'headcount_admissoes',
        'tipo'       => 'bar',
        'formato'    => 'numero',
        'orientacao' => 'horizontal'
    ],
    [
        'titulo'     => 'Desligamentos por Mês e Vínculo',
        'campo'      => 'headcount_desligamentos',
        'tipo'       => 'bar',
        'formato'    => 'numero',
        'orientacao' => 'horizontal'
    ],
    [
        'titulo'     => 'Turnover Geral (%) por Mês',
        'campo'      => 'headcount_turnover',
        'tipo'       => 'line',
        'formato'    => 'percentual',
        'orientacao' => 'horizontal'
    ],
    [
        'titulo'     => 'Turnover Acumulado (%)',
        'campo'      => 'headcount_turnover_acumulado',
        'tipo'       => 'bar',
        'formato'    => 'percentual',
        'orientacao' => 'horizontal'
    ],
    [
        'titulo'     => 'Turnover Voluntário (%) por Mês',
        'campo'      => 'headcount_turnover_voluntario',
        'tipo'       => 'line',
        'formato'    => 'percentual',
        'orientacao' => 'horizontal'
    ],
    [
        'titulo'     => 'Turnover Involuntário (%) por Mês',
        'campo'      => 'headcount_turnover_involuntario',
        'tipo'       => 'line',
        'formato'    => 'percentual',
        'orientacao' => 'horizontal'
    ],
];

foreach ($graficosHeadcount as &$g) {
    $tipoCalculo = $g['formato'] === 'numero' ? 'quantidade' : 'percentual';
    $g['dados'] = gerarDadosHeadcount($pdo, $g['campo'], $filtros, $tipoCalculo);
}
unset($g);
