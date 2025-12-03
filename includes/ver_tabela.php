<?php
require_once __DIR__ . '/conexao.php';

$tabela = preg_replace('/[^a-z0-9_]/', '', $_GET['tabela'] ?? '');
if (!$tabela) exit('<div class="alert alert-error">Tabela inválida.</div>');

$stmt = $pdo->query("SELECT * FROM `$tabela` LIMIT 150");
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$dados) {
  exit('<p class="text-gray-500 text-sm italic">Nenhum dado disponível nesta tabela.</p>');
}
?>

<div class="relative bg-base-100 rounded-xl shadow-lg p-6 max-h-[85vh] overflow-hidden flex flex-col">
  
  <!-- Botão fechar -->
  <button id="btnFecharModalTabela" class="absolute top-3 right-3 btn btn-sm btn-circle btn-ghost">
    <i class="ph ph-x text-lg"></i>
  </button>

  <div class="flex justify-between items-center mb-4">
    <h2 class="text-xl font-semibold">Visualização da Tabela</h2>
  </div>

  <!-- Filtro -->
  <div class="flex gap-2 mb-4">
    <input
      type="text"
      id="filtroTabela"
      placeholder="Buscar por nome do colaborador..."
      class="input input-bordered input-sm flex-1" />

    <button id="btnBuscar" class="btn btn-sm btn-success flex items-center gap-1 text-white">
      <i class="ph ph-magnifying-glass text-lg"></i> Buscar
    </button>
  </div>

  <!-- Tabela -->
  <div class="overflow-x-auto overflow-y-auto border border-base-200 rounded-lg shadow-inner flex-1">
    <table id="tabelaDados" class="table table-auto text-sm w-full whitespace-nowrap">
      <thead>
        <tr class="bg-base-200 text-gray-700 sticky top-0 z-10">
          <?php foreach (array_keys($dados[0]) as $col): ?>
            <th class="font-semibold px-3 py-2 text-left"><?= strtoupper(str_replace('_', ' ', $col)) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($dados as $linha): ?>
          <tr class="hover:bg-base-100 transition-colors">
            <?php foreach ($linha as $v): ?>
              <td class="px-3 py-2 border-t border-base-200">
                <?= htmlspecialchars($v === '' ? '(vazio)' : $v) ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Contador de resultados -->
  <p id="contadorResultados" class="text-xs text-gray-400 mt-2 italic">
    Exibindo <?= count($dados) ?> de <?= count($dados) ?> registros
  </p>
</div>
