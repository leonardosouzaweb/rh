<?php

$graficosDiversidade = [
    [
        'titulo'     => 'Distribuição por Gênero',
        'campo'      => 'div_genero',
        'tipo'       => 'bar',
        'formato'    => 'numero',
        'orientacao' => 'horizontal'
    ],
    [
        'titulo'     => 'Distribuição por Raça/Cor',
        'campo'      => 'div_raca',
        'tipo'       => 'bar',
        'formato'    => 'numero',
        'orientacao' => 'horizontal'
    ],
    [
        'titulo'     => 'Distribuição por Orientação Sexual',
        'campo'      => 'div_orientacao',
        'tipo'       => 'bar',
        'formato'    => 'numero',
        'orientacao' => 'horizontal'
    ],
    [
        'titulo'     => 'PCD - Pessoas com Deficiência',
        'campo'      => 'div_pcd',
        'tipo'       => 'bar',
        'formato'    => 'numero',
        'orientacao' => 'horizontal'
    ],
    [
        'titulo'     => 'Distribuição por Geração',
        'campo'      => 'div_geracao',
        'tipo'       => 'bar',
        'formato'    => 'numero',
        'orientacao' => 'horizontal'
    ],
    [
        'titulo'     => 'Distribuição por Escolaridade',
        'campo'      => 'div_escolaridade',
        'tipo'       => 'bar',
        'formato'    => 'numero',
        'orientacao' => 'horizontal'
    ],
    [
        'titulo'     => 'Distribuição por Estado Civil',
        'campo'      => 'div_estado_civil',
        'tipo'       => 'bar',
        'formato'    => 'numero',
        'orientacao' => 'horizontal'
    ],
    [
        'titulo'     => 'Diversidade por Diretoria',
        'campo'      => 'div_diretoria_genero',
        'tipo'       => 'bar',
        'formato'    => 'numero',
        'orientacao' => 'horizontal'
    ],
];

foreach ($graficosDiversidade as &$g) {
    $tipoCalculo = $g['formato'] === 'numero' ? 'quantidade' : 'percentual';
    $g['dados'] = gerarDadosDiversidade($pdo, $g['campo'], $filtros, $tipoCalculo);
}
unset($g);
