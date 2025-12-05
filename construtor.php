<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: index.php');
  exit;
}

require_once __DIR__ . '/includes/conexao.php';
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="w-full mx-auto space-y-6">
  <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <h1 class="text-3xl font-bold text-gray-700">Construtor de Gráficos</h1>
  </header>

  <!-- GRID PRINCIPAL -->
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mt-2 items-stretch" style="margin-bottom:100px">

    <!-- CONFIGURAÇÕES -->
    <section class="bg-base-100 rounded-2xl shadow-md border border-base-300 p-6 flex flex-col justify-between">

      <div>
        <h2 class="text-xl font-semibold text-gray-700 mb-4 flex items-center gap-2">
          Configurações
        </h2>

        <form id="formGrafico" class="space-y-6">

          <!-- FONTE DE DADOS -->
          <div>
            <h3 class="font-bold text-gray-400 mb-2 text-sm uppercase tracking-wide">Fonte de Dados</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

              <!-- TABELA -->
              <div>
                <label class="label font-semibold text-gray-600">Tabela</label>
                <select id="tabela" class="select select-bordered w-full" style="font-size:15px;" required>
                  <option value="">Selecione</option>
                  <?php
                  try {
                    $stmt = $pdo->query("SHOW TABLES");
                    while ($tabela = $stmt->fetchColumn()) {
                      echo "<option value='{$tabela}'>$tabela</option>";
                    }
                  } catch (Exception $e) {
                    echo "<option disabled>Erro: " . htmlspecialchars($e->getMessage()) . "</option>";
                  }
                  ?>
                </select>
              </div>

              <!-- TIPO DE GRÁFICO -->
              <div>
                <label class="label font-semibold text-gray-600">Tipo de Gráfico</label>
                <select id="tipo" class="select select-bordered w-full" style="font-size:15px;" required>
                  <option value="bar">Barras</option>
                  <option value="stackedBar">Barras Empilhadas</option>
                  <option value="line">Linha</option>
                  <option value="pie">Pizza</option>
                  <option value="doughnut">Rosca</option>
                </select>
              </div>

            </div>
          </div>

          <!-- EIXOS -->
          <div>
            <h3 class="font-bold text-gray-400 mb-2 text-sm uppercase tracking-wide">Eixos</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

              <!-- EIXO X -->
              <div>
                <label class="label font-semibold text-gray-600">Eixo X</label>
                <select id="eixoX" class="select select-bordered w-full" style="font-size:15px;" required disabled>
                  <option value="">Selecione</option>
                </select>
              </div>

              <!-- EIXO Y -->
              <div>
                <label class="label font-semibold text-gray-600">Eixo Y</label>
                <select id="eixoY" class="select select-bordered w-full" style="font-size:15px;" required disabled>
                  <option value="">Selecione</option>
                </select>
              </div>
            </div>
          </div>

          <!-- AGRUPAMENTO (DATASET) -->
          <div id="grupoDataset" class="hidden transition-all duration-300">
            <h3 class="font-bold text-gray-400 mb-2 text-sm uppercase tracking-wide">Agrupamento</h3>
            <div>
              <label class="label font-semibold text-gray-600">
                Agrupar por (Dataset)
                <span class="text-xs text-gray-400">(somente para barras empilhadas)</span>
              </label>
              <select id="campoDataset" class="select select-bordered w-full" style="font-size:15px;" disabled>
                <option value="">Selecione</option>
              </select>
            </div>
          </div>

          <!-- FILTROS -->
          <div>
            <h3 class="font-bold text-gray-400 mb-2 text-sm uppercase tracking-wide">Filtros</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <input id="condicaoCampo" type="text" placeholder="Campo (ex: cargo)" class="input input-bordered w-full" />
              <select id="condicaoTipo" class="select select-bordered w-full" style="font-size:15px;">
                <option value="">Tipo</option>
                <option value="contém">Contém</option>
                <option value="igual">Igual</option>
                <option value="maior">Maior que</option>
                <option value="menor">Menor que</option>
                <option value="diferente">Diferente</option>
              </select>
              <input id="condicaoValor" type="text" placeholder="Valor" class="input input-bordered w-full" />
            </div>
          </div>

        </form>
      </div>

      <!-- BOTÃO -->
      <div class="flex justify-end mt-6">
        <button type="submit" form="formGrafico" class="btn btn-success text-white" style="width:243px">
          <i class="ph ph-play-circle"></i> Gerar Gráfico
        </button>
      </div>

    </section>

    <!-- ÁREA DO GRÁFICO -->
    <section class="bg-base-100 rounded-2xl shadow-md border border-base-300 p-6 relative flex flex-col justify-center items-center min-h-[520px]">

      <div id="loaderContainer" class="hidden absolute inset-0 flex flex-col items-center justify-center bg-white/90 rounded-2xl z-10">
        <span class="loading loading-spinner loading-lg text-[#f78e23] mb-3"></span>
        <p class="text-[#f78e23] font-semibold">Gerando Gráfico...</p>
      </div>

      <div id="emptyState" class="flex flex-col items-center justify-center text-gray-400 transition-all duration-300">
        <i class="ph ph-chart-line text-6xl text-[#f78e23] mb-3"></i>
        <p class="text-gray-500 text-lg font-medium">Nenhum gráfico gerado ainda</p>
      </div>

      <div id="canvasContainer" class="hidden w-full h-full flex flex-col justify-between">
        <div>
          <h2 class="text-xl font-semibold mb-4 text-gray-700 flex items-center gap-2">
            <i class="ph ph-presentation-chart text-[#f78e23]"></i>
            Visualização do Gráfico
          </h2>
          <canvas id="graficoCustom" class="w-full max-h-[400px]"></canvas>
        </div>

        <div id="infoGrafico" class="hidden p-3 bg-gray-50 rounded-lg border text-sm text-gray-600"></div>

        <div class="flex justify-end mt-6 gap-3">
          <button id="salvarGrafico" class="btn btn-success text-white">
            <i class="ph ph-floppy-disk"></i> Salvar Gráfico
          </button>
        </div>
      </div>

    </section>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

