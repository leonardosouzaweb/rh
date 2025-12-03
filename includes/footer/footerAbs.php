<?php ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

<script>
  window.addEventListener('load', () => {
    const chartInstances = {};

    const chartConfigs = [
      <?php foreach ($graficosAbsenteismo as $i => $g):
        $dados = $g['dados'] ?? ['labels' => [], 'datasets' => []];
        $dataset = $dados['datasets'][0] ?? ['data' => []];
      ?> {
          id: 'grafico<?= $i ?>',
          index: <?= $i ?>,
          titulo: <?= json_encode($g['titulo'], JSON_UNESCAPED_UNICODE) ?>,
          tipo: '<?= $g['tipo'] ?>',
          formato: '<?= $g['formato'] ?? 'numero' ?>',
          labels: <?= json_encode($dados['labels'] ?? [], JSON_UNESCAPED_UNICODE) ?>,
          valores: <?= json_encode($dataset['data'] ?? [], JSON_UNESCAPED_UNICODE) ?>,
          labelDataset: <?= json_encode($dataset['label'] ?? 'Valores', JSON_UNESCAPED_UNICODE) ?>
        },
      <?php endforeach; ?>
    ];

    chartConfigs.forEach(cfg => renderChart(cfg));

    function renderChart(cfg) {
      const el = document.getElementById(cfg.id);
      if (!el) return;
      if (chartInstances[cfg.id]) chartInstances[cfg.id].destroy();

      const valores = cfg.valores.map(v => parseFloat(v) || 0);

      if (valores.length === 0 || valores.every(v => v === 0)) {
        el.outerHTML = `
        <div class="flex flex-col items-center justify-center text-gray-500 text-center h-[300px]">
          <i class="ph ph-chart-line-up text-4xl mb-2"></i>
          <span>Nenhum dado disponível</span>
          <small class="text-gray-400">Aguardando informações</small>
        </div>`;
        return;
      }

      const chart = new Chart(el, {
        type: cfg.tipo || 'bar',
        data: {
          labels: cfg.labels,
          datasets: [{
            label: cfg.labelDataset,
            data: valores,
            backgroundColor: '#0b6fa4',
            borderColor: '#0b6fa4',
            fill: cfg.tipo === 'line' ? true : false,
            tension: cfg.tipo === 'line' ? 0.4 : 0
          }]
        },
        options: {
          indexAxis: cfg.titulo.includes("Top 10") ? 'y' : 'x',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            },
            datalabels: {
              color: '#111',
              anchor: (ctx) => (ctx.chart.config.type === 'bar' && ctx.chart.config.options.indexAxis === 'y') ?
                'end' : 'end',
              align: (ctx) => (ctx.chart.config.type === 'bar' && ctx.chart.config.options.indexAxis === 'y') ?
                'right' : 'top',
              font: {
                size: 12,
                weight: 'bold'
              },
              formatter: (value, context) => {
                const cfgAtual = chartConfigs.find(c => c.id === context.chart.canvas.id);
                if (cfgAtual && cfgAtual.index !== undefined &&
                  cfgAtual.index < <?= count($graficosAbsenteismo) ?>) {
                  const dados =
                    <?= json_encode(array_column($graficosAbsenteismo, 'dados'), JSON_UNESCAPED_UNICODE) ?>;
                  const labelsFormatados = dados[cfgAtual.index]['labelsFormatados'] ?? [];
                  const i = context.dataIndex;
                  return labelsFormatados[i] ?? formatarValor(value, cfgAtual.formato);
                }
                return formatarValor(value, cfgAtual ? cfgAtual.formato : 'numero');
              }
            },

            tooltip: {
              backgroundColor: '#fff',
              titleColor: '#111',
              bodyColor: '#111',
              borderColor: '#e5e7eb',
              borderWidth: 1,
              callbacks: {
                label: ctx => formatarValor(ctx.parsed.y ?? ctx.parsed, cfg.formato)
              }
            }
          },
          scales: {
            x: {
              grid: {
                display: false
              },
              ticks: {
                color: '#6b7280',
                font: {
                  size: 11
                }
              }
            },
            y: {
              grid: {
                display: true,
                color: '#f3f4f6'
              },
              ticks: {
                color: '#6b7280',
                font: {
                  size: 11
                }
              }
            }
          },
          elements: {
            bar: {
              borderRadius: 4
            }
          }
        },
        plugins: [ChartDataLabels]
      });

      chartInstances[cfg.id] = chart;
    }

    function formatarValor(valor, formato) {
      if (formato === 'percent') return valor.toFixed(2) + '%';
      if (!isNaN(valor)) return valor.toLocaleString('pt-BR');
      return valor;
    }
  });
</script>
</body>

</html>