</main><!-- /.main-content -->
<dialog class="nav-dialog" id="navDialog" aria-labelledby="navDialogTitle">
  <div class="nav-dialog-head"><h2 id="navDialogTitle">Para onde vamos?</h2><button type="button" id="navClose" aria-label="Fechar busca"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
  <label class="visually-hidden" for="navQuery">Buscar tela</label>
  <input id="navQuery" type="search" class="form-control" placeholder="Buscar revisão, cobrança, perfil…" autocomplete="off">
  <div class="nav-results" id="navResults"></div>
  <p class="nav-empty" id="navEmpty" role="status" hidden>Nenhuma tela encontrada. Tente outro termo.</p>
</dialog>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Confirmação (SweetAlert2) para qualquer botão de formulário com data-confirm.
// Ações que mudam dados são POST com CSRF — nunca link GET.
document.querySelectorAll('[data-confirm]').forEach(function (btn) {
  btn.addEventListener('click', function (e) {
    e.preventDefault();
    var form = btn.closest('form');
    Swal.fire({
      title: btn.dataset.confirm || 'Tem certeza?',
      text: btn.dataset.confirmText || '',
      icon: btn.dataset.confirmIcon || 'warning',
      showCancelButton: true,
      confirmButtonText: btn.dataset.confirmBtn || 'Sim, confirmar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d',
      reverseButtons: true,
    }).then(function (r) {
      if (!r.isConfirmed || !form) return;
      // form.submit() não leva o botão clicado: o nome/valor dele vai num campo escondido
      if (btn.name) { var h = document.createElement('input'); h.type = 'hidden'; h.name = btn.name; h.value = btn.value; form.appendChild(h); }
      form.submit();
    });
  });
});

</script>
<script src="<?= BASE_URL ?>/assets/js/experience.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/experience.js') ?: 1 ?>"></script>
<?php if (!empty($extraJs)) echo $extraJs; ?>
</body>
</html>