<script>
/* =============================
   VARIÁVEIS PRINCIPAIS
============================= */

let chart;

const tabelaSelect        = document.getElementById('tabela');
const eixoXSelect         = document.getElementById('eixoX');
const eixoYSelect         = document.getElementById('eixoY');
const campoDatasetSelect  = document.getElementById('campoDataset');
const grupoDataset        = document.getElementById('grupoDataset');

const loaderContainer     = document.getElementById('loaderContainer');
const emptyState          = document.getElementById('emptyState');
const canvasContainer     = document.getElementById('canvasContainer');
const infoGrafico         = document.getElementById('infoGrafico');
const tipoSelect          = document.getElementById('tipo');

/* =============================
   MOSTRAR/OCULTAR AGRUPAMENTO
============================= */

tipoSelect.addEventListener("change", () => {
  const tipo = tipoSelect.value;

  if (tipo === "stackedBar") {
    grupoDataset.classList.remove("hidden");
    campoDatasetSelect.required = true;
  } else {
    grupoDataset.classList.add("hidden");
    campoDatasetSelect.required = false;
  }
});

/* =============================
   CORES — PADRÃO HEADCOUNT
============================= */

function gerarCor(i) {
  const paleta = [
    '#1374a5', '#ff7900', '#032e44', '#80b6d8',
    '#99a1b9', '#001830', '#23a550', '#f7b924'
  ];
  return paleta[i % paleta.length];
}

/* =============================
   CARREGAR COLUNAS
============================= */

tabelaSelect.addEventListener('change', async () => {
  const tabela = tabelaSelect.value;
  if (!tabela) return;

  eixoXSelect.disabled = true;
  eixoYSelect.disabled = true;
  campoDatasetSelect.disabled = true;

  eixoXSelect.innerHTML = '<option value="">Carregando...</option>';
  eixoYSelect.innerHTML = '<option value="">Carregando...</option>';
  campoDatasetSelect.innerHTML = '<option value="">Carregando...</option>';

  try {
    const resp = await fetch('builder/get_colunas.php?tabela=' + tabela);
    const colunas = await resp.json();

    eixoXSelect.innerHTML = '<option value="">Selecione</option>';
    eixoYSelect.innerHTML = '<option value="">Selecione</option>';
    campoDatasetSelect.innerHTML = '<option value="">Selecione</option>';

    colunas.forEach(c => {
      const dis = c.temDados ? '' : 'disabled';
      eixoXSelect.innerHTML        += `<option value="${c.nome}" ${dis}>${c.nome}</option>`;
      eixoYSelect.innerHTML        += `<option value="${c.nome}" ${dis}>${c.nome}</option>`;
      campoDatasetSelect.innerHTML += `<option value="${c.nome}" ${dis}>${c.nome}</option>`;
    });

    eixoXSelect.disabled = false;
    eixoYSelect.disabled = false;
    campoDatasetSelect.disabled = false;

  } catch (err) {
    alert("Erro ao carregar colunas.");
  }
});

/* =============================
   GERAR GRÁFICO
============================= */

