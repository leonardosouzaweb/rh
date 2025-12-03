<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: index.php');
  exit;
}

require_once __DIR__ . '/includes/conexao.php';
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

// === Detecta tabelas mensais ===
$tabelasMes = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
  $nome = strtolower($row[0]);
  if (preg_match('/^(janeiro|fevereiro|marco|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)$/i', $nome)) {
    $tabelasMes[] = $nome;
  }
}

$mesesOrdenados = [
  'janeiro','fevereiro','marco','abril','maio','junho',
  'julho','agosto','setembro','outubro','novembro','dezembro'
];

// === Último ano existente ===
$ultimoAno = date('Y');
$anos = [];
foreach ($tabelasMes as $tab) {
  $r = $pdo->query("
    SELECT MAX(YEAR(STR_TO_DATE(dataadmissao, '%d/%m/%Y'))) AS a1,
           MAX(YEAR(STR_TO_DATE(datadesligamento, '%d/%m/%Y'))) AS a2
    FROM `$tab`
  ")->fetch(PDO::FETCH_ASSOC);
  if ($r) {
    if ($r['a1']) $anos[] = $r['a1'];
    if ($r['a2']) $anos[] = $r['a2'];
  }
}
if ($anos) $ultimoAno = max($anos);

// === Filtros ===
$mesAdm = $_GET['mesAdm'] ?? '';
$anoAdm = $_GET['anoAdm'] ?? $ultimoAno;
$mesDesl = $_GET['mesDesl'] ?? '';
$anoDesl = $_GET['anoDesl'] ?? $ultimoAno;

// === Admissões ===
$dadosAdm = [];
$totalAdm = 0;
if ($mesAdm && in_array($mesAdm, $tabelasMes)) {
  $mesNumero = array_search(strtolower($mesAdm), $mesesOrdenados) + 1;
  $sql = "
    SELECT nomecolaborador, dataadmissao, diretoria, cargo
    FROM `$mesAdm`
    WHERE dataadmissao IS NOT NULL AND dataadmissao <> ''
      AND YEAR(STR_TO_DATE(dataadmissao, '%d/%m/%Y')) = :ano
      AND MONTH(STR_TO_DATE(dataadmissao, '%d/%m/%Y')) = :mes
    ORDER BY STR_TO_DATE(dataadmissao, '%d/%m/%Y')
  ";
  $st = $pdo->prepare($sql);
  $st->execute(['ano' => $anoAdm, 'mes' => $mesNumero]);
  $dadosAdm = $st->fetchAll(PDO::FETCH_ASSOC);
  $totalAdm = count($dadosAdm);
}

// === Desligamentos ===
$dadosDesl = [];
$totalDesl = 0;
$totalAtivos = 0;
if ($mesDesl && in_array($mesDesl, $tabelasMes)) {
  $mesNumeroDesl = array_search(strtolower($mesDesl), $mesesOrdenados) + 1;
  $sql = "
    SELECT nomecolaborador, dataadmissao, datadesligamento, diretoria, cargo, turnover, statusmes
    FROM `$mesDesl`
    WHERE datadesligamento IS NOT NULL AND datadesligamento <> ''
      AND YEAR(STR_TO_DATE(datadesligamento, '%d/%m/%Y')) = :ano
      AND MONTH(STR_TO_DATE(datadesligamento, '%d/%m/%Y')) = :mes
    ORDER BY STR_TO_DATE(datadesligamento, '%d/%m/%Y')
  ";
  $st = $pdo->prepare($sql);
  $st->execute(['ano' => $anoDesl, 'mes' => $mesNumeroDesl]);
  $dadosDesl = $st->fetchAll(PDO::FETCH_ASSOC);
  $totalDesl = count($dadosDesl);

  // Total de ativos
  $ativosQuery = $pdo->prepare("SELECT COUNT(*) FROM `$mesDesl` WHERE statusmes = 'Ativo'");
  $ativosQuery->execute();
  $totalAtivos = $ativosQuery->fetchColumn() ?: 0;
}

// === Indicadores ===
$admStatus = 'Baixa admissão';
$admCor = 'bg-yellow-100 text-yellow-700';
$admIcon = 'ph ph-info';
if ($totalAdm > 20) {
  $admStatus = 'Crescimento saudável';
  $admCor = 'bg-green-100 text-green-700';
  $admIcon = 'ph ph-check-circle';
} elseif ($totalAdm == 0) {
  $admStatus = 'Sem admissões';
  $admCor = 'bg-gray-100 text-gray-700';
  $admIcon = 'ph ph-prohibit';
}

// Turnover
$turnoverTaxa = 0;
$turnoverCor = 'bg-green-100 text-green-700';
$turnoverTexto = 'Baixo turnover';
$turnoverIcon = 'ph ph-check-circle';
if ($totalAtivos > 0) {
  $turnoverTaxa = round(($totalDesl / $totalAtivos) * 100, 1);
  if ($turnoverTaxa > 20) {
    $turnoverCor = 'bg-red-100 text-red-700';
    $turnoverTexto = 'Turnover crítico';
    $turnoverIcon = 'ph ph-warning-circle';
  } elseif ($turnoverTaxa > 10) {
    $turnoverCor = 'bg-orange-100 text-orange-700';
    $turnoverTexto = 'Turnover alto (risco)';
    $turnoverIcon = 'ph ph-warning';
  } elseif ($turnoverTaxa > 5) {
    $turnoverCor = 'bg-yellow-100 text-yellow-700';
    $turnoverTexto = 'Turnover moderado';
    $turnoverIcon = 'ph ph-info';
  }
}
?>

<div class="w-full mx-auto space-y-6">
  <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <h1 class="text-3xl font-bold text-gray-700">Relatórios</h1>
  </header>

  <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

    <!-- Admissões -->
    <section class="bg-white rounded-2xl shadow-md border border-gray-200 p-6">
      <h2 class="text-xl font-semibold text-gray-700 mb-4 flex items-center gap-2">
        Admissões
      </h2>

      <form method="get" class="flex flex-wrap items-end gap-3">
        <div class="dropdown w-60">
          <label class="block text-gray-700 text-sm font-medium mb-1">Escolha o mês</label>
          <label tabindex="0" class="btn w-full justify-between flex items-center gap-2">
            <span style="font-size:16px;"><?= $mesAdm ? ucfirst($mesAdm) : 'Selecione' ?></span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </label>
          <div tabindex="0" class="dropdown-content z-[1] bg-base-100 rounded-box shadow w-60 max-h-64 overflow-y-auto">
            <ul class="menu p-2">
              <?php foreach ($mesesOrdenados as $m): ?>
                <li>
                  <label class="cursor-pointer flex justify-between items-center">
                    <span style="font-size:16px;"><?= ucfirst($m) ?></span>
                    <input type="radio" name="mesAdm" value="<?= $m ?>" class="radio radio-primary"
                      <?= $mesAdm === $m ? 'checked' : '' ?>>
                  </label>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>

        <div>
          <label class="block text-gray-700 text-sm font-medium mb-1">Ano</label>
          <select name="anoAdm" class="select select-bordered w-32" style="font-size:16px;">
            <?php for ($a = $ultimoAno; $a >= $ultimoAno - 5; $a--): ?>
              <option value="<?= $a ?>" <?= $anoAdm == $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <button type="submit" class="btn btn-success" style="color:#fff;">Gerar Relatório</button>
      </form>

      <?php if ($mesAdm): ?>
        <div class="flex justify-between items-center mt-4 mb-2">
          <h3 class="text-lg font-medium text-gray-700">Resultados - <?= ucfirst($mesAdm) ?>/<?= $anoAdm ?></h3>
          <button id="pdfAdm" class="btn btn-sm btn-outline btn-accent">Baixar PDF</button>
        </div>

        <?php if ($dadosAdm): ?>
          <div class="overflow-x-auto">
            <table id="tabelaAdm" class="table table-zebra w-full text-sm">
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>Data de Admissão</th>
                  <th>Diretoria</th>
                  <th>Cargo</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($dadosAdm as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['nomecolaborador']) ?></td>
                  <td><?= htmlspecialchars($r['dataadmissao']) ?></td>
                  <td><?= htmlspecialchars($r['diretoria']) ?></td>
                  <td><?= htmlspecialchars($r['cargo']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-gray-500 text-sm mt-3">Nenhum registro encontrado.</p>
        <?php endif; ?>

      <?php else: ?>
        <div class="flex flex-col items-center justify-center py-24 text-gray-400 animate-fadeIn">
          <i class="ph ph-calendar-blank text-6xl text-[#f78e23] mb-3"></i>
          <p class="text-lg font-medium">Selecione um mês para visualizar as admissões</p>
        </div>
      <?php endif; ?>
    </section>

    <!-- Desligamentos -->
    <section class="bg-white rounded-2xl shadow-md border border-gray-200 p-6">
      <h2 class="text-xl font-semibold text-gray-700 mb-4 flex items-center gap-2">
        Desligamentos
      </h2>

      <form method="get" class="flex flex-wrap items-end gap-3">
        <div class="dropdown w-60">
          <label class="block text-gray-700 text-sm font-medium mb-1">Escolha o mês</label>
          <label tabindex="0" class="btn w-full justify-between flex items-center gap-2">
            <span style="font-size:16px;"><?= $mesDesl ? ucfirst($mesDesl) : 'Selecione' ?></span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </label>
          <div tabindex="0" class="dropdown-content z-[1] bg-base-100 rounded-box shadow w-60 max-h-64 overflow-y-auto">
            <ul class="menu p-2">
              <?php foreach ($mesesOrdenados as $m): ?>
                <li>
                  <label class="cursor-pointer flex justify-between items-center">
                    <span style="font-size:16px;"><?= ucfirst($m) ?></span>
                    <input type="radio" name="mesDesl" value="<?= $m ?>" class="radio radio-primary"
                      <?= $mesDesl === $m ? 'checked' : '' ?>>
                  </label>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>

        <div>
          <label class="block text-gray-700 text-sm font-medium mb-1">Ano</label>
          <select name="anoDesl" class="select select-bordered w-32" style="font-size:16px;">
            <?php for ($a = $ultimoAno; $a >= $ultimoAno - 5; $a--): ?>
              <option value="<?= $a ?>" <?= $anoDesl == $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <button type="submit" class="btn btn-success" style="color:#fff;">Gerar Relatório</button>
      </form>

      <?php if ($mesDesl): ?>
        <div class="flex justify-between items-center mt-4 mb-2">
          <h3 class="text-lg font-medium text-gray-700">Resultados - <?= ucfirst($mesDesl) ?>/<?= $anoDesl ?></h3>
          <button id="pdfDesl" class="btn btn-sm btn-outline btn-accent">Baixar PDF</button>
        </div>

        <?php if ($dadosDesl): ?>
          <div class="overflow-x-auto">
            <table id="tabelaDesl" class="table table-zebra w-full text-sm">
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>Data de Admissão</th>
                  <th>Data de Demissão</th>
                  <th>Diretoria</th>
                  <th>Cargo</th>
                  <th>Turnover</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($dadosDesl as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['nomecolaborador']) ?></td>
                  <td><?= htmlspecialchars($r['dataadmissao']) ?></td>
                  <td><?= htmlspecialchars($r['datadesligamento']) ?></td>
                  <td><?= htmlspecialchars($r['diretoria']) ?></td>
                  <td><?= htmlspecialchars($r['cargo']) ?></td>
                  <td><?= htmlspecialchars($r['turnover']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-gray-500 text-sm mt-3">Nenhum registro encontrado.</p>
        <?php endif; ?>

      <?php else: ?>
        <div class="flex flex-col items-center justify-center py-24 text-gray-400 animate-fadeIn">
          <i class="ph ph-calendar-x text-6xl text-[#f78e23] mb-3"></i>
          <p class="text-lg font-medium">Selecione um mês para visualizar os desligamentos</p>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

<script>
const { jsPDF } = window.jspdf;

function gerarPDF(idTabela, titulo, mes, ano, nomeArquivo) {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

  doc.autoTable({
    html: idTabela,
    startY: 30,
    styles: { fontSize: 9 },
    margin: { top: 30, bottom: 20 },
    didDrawPage: function () {
      const pageHeight = doc.internal.pageSize.height;
      const pageWidth = doc.internal.pageSize.width;
      const pageNumber = doc.internal.getNumberOfPages();

      doc.addImage('./assets/images/logo.png', 'PNG', 12, 6, 45, 13);
      doc.setFontSize(12);
      doc.text(titulo + ' - ' + mes + '/' + ano, pageWidth / 2, 16, { align: 'center' });
      doc.setFontSize(9);
      doc.text('Página ' + pageNumber, pageWidth - 15, 16, { align: 'right' });

      const footerText =
        'Documento confidencial – Uso restrito e protegido. Este relatório contém informações estratégicas, sensíveis e de propriedade exclusiva da Artesanal Investimentos. ' +
        'Sua divulgação, cópia ou compartilhamento, total ou parcial, é expressamente proibida sem autorização formal e escrita da Diretoria. ' +
        'O descumprimento desta política poderá resultar em medidas administrativas, civis e legais conforme as normas internas de compliance e a legislação vigente sobre sigilo corporativo e proteção de dados.';

      doc.setFontSize(8);
      doc.setTextColor(80, 80, 80);
      doc.text(footerText, 15, pageHeight - 10, { maxWidth: pageWidth - 30 });
    },
  });

  doc.save(nomeArquivo);
}

document.getElementById('pdfAdm')?.addEventListener('click', () => {
  gerarPDF('#tabelaAdm', 'Relatório de Admissões', '<?= ucfirst($mesAdm) ?>', '<?= $anoAdm ?>', 'Admissoes_<?= ucfirst($mesAdm) ?>_<?= $anoAdm ?>.pdf');
});

document.getElementById('pdfDesl')?.addEventListener('click', () => {
  gerarPDF('#tabelaDesl', 'Relatório de Desligamentos', '<?= ucfirst($mesDesl) ?>', '<?= $anoDesl ?>', 'Desligamentos_<?= ucfirst($mesDesl) ?>_<?= $anoDesl ?>.pdf');
});
</script>

<?php include __DIR__ . '/includes/footer/footer.php'; ?>
