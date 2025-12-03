<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: index.php');
  exit;
}

require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/functions/funcGraphAbs.php';
include __DIR__ . '/includes/header.php';

function formatarMes($valor)
{
  $mapa = [
    'janeiro' => 'Janeiro',
    'fevereiro' => 'Fevereiro',
    'marco' => 'Março',
    'abril' => 'Abril',
    'maio' => 'Maio',
    'junho' => 'Junho',
    'julho' => 'Julho',
    'agosto' => 'Agosto',
    'setembro' => 'Setembro',
    'outubro' => 'Outubro',
    'novembro' => 'Novembro',
    'dezembro' => 'Dezembro'
  ];
  return $mapa[strtolower($valor)] ?? ucfirst($valor);
}

$filtros = [
  'mesreferencia' => isset($_GET['mesreferencia']) ? (array)$_GET['mesreferencia'] : [],
  'vinculo'       => isset($_GET['vinculo']) ? (array)$_GET['vinculo'] : [],
  'lideranca'     => isset($_GET['lideranca']) ? (array)$_GET['lideranca'] : [],
  'diretoria'     => isset($_GET['diretoria']) ? (array)$_GET['diretoria'] : []
];

$meses = [
  'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'
];

$vinculos = $liderancas = $diretorias = [];

if (!empty($filtros['mesreferencia'])) {
  $tabelaMes = strtolower($filtros['mesreferencia'][0]);
  if (in_array($tabelaMes, $meses)) {
    function getDistinctMensal($pdo, $tabela, $campo)
    {
      $stmt = $pdo->prepare("SELECT DISTINCT $campo FROM `$tabela` WHERE $campo IS NOT NULL AND $campo <> '' ORDER BY $campo ASC");
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $vinculos   = getDistinctMensal($pdo, $tabelaMes, 'vinculo');
    $liderancas = getDistinctMensal($pdo, $tabelaMes, 'lideranca');
    $diretorias = getDistinctMensal($pdo, $tabelaMes, 'diretoria');
  }
}

require_once __DIR__ . '/includes/graphs/absenteismo.php';
?>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<header class="mb-6 flex justify-between items-center">
  <h1 class="text-3xl font-bold">Absenteísmo</h1>
</header>

<form method="get" class="bg-base-100 p-2 rounded-xl shadow-lg mb-8">
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 items-end">
    <?php
    $filtrosConfig = [
      'mesreferencia' => ['label' => 'Mês', 'opcoes' => $meses],
      'vinculo'       => ['label' => 'Vínculo', 'opcoes' => $vinculos],
      'lideranca'     => ['label' => 'Liderança', 'opcoes' => $liderancas],
      'diretoria'     => ['label' => 'Diretoria', 'opcoes' => $diretorias]
    ];

foreach ($filtrosConfig as $campo => $cfg):
  $selecionados = $filtros[$campo] ?? [];
  $textoBotao = empty($selecionados)
    ? $cfg['label']
    : "{$cfg['label']} - " . htmlspecialchars(formatarMes($selecionados[0]));
?>
  <div class="dropdown w-full">
    <label tabindex="0" class="btn w-full justify-between flex items-center gap-2" style="font-size:16px;">
      <span><?= $textoBotao ?></span>
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </label>
    <div tabindex="0" class="dropdown-content z-[1] bg-base-100 rounded-box shadow w-64 max-h-60 overflow-y-auto">
      <ul class="menu p-2">
        <?php foreach ($cfg['opcoes'] as $opt): if (!$opt) continue; ?>
          <li>
            <label class="cursor-pointer flex justify-between items-center">
              <span style="font-size:15px;"><?= htmlspecialchars(formatarMes($opt)) ?></span>
              <?php if ($campo === 'mesreferencia'): ?>
                <input type="radio" name="<?= $campo ?>[]" value="<?= htmlspecialchars($opt) ?>"
                  class="radio radio-primary"
                  <?= in_array($opt, $selecionados) ? 'checked' : '' ?>>
              <?php else: ?>
                <input type="checkbox" name="<?= $campo ?>[]" value="<?= htmlspecialchars($opt) ?>"
                  class="checkbox checkbox-primary"
                  <?= in_array($opt, $selecionados) ? 'checked' : '' ?>>
              <?php endif; ?>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endforeach; ?>
<div><button class="btn btn-success w-full h-full text-white">Aplicar Filtros</button></div>

  </div>
</form>

<main id="dashboard" class="w-full mx-auto space-y-10">
  <?php if (empty($filtros['mesreferencia'])): ?>
  <div class="flex flex-col items-center justify-center py-24 text-gray-500 animate-fadeIn">
    <i class="ph ph-calendar-blank text-6xl text-[#f78e23] mb-4"></i>
    <p class="text-lg font-medium">Selecione um mês para exibir os indicadores</p>
  </div>
  <?php else: ?>

  <section>
    <div class="card bg-base-100 shadow-xl p-6 hover:shadow-2xl transition">
      <div class="flex justify-between items-center">
        <h3 class="text-lg font-semibold">Horas Mensais Previstas</h3>
        <div class="flex items-center gap-2">
          <button type="button" class="btn btn-ghost btn-xs text-gray-500 hover:text-[#f78e23] baixarGrafico" data-grafico="0" title="Baixar gráfico">
            <i class="ph ph-download-simple text-lg"></i>
          </button>
          <select class="select select-sm select-bordered tipoGrafico" data-grafico="0">
            <option value="bar" selected>Barras</option>
            <option value="pie">Pizza</option>
            <option value="doughnut">Rosca</option>
            <option value="line">Linha</option>
          </select>
        </div>
      </div>
      <div class="divider"></div>
      <canvas id="grafico0" style="height:300px;"></canvas>
    </div>
  </section>

  <section>
    <div class="card bg-base-100 shadow-xl p-6 hover:shadow-2xl transition">
      <div class="flex justify-between items-center">
        <h3 class="text-lg font-semibold">Horas de Afastamento por mês</h3>
        <div class="flex items-center gap-2">
          <button type="button" class="btn btn-ghost btn-xs text-gray-500 hover:text-[#f78e23] baixarGrafico" data-grafico="1" title="Baixar gráfico">
            <i class="ph ph-download-simple text-lg"></i>
          </button>
          <select class="select select-sm select-bordered tipoGrafico" data-grafico="1">
            <option value="bar" selected>Barras</option>
            <option value="pie">Pizza</option>
            <option value="doughnut">Rosca</option>
            <option value="line">Linha</option>
          </select>
        </div>
      </div>
      <div class="divider"></div>
      <canvas id="grafico1" style="height:300px;"></canvas>
    </div>
  </section>

  <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card bg-base-100 shadow-xl p-6 hover:shadow-2xl transition">
      <div class="flex justify-between items-center">
        <h3 class="text-lg font-semibold">Tipo de Afastamento</h3>
        <div class="flex items-center gap-2">
          <button type="button" class="btn btn-ghost btn-xs text-gray-500 hover:text-[#f78e23] baixarGrafico" data-grafico="2" title="Baixar gráfico">
            <i class="ph ph-download-simple text-lg"></i>
          </button>
          <select class="select select-sm select-bordered tipoGrafico" data-grafico="2">
            <option value="bar" selected>Barras</option>
            <option value="pie">Pizza</option>
            <option value="doughnut">Rosca</option>
            <option value="line">Linha</option>
          </select>
        </div>
      </div>
      <div class="divider"></div>
      <canvas id="grafico2" style="height:300px;"></canvas>
    </div>

<div class="card bg-base-100 shadow-xl p-6 hover:shadow-2xl transition">
  <div class="flex justify-between items-center">
    <h3 class="text-lg font-semibold">Top 10 Horas Abonadas</h3>
    <div class="flex items-center gap-2">
      <button type="button" class="btn btn-ghost btn-xs text-gray-500 hover:text-[#f78e23] baixarGrafico" data-grafico="3" title="Baixar gráfico">
        <i class="ph ph-download-simple text-lg"></i>
      </button>
      <select class="select select-sm select-bordered tipoGrafico" data-grafico="3">
        <option value="bar" selected>Barras</option>
        <option value="pie">Pizza</option>
        <option value="doughnut">Rosca</option>
        <option value="line">Linha</option>
      </select>
    </div>
  </div>
  <div class="divider"></div>
  <canvas id="grafico3" style="height:300px;"></canvas>
</div>

  </section>

  <?php endif; ?>

</main>

<?php include __DIR__ . '/includes/footer/footerAbs.php'; ?>
