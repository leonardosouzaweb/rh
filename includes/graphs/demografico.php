<?php
$graficos = [
    ['titulo' => 'Vínculo', 'campo' => 'vinculo', 'tipo' => 'doughnut', 'formato' => 'percent'],
    ['titulo' => 'Distribuição em Empresas', 'campo' => 'empresa', 'tipo' => 'bar', 'formato' => 'numero', 'orientacao' => 'horizontal'],
    ['titulo' => 'Liderança', 'campo' => 'lideranca', 'tipo' => 'pie', 'formato' => 'percent'],
    ['titulo' => 'Gênero', 'campo' => 'genero', 'tipo' => 'pie', 'formato' => 'percent'],
    ['titulo' => 'Raça e Cor', 'campo' => 'corraca', 'tipo' => 'doughnut', 'formato' => 'percent', 'orientacao' => 'horizontal'],
    ['titulo' => 'Orientação Sexual', 'campo' => 'orientacaosexual', 'tipo' => 'bar', 'formato' => 'percent', 'orientacao' => 'horizontal'],
    ['titulo' => 'Tempo de Casa', 'campo' => 'tempocasa', 'tipo' => 'doughnut', 'formato' => 'percent', 'orientacao' => 'vertical'],
    ['titulo' => 'Gerações', 'campo' => 'geracao', 'tipo' => 'bar', 'formato' => 'percent', 'orientacao' => 'horizontal'],
    ['titulo' => 'Escolaridade', 'campo' => 'escolaridade', 'tipo' => 'bar', 'formato' => 'percent', 'orientacao' => 'horizontal'],
    ['titulo' => 'Estado Civil', 'campo' => 'estadocivil', 'tipo' => 'doughnut', 'formato' => 'percent'],
    ['titulo' => 'Colaboradores por Nível', 'campo' => 'nivel', 'tipo' => 'bar', 'formato' => 'numero', 'orientacao' => 'horizontal'],
    ['titulo' => 'HC por área PJ x CLT', 'campo' => 'vinculo-diretoria-stacked', 'tipo' => 'bar', 'formato' => 'numero', 'orientacao' => 'vertical', 'stacked' => true, 'dados' => gerarDadosStackedVinculoPorDiretoria($pdo, $filtros)],
];


foreach ($graficos as &$g) {
    $tipoCalculo = ($g['formato'] === 'numero') ? 'quantidade' : 'percentual';
    $g['dados'] = gerarDados($pdo, $g['campo'], $filtros, $tipoCalculo);
}
unset($g);
