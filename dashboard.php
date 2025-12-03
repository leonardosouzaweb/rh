<?php
  session_start();
  if (!isset($_SESSION['usuario_id'])) {
    header('Location: ./index.php');
    exit;
  }

  require_once __DIR__ . '/includes/conexao.php';
  include __DIR__ . '/includes/header.php';
?>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="w-full mx-auto space-y-8">
  <!-- Header do Dashboard -->
  <div class="flex items-center justify-between mb-8">
    <div>
      <h1 class="text-4xl font-bold text-gray-800 mb-2 flex items-center gap-3">
        <i class="ph ph-gauge text-[#f78e23]"></i>
        Dashboard
      </h1>
      <p class="text-gray-500">Visão geral dos indicadores de RH</p>
    </div>
    <div class="text-right">
      <p class="text-sm text-gray-500">Última atualização</p>
      <p class="text-sm font-semibold text-gray-700"><?= date('d/m/Y H:i') ?></p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer/footer.php'; ?>
