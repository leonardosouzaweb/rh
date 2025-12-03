<?php
require_once __DIR__ . '/includes/conexao.php';
include __DIR__ . '/includes/header.php';

$logFile = __DIR__ . '/logs/debug.log';
function logDebug($msg)
{
  global $logFile;
  $time = date('Y-m-d H:i:s');
  file_put_contents($logFile, "[$time] builder_list.php: $msg\n", FILE_APPEND);
}

try {
  $stmt = $pdo->query("SELECT id, nome, tabela, eixo_x AS eixoX, eixo_y AS eixoY, tipo, criado_em FROM graficos_salvos ORDER BY criado_em DESC");
  $graficos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $graficos = [];
}
?>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="w-full mx-auto space-y-8">
  <header class="flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-700 flex items-center gap-2">
      <i class="ph ph-chart-line text-[#f78e23] text-4xl"></i>
      Meus Gráficos Salvos
    </h1>
  </header>

  <section class="bg-base-100 p-6 rounded-2xl shadow-lg border border-base-300" style="margin-top:20px;">
    <?php if (empty($graficos)): ?>
      <p class="text-center text-gray-500">Nenhum gráfico salvo ainda.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="table table-zebra w-full text-sm">
          <thead>
            <tr class="text-gray-700">
              <th>ID</th>
              <th>Nome</th>
              <th>Tabela</th>
              <th>Eixo X</th>
              <th>Eixo Y</th>
              <th>Tipo</th>
              <th>Criado em</th>
              <th class="text-center">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($graficos as $g): ?>
              <tr>
                <td><?= htmlspecialchars($g['id']) ?></td>
                <td><?= htmlspecialchars($g['nome']) ?></td>
                <td><?= htmlspecialchars($g['tabela']) ?></td>
                <td><?= htmlspecialchars($g['eixoX']) ?></td>
                <td><?= htmlspecialchars($g['eixoY']) ?></td>
                <td><?= ucfirst(htmlspecialchars($g['tipo'])) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($g['criado_em'])) ?></td>
                <td class="flex gap-2 justify-end">
                  <button type="button" class="btn btn-sm btn-info text-white btnView text-sm" data-id="<?= $g['id'] ?>" style="width:158px">
                    <i class="ph ph-eye"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-error text-white btnDel text-sm" data-id="<?= $g['id'] ?>" style="width:158px">
                    <i class="ph ph-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<dialog id="modalView" class="modal">
  <div class="modal-box max-w-4xl">
    <h3 class="font-bold text-lg mb-4">Visualização do Gráfico</h3>

    <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
      <div class="flex items-center gap-2">
        <label class="font-semibold">Tipo:</label>
        <select id="tipoPreview" class="select select-bordered select-sm">
          <option value="bar">Barras</option>
          <option value="line">Linha</option>
          <option value="pie">Pizza</option>
          <option value="doughnut">Rosca</option>
          <option value="radar">Radar</option>
        </select>
      </div>
      <button id="btnDownload" class="btn btn-sm btn-primary text-white">
        <i class="ph ph-download-simple"></i> Baixar Imagem
      </button>
    </div>

    <canvas id="previewChart" class="w-full h-80"></canvas>

    <div class="modal-action">
      <form method="dialog">
        <button class="btn">Fechar</button>
      </form>
    </div>
  </div>
</dialog>

<script>
  function logDebug(msg) {
  }

  const modalView = document.getElementById('modalView');
  const previewCanvas = document.getElementById('previewChart');
  const tipoPreview = document.getElementById('tipoPreview');
  const btnDownload = document.getElementById('btnDownload');
  let previewChart = null;
  let graficoAtual = null;

  document.querySelectorAll('.btnView').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-id');
      try {
        const resp = await fetch('./builder/get_grafico.php?id=' + id);
        const grafico = await resp.json();
        graficoAtual = grafico;

        tipoPreview.value = grafico.tipo.toLowerCase();

        const dataResp = await fetch(`./builder/get_dados.php?tabela=${grafico.tabela}&x=${grafico.eixoX}&y=${grafico.eixoY}`);
        const data = await dataResp.json();

        const labels = data.map(d => d.x);
        const valores = data.map(d => parseFloat(d.y) || 1);

        if (previewChart) previewChart.destroy();

        previewChart = new Chart(previewCanvas, {
          type: grafico.tipo,
          data: {
            labels,
            datasets: [{
              label: `${grafico.eixoY} por ${grafico.eixoX}`,
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
            maintainAspectRatio: false
          }
        });

        modalView.showModal();
      } catch (err) {
        alert('Erro ao visualizar gráfico.');
      }
    });
  });

  tipoPreview.addEventListener('change', () => {
    if (!previewChart || !graficoAtual) return;
    const novoTipo = tipoPreview.value;
    const dados = previewChart.data;
    previewChart.destroy();
    previewChart = new Chart(previewCanvas, {
      type: novoTipo,
      data: dados,
      options: {
        responsive: true,
        maintainAspectRatio: false
      }
    });
  });

  btnDownload.addEventListener('click', () => {
    if (!previewChart) return;
    const link = document.createElement('a');
    link.download = `${graficoAtual?.nome || 'grafico'}.png`;
    link.href = previewChart.toBase64Image();
    link.click();
  });

  document.querySelectorAll('.btnDel').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-id');
      if (!confirm('Deseja realmente excluir este gráfico?')) return;

      fetch(`./builder/del_grafico.php?id=${id}`)
        .then(r => r.json())
        .then(res => {
          if (res.sucesso) {
            window.location.reload();
          } else {
            alert('Erro ao deletar gráfico.');
          }
        })
        .catch(err => {
          alert('Erro ao deletar gráfico.');
        });
    });
  });
</script>