document.getElementById('formGrafico').addEventListener('submit', async e => {
  e.preventDefault();

  const tabela        = tabelaSelect.value;
  const eixoX         = eixoXSelect.value;
  const eixoY         = eixoYSelect.value;
  const campoDataset  = campoDatasetSelect.value;
  const tipo          = tipoSelect.value;

  if (!tabela || !eixoX || !eixoY) {
    alert("Selecione tabela, eixo X e eixo Y.");
    return;
  }

  if (tipo === "stackedBar" && !campoDataset) {
    alert("Selecione o campo de agrupamento (dataset).");
    return;
  }

  emptyState.classList.add('hidden');
  canvasContainer.classList.add('hidden');
  loaderContainer.classList.remove('hidden');

  let url = `builder/get_dados.php?tabela=${tabela}&x=${eixoX}&y=${eixoY}`;

  if (tipo === "stackedBar") {
    url += `&dataset=${campoDataset}`;
  }

  const campoCond = document.getElementById('condicaoCampo').value.trim();
  const tipoCond  = document.getElementById('condicaoTipo').value;
  const valorCond = document.getElementById('condicaoValor').value.trim();

  if (campoCond && tipoCond && valorCond) {
    url += `&campoCond=${encodeURIComponent(campoCond)}&tipoCond=${encodeURIComponent(tipoCond)}&valorCond=${encodeURIComponent(valorCond)}`;
  }

  try {
    const resp = await fetch(url);
    const dados = await resp.json();

    loaderContainer.classList.add('hidden');

    if (!dados || !dados.labels || dados.labels.length === 0) {
      emptyState.classList.remove('hidden');
      return;
    }

    const labels = dados.labels;

    let datasets = [];

    if (tipo === "stackedBar") {
      datasets = dados.datasets.map((ds, i) => ({
        label: ds.label,
        data: ds.data,
        backgroundColor: gerarCor(i),
        borderColor: gerarCor(i),
        borderWidth: 2
      }));
    } else {
      // gráfico simples com 1 dataset
      datasets = [{
        label: eixoY,
        data: dados.datasets?.[0]?.data || dados.datasets || [],
        backgroundColor: gerarCor(0),
        borderColor: gerarCor(0),
        borderWidth: 2
      }];
    }

    if (chart) chart.destroy();

    const ctx = document.getElementById('graficoCustom');

    chart = new Chart(ctx, {
      type: tipo === 'stackedBar' ? 'bar' : tipo,
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          datalabels: { anchor: 'center', align: 'center', color: '#fff' }
        },
        scales: {
          x: { stacked: tipo === 'stackedBar' },
          y: { stacked: tipo === 'stackedBar', beginAtZero: true }
        }
      },
      plugins: [
        ChartDataLabels,
        {
          id: 'totaisNoTopo',
          afterDatasetsDraw(chart) {

            if (tipo !== "stackedBar") return;

            const { ctx, scales } = chart;

            ctx.save();
            ctx.font = "bold 13px Inter";
            ctx.textAlign = "center";

            chart.data.labels.forEach((label, idx) => {
              const total = chart.data.datasets.reduce((s, ds) => s + (ds.data[idx] || 0), 0);
              const x = scales.x.getPixelForValue(idx);
              const y = scales.y.getPixelForValue(total);

              const isNearTop = (y - 14) < scales.y.top;
              let posY = isNearTop ? y + 18 : y - 14;

              ctx.strokeStyle = "rgba(255,255,255,0.85)";
              ctx.lineWidth = 3;
              ctx.strokeText(total, x, posY);

              ctx.fillStyle = "#0f172a";
              ctx.fillText(total, x, posY);
            });

            ctx.restore();
          }
        }
      ]
    });

    canvasContainer.classList.remove('hidden');
    infoGrafico.classList.remove('hidden');

    infoGrafico.innerHTML =
      `<strong>Tabela:</strong> ${tabela} |
       <strong>X:</strong> ${eixoX} |
       <strong>Y:</strong> ${eixoY} |
       <strong>Dataset:</strong> ${campoDataset || 'N/A'} |
       <strong>Tipo:</strong> ${tipo}`;
  }

  catch (err) {
    loaderContainer.classList.add('hidden');
    emptyState.classList.remove('hidden');
    alert("Erro ao gerar gráfico.");
  }
});

/* =============================
   SALVAR GRÁFICO
============================= */

document.getElementById('salvarGrafico').addEventListener('click', async () => {
  const nome = prompt('Digite um nome para o gráfico:');
  if (!nome) return;

  const payload = {
    nome,
    tabela: tabelaSelect.value,
    eixoX: eixoXSelect.value,
    eixoY: eixoYSelect.value,
    dataset: campoDatasetSelect.value,
    tipo: tipoSelect.value
  };

  await fetch('builder/salvar_grafico.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });

  alert('Gráfico salvo com sucesso.');
});
</script>

<?php include __DIR__ . '/includes/footer/footer.php'; ?>
