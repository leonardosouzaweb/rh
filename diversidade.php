<?php

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}


require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/functions/funcGraphDiversidade.php';
include __DIR__ . '/includes/header.php';

$filtros = [
    'diretoria' => isset($_GET['diretoria']) ? (array)$_GET['diretoria'] : [],
    'time'      => isset($_GET['time']) ? (array)$_GET['time'] : [],
    'empresa'   => isset($_GET['empresa']) ? (array)$_GET['empresa'] : []
];

$tabelasMeses = [];
$stmt = $pdo->query("SHOW TABLES");
while ($t = $stmt->fetchColumn()) {
    if (preg_match('/^(janeiro|fevereiro|marco|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)$/i', $t)) {
        $tabelasMeses[] = strtolower($t);
    }
}

$diretorias = getDistinctDiv($pdo, 'diretoria');
$times      = getDistinctDiv($pdo, 'time');
$empresas   = getDistinctDiv($pdo, 'empresa');

require_once __DIR__ . '/includes/graphs/diversidade.php';


?>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<header class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold">Diversidade & Inclusão</h1>
</header>

<?php
$filtrosConfig = [
    'diretoria' => ['label' => 'Diretoria', 'opcoes' => $diretorias],
    'time'      => ['label' => 'Time', 'opcoes' => $times],
    'empresa'   => ['label' => 'Empresa', 'opcoes' => $empresas]
];
?>

<form method="get" class="bg-base-100 p-2 rounded-xl shadow-lg mb-8">
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-end">
        <?php foreach ($filtrosConfig as $campo => $cfg): 
            $selecionados = $filtros[$campo] ?? [];
            if (empty($selecionados)) {
                $textoBotao = $cfg['label'];
            } elseif (count($selecionados) === 1) {
                $textoBotao = "{$cfg['label']} - {$selecionados[0]}";
            } else {
                $textoBotao = "{$cfg['label']} - {$selecionados[0]} <span class='badge badge-primary badge-sm text-white ml-1'>+" . (count($selecionados)-1) . "</span>";
            }
        ?>
            <div class="dropdown w-full">
                <label tabindex="0" class="btn w-full justify-between flex items-center gap-2" style="font-size:16px">
                    <span><?= $textoBotao ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-70" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </label>

                <div tabindex="0" class="dropdown-content z-[1] bg-base-100 rounded-box shadow w-64 max-h-60 overflow-y-auto">
                    <ul class="menu p-2">
                        <?php foreach ($cfg['opcoes'] as $opt): if (!$opt) continue; ?>
                            <li>
                                <label class="cursor-pointer flex justify-between items-center">
                                    <span style="font-size:15px;"><?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?></span>
                                    <input type="checkbox" name="<?= $campo ?>[]" value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>"
                                        class="checkbox checkbox-primary"
                                        <?= in_array($opt, $selecionados) ? 'checked' : '' ?>>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>

        <div>
            <button class="btn btn-success w-full h-full text-white">Aplicar Filtros</button>
        </div>
    </div>
</form>

<main id="dashboard" class="w-full mx-auto space-y-10">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php foreach ($graficosDiversidade as $i => $g): ?>
            <section>
                <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition p-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold"><?= $g['titulo'] ?></h3>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                class="btn btn-ghost btn-xs text-gray-500 hover:text-[#f78e23] baixarGrafico"
                                data-grafico="<?= $i ?>">
                                <i class="ph ph-download-simple text-lg"></i>
                            </button>

                            <select class="select select-sm select-bordered tipoGrafico" data-grafico="<?= $i ?>">
                                <option value="bar">Barras</option>
                                <option value="pie">Pizza</option>
                                <option value="doughnut">Rosca</option>
                                <option value="line">Linha</option>
                            </select>
                        </div>
                    </div>

                    <div class="divider"></div>
                    <canvas id="graficoDiv<?= $i ?>" style="margin-bottom:15px; height:300px;"></canvas>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</main>

<?php include __DIR__ . '/includes/footer/footerDiversidade.php'; ?>
