(() => {
  'use strict';
  const primary = 'https://ijsbaannederbetuwe.nl';
  const config = window.IJSBAAN;
  const edition = config.editions.find(item => item.id === config.activeEdition);
  const dialog = document.getElementById('sponsor-dialog');
  const form = document.getElementById('sponsor-form');
  const select = form.elements.package;
  const status = document.getElementById('sponsor-status');
  const button = document.getElementById('sponsor-submit');
  const success = document.getElementById('sponsor-success');
  const intro = document.getElementById('sponsor-intro');
  const logo = form.elements.logo;
  const preview = document.getElementById('logo-preview');
  const fileLabel = document.getElementById('logo-filename');
  let token = '', tokenRequest = null, sending = false, completed = false, objectUrl;
  let opener;
  const amount = value => new Intl.NumberFormat('nl-NL', {style: 'currency', currency: 'EUR', maximumFractionDigits: 0}).format(Number(value));
  form.elements.edition.value = edition.id;
  document.getElementById('sponsor-edition').textContent = `${edition.place} · winter ${edition.season}`;

  function updatePackage() {
    const selected = config.sponsorPackages[select.value];
    document.getElementById('package-summary').textContent = selected.coins ? `${selected.coins} schaatsmunten · bekijk alle voordelen` : 'Hoofdsponsor · bekijk alle voordelen';
    document.getElementById('package-includes').replaceChildren(...selected.benefits.map(text => {
      const li = document.createElement('li'); li.textContent = text; return li;
    }));
    button.querySelector('span').textContent = `Vraag pakket van ${amount(select.value)} aan`;
  }
  function clearErrors() {
    form.querySelectorAll('[aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'));
    form.querySelectorAll('.field-error').forEach(el => { el.textContent = ''; });
    status.textContent = '';
  }
  function errorFor(name, message) {
    const field = form.elements[name];
    if (field) field.setAttribute('aria-invalid', 'true');
    const error = document.getElementById(`${name}-error`);
    if (error) error.textContent = message;
  }
  async function loadToken() {
    if (token) return token;
    if (tokenRequest) return tokenRequest;
    tokenRequest = (async () => {
      const response = await fetch('api/sponsor.php', {cache: 'no-store', credentials: 'same-origin', signal: AbortSignal.timeout(15000)});
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || 'Het formulier is tijdelijk niet beschikbaar. Probeer het later opnieuw.');
      if (result.edition !== edition.id) throw new Error('De editie is gewijzigd. Vernieuw de pagina om verder te gaan.');
      token = result.token;
      return token;
    })();
    try { return await tokenRequest; } finally { tokenRequest = null; }
  }
  function removeLogo() {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = null; logo.value = ''; preview.hidden = true; preview.removeAttribute('src');
    fileLabel.textContent = 'PNG, JPG of WebP · maximaal 2 MB';
    document.getElementById('logo-remove').hidden = true;
    document.getElementById('logo-error').textContent = ''; logo.removeAttribute('aria-invalid');
  }
  function open(value, source) {
    if (location.origin !== primary || typeof dialog.showModal !== 'function') {
      location.assign(`${primary}/?pakket=${encodeURIComponent(value)}#sponsoren`); return;
    }
    opener = source;
    if (completed) { form.reset(); removeLogo(); completed = false; token = ''; }
    select.value = config.sponsorPackages[value] ? value : '500';
    form.elements.edition.value = edition.id;
    updatePackage(); clearErrors();
    form.hidden = false; success.hidden = true; intro.hidden = false;
    dialog.showModal();
    document.body.classList.add('sponsor-modal-open');
    dialog.scrollTop = 0;
    document.getElementById('dialog-title').focus();
    loadToken().catch(error => { status.textContent = error.message; });
  }
  document.querySelectorAll('[data-package]').forEach(link => link.addEventListener('click', event => {
    event.preventDefault(); open(link.dataset.package, link);
  }));
  dialog.querySelectorAll('[data-close-sponsor]').forEach(el => el.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('close', () => { document.body.classList.remove('sponsor-modal-open'); opener?.focus(); });
  dialog.addEventListener('click', event => {
    if (event.target !== dialog || sending) return;
    const rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
  });
  dialog.addEventListener('cancel', event => { if (sending) event.preventDefault(); });
  select.addEventListener('change', updatePackage);
  document.getElementById('logo-remove').addEventListener('click', removeLogo);
  logo.addEventListener('change', () => {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    preview.hidden = true; document.getElementById('logo-error').textContent = ''; logo.removeAttribute('aria-invalid');
    const file = logo.files[0];
    if (!file) { removeLogo(); return; }
    fileLabel.textContent = `${file.name} · ${(file.size / 1024).toFixed(0)} KB`;
    document.getElementById('logo-remove').hidden = false;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
      errorFor('logo', 'Kies een PNG, JPG of WebP van maximaal 2 MB.'); return;
    }
    objectUrl = URL.createObjectURL(file); preview.src = objectUrl; preview.hidden = false;
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (sending) return;
    clearErrors();
    if (!form.reportValidity()) return;
    const file = logo.files[0];
    if (file && (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024)) {
      errorFor('logo', 'Kies een PNG, JPG of WebP van maximaal 2 MB.'); logo.focus(); return;
    }
    sending = true;
    const data = new FormData(form);
    form.querySelector('fieldset').disabled = true;
    dialog.querySelector('.dialog-close').disabled = true;
    button.disabled = true; button.setAttribute('aria-busy', 'true');
    button.querySelector('span').textContent = 'Je aanvraag wordt verstuurd…';
    status.textContent = 'Even geduld. We slaan je aanvraag op en sturen de e-mails.';
    try {
      data.set('token', await loadToken());
      const response = await fetch('api/sponsor.php', {method: 'POST', body: data, credentials: 'same-origin', signal: AbortSignal.timeout(90000)});
      const result = await response.json();
      if (!response.ok || !result.ok) {
        if (response.status === 403) token = '';
        Object.entries(result.fields || {}).forEach(([name, message]) => errorFor(name, message));
        throw new Error(result.message || 'Versturen is niet gelukt. Je gegevens staan nog in het formulier.');
      }
      completed = true; form.hidden = true; intro.hidden = true; success.hidden = false;
      document.getElementById('sponsor-reference').textContent = result.reference;
      document.getElementById('sponsor-confirmation').textContent = result.confirmationSent
        ? `De bevestiging is verstuurd naar ${data.get('email')}. Ton neemt contact met je op om alles af te stemmen. Kijk ook in je ongewenste e-mail.`
        : 'Je aanvraag is veilig opgeslagen. De bevestigingsmail is nog niet verstuurd. Je hoeft de aanvraag niet opnieuw in te vullen; neem bij vragen contact op met Ton en vermeld je aanvraagnummer.';
      document.getElementById('success-title').focus(); dialog.scrollTop = 0;
    } catch (error) {
      status.textContent = error.name === 'TimeoutError' || error instanceof TypeError
        ? 'De verbinding is onderbroken. Je gegevens staan nog klaar. Klik opnieuw op aanvragen; dezelfde aanvraag wordt niet dubbel opgeslagen.'
        : error.message;
      status.focus();
    } finally {
      sending = false; form.querySelector('fieldset').disabled = false;
      dialog.querySelector('.dialog-close').disabled = false;
      button.disabled = false; button.removeAttribute('aria-busy'); updatePackage();
    }
  });
  const initial = new URLSearchParams(location.search).get('pakket');
  if (initial && config.sponsorPackages[initial]) open(initial, null);
})();
