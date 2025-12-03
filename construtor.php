<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: index.php');
  exit;
}

require_once __DIR__ . '/includes/conexao.php';
include __DIR__ . '/includes/header.php';

$logFile = __DIR__ . '/logs/debug.log';
function logDebug($msg)
{
  global $logFile;
  $time = date('Y-m-d H:i:s');
  file_put_contents($logFile, "[$time] builder.php: $msg\n", FILE_APPEND);
}
?>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="w-full mx-auto space-y-6">
  <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <h1 class="text-3xl font-bold text-gray-700 flex items-center gap-2">
      Construtor de Gráficos
    </h1>
    <a href="./graficos" class="btn btn-outline btn-sm md:btn-md" style="font-size:16px; width:170px;">
      <i class="ph ph-chart-line text-lg"></i> Ver Gráficos
    </a>
  </header>

  <!-- GRID PRINCIPAL -->
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mt-2 items-stretch">

    <!-- COLUNA ESQUERDA - CONFIGURAÇÃO -->
    <section class="bg-base-100 rounded-2xl shadow-md border border-base-300 p-6 flex flex-col justify-between">
      <div>
        <h2 class="text-xl font-semibold text-gray-700 mb-4 flex items-center gap-2">
          Configurações
        </h2>

        <form id="formGrafico" class="space-y-6">
          <!-- Bloco 1 - Fonte -->
          <div>
            <h3 class="font-bold text-gray-400 mb-2 text-sm uppercase tracking-wide">Fonte de Dados</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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

              <div>
                <label class="label font-semibold text-gray-600">Tipo de Gráfico</label>
                <select id="tipo" class="select select-bordered w-full" style="font-size:15px;" required>
                  <option value="bar">Barras</option>
                  <option value="line">Linha</option>
                  <option value="pie">Pizza</option>
                  <option value="doughnut">Rosca</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Bloco 2 - Eixos -->
          <div>
            <h3 class="font-bold text-gray-400 mb-2 text-sm uppercase tracking-wide">Eixos</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="label font-semibold text-gray-600">Eixo X</label>
                <select id="eixoX" class="select select-bordered w-full" style="font-size:15px;" required disabled>
                  <option value="">Selecione uma tabela</option>
                </select>
              </div>
              <div>
                <label class="label font-semibold text-gray-600">Eixo Y</label>
                <select id="eixoY" class="select select-bordered w-full" style="font-size:15px;" required disabled>
                  <option value="">Selecione uma tabela</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Bloco 3 - Condição -->
          <div>
            <h3 class="font-bold text-gray-400 mb-2 text-sm uppercase tracking-wide">Filtros</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <input id="condicaoCampo" type="text" placeholder="Campo (ex: cargo ou idade)" class="input input-bordered w-full"  />
              <select id="condicaoTipo" class="select select-bordered w-full" style="font-size:15px;">
                <option value="">Tipo de Condição</option>
                <option value="contém">Contém</option>
                <option value="igual">Igual</option>
                <option value="maior">Maior que</option>
                <option value="menor">Menor que</option>
                <option value="diferente">Diferente de</option>
              </select>
              <input id="condicaoValor" type="text" placeholder="Valor (ex: 30 ou Analista)" class="input input-bordered w-full" />
            </div>
          </div>
        </form>
      </div>

      <div class="flex justify-end mt-6">
        <button type="submit" form="formGrafico" class="btn btn-success text-white" style="width:283px">
          <i class="ph ph-play-circle"></i> Gerar Gráfico
        </button>
      </div>
    </section>

    <!-- COLUNA DIREITA - VISUALIZAÇÃO -->
    <section class="bg-base-100 rounded-2xl shadow-md border border-base-300 p-6 relative flex flex-col justify-center items-center min-h-[520px]">

      <!-- Loader central -->
      <div id="loaderContainer" class="hidden absolute inset-0 flex flex-col items-center justify-center bg-white/90 rounded-2xl z-10">
        <span class="loading loading-spinner loading-lg text-[#f78e23] mb-3"></span>
        <p class="text-[#f78e23] font-semibold">Gerando Gráfico...</p>
      </div>

      <!-- Estado vazio -->
      <div id="emptyState" class="flex flex-col items-center justify-center text-gray-400 transition-all duration-300">
        <i class="ph ph-chart-line text-6xl text-[#f78e23] mb-3"></i>
        <p class="text-gray-500 text-lg font-medium">Nenhum gráfico gerado ainda</p>
        <p class="text-sm text-gray-400">Configure os campos e clique em “Gerar Gráfico”</p>
      </div>

      <!-- Canvas -->
      <div id="canvasContainer" class="hidden w-full h-full flex flex-col justify-between">
        <div>
          <h2 class="text-xl font-semibold mb-4 text-gray-700 flex items-center gap-2">
            <i class="ph ph-presentation-chart text-[#f78e23]"></i>
            Visualização do Gráfico
          </h2>
          <canvas id="graficoCustom" class="w-full max-h-[400px]"></canvas>
        </div>

        <div id="infoGrafico" class="hidden mt-4 p-3 bg-gray-50 rounded-lg border text-sm text-gray-600"></div>

        <div class="flex justify-end mt-6 gap-3">
          <button id="salvarGrafico" class="btn btn-success text-white">
            <i class="ph ph-floppy-disk"></i> Salvar Gráfico
          </button>
          <button id="mostrarDebug" class="btn btn-ghost">
            <i class="ph ph-terminal"></i> Debug
          </button>
        </div>
      </div>

      <!-- Debug lateral -->
      <aside id="debugSection" class="hidden fixed top-0 right-0 w-96 h-screen bg-gray-900 text-gray-100 shadow-lg flex flex-col z-50">
        <div class="flex justify-between items-center p-3 border-b border-gray-700">
          <h2 class="text-lg font-semibold">Console de Debug</h2>
          <button id="toggleDebug" class="btn btn-xs btn-error text-white">Fechar</button>
        </div>
        <div id="debugArea" class="p-4 text-xs overflow-y-auto flex-1 font-mono bg-gray-950"></div>
      </aside>
    </section>
  </div>
