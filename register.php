<?php
require_once __DIR__ . '/includes/conexao.php';
include __DIR__ . '/includes/header.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome = trim($_POST['nome'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $senha = trim($_POST['senha'] ?? '');

  if ($nome && $email && $senha) {
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
      $erro = 'E-mail já cadastrado.';
    } else {
      $hash = password_hash($senha, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, criado_em) VALUES (?, ?, ?, NOW())");
      $stmt->execute([$nome, $email, $hash]);
      echo "<script>
        document.addEventListener('DOMContentLoaded', () => {
          const loading = document.getElementById('loading-screen');
          loading.classList.remove('hidden');
          setTimeout(() => {
            window.location.href = './';
          }, 5000);
        });
      </script>";
    }
  } else {
    $erro = 'Preencha todos os campos.';
  }
}
?>

<div id="loading-screen" class="hidden fixed inset-0 flex flex-col items-center justify-center bg-base-100 z-50">
  <span class="loading loading-spinner loading-lg text-[#f78e23] mb-4"></span>
  <p class="text-lg font-semibold text-gray-700">Estamos criando sua conta...</p>
  <p class="text-sm text-gray-500 mt-2">Após a criação, você será redirecionado automaticamente para a página de login.</p>
</div>


<div class="flex flex-col justify-center items-center min-h-screen bg-base-200">
  <img src="assets/images/logo.png" alt="Artesanal Investimentos" class="w-52 mb-10">

  <div class="card w-full max-w-sm bg-base-100 shadow-xl mb-6">
    <div class="card-body">
      <?php if ($erro): ?>
        <div class="alert alert-error text-sm mb-3 text-white font-medium" style="font-size:15px;">
          <?= htmlspecialchars($erro) ?>
        </div>
      <?php endif; ?>

      <form id="registroForm" method="post" class="space-y-3 relative">
        <input type="text" name="nome" placeholder="Nome completo" class="input input-bordered w-full rounded-xl" required>
        <input type="email" name="email" placeholder="E-mail" class="input input-bordered w-full rounded-xl" required>

        <div class="relative">
          <input type="password" id="senha" name="senha" placeholder="Senha" class="input input-bordered w-full pr-10 rounded-xl" required>
          <button type="button" id="toggleSenha" class="absolute inset-y-0 right-2 flex items-center text-gray-400">
            <i class="ph ph-eye text-xl"></i>
          </button>
        </div>

        <button type="submit" class="btn btn-success w-full text-white text-lg rounded-xl">Criar Conta</button>
      </form>
    </div>
  </div>

  <footer class="text-center text-gray-500 text-sm mt-4 mb-2">
    <div class="flex items-center justify-center gap-2 mb-1">
      <i class="ph ph-shield-check text-[#f78e23] text-xl"></i>
      <span class="font-medium">Sistema seguro</span>
    </div>
    <p class="text-gray-600">
      Todas as informações são criptografadas e protegidas.<br>
      <span class="text-gray-400 text-xs">© 2025 Artesanal Investimentos – Software proprietário.</span>
    </p>
  </footer>
</div>

<script>
  const toggle = document.getElementById('toggleSenha');
  const senha = document.getElementById('senha');
  let visivel = false;

  toggle.addEventListener('click', () => {
    visivel = !visivel;
    senha.type = visivel ? 'text' : 'password';
    toggle.innerHTML = visivel
      ? '<i class="ph ph-eye-slash text-xl"></i>'
      : '<i class="ph ph-eye text-xl"></i>';
  });
</script>

</body>
</html>
