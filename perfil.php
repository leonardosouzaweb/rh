<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header('Location: index.php');
  exit;
}

require_once __DIR__ . '/includes/conexao.php';
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$erro = '';

$uploadDir = __DIR__ . '/uploads/perfis';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// Buscar dados
$stmt = $pdo->prepare("SELECT nome, email, cargo, departamento, telefone, foto_perfil FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Mock de plano
$plano_atual = [
  'nome' => 'Plano Premium',
  'status' => 'Ativo',
  'renovacao' => '15/12/2025',
  'descricao' => 'Recursos completos e suporte prioritário.'
];

// Atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome = trim($_POST['nome'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $cargo = trim($_POST['cargo'] ?? '');
  $departamento = trim($_POST['departamento'] ?? '');
  $telefone = trim($_POST['telefone'] ?? '');
  $senha_atual = trim($_POST['senha_atual'] ?? '');
  $nova_senha = trim($_POST['nova_senha'] ?? '');
  $confirmar_senha = trim($_POST['confirmar_senha'] ?? '');
  $fotoPath = $usuario['foto_perfil'];

  // Upload
  if (isset($_FILES['foto']) && !empty($_FILES['foto']['name'])) {
    $foto = $_FILES['foto'];
    if ($foto['error'] === UPLOAD_ERR_OK) {
      $ext = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
      if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
        $novoNome = 'perfil_' . $usuario_id . '_' . time() . '.' . $ext;
        $destino = $uploadDir . '/' . $novoNome;
        if (move_uploaded_file($foto['tmp_name'], $destino)) {
          if (!empty($usuario['foto_perfil']) && file_exists($uploadDir . '/' . $usuario['foto_perfil'])) {
            unlink($uploadDir . '/' . $usuario['foto_perfil']);
          }
          $fotoPath = $novoNome;
        } else {
          $erro = 'Falha ao mover o arquivo.';
        }
      } else {
        $erro = 'Apenas imagens JPG ou PNG são permitidas.';
      }
    } else {
      $erro = 'Erro no upload.';
    }
  }

  if (!$erro) {
    $stmt = $pdo->prepare("UPDATE usuarios SET nome=?, email=?, cargo=?, departamento=?, telefone=?, foto_perfil=? WHERE id=?");
    $ok = $stmt->execute([$nome, $email, $cargo, $departamento, $telefone, $fotoPath, $usuario_id]);

    if ($senha_atual && $nova_senha && $nova_senha === $confirmar_senha) {
      $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id=?");
      $stmt->execute([$usuario_id]);
      $hashAtual = $stmt->fetchColumn();
      if (password_verify($senha_atual, $hashAtual)) {
        $novoHash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE usuarios SET senha=? WHERE id=?")->execute([$novoHash, $usuario_id]);
      } else {
        $erro = 'Senha atual incorreta.';
      }
    }

    if (!$erro) {
      $mensagem = 'Perfil atualizado com sucesso!';
      $_SESSION['usuario_nome'] = $nome;
      $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id=?");
      $stmt->execute([$usuario_id]);
      $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    }
  }
}
?>

