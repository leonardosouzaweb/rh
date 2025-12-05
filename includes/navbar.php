<?php
$usuario_id = $_SESSION['usuario_id'] ?? null;
$foto_perfil = null;
$nome_usuario = $_SESSION['usuario_nome'] ?? 'Usuário';
$ultimo_acesso = null;

if ($usuario_id) {
  $stmt = $pdo->prepare("SELECT foto_perfil, ultimo_login FROM usuarios WHERE id = ?");
  $stmt->execute([$usuario_id]);
  $dados = $stmt->fetch(PDO::FETCH_ASSOC);
  $foto_perfil = $dados['foto_perfil'] ?? null;
  $ultimo_acesso = $dados['ultimo_login'] ?? null;
}

$uploadDir = 'uploads/perfis/';
$foto_src = ($foto_perfil && file_exists(__DIR__ . '/../' . $uploadDir . $foto_perfil))
  ? $uploadDir . htmlspecialchars($foto_perfil)
  : 'assets/images/user-placeholder.png';

$ultimo_acesso_fmt = $ultimo_acesso ? date('d/m/Y H:i', strtotime($ultimo_acesso)) : 'Não informado';
?>

<nav class="w-full bg-base-100 shadow mb-6 rounded-xl relative z-20">
  <div class="mx-auto flex items-center justify-between px-6 py-4">
    <div class="flex items-center gap-2">
      <a href="./">
        <img src="assets/images/logo.png" class="logo-head" alt="Logo">
      </a>
    </div>

    <div class="hidden md:flex flex-1 justify-center">
      <ul class="flex items-center gap-6">
        <li class="relative group">
          <button class="btn btn-ghost flex items-center gap-1 text-[16px] font-medium" id="indicadoresBtn">
            Indicadores
            <i class="ph ph-caret-down"></i>
          </button>

          <div id="indicadoresMenu"
            class="hidden absolute left-0 mt-2 w-72 bg-white shadow-lg rounded-xl border border-gray-200 py-2 z-30">
            <a href="./demografico" class="dropdown-item">Demográfico</a>
            <a href="./headcount" class="dropdown-item">Headcount</a>
            <a href="./absenteismo" class="dropdown-item">Absenteísmo</a>

            <hr class="my-2">
            <div class="px-3 py-1 text-xs font-semibold text-gray-500">Pessoas e Operação</div>
            <div class="dropdown-item disabled">Operação Geral <span class="tag">Em breve</span></div>
            <div class="dropdown-item disabled">Operação da Área <span class="tag">Em breve</span></div>
            <div class="dropdown-item disabled">Custo por Colaborador <span class="tag">Em breve</span></div>
            <div class="dropdown-item disabled">Salarial <span class="tag">Em breve</span></div>

            <hr class="my-2">
            <div class="px-3 py-1 text-xs font-semibold text-gray-500">Cultura e Engajamento</div>
            <div class="dropdown-item disabled">Turnover <span class="tag">Em breve</span></div>
            <div class="dropdown-item disabled">Educacional <span class="tag">Em breve</span></div>

            <hr class="my-2">
            <div class="px-3 py-1 text-xs font-semibold text-gray-500">Riscos e Compliance</div>

            <div class="dropdown-item disabled">Risco <span class="tag">Em breve</span></div>
            <div class="dropdown-item disabled">Impacto <span class="tag">Em breve</span></div>
            <div class="dropdown-item disabled">Risco vs Impacto <span class="tag">Em breve</span></div>
          </div>
        </li>

        <li>
          <a href="./construtor" class="btn btn-ghost text-[16px] font-medium">Construtor</a>
        </li>
        <li>
          <a href="./relatorios" class="btn btn-ghost text-[16px] font-medium">Relatórios</a>
        </li>
        <li>
          <a href="./configuracoes" class="btn btn-ghost text-[16px] font-medium">Configurações</a>
        </li>
      </ul>
    </div>

    <div class="relative">
      <button id="profileBtn" class="flex items-center gap-2 hover:opacity-80 transition">
        <img src="<?= $foto_src ?>" class="w-10 h-10 rounded-full border-2 border-[#f78e23] object-cover">
        <i class="ph ph-caret-down text-lg text-gray-600"></i>
      </button>

      <div id="profileMenu"
        class="hidden absolute right-0 top-12 w-60 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden z-30">
        <div class="px-4 py-3 border-b bg-gray-50">
          <p class="text-sm text-gray-600">Bem-vindo,</p>
          <p class="font-semibold text-gray-800 truncate"><?= htmlspecialchars($nome_usuario) ?></p>
          <p class="text-xs text-gray-400 mt-1">Último acesso: <?= $ultimo_acesso_fmt ?></p>
        </div>
        <a href="./perfil" class="block px-4 py-2 text-sm hover:bg-gray-100">Meu Perfil</a>
        <a href="./logout" class="block px-4 py-2 text-sm text-red-500 hover:bg-gray-100">Sair</a>
      </div>
    </div>

    <button id="mobileMenuBtn" class="md:hidden ml-3 text-2xl">
      <i class="ph ph-list"></i>
    </button>
  </div>

  <div id="mobileMenu" class="hidden md:hidden bg-white border-t px-6 py-4">

    <details class="mb-2">
      <summary class="font-semibold cursor-pointer py-2">Indicadores</summary>

      <div class="pl-4 flex flex-col gap-2 py-2">
        <a href="./demografico" class="accordion-item">Demográfico</a>
        <a href="./headcount" class="accordion-item">Headcount</a>
        <a href="./absenteismo" class="accordion-item">Absenteísmo</a>
        <hr>
        <span class="text-xs font-semibold text-gray-500">Pessoas e Operação</span>
        <span class="accordion-item disabled">Operação Geral <span class="tag">Em breve</span></span>
        <span class="accordion-item disabled">Operação da Área <span class="tag">Em breve</span></span>
        <span class="accordion-item disabled">Custo por Colaborador <span class="tag">Em breve</span></span>
        <span class="accordion-item disabled">Salarial <span class="tag">Em breve</span></span>
        <hr>
        <span class="text-xs font-semibold text-gray-500">Cultura e Engajamento</span>
        <span class="accordion-item disabled">Turnover <span class="tag">Em breve</span></span>
        <span class="accordion-item disabled">Educacional <span class="tag">Em breve</span></span>
        <hr>
        <span class="text-xs font-semibold text-gray-500">Riscos e Compliance</span>
        <span class="accordion-item disabled">Risco <span class="tag">Em breve</span></span>
        <span class="accordion-item disabled">Impacto <span class="tag">Em breve</span></span>
        <span class="accordion-item disabled">Risco vs Impacto <span class="tag">Em breve</span></span>
      </div>
    </details>
    <a href="./construtor" class="block py-2">Construtor</a>
    <a href="./relatorios" class="block py-2">Relatórios2</a>
  </div>
