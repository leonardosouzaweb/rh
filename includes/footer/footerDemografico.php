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
      <?php foreach ($graficos as $i => $g): ?> {
          id: 'grafico<?= $i ?>',
          tipo: '<?= $g['tipo'] ?>',
          formato: '<?= $g['formato'] ?? 'percent' ?>',
          orientacao: '<?= $g['orientacao'] ?? 'vertical' ?>',
          labels: <?= json_encode($g['dados']['labels'], JSON_UNESCAPED_UNICODE) ?>,
          valores: <?= json_encode($g['dados']['valores']) ?>
        },
      <?php endforeach; ?>
    ];

    chartConfigs.forEach(cfg => renderChart(cfg));

    function renderChart(cfg) {
      const el = document.getElementById(cfg.id);
      if (!el) return;

      if (chartInstances[cfg.id]) chartInstances[cfg.id].destroy();
      el.style.height = '340px';

      const labels = (cfg.labels || []).map(l => l || '');
      const valores = (cfg.valores || []).map(v => parseFloat(v) || 0);
      const cores = ['#1374a5', '#ff7900', '#032e44', '#80b6d8', '#99a1b9ff', '#001830'];

      if (valores.length === 0 || valores.every(v => v === 0)) {
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
          <i class="ph ph-chart-line-up" style="font-size:42px; color:#d1d5db; margin-bottom:10px;"></i>
          <span>Nenhum dado disponível</span>
          <small style="color:#9ca3af; margin-top:4px;">Aguardando informações para este indicador</small>
        </div>`;
        return;
      }

      const isBar = cfg.tipo === 'bar';
      const isHorizontal = isBar && cfg.orientacao === 'horizontal';

      const chart = new Chart(el, {
        type: cfg.tipo,
        data: {
          labels,
          datasets: [{
            data: valores,
            backgroundColor: cores,
            borderWidth: 0
          }]
        },
        options: {
          indexAxis: isHorizontal ? 'y' : 'x',
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: 'nearest',
            intersect: true
          },
          plugins: {
            legend: {
              display: !isBar,
              position: 'bottom',
              labels: {
                boxWidth: 14,
                boxHeight: 14,
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
              displayColors: false,
              callbacks: {
                label: ctx => formatarValor(parseFloat(ctx.formattedValue).toFixed(0), cfg.formato)
              }
            },

            datalabels: {
              color: '#fff',
              anchor: 'center',
              align: 'center',
              font: {
                weight: 'bold',
                size: 13
              },
              formatter: value => value > 0 ? formatarValor(parseFloat(value).toFixed(0), cfg.formato) : ''
            }
          },

          hover: {
            mode: 'nearest',
            intersect: true
          },

          scales: isBar ? (
            isHorizontal ? {
              x: {
                grid: {
                  display: false
                },
                ticks: {
                  display: false
                },
                title: {
                  display: false
                }
              },
              y: {
                grid: {
                  display: false
                },
                ticks: {
                  display: true,
                  color: '#374151',
                  font: {
                    size: 11,
                    family: 'Inter, sans-serif'
                  }
                }
              }
            } : {
              x: {
                grid: {
                  display: false
                },
                ticks: {
                  display: false
                },
                title: {
                  display: false
                }
              },
              y: {
                grid: {
                  display: false
                },
                ticks: {
                  display: true,
                  color: '#374151',
                  font: {
                    size: 11,
                    family: 'Inter, sans-serif'
                  },
                  callback: (val, index) => labels[index] || ''
                }
              }
            }
          ) : {},


          elements: {
            bar: {
              borderRadius: 6,
              hoverBackgroundColor: '#ff7900',
              hoverBorderColor: '#ff7900',
              borderWidth: 0
            }
          },

          animation: {
            duration: 400,
            easing: 'easeOutQuart'
          }
        },
        plugins: [ChartDataLabels]
      });

      chartInstances[cfg.id] = chart;
    }

    function formatarValor(valor, formato) {
      if (formato === 'percent') return valor + '%';
      return valor;
    }

    document.querySelectorAll('.tipoGrafico').forEach(sel => {
      sel.addEventListener('change', e => {
        const id = 'grafico' + e.target.dataset.grafico;
        const novoTipo = e.target.value;
        const cfg = chartConfigs.find(c => c.id === id);
        if (cfg) {
          cfg.tipo = novoTipo;
          renderChart(cfg);
        }
      });
    });

    document.querySelectorAll('.baixarGrafico').forEach(btn => {
      btn.addEventListener('click', e => {
        const id = 'grafico' + e.target.closest('button').dataset.grafico;
        const chart = chartInstances[id];
        if (!chart) return;

        const link = document.createElement('a');
        link.download = id + '.png';
        link.href = chart.toBase64Image('image/png', 1);
        link.click();
      });
    });
  });
</script>
</body>

</html>