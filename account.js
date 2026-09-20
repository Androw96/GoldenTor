(() => {
  const $ = (selector) => document.querySelector(selector);
  const query = new URLSearchParams(location.search);
  const next = query.get('next');
  function destination() {
    if (!next) return null;
    try {
      const target = new URL(next, location.origin);
      const allowed = ['/kalkulatorok.html', '/biztositas.html', '/finanszirozas.html', '/szakertoink.html'];
      return target.origin === location.origin && allowed.includes(target.pathname) ? target.pathname + target.search + (target.hash || (target.pathname === "/kalkulatorok.html" ? location.hash : "")) : null;
    } catch { return null; }
  }
  async function post(url, payload) {
    const response = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json', 'X-GoldenTor-Request':'1'}, body:JSON.stringify(payload)});
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'A művelet nem sikerült.');
    return data;
  }
  async function submit(form, status, operation) {
    const button = form.querySelector('button[type="submit"], button:not([type])');
    if (button) button.disabled = true;
    status.textContent = '';
    try { await operation(); } catch (error) { status.textContent = error.message || 'Kapcsolódási hiba. Próbálja újra.'; }
    finally { if (button) button.disabled = false; }
  }
  let mode = 'login';
  document.querySelectorAll('[data-auth-mode]').forEach(button => button.addEventListener('click', () => {
    mode = button.dataset.authMode;
    const register = mode === 'register';
    document.querySelectorAll('[data-auth-mode]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    document.querySelectorAll('[data-register-only]').forEach(item => item.hidden = !register);
    $('[data-auth-form]').elements.name.required = register;
    $('[data-auth-form]').elements.privacy.required = register;
    $('[data-auth-form]').elements.password.autocomplete = register ? 'new-password' : 'current-password';
    $('[data-auth-title]').textContent = register ? 'Hozza létre személyes fiókját' : 'Üdvözöljük újra!';
    $('[data-auth-submit]').textContent = register ? 'Fiók létrehozása' : 'Bejelentkezés';
    $('[data-auth-status]').textContent = '';
  }));
  $('[data-auth-form]')?.addEventListener('submit', event => {
    event.preventDefault(); const form = event.currentTarget;
    submit(form, $('[data-auth-status]'), async () => {
      await post(`/api/auth/${mode}`, {email:form.elements.email.value, password:form.elements.password.value, name:form.elements.name.value, privacy:form.elements.privacy.checked});
      location.replace(destination() || '/fiok.html');
    });
  });
  $('[data-profile-form]')?.addEventListener('submit', event => {
    event.preventDefault(); const form = event.currentTarget;
    submit(form, $('[data-profile-status]'), async () => {
      await post('/api/profile', Object.fromEntries(new FormData(form)));
      $('[data-profile-status]').textContent = 'Profil elmentve.';
      $('[data-profile-greeting]').textContent = form.elements.name.value;
    });
  });
  $('[data-logout]')?.addEventListener('click', async event => {
    event.currentTarget.disabled = true;
    try { await post('/api/auth/logout', {}); location.replace('/fiok.html'); }
    catch (error) { $('[data-logout-status]').textContent = error.message; event.target.disabled = false; }
  });
  $('[data-feedback-form]')?.addEventListener('submit', event => {
    event.preventDefault(); const form = event.currentTarget;
    submit(form, $('[data-feedback-status]'), async () => {
      const data = Object.fromEntries(new FormData(form)); data.rating = Number(data.rating);
      await post('/api/feedback', data);
      form.reset(); $('[data-feedback-status]').textContent = 'Köszönjük! Visszajelzését rögzítettük.';
    });
  });
  async function initialize() {
    const response = await fetch('/api/auth/me');
    if (!response.ok) throw new Error('A fiókszolgáltatás átmenetileg nem érhető el. Kérjük, frissítse az oldalt.');
    const {user} = await response.json();
    if ($('[data-account-loading]')) $('[data-account-loading]').hidden = true;
    if ($('[data-account-auth]')) $('[data-account-auth]').hidden = Boolean(user);
    if (user) {
      document.querySelectorAll('[data-account-link]').forEach(link => link.textContent = 'Saját profil');
      if ($('[data-profile]')) {
        if (destination()) { location.replace(destination()); return; }
        $('[data-profile]').hidden = false;
        $('[data-profile-greeting]').textContent = user.name;
        $('[data-profile-email]').textContent = user.email;
        $('[data-profile-form]').elements.name.value = user.name;
        $('[data-profile-form]').elements.phone.value = user.phone;
        const savedSection = document.createElement('section');
        savedSection.className = 'saved-calculations';
        savedSection.innerHTML = '<h3>Elmentett kalkulációk</h3><p class="saved-calculations-status">Betöltés…</p><div class="saved-calculations-list"></div>';
        $('[data-profile]').append(savedSection);
        try {
          const response = await fetch('/api/calculations');
          const {items = []} = await response.json();
          const status = savedSection.querySelector('.saved-calculations-status');
          const list = savedSection.querySelector('.saved-calculations-list');
          if (!response.ok) throw new Error('Az elmentett kalkulációk nem tölthetők be.');
          if (!items.length) { status.textContent = 'Még nincs elmentett kalkulációja.'; }
          else {
            status.remove();
            const labels = {investment:'Vagyonépítés', loan:'Hiteltervezés', reserve:'Védelmi tartalék'};
            const money = new Intl.NumberFormat('hu-HU', {style:'currency',currency:'HUF',maximumFractionDigits:0});
            items.forEach((item) => {
              const card = document.createElement('article'); const title = document.createElement('strong'); const detail = document.createElement('span');
              title.textContent = labels[item.calculator_type] || 'Kalkuláció';
              const value = item.calculator_type === 'loan' ? item.results.payment : item.calculator_type === 'reserve' ? item.results.target : item.results.total;
              detail.textContent = `${money.format(value)} · ${new Date(item.created_at).toLocaleDateString('hu-HU')}`;
              card.append(title, detail); list.append(card);
            });
          }
        } catch (error) { savedSection.querySelector('.saved-calculations-status').textContent = error.message; }
      }
      if ($('[data-feedback-form]')) { $('[data-feedback-form]').hidden = false; $('[data-feedback-gate]').hidden = true; }
      if ($('[data-valiora-gate]')) {
        const gate = $('[data-valiora-gate]');
        try {
          const result = await fetch('/api/member/valiora');
          if (!result.ok) throw new Error('A munkamenet lejárt. Jelentkezzen be újra.');
          const content = await result.json();
          gate.outerHTML = content.html;
          const script = document.createElement('script'); script.src = '/insurance-market.js';
          script.onerror = () => { $('[data-insurance-market]').textContent = 'Az eszköz nem tölthető be. Frissítse az oldalt, vagy jelentkezzen be újra.'; };
          document.body.append(script);
        } catch (error) { gate.querySelector('[data-member-status]').textContent = error.message; }
      }
    }
  }
  initialize().catch(error => {
    const status = $('[data-account-loading]') || $('[data-member-status]');
    if (status) status.textContent = error.message;
  });
  // Browsers may restore authenticated documents from their back-forward cache.
  window.addEventListener('pageshow', event => { if (event.persisted) location.reload(); });
})();
