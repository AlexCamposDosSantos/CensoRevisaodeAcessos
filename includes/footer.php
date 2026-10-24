</div><!-- /.main-content -->
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
    }).then(function (r) { if (r.isConfirmed && form) form.submit(); });
  });
});

function toggleSidebar() {
  var sidebar = document.getElementById('sidebar'), overlay = document.getElementById('sidebarOverlay'),
      btn = document.getElementById('btnHamburger');
  var open = sidebar.classList.toggle('open');
  overlay.classList.toggle('active', open);
  btn.innerHTML = open ? '<i class="bi bi-x-lg"></i>' : '<i class="bi bi-list"></i>';
}
document.querySelectorAll('.sidebar a.nav-link').forEach(function (link) {
  link.addEventListener('click', function () { if (window.innerWidth <= 992) toggleSidebar(); });
});
</script>
<?php if (!empty($extraJs)) echo $extraJs; ?>
</body>
</html>
