<?php
?>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script>
  window.addEventListener('load', () => {
    const loader = document.getElementById('loader');
    if (loader) {
      loader.style.opacity = '0';
      setTimeout(() => loader.remove(), 400);
    }

    const chartInstances = {};

    const chartConfigs = [
      <?php foreach ($graficosHeadcount as $i => $g): ?> {
          id: 'grafico<?= $i ?>',
          tipo: '<?= $g['tipo'] ?>',
          formato: '<?= $g['formato'] ?? 'numero' ?>',
          labels: <?= json_encode($g['dados']['labels'] ?? [], JSON_UNESCAPED_UNICODE) ?>,
          datasets: <?= json_encode($g['dados']['datasets'] ?? [], JSON_UNESCAPED_UNICODE) ?>
        },
      <?php endforeach; ?>
    ];

    chartConfigs.forEach(cfg => renderChart(cfg));

    function renderChart(cfg) {
      const el = document.getElementById(cfg.id);
      if (!el) return;

      if (chartInstances[cfg.id]) chartInstances[cfg.id].destroy();
      el.style.minHeight = '320px';
      el.style.paddingTop = '0px';

      const labels = (cfg.labels || []).map(l => l || '');
      const datasets = cfg.datasets && cfg.datasets.length ?
        cfg.datasets.map((d, i) => ({
          label: d.label,
          data: d.data.map(v => parseFloat(v) || 0),
          backgroundColor: gerarCor(i),
          borderColor: gerarCor(i),
          borderWidth: 2,
          fill: false,
          tension: 0.3,
          yAxisID: d.yAxisID || 'y'
        })) : [{
          label: '',
          data: [],
          backgroundColor: '#ccc'
        }];

      const totalValores = datasets.reduce((acc, ds) => acc + ds.data.reduce((a, v) => a + v, 0), 0);
      if (totalValores === 0) {
        el.outerHTML = `
        <div style="
          display:flex;
          align-items:center;
          justify-content:center;
          flex-direction:column;
          height:300px;
          background:#fff;
          border-radius:12px;
          color:#6b7280;
          font-size:15px;
          font-family:Inter, sans-serif;
          text-align:center;
        ">
          <i class="ph ph-chart-bar" style="font-size:42px; color:#d1d5db; margin-bottom:10px;"></i>
          <span>Nenhum dado disponível</span>
          <small style="color:#9ca3af; margin-top:4px;">Aguardando informações para este indicador</small>
        </div>`;
        return;
      }

      const isBar = ['bar'].includes(cfg.tipo);

      const chart = new Chart(el, {
        type: cfg.tipo,
        data: {
          labels,
          datasets
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: 'nearest',
            intersect: true
          },
          layout: {
            padding: {
              top: 20,
              bottom: 0
            }
          },
          plugins: {
            legend: {
              display: true,
              position: 'bottom',
              labels: {
                boxWidth: 14,
                boxHeight: 14,
                padding: 10,
                color: '#374151',
                font: {
                  size: 12,
                  family: 'Inter, sans-serif'
                },
                usePointStyle: true,
                pointStyle: 'circle'
              }
            },
            tooltip: {
              backgroundColor: '#fff',
              titleColor: '#001830',
              bodyColor: '#2d2d2dff',
              borderColor: '#fff',
              borderWidth: 1,
              cornerRadius: 6,
              padding: 10,
              displayColors: true,
              callbacks: {
                label: ctx => `${ctx.dataset.label}: ${formatarValor(ctx.formattedValue, cfg.formato)}`
              }
            },
            datalabels: {
              color: cfg.tipo === 'line' ? '#374151' : '#fff',
              anchor: cfg.tipo === 'line' ? 'end' : 'center',
              align: cfg.tipo === 'line' ? 'top' : 'center',
              font: {
                weight: 'bold',
                size: 11
              },
              clip: false,
              padding: {
                top: 8
              },
              formatter: value => value > 0 ? formatarValor(value, cfg.formato) : '',
              offset: ctx => {
                if (cfg.tipo !== 'line') return 0;

                const chart = ctx.chart;
                const index = ctx.dataIndex;
                const datasetIndex = ctx.datasetIndex;

                const currentY = chart.getDatasetMeta(datasetIndex).data[index].y;
                const proximos = chart.data.datasets
                  .map((ds, i) => i !== datasetIndex ? chart.getDatasetMeta(i).data[index].y : null)
                  .filter(y => y !== null && Math.abs(y - currentY) < 15);

                if (proximos.length > 0) {
                  return datasetIndex % 2 === 0 ? -10 : 10;
                }
                return 0;
              }
            }
          },
          hover: {
            mode: 'nearest',
            intersect: true
          },
          scales: cfg.tipo === 'line' ? {
            y: {
              beginAtZero: true,
              grace: '25%',
              min: 0,
              grid: {
                color: '#f1f5f9',
                lineWidth: 1.5
              },
              ticks: {
                display: false
              },
              border: {
                display: false
              }
            },
            x: {
              grid: {
                display: false
              },
              ticks: {
                color: '#374151'
              }
            },
            y1: {
              display: false
            },
            y2: {
              display: false
            }
          } : isBar ? {
            x: {
              stacked: true,
              grid: {
                display: false
              },
              ticks: {
                color: '#374151'
              }
            },
            y: {
              stacked: true,
              grid: {
                display: false
              },
              ticks: {
                display: false
              },
              border: {
                display: false
              }
            }
          } : {},
          elements: {
            bar: {
              borderRadius: 4,
              hoverBackgroundColor: 'none',
              borderWidth: 0
            }
          },
          animation: {
            duration: 400,
            easing: 'easeOutQuart'
          }
        },
        plugins: [
          ChartDataLabels,
          {
            id: 'totaisNoTopo',
            afterDatasetsDraw(chart) {
              if (chart.config.type !== 'bar') return;

              const {
                ctx,
                scales
              } = chart;
              ctx.save();
              chart.data.labels.forEach((label, index) => {
                const total = chart.data.datasets.reduce((sum, ds) => sum + (ds.data[index] || 0), 0);
                const x = scales.x.getPixelForValue(index);
                const y = scales.y?.getPixelForValue(total) ?? scales.y1.getPixelForValue(total);
                ctx.font = 'bold 12px Inter, sans-serif';
                ctx.fillStyle = '#374151';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';
                ctx.fillText(total, x, y - 6);
              });
              ctx.restore();
            }
          }
        ]
      });

      chartInstances[cfg.id] = chart;
    }

    function gerarCor(i) {
      const paleta = ['#1374a5', '#ff7900', '#032e44', '#80b6d8', '#99a1b9', '#001830', '#23a550', '#f7b924'];
      return paleta[i % paleta.length];
    }

    function formatarValor(valor, formato) {
      if (formato === 'percent') return valor + '%';
      return valor;
    }

    document.querySelectorAll('.tipoGrafico').forEach(select => {
      select.addEventListener('change', e => {
        const id = 'grafico' + e.target.dataset.grafico;
        const cfg = chartConfigs.find(c => c.id === id);
        if (!cfg) return;
        cfg.tipo = e.target.value;
        renderChart(cfg);
      });
    });

    document.querySelectorAll('.baixarGrafico').forEach(btn => {
      btn.addEventListener('click', e => {
        const button = e.target.closest('button');
        if (!button) return;
        const id = 'grafico' + button.dataset.grafico;
        const chart = chartInstances[id];
        if (!chart) return;

        const link = document.createElement('a');
        link.download = id + '.png';
        link.href = chart.toBase64Image('image/png', 1);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      });
    });
  });
</script>

</body>

</html>