</nav>

<style>
  .dropdown-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 14px; font-size: 0.95rem; color: #333; transition: .15s ease; }
  .dropdown-item:hover { background: #fef3e6; color: #f78e23; }
  .dropdown-item.disabled { opacity: .6; cursor: default; pointer-events: none; }
  .tag { background: #f78e23; color: white; padding: 2px 6px; font-size: 0.7rem; border-radius: 6px; font-weight: bold; }
  .accordion-item { font-size: 0.95rem; padding: 4px 0; }
  .accordion-item.disabled { opacity: .6; }
</style>

<script>
  const btn = document.getElementById('indicadoresBtn');
  const menu = document.getElementById('indicadoresMenu');

  btn.onclick = (e) => {
    e.stopPropagation();
    menu.classList.toggle('hidden');
  };

  document.addEventListener('click', () => menu.classList.add('hidden'));

  const profileBtn = document.getElementById('profileBtn');
  const profileMenu = document.getElementById('profileMenu');

  profileBtn.onclick = (e) => {
    e.stopPropagation();
    profileMenu.classList.toggle('hidden');
  };

  document.addEventListener('click', () => profileMenu.classList.add('hidden'));

  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const mobileMenu = document.getElementById('mobileMenu');

  mobileMenuBtn.onclick = () => mobileMenu.classList.toggle('hidden');
</script>
