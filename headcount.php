<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: index.php');
  exit;
}

require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/functions/funcGraphHeadcount.php';
include __DIR__ . '/includes/header.php';

function formatarMes($valor)
{
  if (!$valor) return '';
  $mapa = [
    'jan.' => 'Janeiro',
    'fev.' => 'Fevereiro',
    'mar.' => 'Março',
    'marco' => 'Março',
    'março' => 'Março',
    'abr.' => 'Abril',
    'mai.' => 'Maio',
    'jun.' => 'Junho',
    'jul.' => 'Julho',
    'ago.' => 'Agosto',
    'set.' => 'Setembro',
    'out.' => 'Outubro',
    'nov.' => 'Novembro',
    'dez.' => 'Dezembro'
  ];

  if (preg_match('/([a-zç\.]+)-(\d{2})/i', $valor, $m)) {
    $mes = strtolower($m[1]);
    $ano = '20' . $m[2];
    $nomeMes = $mapa[$mes] ?? ucfirst($mes);
    return "$nomeMes de $ano";
  }

  $valor = strtolower(trim($valor));
  if (isset($mapa[$valor])) {
    return $mapa[$valor];
  }

  $valor = preg_replace('/\s+de\s+\d{4}/', '', $valor);
  return ucfirst($valor);
}

$filtros = [
  'vinculo' => isset($_GET['vinculo']) ? (array)$_GET['vinculo'] : [],
  'lideranca' => isset($_GET['lideranca']) ? (array)$_GET['lideranca'] : [],
  'diretoria' => isset($_GET['diretoria']) ? (array)$_GET['diretoria'] : []
];

$tabelasMeses = [];
$stmt = $pdo->query("SHOW TABLES");
while ($t = $stmt->fetchColumn()) {
  if (preg_match('/^(janeiro|fevereiro|marco|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)$/i', $t)) {
    $tabelasMeses[] = strtolower($t);
  }
}

$vinculos   = getDistinct($pdo, 'vinculo', $filtros);
$liderancas = getDistinct($pdo, 'lideranca', $filtros);
$diretorias = getDistinct($pdo, 'diretoria', $filtros);

require_once __DIR__ . '/includes/graphs/headcount.php';
?>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<header class="mb-6 flex justify-between items-center">
  <h1 class="text-3xl font-bold">Headcount</h1>
</header>

<?php
$filtrosConfig = [
  'vinculo' => ['label' => 'Vínculo', 'opcoes' => $vinculos],
  'lideranca' => ['label' => 'Liderança', 'opcoes' => $liderancas],
  'diretoria' => ['label' => 'Diretoria', 'opcoes' => $diretorias]
];
?>

