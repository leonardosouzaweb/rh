<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: index.php');
  exit;
}

require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/functions/funcGraphDemografico.php';

function formatarMes($valor)
{
  if (!$valor) return '';
  $mapa = [
    'jan.' => 'Janeiro',
    'fev.' => 'Fevereiro',
    'mar.' => 'Março',
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
  return ucfirst($valor);
}

$filtros = [
  'mes' => isset($_GET['mes']) ? (array)$_GET['mes'] : [],
  'vinculo' => isset($_GET['vinculo']) ? (array)$_GET['vinculo'] : [],
  'lideranca' => isset($_GET['lideranca']) ? (array)$_GET['lideranca'] : [],
  'diretoria' => isset($_GET['diretoria']) ? (array)$_GET['diretoria'] : [],
  'empresa' => isset($_GET['empresa']) ? (array)$_GET['empresa'] : [],
  'statusmes' => isset($_GET['statusmes']) ? (array)$_GET['statusmes'] : []
];

$tabelasMeses = [];
$stmt = $pdo->query("SHOW TABLES");
while ($t = $stmt->fetchColumn()) {
  if (preg_match('/^(janeiro|fevereiro|marco|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)$/i', $t)) {
    $tabelasMeses[] = strtolower($t);
  }
}
$mapaCurto = [
  'janeiro' => 'jan.-25',
  'fevereiro' => 'fev.-25',
  'marco' => 'mar.-25',
  'abril' => 'abr.-25',
  'maio' => 'mai.-25',
  'junho' => 'jun.-25',
  'julho' => 'jul.-25',
  'agosto' => 'ago.-25',
  'setembro' => 'set.-25',
  'outubro' => 'out.-25',
  'novembro' => 'nov.-25',
  'dezembro' => 'dez.-25'
];
$meses = array_map(fn($t) => $mapaCurto[strtolower($t)] ?? ucfirst($t), $tabelasMeses);

$vinculos   = getDistinct($pdo, 'vinculo', $filtros);
$liderancas = getDistinct($pdo, 'lideranca', $filtros);
$diretorias = getDistinct($pdo, 'diretoria', $filtros);
$empresas   = getDistinct($pdo, 'empresa', $filtros);
$status     = getDistinct($pdo, 'statusmes', $filtros);

include __DIR__ . '/includes/header.php';

require_once __DIR__ . '/includes/functions/funcCardDemografico.php';
require_once __DIR__ . '/includes/graphs/demografico.php';
?>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<header class="mb-6 flex justify-between items-center">
  <h1 class="text-3xl font-bold">Demográfico</h1>
  <?php
  $mesSelecionado = isset($filtros['mes']) && !empty($filtros['mes'])
    ? formatarMes($filtros['mes'][0])
    : '';
  ?>
  <?php if ($mesSelecionado): ?>
  <h2 class="text-center text-lg font-semibold">
    <?= htmlspecialchars($mesSelecionado, ENT_QUOTES, 'UTF-8') ?>
  </h2>
  <?php endif; ?>
</header>

<section class="bg-base-100 p-2 rounded-xl shadow-lg mb-8">
  <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
    <div class="p-4 border rounded-xl shadow flex gap-3 items-center">
      <div><i class="ph ph-users-four text-5xl text-[#f78e23]"></i></div>
      <div class="text-left">
        <span class="text-sm text-gray-400 block">Headcount</span>
        <h3 class="text-4xl font-bold"><?= $headcount ?></h3>
      </div>
    </div>

    <div class="p-4 border rounded-xl shadow flex gap-3 items-center">
      <div><i class="ph ph-user-gear text-5xl text-[#f78e23]"></i></div>
      <div class="text-left">
        <span class="text-sm text-gray-400 block">Liderança</span>
        <h3 class="text-4xl font-bold"><?= $lideranca ?></h3>
      </div>
    </div>

    <div class="p-4 border rounded-xl shadow flex gap-3 items-center">
      <div><i class="ph ph-users text-5xl text-[#f78e23]"></i></div>
      <div class="text-left">
        <span class="text-sm text-gray-400 block">Liderados</span>
        <h3 class="text-4xl font-bold"><?= $liderados ?></h3>
      </div>
    </div>

    <div class="p-4 border rounded-xl shadow flex gap-3 items-center">
      <div><i class="ph ph-hourglass text-5xl text-[#f78e23]"></i></div>
      <div class="text-left">
        <span class="text-sm text-gray-400 block">Média de Idade</span>
        <h3 class="text-4xl font-bold"><?= $mediaIdade ?></h3>
      </div>
    </div>

    <div class="p-4 border rounded-xl shadow flex gap-3 items-center">
      <div><i class="ph ph-calendar text-5xl text-[#f78e23]"></i></div>
      <div class="text-left">
        <span class="text-sm text-gray-400 block">Média Tempo de Casa</span>
        <h3 class="text-4xl font-bold"><?= $mediaTempo ?></h3>
      </div>
    </div>

    <div class="p-4 border rounded-xl shadow flex gap-3 items-center">
      <div><i class="ph ph-users-three text-5xl text-[#f78e23]"></i></div>
      <div class="text-left">
        <span class="text-sm text-gray-400 block">Colaborador/Gestor</span>
        <h3 class="text-4xl font-bold"><?= $mediaPorGestor ?></h3>
      </div>
    </div>

  </div>
</section>

<?php
$filtrosConfig = [
  'mes' => ['label' => 'Mês', 'opcoes' => $meses],
  'vinculo' => ['label' => 'Vínculo', 'opcoes' => $vinculos],
  'lideranca' => ['label' => 'Liderança', 'opcoes' => $liderancas],
  'statusmes' => ['label' => 'Status', 'opcoes' => $status],
  'diretoria' => ['label' => 'Diretoria', 'opcoes' => $diretorias],
  'empresa' => ['label' => 'Empresa', 'opcoes' => $empresas]
];
?>

<form method="get" class="bg-base-100 p-2 rounded-xl shadow-lg mb-8">
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-7 gap-4 items-end">
    <?php foreach ($filtrosConfig as $campo => $cfg):
      $selecionados = $filtros[$campo] ?? [];
      $textoBotao = '';

      if ($campo === 'mes') {
        $mapa = [
          'jan.' => 1, 'fev.' => 2, 'mar.' => 3, 'abr.' => 4, 'mai.' => 5,
          'jun.' => 6, 'jul.' => 7, 'ago.' => 8, 'set.' => 9, 'out.' => 10,
          'nov.' => 11, 'dez.' => 12
        ];

        usort($cfg['opcoes'], function ($a, $b) use ($mapa) {
          preg_match('/([a-zç\.]+)-(\d{2})/i', strtolower($a), $ma);
          preg_match('/([a-zç\.]+)-(\d{2})/i', strtolower($b), $mb);

          $mesA = $mapa[$ma[1] ?? ''] ?? 0;
          $mesB = $mapa[$mb[1] ?? ''] ?? 0;
          $anoA = isset($ma[2]) ? (int)$ma[2] : 0;
          $anoB = isset($mb[2]) ? (int)$mb[2] : 0;

          if ($anoA === $anoB) return $mesA <=> $mesB;
          return $anoA <=> $anoB;
        });
      }


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
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-70" fill="none" viewBox="0 0 24 24"
          stroke="currentColor">
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
              <?php if ($campo === 'mes'): ?>
              <input type="radio" name="<?= $campo ?>[]" value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>"
                class="radio radio-primary" <?= in_array($opt, $filtros[$campo]) ? 'checked' : '' ?>>
              <?php else: ?>
              <input type="checkbox" name="<?= $campo ?>[]" value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>"
                class="checkbox checkbox-primary" <?= in_array($opt, $filtros[$campo]) ? 'checked' : '' ?>>
              <?php endif; ?>
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
  <?php if (empty($filtros['mes'])): ?>
  <div class="flex flex-col items-center justify-center py-24 text-gray-500 animate-fadeIn">
    <i class="ph ph-calendar-blank text-6xl text-[#f78e23] mb-4"></i>
    <p class="text-lg font-medium">Selecione um mês para exibir os indicadores</p>
  </div>
  <?php else: ?>
  <?php
    $secoes = [
      ['Perfil Geral', 0, 3],
      ['Diversidade', 3, 3],
      ['Estrutura e Experiência', 6, 5]
    ];
    foreach ($secoes as [$titulo, $inicio, $quantidade]): ?>
  <section>
    <h2 class="text-2xl font-bold mb-5"><?= $titulo ?></h2>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
      <?php foreach (array_slice($graficos, $inicio, $quantidade) as $i => $g):
            $id = $inicio + $i; ?>
      <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition p-6">
        <div class="flex justify-between items-center">
          <h3 class="text-lg font-semibold"><?= $g['titulo'] ?></h3>
          <div class="flex items-center gap-2">
            <button type="button" class="btn btn-ghost btn-xs text-gray-500 hover:text-[#f78e23] baixarGrafico"
              data-grafico="<?= $id ?>" title="Baixar gráfico">
              <i class="ph ph-download-simple text-lg"></i>
            </button>
            <select class="select select-sm select-bordered tipoGrafico" data-grafico="<?= $id ?>">
              <option value="bar" <?= $g['tipo'] === 'bar' ? 'selected' : '' ?>>Barras</option>
              <option value="pie" <?= $g['tipo'] === 'pie' ? 'selected' : '' ?>>Pizza</option>
              <option value="doughnut" <?= $g['tipo'] === 'doughnut' ? 'selected' : '' ?>>Rosca</option>
            </select>
          </div>
        </div>
        <div class="divider"></div>
        <canvas id="grafico<?= $id ?>" style="margin-bottom:15px;"></canvas>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer/footerDemografico.php'; ?>