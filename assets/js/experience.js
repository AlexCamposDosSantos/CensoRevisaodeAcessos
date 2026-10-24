/* Navegação e controles com melhoria progressiva. */
(() => {
  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('btnHamburger');
  const overlay = document.getElementById('sidebarOverlay');
  if (sidebar && toggle) {
    const links = [...sidebar.querySelectorAll('a.nav-link')];
    links.filter(link => link.classList.contains('active')).forEach(link => link.setAttribute('aria-current', 'page'));
    const mobile = matchMedia('(max-width: 992px)');
    const setMenu = open => {
      sidebar.classList.toggle('open', open);
      overlay?.classList.toggle('active', open);
      document.body.classList.toggle('menu-open', open);
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
      toggle.innerHTML = open ? '<i class="bi bi-x-lg" aria-hidden="true"></i>' : '<i class="bi bi-list" aria-hidden="true"></i>';
      sidebar.inert = mobile.matches && !open;
      document.getElementById('conteudo').inert = mobile.matches && open;
    };
    window.toggleSidebar = () => { const open = !sidebar.classList.contains('open'); setMenu(open); if (open) (sidebar.querySelector('[aria-current]') || links[0])?.focus(); };
    mobile.addEventListener('change', () => setMenu(false));
    sidebar.inert = mobile.matches;
    document.addEventListener('keydown', event => {
      if (!sidebar.classList.contains('open')) return;
      if (event.key === 'Escape') { setMenu(false); toggle.focus(); }
      if (event.key === 'Tab') {
        const items = [toggle, ...links];
        const index = items.indexOf(document.activeElement);
        if (event.shiftKey && index <= 0) { event.preventDefault(); items.at(-1).focus(); }
        else if (!event.shiftKey && (index === items.length - 1 || index < 0)) { event.preventDefault(); toggle.focus(); }
      }
    });
    overlay?.addEventListener('click', () => { setMenu(false); toggle.focus(); });
  }
  const dialog = document.getElementById('navDialog');
  const trigger = document.getElementById('navSearchTrigger');
  const query = document.getElementById('navQuery');
  if (dialog && trigger && query && typeof dialog.showModal === 'function') {
    const results = document.getElementById('navResults');
    const entries = [...sidebar.querySelectorAll('a.nav-link:not(.nav-sair)')].map(link => { const copy = link.cloneNode(true); copy.removeAttribute('aria-current'); copy.className = ''; results.appendChild(copy); return copy; });
    const empty = document.getElementById('navEmpty');
    const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const filter = () => { const value = normalize(query.value.trim()); let count = 0; entries.forEach(link => { link.hidden = !normalize(link.textContent + ' ' + (link.dataset.search || '')).includes(value); if (!link.hidden) count++; }); empty.hidden = count !== 0; };
    const open = () => { if (!dialog.open) { if (sidebar.classList.contains('open')) window.toggleSidebar(); query.value = ''; filter(); dialog.showModal(); query.focus(); } };
    trigger.addEventListener('click', open);
    document.getElementById('navClose').addEventListener('click', () => dialog.close());
    query.addEventListener('input', filter);
    query.addEventListener('keydown', event => { if (event.key === 'ArrowDown') { event.preventDefault(); entries.find(link => !link.hidden)?.focus(); } });
    dialog.addEventListener('click', event => { if (event.target === dialog) { const rect = dialog.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close(); } });
    document.addEventListener('keydown', event => { if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); open(); } });
  }
  document.querySelectorAll('[data-password-toggle]').forEach(button => {
    const input = document.getElementById(button.dataset.passwordToggle);
    if (!input) return;
    button.addEventListener('click', () => { const visible = input.type === 'password'; input.type = visible ? 'text' : 'password'; button.setAttribute('aria-label', visible ? 'Ocultar senha' : 'Mostrar senha'); button.setAttribute('aria-pressed', String(visible)); button.innerHTML = visible ? '<i class="bi bi-eye-slash" aria-hidden="true"></i>' : '<i class="bi bi-eye" aria-hidden="true"></i>'; });
  });
})();


/* Feedback da atualização manual: aguarda a resposta sem apagar o painel atual. */
(() => {
  document.querySelectorAll('form[data-refresh-review]').forEach(form => {
    if (typeof window.fetch !== 'function') return;
    const button = form.querySelector('button[type="submit"]');
    const label = form.querySelector('[data-refresh-label]');
    const icon = form.querySelector('[data-refresh-icon]');
    const spinner = form.querySelector('[data-refresh-spinner]');
    const status = document.getElementById('reviewRefreshStatus');
    const failure = document.querySelector('[data-refresh-error]');
    const busyRegion = form;
    let submitting = false;
    const restore = () => {
      submitting = false;
      button.disabled = false;
      label.textContent = 'Atualizar andamento';
      icon.hidden = false;
      spinner.hidden = true;
      status.hidden = true;
      busyRegion.removeAttribute('aria-busy');
    };
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (submitting) return;
      submitting = true;
      const data = new FormData(form);
      button.disabled = true;
      label.textContent = 'Atualizando…';
      icon.hidden = true;
      spinner.hidden = false;
      status.hidden = false;
      failure.hidden = true;
      busyRegion.setAttribute('aria-busy', 'true');
      status.scrollIntoView({ block: 'nearest', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
      let navigating = false;
      try {
        const response = await fetch(form.action, {
          method: 'POST', body: data, credentials: 'same-origin',
          headers: { 'Accept': 'application/json' }, cache: 'no-store'
        });
        if (!response.ok) throw new Error('Falha na atualização.');
        // Sessão encerrada: require_login() redireciona para a tela de entrada.
        if (response.redirected) {
          window.location.assign(response.url);
          navigating = true;
          return;
        }
        const result = await response.json();
        if (typeof result.redirect !== 'string' || !result.redirect) throw new Error('Resposta incompleta.');
        window.location.assign(result.redirect);
        navigating = true;
      } catch (_) {
        failure.textContent = 'Não foi possível confirmar a atualização. Confira sua conexão e tente novamente. Se a solicitação já chegou ao servidor, ela pode continuar em execução.';
        failure.hidden = false;
      } finally {
        if (!navigating) restore();
      }
    });
    window.addEventListener('pageshow', event => { if (event.persisted) restore(); });
  });
})();