<div class="w-full mx-auto space-y-6">
  <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <h1 class="text-3xl font-bold text-gray-700 flex items-center gap-2">
      Meu Perfil
    </h1>
  </header>

  <?php if ($erro): ?>
    <div class="alert alert-error shadow-lg text-white font-medium"><?= htmlspecialchars($erro) ?></div>
  <?php elseif ($mensagem): ?>
    <div id="alert-success" class="alert alert-success shadow-lg text-white font-medium transition-opacity duration-500">
      <?= htmlspecialchars($mensagem) ?>
    </div>
    <script>
      setTimeout(() => {
        const alertBox = document.getElementById('alert-success');
        if (alertBox) alertBox.style.opacity = '0';
        setTimeout(() => location.reload(), 1000);
      }, 3000);
    </script>
  <?php endif; ?>

  <!-- Um único formulário -->
  <form method="post" enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-stretch">

    <!-- Coluna esquerda -->
    <section class="bg-base-100 rounded-2xl shadow-md border border-base-300 p-6 flex flex-col justify-center items-center text-center min-h-[445px]">
      <div class="relative group">
        <img 
          src="<?= $usuario['foto_perfil'] && file_exists($uploadDir . '/' . $usuario['foto_perfil']) 
            ? 'uploads/perfis/' . htmlspecialchars($usuario['foto_perfil']) 
            : 'assets/images/user-placeholder.png' ?>" 
          alt="Foto de Perfil" 
          class="w-40 h-40 rounded-full object-cover border-4 border-[#f78e23] shadow group-hover:opacity-80 transition"
        >
        <label for="foto" class="absolute bottom-2 right-2 bg-[#f78e23] text-white p-2 rounded-full cursor-pointer hover:bg-[#e37a1e] transition">
          <i class="ph ph-camera text-lg"></i>
        </label>
        <input type="file" id="foto" name="foto" accept=".jpg,.jpeg,.png" class="hidden" />
      </div>

      <h2 class="text-2xl font-semibold mt-2"><?= htmlspecialchars($usuario['nome']) ?></h2>
      <p class="text-gray-500"><?= htmlspecialchars($usuario['cargo'] ?: 'Cargo não informado') ?></p>

      <span class="mt-2 px-4 py-1 rounded-full text-sm font-medium bg-green-100 text-green-700">
        <?= htmlspecialchars($plano_atual['nome']) ?> (<?= htmlspecialchars($plano_atual['status']) ?>)
      </span>

      <p class="text-gray-500 mt-2 text-sm w-2/3 mx-auto"><?= htmlspecialchars($plano_atual['descricao']) ?></p>
    </section>

    <!-- Coluna direita -->
    <section class="bg-base-100 rounded-2xl shadow-md border border-base-300 p-6 min-h-[445px] flex flex-col justify-between">
      <div>
        <h2 class="text-xl font-semibold mb-4 text-gray-700 flex items-center gap-2">
          <i class="ph ph-pencil-simple text-[#f78e23]"></i> Informações do Usuário
        </h2>

        <div class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="label font-semibold text-gray-600">Nome</label>
              <input type="text" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" class="input input-bordered w-full" required>
            </div>
            <div>
              <label class="label font-semibold text-gray-600">E-mail</label>
              <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" class="input input-bordered w-full" required>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="label font-semibold text-gray-600">Cargo</label>
              <input type="text" name="cargo" value="<?= htmlspecialchars($usuario['cargo'] ?? '') ?>" class="input input-bordered w-full">
            </div>
            <div>
              <label class="label font-semibold text-gray-600">Departamento</label>
              <input type="text" name="departamento" value="<?= htmlspecialchars($usuario['departamento'] ?? '') ?>" class="input input-bordered w-full">
            </div>
            <div>
              <label class="label font-semibold text-gray-600">Telefone</label>
              <input type="text" name="telefone" value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>" class="input input-bordered w-full">
            </div>
          </div>

          <h3 class="text-lg font-semibold text-gray-700 mt-6 flex items-center gap-2">
            <i class="ph ph-lock text-[#f78e23]"></i> Alterar Senha
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <input type="password" name="senha_atual" placeholder="Senha atual" class="input input-bordered w-full">
            <input type="password" name="nova_senha" placeholder="Nova senha" class="input input-bordered w-full">
            <input type="password" name="confirmar_senha" placeholder="Confirmar senha" class="input input-bordered w-full">
          </div>

          <div class="flex justify-between items-center mt-6">
            <div class="text-sm text-gray-500">
              Próxima renovação: <span class="font-semibold text-gray-700"><?= htmlspecialchars($plano_atual['renovacao']) ?></span>
            </div>
            <button type="submit" class="btn btn-success text-white px-8">
              <i class="ph ph-check-circle"></i> Salvar Alterações
            </button>
          </div>
        </div>
      </div>
    </section>
  </form>
</div>

<script>
  const inputFoto = document.getElementById('foto');
  inputFoto.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (ev) => {
        document.querySelector('img[alt="Foto de Perfil"]').src = ev.target.result;
      };
      reader.readAsDataURL(file);
    }
  });
</script>

<?php include __DIR__ . '/includes/footer/footer.php'; ?>