<form method="get" class="bg-base-100 p-2 rounded-xl shadow-lg mb-8">
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-end">
    <?php
    foreach ($filtrosConfig as $campo => $cfg):
      $selecionados = $filtros[$campo] ?? [];

      if (empty($selecionados)) {
        $textoBotao = $cfg['label'];
      } elseif (count($selecionados) === 1) {
        $valorExibido = ($campo === 'mes') ? formatarMes($selecionados[0]) : $selecionados[0];
        $textoBotao = "{$cfg['label']} - {$valorExibido}";
      } else {
        $primeiro = ($campo === 'mes') ? formatarMes($selecionados[0]) : $selecionados[0];
        $countExtra = count($selecionados) - 1;
        $textoBotao = "{$cfg['label']} - {$primeiro} <span class='badge badge-primary badge-sm text-white ml-1'>+{$countExtra}</span>";
      }
    ?>
      <div class="dropdown w-full">
        <label tabindex="0" class="btn w-full justify-between flex items-center gap-2" style="font-size:16px">
          <span><?= $textoBotao ?></span>
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </label>
        <div tabindex="0" class="dropdown-content z-[1] bg-base-100 rounded-box shadow w-64 max-h-60 overflow-y-auto">
          <ul class="menu p-2">
            <?php foreach ($cfg['opcoes'] as $opt): if (!$opt) continue;
              $labelExibido = ($campo === 'mes') ? formatarMes($opt) : $opt;
            ?>
              <li>
                <label class="cursor-pointer flex justify-between items-center">
                  <span style="font-size:15px;"><?= htmlspecialchars($labelExibido, ENT_QUOTES, 'UTF-8') ?></span>
                  <input type="checkbox" name="<?= $campo ?>[]" value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>"
                    class="checkbox checkbox-primary"
                    <?= in_array($opt, $filtros[$campo]) ? 'checked' : '' ?>>
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
    <?php foreach ([0, 1] as $i): $g = $graficosHeadcount[$i]; ?>
      <section>
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition p-6">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold"><?= $g['titulo'] ?></h3>
            <div class="flex items-center gap-2">
              <button type="button" class="btn btn-ghost btn-xs text-gray-500 hover:text-[#f78e23] baixarGrafico" data-grafico="<?= $i ?>" title="Baixar gráfico">
                <i class="ph ph-download-simple text-lg"></i>
              </button>
              <select class="select select-sm select-bordered tipoGrafico" data-grafico="<?= $i ?>">
                <option value="bar" <?= $g['tipo'] === 'bar' ? 'selected' : '' ?>>Barras</option>
                <option value="pie" <?= $g['tipo'] === 'pie' ? 'selected' : '' ?>>Pizza</option>
                <option value="doughnut" <?= $g['tipo'] === 'doughnut' ? 'selected' : '' ?>>Rosca</option>
                <option value="line" <?= $g['tipo'] === 'line' ? 'selected' : '' ?>>Linha</option>
              </select>
            </div>
          </div>
          <div class="divider"></div>
          <canvas id="grafico<?= $i ?>" style="margin-bottom:15px; height:300px;"></canvas>
        </div>
      </section>
    <?php endforeach; ?>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <?php foreach ([2, 3] as $i): $g = $graficosHeadcount[$i]; ?>
      <section>
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition p-6">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold"><?= $g['titulo'] ?></h3>
            <div class="flex items-center gap-2">
              <button type="button" class="btn btn-ghost btn-xs text-gray-500 hover:text-[#f78e23] baixarGrafico" data-grafico="<?= $i ?>" title="Baixar gráfico">
                <i class="ph ph-download-simple text-lg"></i>
              </button>
              <select class="select select-sm select-bordered tipoGrafico" data-grafico="<?= $i ?>">
                <option value="bar" <?= $g['tipo'] === 'bar' ? 'selected' : '' ?>>Barras</option>
                <option value="pie" <?= $g['tipo'] === 'pie' ? 'selected' : '' ?>>Pizza</option>
                <option value="doughnut" <?= $g['tipo'] === 'doughnut' ? 'selected' : '' ?>>Rosca</option>
                <option value="line" <?= $g['tipo'] === 'line' ? 'selected' : '' ?>>Linha</option>
              </select>
            </div>
          </div>
          <div class="divider"></div>
          <canvas id="grafico<?= $i ?>" style="margin-bottom:15px; height:300px;"></canvas>
        </div>
      </section>
    <?php endforeach; ?>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <?php foreach ([4, 5] as $i): $g = $graficosHeadcount[$i]; ?>
      <section class="<?= $i === 4 ? 'lg:col-span-9' : 'lg:col-span-3' ?>">
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition p-6">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold"><?= $g['titulo'] ?></h3>
            <div class="flex items-center gap-2">
              <button type="button"
                class="btn btn-ghost btn-xs text-gray-500 hover:text-[#f78e23] baixarGrafico"
                data-grafico="<?= $i ?>"
                title="Baixar gráfico">
                <i class="ph ph-download-simple text-lg"></i>
              </button>
              <select class="select select-sm select-bordered tipoGrafico" data-grafico="<?= $i ?>">
                <option value="bar" <?= $g['tipo'] === 'bar' ? 'selected' : '' ?>>Barras</option>
                <option value="pie" <?= $g['tipo'] === 'pie' ? 'selected' : '' ?>>Pizza</option>
                <option value="doughnut" <?= $g['tipo'] === 'doughnut' ? 'selected' : '' ?>>Rosca</option>
                <option value="line" <?= $g['tipo'] === 'line' ? 'selected' : '' ?>>Linha</option>
              </select>
            </div>
          </div>
          <div class="divider"></div>
          <canvas id="grafico<?= $i ?>" style="margin-bottom:15px; height:300px;"></canvas>
        </div>
      </section>
    <?php endforeach; ?>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <?php foreach ([6, 7] as $i): $g = $graficosHeadcount[$i]; ?>
      <section class="<?= $i === 4 ? 'lg:col-span-6' : 'lg:col-span-6' ?>">
        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition p-6">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold"><?= $g['titulo'] ?></h3>
            <div class="flex items-center gap-2">
              <button type="button"
                class="btn btn-ghost btn-xs text-gray-500 hover:text-[#f78e23] baixarGrafico"
                data-grafico="<?= $i ?>"
                title="Baixar gráfico">
                <i class="ph ph-download-simple text-lg"></i>
              </button>
              <select class="select select-sm select-bordered tipoGrafico" data-grafico="<?= $i ?>">
                <option value="bar" <?= $g['tipo'] === 'bar' ? 'selected' : '' ?>>Barras</option>
                <option value="pie" <?= $g['tipo'] === 'pie' ? 'selected' : '' ?>>Pizza</option>
                <option value="doughnut" <?= $g['tipo'] === 'doughnut' ? 'selected' : '' ?>>Rosca</option>
                <option value="line" <?= $g['tipo'] === 'line' ? 'selected' : '' ?>>Linha</option>
              </select>
            </div>
          </div>
          <div class="divider"></div>
          <canvas id="grafico<?= $i ?>" style="margin-bottom:15px; height:300px;"></canvas>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</main>

<?php include __DIR__ . '/includes/footer/footerHeadcount.php'; ?>