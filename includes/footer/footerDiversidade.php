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
        <?php foreach ($graficosDiversidade as $i => $g): ?> {
            id: 'graficoDiv<?= $i ?>',
            tipo: '<?= $g['tipo'] ?>',
            formato: '<?= $g['formato'] ?? 'numero' ?>',
            labels: <?= json_encode($g['dados']['labels'] ?? [], JSON_UNESCAPED_UNICODE) ?>,
            datasets: <?= json_encode($g['dados']['datasets'] ?? [], JSON_UNESCAPED_UNICODE) ?>
        },
        <?php endforeach; ?>
    ];

    console.log("Configurações carregadas:", chartConfigs);

    chartConfigs.forEach(cfg => renderChart(cfg));

    function renderChart(cfg) {
        console.log("Renderizando:", cfg.id);

        const el = document.getElementById(cfg.id);
        if (!el) {
            console.error("ERRO: Canvas não encontrado:", cfg.id);
            return;
        }

        if (chartInstances[cfg.id]) chartInstances[cfg.id].destroy();

        el.style.minHeight = "320px";
        el.style.paddingTop = "0px";

        const labels = (cfg.labels || []).map(l => l || "");
        const datasets = cfg.datasets && cfg.datasets.length
            ? cfg.datasets.map((d, i) => ({
                label: d.label,
                data: d.data.map(v => parseFloat(v) || 0),
                backgroundColor: gerarCor(i),
                borderColor: gerarCor(i),
                borderWidth: 2,
                fill: cfg.tipo !== "line",
                tension: 0.3
            }))
            : [{ label: "", data: [], backgroundColor: "#ccc" }];

        const totalValores = datasets.reduce(
            (acc, ds) => acc + ds.data.reduce((a, v) => a + v, 0),
            0
        );

        if (totalValores === 0) {
            console.warn(`Sem dados para ${cfg.id}`);

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

        const isBar = ["bar"].includes(cfg.tipo);

        const chart = new Chart(el, {
            type: cfg.tipo,
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: "nearest", intersect: true },
                layout: { padding: { top: 20, bottom: 0 } },
                plugins: {
                    legend: {
                        display: true,
                        position: "bottom",
                        labels: {
                            boxWidth: 14,
                            boxHeight: 14,
                            padding: 10,
                            color: "#374151",
                            font: { size: 12, family: "Inter, sans-serif" },
                            usePointStyle: true,
                            pointStyle: "circle"
                        }
                    },
                    tooltip: {
                        backgroundColor: "#fff",
                        titleColor: "#001830",
                        bodyColor: "#2d2d2dff",
                        borderColor: "#fff",
                        borderWidth: 1,
                        cornerRadius: 6,
                        padding: 10,
                        callbacks: {
                            label: ctx =>
                                `${ctx.dataset.label}: ${formatarValor(ctx.formattedValue, cfg.formato)}`
                        }
                    },
                    datalabels: {
                        color: cfg.tipo === "line" ? "#374151" : "#fff",
                        anchor: cfg.tipo === "line" ? "end" : "center",
                        align: cfg.tipo === "line" ? "top" : "center",
                        font: { weight: "bold", size: 11 },
                        formatter: value => (value > 0 ? formatarValor(value, cfg.formato) : "")
                    }
                },
                scales: cfg.tipo === "bar" ? {
                    x: { stacked: false, grid: { display: false } },
                    y: { stacked: false, ticks: { display: false }, grid: { display: false } }
                } : {}
            },
            plugins: [ChartDataLabels]
        });

        chartInstances[cfg.id] = chart;
    }

    function gerarCor(i) {
        const paleta = ['#1374a5', '#ff7900', '#032e44', '#80b6d8', '#99a1b9', '#001830', '#23a550', '#f7b924'];
        return paleta[i % paleta.length];
    }

    function formatarValor(valor, formato) {
        if (formato === "percent") return valor + "%";
        return valor;
    }

    document.querySelectorAll(".tipoGrafico").forEach(select => {
        select.addEventListener("change", e => {
            const id = "grafico" + e.target.dataset.grafico;
            const cfg = chartConfigs.find(c => c.id === id);
            if (!cfg) return;
            cfg.tipo = e.target.value;
            renderChart(cfg);
        });
    });

    document.querySelectorAll(".baixarGrafico").forEach(btn => {
        btn.addEventListener("click", e => {
            const button = e.target.closest("button");
            if (!button) return;
            const id = "grafico" + button.dataset.grafico;
            const chart = chartInstances[id];
            if (!chart) return;

            const link = document.createElement("a");
            link.download = id + ".png";
            link.href = chart.toBase64Image("image/png", 1);
            link.click();
        });
    });
});
</script>

</body>
</html>
