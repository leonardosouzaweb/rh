<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: index.php');
  exit;
}

require_once __DIR__ . '/includes/conexao.php';
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

// ================================================
// === Detecta tabelas mensais existentes no BD ===
// ================================================
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

// ====================================================
// === Determinar o último ano existente nos dados  ===
// ====================================================
$ultimoAno = date('Y');
$anos = [];

foreach ($tabelasMes as $tab) {
  $r = $pdo->query("
    SELECT 
        MAX(YEAR(STR_TO_DATE(dataadmissao, '%d/%m/%Y'))) AS a1,
        MAX(YEAR(STR_TO_DATE(datadesligamento, '%d/%m/%Y'))) AS a2
    FROM `$tab`
  ")->fetch(PDO::FETCH_ASSOC);

  if ($r) {
    if ($r['a1']) $anos[] = $r['a1'];
    if ($r['a2']) $anos[] = $r['a2'];
  }
}

if ($anos) {
  $ultimoAno = max($anos);
}

// =======================
// === Filtros GET    ===
// =======================
$mesAdm = $_GET['mesAdm'] ?? '';
$anoAdm = $_GET['anoAdm'] ?? $ultimoAno;

$mesDesl = $_GET['mesDesl'] ?? '';
$anoDesl = $_GET['anoDesl'] ?? $ultimoAno;

// ===============================================
// === ADMISSÕES — sempre usando tabela admissoes
// ===============================================
$dadosAdm = [];
if ($mesAdm && in_array($mesAdm, $tabelasMes)) {

  $mesNumero = array_search(strtolower($mesAdm), $mesesOrdenados) + 1;

  $sql = "
      SELECT 
          nomecolaborador,
          dataadmissao,
          diretoria,
          cargo
      FROM admissoes
      WHERE 
          dataadmissao IS NOT NULL 
          AND dataadmissao <> ''
          AND YEAR(
              CASE 
                  WHEN SUBSTRING_INDEX(dataadmissao,'/',1) <= 12 
                      THEN STR_TO_DATE(dataadmissao, '%m/%d/%Y')
                  ELSE STR_TO_DATE(dataadmissao, '%d/%m/%Y')
              END
          ) = :ano
          AND MONTH(
              CASE 
                  WHEN SUBSTRING_INDEX(dataadmissao,'/',1) <= 12 
                      THEN STR_TO_DATE(dataadmissao, '%m/%d/%Y')
                  ELSE STR_TO_DATE(dataadmissao, '%d/%m/%Y')
              END
          ) = :mes
      ORDER BY 
          CASE 
              WHEN SUBSTRING_INDEX(dataadmissao,'/',1) <= 12 
                  THEN STR_TO_DATE(dataadmissao, '%m/%d/%Y')
              ELSE STR_TO_DATE(dataadmissao, '%d/%m/%Y')
          END
  ";

  $st = $pdo->prepare($sql);
  $st->execute(['ano' => $anoAdm, 'mes' => $mesNumero]);
  $dadosAdm = $st->fetchAll(PDO::FETCH_ASSOC);
}

// =======================================================
// === DESLIGAMENTOS — sempre usando tabela desligamentos
// =======================================================
$dadosDesl = [];

if ($mesDesl && in_array($mesDesl, $tabelasMes)) {

  $mesNumeroDesl = array_search(strtolower($mesDesl), $mesesOrdenados) + 1;

  $sql = "
      SELECT 
          nomecolaborador,
          dataadmissao,
          datadesligamento,
          diretoria,
          cargo,
          turnover,
          statusmes
      FROM desligamentos
      WHERE 
          datadesligamento IS NOT NULL 
          AND datadesligamento <> ''
          AND YEAR(
              CASE 
                  WHEN SUBSTRING_INDEX(datadesligamento,'/',1) <= 12 
                      THEN STR_TO_DATE(datadesligamento, '%m/%d/%Y')
                  ELSE STR_TO_DATE(datadesligamento, '%d/%m/%Y')
              END
          ) = :ano
          AND MONTH(
              CASE 
                  WHEN SUBSTRING_INDEX(datadesligamento,'/',1) <= 12 
                      THEN STR_TO_DATE(datadesligamento, '%m/%d/%Y')
                  ELSE STR_TO_DATE(datadesligamento, '%d/%m/%Y')
              END
          ) = :mes
      ORDER BY 
          CASE 
              WHEN SUBSTRING_INDEX(datadesligamento,'/',1) <= 12 
                  THEN STR_TO_DATE(datadesligamento, '%m/%d/%Y')
              ELSE STR_TO_DATE(datadesligamento, '%d/%m/%Y')
          END
  ";

  $st = $pdo->prepare($sql);
  $st->execute(['ano' => $anoDesl, 'mes' => $mesNumeroDesl]);
  $dadosDesl = $st->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!-- === HTML DA PÁGINA (SEM INDICADORES) === -->

<div class="w-full mx-auto space-y-6">
  <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <h1 class="text-3xl font-bold text-gray-700">Relatórios</h1>
  </header>

  <div class="grid grid-cols-1 xl:grid-cols-2 gap-6" style="margin-bottom:100px;">

    <!-- ==================================================== -->
    <!-- === ADMISSÕES ======================================= -->
    <!-- ==================================================== -->

    <section class="bg-white rounded-2xl shadow-md border border-gray-200 p-6">
      <h2 class="text-xl font-semibold text-gray-700 mb-4 flex items-center gap-2">
        Admissões
      </h2>

      <form method="get" class="flex flex-wrap items-end gap-3 justify-between">

        <div class="dropdown" style="width:33%">
          <label class="block text-gray-700 text-sm font-medium mb-1">Escolha o mês</label>
          <label tabindex="0" class="btn w-full justify-between flex items-center gap-2">
            <span style="font-size:16px;"><?= $mesAdm ? ucfirst($mesAdm) : 'Selecione' ?></span>
          </label>
          <div tabindex="0" class="dropdown-content z-[1] bg-base-100 rounded-box shadow w-60 max-h-64 overflow-y-auto">
            <ul class="menu p-2">
              <?php foreach ($mesesOrdenados as $m): ?>
                <li>
                  <label class="cursor-pointer flex justify-between items-center">
                    <span><?= ucfirst($m) ?></span>
                    <input type="radio" name="mesAdm" value="<?= $m ?>" class="radio radio-primary"
                      <?= $mesAdm === $m ? 'checked' : '' ?>>
                  </label>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>

        <div style="width:33%">
          <label class="block text-gray-700 text-sm font-medium mb-1">Ano</label>
          <select name="anoAdm" class="select select-bordered w-full">
            <?php for ($a = $ultimoAno; $a >= $ultimoAno - 5; $a--): ?>
              <option value="<?= $a ?>" <?= $anoAdm == $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <button type="submit" class="btn btn-success w-[30%]" style="color:#fff;">
          Gerar Relatório
        </button>
      </form>

      <?php if ($mesAdm): ?>
        <div class="flex justify-between items-center mt-4 mb-2">
          <h3 class="text-lg font-medium">Resultados - <?= ucfirst($mesAdm) ?>/<?= $anoAdm ?></h3>
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
          <p class="text-gray-500 mt-3">Nenhum registro encontrado.</p>
        <?php endif; ?>

      <?php endif; ?>
    </section>

    <!-- ==================================================== -->
    <!-- === DESLIGAMENTOS ================================== -->
    <!-- ==================================================== -->

    <section class="bg-white rounded-2xl shadow-md border border-gray-200 p-6">
      <h2 class="text-xl font-semibold text-gray-700 mb-4 flex items-center gap-2">
        Desligamentos
      </h2>

      <form method="get" class="flex flex-wrap items-end gap-3">

        <div class="dropdown" style="width:33%">
          <label class="block text-gray-700 text-sm font-medium mb-1">Escolha o mês</label>
          <label tabindex="0" class="btn w-full justify-between flex items-center gap-2">
            <span><?= $mesDesl ? ucfirst($mesDesl) : 'Selecione' ?></span>
          </label>
          <div tabindex="0" class="dropdown-content z-[1] bg-base-100 rounded-box shadow w-60 max-h-64 overflow-y-auto">
            <ul class="menu p-2">
              <?php foreach ($mesesOrdenados as $m): ?>
                <li>
                  <label class="cursor-pointer flex justify-between items-center">
                    <span><?= ucfirst($m) ?></span>
                    <input type="radio" name="mesDesl" value="<?= $m ?>" class="radio radio-primary"
                      <?= $mesDesl === $m ? 'checked' : '' ?>>
                  </label>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>

        <div style="width:33%">
          <label class="block text-gray-700 text-sm font-medium mb-1">Ano</label>
          <select name="anoDesl" class="select select-bordered w-full">
            <?php for ($a = $ultimoAno; $a >= $ultimoAno - 5; $a--): ?>
              <option value="<?= $a ?>" <?= $anoDesl == $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <button type="submit" class="btn btn-success w-[30%]" style="color:#fff;">
          Gerar Relatório
        </button>

      </form>

      <?php if ($mesDesl): ?>
        <div class="flex justify-between items-center mt-4 mb-2">
          <h3 class="text-lg font-medium">Resultados - <?= ucfirst($mesDesl) ?>/<?= $anoDesl ?></h3>
          <button id="pdfDesl" class="btn btn-sm btn-outline btn-accent">Baixar PDF</button>
        </div>

        <?php if ($dadosDesl): ?>
          <div class="overflow-x-auto">
            <table id="tabelaDesl" class="table table-zebra w-full text-sm">
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>Admissão</th>
                  <th>Desligamento</th>
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
          <p class="text-gray-500 mt-3">Nenhum registro encontrado.</p>
        <?php endif; ?>

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
