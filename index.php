<?php
session_start();
require_once __DIR__ . '/includes/conexao.php';

if (isset($_SESSION['usuario_id'])) {
  header('Location: ./dashboard');
  exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $senha = trim($_POST['senha'] ?? '');

  if ($email && $senha) {
    $stmt = $pdo->prepare("SELECT id, nome, senha FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['senha'])) {
      // ✅ Atualiza o último login no banco
      $update = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
      $update->execute([$usuario['id']]);

      // ✅ Salva na sessão e redireciona
      $_SESSION['usuario_id'] = $usuario['id'];
      $_SESSION['usuario_nome'] = $usuario['nome'];

      echo "<script>window.location.href='./dashboard';</script>";
      exit;
    } else {
      $erro = 'E-mail ou senha incorretos.';
    }
  } else {
    $erro = 'Preencha todos os campos.';
  }
}

include __DIR__ . '/includes/header.php';
?>

<div id="loading-screen" class="hidden fixed inset-0 flex flex-col items-center justify-center bg-base-100 z-50">
  <span class="loading loading-spinner loading-lg text-[#f78e23] mb-4"></span>
  <p class="text-lg font-semibold text-gray-700">Carregando dashboard...</p>
</div>

<div class="flex flex-col justify-center items-center min-h-screen bg-base-200">
  <img src="assets/images/logo.png" alt="Artesanal Investimentos" class="w-52 mb-10">

  <div class="card w-full max-w-sm bg-base-100 shadow-xl mb-6">
    <div class="card-body">
      <?php if ($erro): ?>
        <div class="alert alert-error text-sm mb-3"><?= htmlspecialchars($erro) ?></div>
      <?php endif; ?>
      <form id="loginForm" method="post" class="space-y-3 relative">
        <input type="email" name="email" placeholder="E-mail" class="input input-bordered w-full" required>

        <div class="relative">
          <input type="password" id="senha" name="senha" placeholder="Senha" class="input input-bordered w-full pr-10" required>
          <button type="button" id="toggleSenha" class="absolute inset-y-0 right-2 flex items-center text-gray-400">
            <i class="ph ph-eye text-xl"></i>
          </button>
        </div>

        <button type="submit" class="btn btn-success w-full text-white text-lg">Acessar Dashboard</button>
      </form>
    </div>
  </div>

  <footer class="text-center text-gray-500 text-sm mt-4 mb-2 no-bg">
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

  // Mostrar loading após envio do formulário
  const form = document.getElementById('loginForm');
  const loading = document.getElementById('loading-screen');

  form.addEventListener('submit', (e) => {
    loading.classList.remove('hidden');
    setTimeout(() => {
      form.submit();
    }, 3000);
    e.preventDefault();
  });
</script>

</body>
</html>