</div>

<script>
  const tabelaSelect = document.getElementById('tabela');
  const eixoXSelect = document.getElementById('eixoX');
  const eixoYSelect = document.getElementById('eixoY');
  const debugArea = document.getElementById('debugArea');
  const debugSection = document.getElementById('debugSection');
  const canvasContainer = document.getElementById('canvasContainer');
  const loaderContainer = document.getElementById('loaderContainer');
  const infoGrafico = document.getElementById('infoGrafico');
  const emptyState = document.getElementById('emptyState');
  let chart;

  function logDebug(msg, data = null, type = 'info') {
    const colors = {
      info: 'text-blue-400',
      warn: 'text-yellow-400',
      error: 'text-red-400',
      success: 'text-green-400'
    };
    const color = colors[type] || colors.info;
    const time = new Date().toLocaleTimeString();
    let html = `<span class='${color} font-semibold'>[${time}]</span> ${msg}`;
    if (data) {
      const pretty = JSON.stringify(data, null, 2)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      html += `<pre class='bg-gray-900 text-gray-300 text-xs rounded-lg p-2 mt-1 overflow-x-auto'>${pretty}</pre>`;
    }
    debugArea.innerHTML += html + "<hr class='border-gray-700 my-2'>";
    debugArea.scrollTop = debugArea.scrollHeight;
  }

  document.getElementById('mostrarDebug').addEventListener('click', () => debugSection.classList.remove('hidden'));
  document.getElementById('toggleDebug').addEventListener('click', () => debugSection.classList.add('hidden'));

  tabelaSelect.addEventListener('change', async () => {
    const tabela = tabelaSelect.value;
    if (!tabela) return;
    eixoXSelect.disabled = eixoYSelect.disabled = true;
    try {
      const resp = await fetch('builder/get_colunas.php?tabela=' + tabela);
      const colunas = await resp.json();
      eixoXSelect.innerHTML = eixoYSelect.innerHTML = '<option value="">Selecione</option>';
      colunas.forEach(c => {
        const disabled = c.temDados ? '' : 'disabled';
        const marker = c.temDados ? '' : ' ⚠️';
        eixoXSelect.innerHTML += `<option value="${c.nome}" ${disabled}>${c.nome}${marker}</option>`;
        eixoYSelect.innerHTML += `<option value="${c.nome}" ${disabled}>${c.nome}${marker}</option>`;
      });
      eixoXSelect.disabled = eixoYSelect.disabled = false;
    } catch (err) {
      logDebug('Erro ao carregar colunas', err, 'error');
    }
  });

    document.getElementById('formGrafico').addEventListener('submit', async e => {
    e.preventDefault();
    const tabela = tabelaSelect.value;
    const eixoX = eixoXSelect.value;
    const eixoY = eixoYSelect.value;
    const tipo = document.getElementById('tipo').value;
    const campoCond = document.getElementById('condicaoCampo').value.trim();
    const tipoCond = document.getElementById('condicaoTipo').value;
    const valorCond = document.getElementById('condicaoValor').value.trim();

    if (!tabela || !eixoX || !eixoY) {
      alert('Selecione a tabela e ambos os eixos antes de gerar o gráfico.');
      return;
    }
    if (eixoX === eixoY) {
      alert('Os eixos X e Y não podem ser o mesmo campo.');
      return;
    }

    try {
      // Mostra loader
      loaderContainer.classList.remove('hidden');
      emptyState.classList.add('hidden');
      canvasContainer.classList.add('hidden');

      let url = `builder/get_dados.php?tabela=${tabela}&x=${eixoX}&y=${eixoY}`;
      if (campoCond && tipoCond && valorCond) {
        url += `&campoCond=${encodeURIComponent(campoCond)}&tipoCond=${encodeURIComponent(tipoCond)}&valorCond=${encodeURIComponent(valorCond)}`;
      }

      const resp = await fetch(url);
      const data = await resp.json();

      // Adiciona pequeno delay para percepção visual
      await new Promise(resolve => setTimeout(resolve, 1300));
      loaderContainer.classList.add('hidden');

      if (!data || !Array.isArray(data) || !data.length) {
        emptyState.classList.remove('hidden');
        return;
      }

      const filtrados = data.filter(d => d.x && d.y);
      if (!filtrados.length) {
        emptyState.classList.remove('hidden');
        return;
      }

      const labels = filtrados.map(d => d.x);
      const valores = filtrados.map(d => parseFloat(d.y) || 1);

      if (chart) chart.destroy();

      const ctx = document.getElementById('graficoCustom');
      chart = new Chart(ctx, {
        type: tipo,
        data: {
          labels,
          datasets: [{
            label: `${eixoY} por ${eixoX}`,
            data: valores,
            borderWidth: 1,
            backgroundColor: [
              'rgba(247,142,35,0.7)',
              'rgba(255,99,132,0.6)',
              'rgba(54,162,235,0.6)',
              'rgba(75,192,192,0.6)',
              'rgba(153,102,255,0.6)'
            ]
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: { duration: 800 },
          scales: tipo === 'bar' || tipo === 'line' ? { y: { beginAtZero: true } } : {}
        }
      });

      canvasContainer.classList.remove('hidden');
      infoGrafico.classList.remove('hidden');
      infoGrafico.innerHTML = `<strong>Tabela:</strong> ${tabela} | <strong>Eixo X:</strong> ${eixoX} | <strong>Eixo Y:</strong> ${eixoY} | <strong>Tipo:</strong> ${tipo}`;
    } catch (err) {
      loaderContainer.classList.add('hidden');
      emptyState.classList.remove('hidden');
      logDebug('Erro ao gerar gráfico', err, 'error');
    }
  });


  document.getElementById('salvarGrafico').addEventListener('click', async () => {
    const nome = prompt('Digite um nome para o gráfico:');
    if (!nome) return;
    const payload = {
      nome,
      tabela: tabelaSelect.value,
      eixoX: eixoXSelect.value,
      eixoY: eixoYSelect.value,
      tipo: document.getElementById('tipo').value
    };
    try {
      const resp = await fetch('builder/salvar_grafico.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      await resp.json();
      alert('Gráfico salvo com sucesso.');
    } catch (err) {
      logDebug('Erro ao salvar gráfico', err, 'error');
    }
  });
</script>

<?php include __DIR__ . '/includes/footer/footer.php'; ?>
