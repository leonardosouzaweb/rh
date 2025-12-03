<footer class="w-full mt-10 p-4 text-left text-gray-500 text-sm border-t border-gray-200 fixed bottom-5">
  <p>© <?= date('Y') ?> Artesanal Investimentos — Todos os direitos reservados.</p>

<button id="supportButton"
 class="fixed bottom-6 right-6 btn btn-circle bg-[#f78e23] hover:bg-[#e67e1f] text-white shadow-lg transition"> <i class="ph ph-chat-teardrop-text text-2xl"></i> </button>

  <dialog id="supportModal" class="modal">
    <div class="modal-box max-w-md">
      <h3 class="font-bold text-lg mb-2 flex items-center gap-2">
        <i class="ph ph-headset text-[#f78e23] text-2xl"></i> Suporte
      </h3>
      <p class="text-sm text-gray-500 mb-4 text-left">
        Relate um problema ou envie uma sugestão.
      </p>
      <form method="dialog" id="supportForm" class="space-y-3">
        <input type="text" id="supportName" placeholder="Seu nome" class="input input-bordered w-full" required>
        <input type="email" id="supportEmail" placeholder="Seu e-mail" class="input input-bordered w-full" required>
        <textarea id="supportMessage" placeholder="Descreva o problema..." class="textarea textarea-bordered w-full h-24" style="font-size:15px;" required></textarea>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn btn-ghost" id="cancelSupport">Cancelar</button>
          <button type="submit" class="btn btn-success text-white">Enviar</button>
        </div>
      </form>
    </div>
  </dialog>
</footer>

<script>
  const supportButton = document.getElementById('supportButton');
  const supportModal = document.getElementById('supportModal');
  const cancelSupport = document.getElementById('cancelSupport');
  const supportForm = document.getElementById('supportForm');

  // Abrir modal
  supportButton.addEventListener('click', () => {
    supportModal.showModal();
  });

  // Fechar modal
  cancelSupport.addEventListener('click', () => {
    supportModal.close();
  });

  // Envio de mensagem (pode futuramente ser integrado com backend/email)
  supportForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const nome = document.getElementById('supportName').value.trim();
    const email = document.getElementById('supportEmail').value.trim();
    const mensagem = document.getElementById('supportMessage').value.trim();

    if (!nome || !email || !mensagem) return alert('Preencha todos os campos.');

    supportModal.close();
    supportForm.reset();
    alert('Mensagem enviada com sucesso! Nossa equipe retornará em breve.');
  });
</script>
