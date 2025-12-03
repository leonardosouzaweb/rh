<?php
require_once __DIR__ . '/../functions/funcGraphAbs.php';

$graficosAbsenteismo = [
    [
        'titulo'  => 'Horas Mensais Previstas',
        'campo'   => 'horas_mensais_previstas',
        'tipo'    => 'bar',
        'formato' => 'numero'
    ],
    [
        'titulo'  => 'Horas de Afastamento por mês',
        'campo'   => 'horas_afastamento_por_mes',
        'tipo'    => 'line',
        'formato' => 'numero'
    ],
    [
        'titulo'  => 'Tipo de Afastamento',
        'campo'   => 'tipo_afastamento',
        'tipo'    => 'bar',
        'formato' => 'numero'
    ],
    [
        'titulo'  => 'Top 10 Horas Abonadas',
        'campo'   => 'top10_horas_abonadas',
        'tipo'    => 'bar',
        'formato' => 'numero'
    ]
];

foreach ($graficosAbsenteismo as &$g) {
    $g['dados'] = gerarDadosAbsenteismo($pdo, $g['campo'], $filtros);
}
unset($g);

foreach ($graficosAbsenteismo as &$g) {
    $g['dados'] = gerarDadosAbsenteismo($pdo, $g['campo'], $filtros);
}
unset($g);
