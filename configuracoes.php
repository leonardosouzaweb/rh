<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: index.php');
  exit;
}

require_once __DIR__ . '/includes/conexao.php';
include __DIR__ . '/includes/header.php';

$usuario_id = $_SESSION['usuario_id'];
$mensagem = "";
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// Exclusão
if (isset($_GET['deletar'])) {
  $tabela = preg_replace('/[^a-z0-9_]/', '', $_GET['deletar']);
  $stmt = $pdo->prepare("SELECT nome_arquivo FROM importacoes WHERE tabela_criada = ?");
  $stmt->execute([$tabela]);
  $arquivo = $stmt->fetchColumn();
  if ($arquivo && file_exists("$uploadDir/$arquivo")) unlink("$uploadDir/$arquivo");
  $pdo->exec("DROP TABLE IF EXISTS `$tabela`");
  $pdo->prepare("DELETE FROM importacoes WHERE tabela_criada = ?")->execute([$tabela]);
  $mensagem = "Tabela <strong>$tabela</strong> e arquivo excluídos com sucesso.";
}

// Upload CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
  if ($_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
    $nomeOriginal = basename($_FILES['arquivo']['name']);
    $tmp = $_FILES['arquivo']['tmp_name'];
    $destino = "$uploadDir/$nomeOriginal";
    move_uploaded_file($tmp, $destino);

    $base = pathinfo($nomeOriginal, PATHINFO_FILENAME);
    $base = iconv('UTF-8', 'ASCII//TRANSLIT', $base);
    $base = strtolower($base);
    $tabela = preg_replace('/[^a-z0-9_]/', '_', trim($base));

    $handle = fopen($destino, 'r');
    $primeiraLinha = fgets($handle);
    rewind($handle);
    $delimitador = strpos($primeiraLinha, ';') !== false ? ';' : (strpos($primeiraLinha, ',') !== false ? ',' : "\t");
    $cabecalhos = fgetcsv($handle, 0, $delimitador);
    $cabecalhos = array_map('trim', $cabecalhos);

    foreach ($cabecalhos as $i => $coluna) {
      if ($coluna === '' || $coluna === null) $coluna = "coluna_$i";
      $coluna = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($coluna));
      if (strlen($coluna) > 50) $coluna = substr($coluna, 0, 50) . "_$i";
      $cabecalhos[$i] = $coluna;
    }

    $cols = array_map(fn($c) => "`$c` TEXT", $cabecalhos);
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$tabela` (id INT AUTO_INCREMENT PRIMARY KEY," . implode(',', $cols) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare("INSERT INTO `$tabela` (`" . implode('`,`', $cabecalhos) . "`) VALUES (" . rtrim(str_repeat('?,', count($cabecalhos)), ',') . ")");
    $total = 0;
    while (!feof($handle)) {
      if ($linha = fgetcsv($handle, 0, $delimitador)) $total++;
    }
    rewind($handle);
    fgetcsv($handle, 0, $delimitador);
    $processadas = 0;

    function converterMes($valor) {
    if (!$valor) return '';

    $valor = strtolower(trim($valor));

    // Mapas aceitos
    $mapa = [
        'jan.' => '01', 'janeiro' => '01',
        'fev.' => '02', 'fevereiro' => '02',
        'mar.' => '03', 'marco' => '03', 'março' => '03',
        'abr.' => '04', 'abril' => '04',
        'mai.' => '05', 'maio' => '05',
        'jun.' => '06', 'junho' => '06',
        'jul.' => '07', 'julho' => '07',
        'ago.' => '08', 'agosto' => '08',
        'set.' => '09', 'setembro' => '09',
        'out.' => '10', 'outubro' => '10',
        'nov.' => '11', 'novembro' => '11',
        'dez.' => '12', 'dezembro' => '12'
    ];

    // Ex: jan.-24
    if (preg_match('/([a-z\.]+)-(\d{2})/i', $valor, $m)) {
        $mes = $m[1];
        $ano = '20' . $m[2];
        $numMes = $mapa[$mes] ?? null;
        if ($numMes) return "$numMes/$ano";
    }

    // Ex: janeiro-2024
    if (preg_match('/([a-zç]+)-(\d{4})/i', $valor, $m)) {
        $mes = $m[1];
        $ano = $m[2];
        $numMes = $mapa[$mes] ?? null;
        if ($numMes) return "$numMes/$ano";
    }

    return $valor;
}

while (($linha = fgetcsv($handle, 0, $delimitador)) !== false) {
    $linha = array_map(fn($v) => trim($v ?? ''), $linha);
    if (!array_filter($linha)) continue;

    // === CONVERSÃO DO CAMPO MES AUTOMÁTICA ===
    foreach ($cabecalhos as $i => $nomeColuna) {
    // qualquer coluna que contenha "mes" no nome será convertida
    if (strpos($nomeColuna, 'mes') !== false) {
        $linha[$i] = converterMes($linha[$i]);
    }
}

    try {
        $stmt->execute($linha);
        $processadas++;
    } catch (Exception $e) {}
}

    fclose($handle);

    // === APLICA LGPD: gera matricula, criptografa e mascara CPF ===
    try {
      $temCpf = $pdo->query("SHOW COLUMNS FROM `$tabela` LIKE 'cpf'")->rowCount() > 0;
      if ($temCpf) {
        if ($pdo->query("SHOW COLUMNS FROM `$tabela` LIKE 'matricula'")->rowCount() == 0) {
          $pdo->exec("ALTER TABLE `$tabela` ADD COLUMN `matricula` VARCHAR(50) AFTER `id`");
        }
        if ($pdo->query("SHOW COLUMNS FROM `$tabela` LIKE 'cpf_enc'")->rowCount() == 0) {
          $pdo->exec("ALTER TABLE `$tabela` ADD COLUMN `cpf_enc` TEXT DEFAULT NULL");
        }

        $temData = $pdo->query("SHOW COLUMNS FROM `$tabela` LIKE 'datanascimento'")->rowCount() > 0;

        if ($temData) {
          $pdo->exec("
            UPDATE `$tabela`
            SET matricula = CONCAT(
              RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ',''),' ',''),3),
              LEFT(REPLACE(REPLACE(REPLACE(datanascimento,'/',''),'-',''),'.',''),2)
            )
            WHERE cpf IS NOT NULL AND datanascimento IS NOT NULL
          ");
        } else {
          $pdo->exec("
            UPDATE `$tabela`
            SET matricula = RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ',''),' ',''),3)
            WHERE cpf IS NOT NULL
          ");
        }

        $chave = 'Cqkc8SChPPeJ91w8X/3IRkVj0+seeb8D9Rlj6bfUQEg=';
        $pdo->exec("
          UPDATE `$tabela`
          SET cpf_enc = TO_BASE64(AES_ENCRYPT(REPLACE(REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ',''),' ',''), '$chave'))
          WHERE cpf IS NOT NULL
        ");

        $pdo->exec("
          UPDATE `$tabela`
          SET cpf = CONCAT('***.***.', RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ',''),' ',''),3))
          WHERE cpf IS NOT NULL
        ");
      }
    } catch (Exception $e) {
      error_log('Erro LGPD na tabela ' . $tabela . ': ' . $e->getMessage());
    }

    $pdo->prepare("INSERT INTO importacoes (usuario_id, nome_arquivo, tabela_criada, linhas_importadas, total_linhas)
      VALUES (?, ?, ?, ?, ?)")
      ->execute([$usuario_id, $nomeOriginal, $tabela, $processadas, $total]);

    $mensagem = "Importação concluída: <strong>$processadas</strong> linhas adicionadas à tabela <strong>$tabela</strong>.";
  } else {
    $mensagem = "Erro ao fazer upload do arquivo.";
  }
}

// Histórico
$stmt = $pdo->query("
  SELECT i.*, u.nome AS usuario
  FROM importacoes i
  JOIN usuarios u ON u.id = i.usuario_id
  ORDER BY i.data_importacao DESC
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="w-full mx-auto space-y-6">
  <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <h1 class="text-3xl font-bold text-gray-700 flex items-center gap-2">
      Configurações
    </h1>
  </header>

  <?php if ($mensagem): ?>
    <div id="alertaMsg" class="alert alert-success shadow-lg flex items-center gap-2 transition-opacity duration-700">
      <i class="ph ph-check-circle text-lg"></i>
      <span><?= $mensagem ?></span>
    </div>
    <script>
      setTimeout(() => {
        const alerta = document.getElementById("alertaMsg");
        if (alerta) alerta.classList.add("opacity-0");
        setTimeout(() => location.href = "configuracoes.php", 800);
      }, 2500);
    </script>
  <?php endif; ?>

  <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-stretch">
    <!-- Upload CSV -->
    <section class="bg-base-100 rounded-2xl shadow-md border border-base-300 p-6 flex flex-col justify-between h-full">
      <div class="flex flex-col flex-grow justify-center">
        <h2 class="text-xl font-semibold mb-4 text-gray-700 flex items-center gap-2">
          Importar Novo CSV
        </h2>

        <form id="uploadForm" method="post" enctype="multipart/form-data" class="flex flex-col flex-grow">
          <div id="dropZone"
            class="border-2 border-dashed border-gray-300 rounded-xl flex flex-col items-center justify-center flex-grow text-center p-8 transition hover:border-[#f78e23] hover:bg-orange-50 cursor-pointer min-h-[380px] relative">
            <i class="ph ph-cloud-arrow-up text-6xl text-[#f78e23] mb-3"></i>
            <p class="text-gray-600 font-medium">Arraste e solte o arquivo CSV aqui</p>
            <p class="text-gray-400 text-sm mb-3">ou clique abaixo para selecionar</p>
            <label for="csvFile" class="btn btn-outline btn-primary w-full max-w-xs mx-auto mb-4">
              <i class="ph ph-upload"></i> Escolher arquivo
            </label>
            <input type="file" name="arquivo" id="csvFile" accept=".csv" class="hidden" required />

            <div id="fileInfo" class="hidden mt-4 flex flex-col items-center justify-center">
              <p class="text-gray-700 font-medium flex items-center gap-2">
                <i class="ph ph-file-csv text-[#f78e23]"></i> <span id="fileName"></span>
              </p>
              <p class="text-gray-500 text-sm" id="fileSize"></p>
              <button type="submit" form="uploadForm"
                class="btn btn-success text-white mt-4 flex items-center gap-2 justify-center">
                <i class="ph ph-play-circle"></i> Iniciar Importação
              </button>
            </div>

            <div id="loaderContainer" class="hidden absolute inset-0 bg-white/80 flex flex-col items-center justify-center rounded-xl">
              <span class="loading loading-spinner text-[#f78e23]"></span>
              <p class="mt-2 text-gray-600 font-medium">Importando arquivo...</p>
            </div>
          </div>
        </form>
      </div>
    </section>

    <!-- Histórico -->
    <section class="bg-base-100 rounded-2xl shadow-md border border-base-300 p-6 flex flex-col justify-between">
      <div>
        <h2 class="text-xl font-semibold mb-4 text-gray-700 flex items-center gap-2">
          Histórico de Importações
        </h2>

        <?php if ($logs): ?>
          <div class="overflow-x-auto max-h-[420px]">
            <table class="table table-zebra w-full text-sm">
              <thead>
                <tr>
                  <th>Usuário</th>
                  <th>Arquivo</th>
                  <th>Tabela</th>
                  <th>Linhas</th>
                  <th>Data</th>
                  <th class="text-center">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($logs as $l): ?>
                  <tr>
                    <td><?= htmlspecialchars($l['usuario']) ?></td>
                    <td><?= htmlspecialchars($l['nome_arquivo']) ?></td>
                    <td><?= htmlspecialchars($l['tabela_criada']) ?></td>
                    <td><?= "{$l['linhas_importadas']} / {$l['total_linhas']}" ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($l['data_importacao'])) ?></td>
                    <td class="flex gap-2 justify-end">
                      <button class="btn btn-sm btn-outline btn-primary verTabela flex items-center gap-1"
                        data-tabela="<?= htmlspecialchars($l['tabela_criada']) ?>">
                        <i class="ph ph-eye"></i> Ver
                      </button>
                      <a href="uploads/<?= urlencode($l['nome_arquivo']) ?>" download
                        class="btn btn-sm btn-outline btn-accent flex items-center gap-1">
                        <i class="ph ph-download-simple"></i> Baixar
                      </a>
                      <button class="btn btn-sm btn-outline btn-error deletarTabela flex items-center gap-1"
                        data-tabela="<?= htmlspecialchars($l['tabela_criada']) ?>">
                        <i class="ph ph-trash"></i> Excluir
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="flex flex-col items-center justify-center text-gray-400 py-10">
            <i class="ph ph-database text-5xl mb-2 text-[#f78e23]"></i>
            <p class="text-gray-500 text-lg font-medium">Nenhuma importação encontrada</p>
            <p class="text-sm text-gray-400">Importe um arquivo CSV para começar</p>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>

<dialog id="modalTabela" class="modal">
  <div class="modal-box max-w-6xl">
    <div id="conteudoTabela" class="overflow-x-auto"></div>
  </div>
</dialog>

<script>
  const dropZone = document.getElementById('dropZone');
  const csvInput = document.getElementById('csvFile');
  const fileInfo = document.getElementById('fileInfo');
  const fileName = document.getElementById('fileName');
  const fileSize = document.getElementById('fileSize');
  const loaderContainer = document.getElementById('loaderContainer');

  dropZone.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.classList.add('border-[#f78e23]', 'bg-orange-50');
  });

  dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('border-[#f78e23]', 'bg-orange-50');
  });

  dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('border-[#f78e23]', 'bg-orange-50');
    const file = e.dataTransfer.files[0];
    if (file && file.name.endsWith('.csv')) {
      csvInput.files = e.dataTransfer.files;
      showFileInfo(file);
    } else {
      alert('Por favor, envie um arquivo CSV válido.');
    }
  });

  csvInput.addEventListener('change', () => {
    if (csvInput.files.length > 0) showFileInfo(csvInput.files[0]);
  });

  function showFileInfo(file) {
    fileName.textContent = file.name;
    fileSize.textContent = (file.size / 1024).toFixed(2) + ' KB';
    fileInfo.classList.remove('hidden');
  }

  document.getElementById('uploadForm').addEventListener('submit', () => {
    loaderContainer.classList.remove('hidden');
  });

  document.querySelectorAll(".deletarTabela").forEach(btn => {
    btn.addEventListener("click", () => {
      const tabela = btn.dataset.tabela;
      if (confirm(`Excluir a tabela "${tabela}" e seu arquivo?`)) {
        location.href = `configuracoes.php?deletar=${encodeURIComponent(tabela)}`;
      }
    });
  });

document.querySelectorAll(".verTabela").forEach(btn => {
  btn.addEventListener("click", async () => {

    const tabela = btn.dataset.tabela;
    const modal = document.getElementById("modalTabela");
    const conteudo = document.getElementById("conteudoTabela");

    conteudo.innerHTML = `
      <div class="flex justify-center py-10">
        <span class="loading loading-spinner text-primary"></span>
      </div>`;

    modal.showModal();

    const resposta = await fetch(`includes/ver_tabela.php?tabela=${encodeURIComponent(tabela)}`);
    conteudo.innerHTML = await resposta.text();

    // Inicializa eventos após inserir HTML
    initEventosTabela();
  });
});

function initEventosTabela() {
  const modal = document.getElementById("modalTabela");
  const modalBox = modal.querySelector(".modal-box");
  const fechar = document.getElementById("btnFecharModalTabela");
  const filtro = document.getElementById("filtroTabela");
  const btnBuscar = document.getElementById("btnBuscar");
  const tabela = document.getElementById("tabelaDados");
  const contador = document.getElementById("contadorResultados");

  if (!fechar) {
    return;
  }


  // FECHAR — clique no botão
  fechar.addEventListener("click", () => {
    modal.close();
  });

  // FECHAR — clique fora
  modal.addEventListener("click", (e) => {
    const clicouFora = !modalBox.contains(e.target);

    if (clicouFora) {
      modal.close();
    }
  });

  // FILTRAR TABELA
  function filtrarTabela() {
    const termo = filtro.value.trim().toLowerCase();
    const linhas = tabela.querySelectorAll("tbody tr");
    let visiveis = 0;

    linhas.forEach(linha => {
      const coluna = linha.cells[3]?.textContent.toLowerCase() || "";
      const match = coluna.includes(termo);
      linha.style.display = match ? "" : "none";
      if (match) visiveis++;
    });

    contador.textContent = `Exibindo ${visiveis} de ${tabela.rows.length - 1} registros`;
  }

  btnBuscar?.addEventListener("click", filtrarTabela);
  filtro?.addEventListener("keypress", e => e.key === "Enter" && filtrarTabela());
}
</script>

<?php include __DIR__ . '/includes/footer/footer.php'; ?>
