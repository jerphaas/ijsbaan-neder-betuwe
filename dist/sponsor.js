(() => {
  'use strict';
  const primary = 'https://ijsbaannederbetuwe.nl';
  const config = window.IJSBAAN;
  const edition = config.editions.find(item => item.id === config.activeEdition);
  const dialog = document.getElementById('sponsor-dialog');
  const compareDialog = document.getElementById('compare-dialog');
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
    if (!dialog.open) dialog.showModal();
    document.body.classList.add('sponsor-modal-open');
    dialog.scrollTop = 0;
    document.getElementById('dialog-title').focus();
    loadToken().catch(error => { status.textContent = error.message; });
  }
  document.querySelectorAll('[data-package]').forEach(link => link.addEventListener('click', event => {
    event.preventDefault(); open(link.dataset.package, link);
  }));
  dialog.querySelectorAll('[data-close-sponsor]').forEach(el => el.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('close', () => { if (!compareDialog.open) document.body.classList.remove('sponsor-modal-open'); opener?.focus(); });
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
  // One semantic table; on small screens compare two selectable packages without sideways scrolling.
  const comparison = config.sponsorComparison;
  const packageIds = Object.keys(config.sponsorPackages);
  const compactComparison = matchMedia('(max-width: 1000px)');
  const firstChoice = document.getElementById('compare-first');
  const secondChoice = document.getElementById('compare-second');
  let comparisonOpener, comparisonChosen = false;
  function comparisonIcon(name) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'icon'); svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true'); svg.setAttribute('focusable', 'false');
    const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', `assets/icons.svg#${name}`); svg.append(use); return svg;
  }
  [firstChoice, secondChoice].forEach(picker => packageIds.forEach(id => {
    const option = document.createElement('option'); option.value = id;
    option.textContent = amount(id); picker.append(option);
  }));
  firstChoice.value = '500'; secondChoice.value = '1000';
  function renderComparison() {
    const ids = compactComparison.matches ? [firstChoice.value, secondChoice.value] : packageIds;
    const table = document.getElementById('compare-table');
    table.querySelectorAll('thead, tbody, tfoot').forEach(el => el.remove());
    const head = table.createTHead().insertRow();
    const corner = document.createElement('th'); corner.scope = 'col'; corner.className = 'compare-corner';
    corner.textContent = 'Dit krijg je'; head.append(corner);
    ids.forEach(id => {
      const th = document.createElement('th'); th.scope = 'col';
      const label = document.createElement('span'); label.className = 'compare-package-label';
      label.textContent = id === '5000' ? 'Hoofdsponsor' : 'Sponsorpakket';
      const price = document.createElement('strong'); price.textContent = amount(id);
      th.append(label, price); head.append(th);
    });
    const body = table.createTBody();
    comparison.rows.forEach(feature => {
      const row = body.insertRow(); row.dataset.feature = feature.key;
      const th = document.createElement('th'); th.scope = 'row';
      const label = document.createElement('span'); label.textContent = feature.label;
      const note = document.createElement('small'); note.textContent = feature.note;
      const copy = document.createElement('span'); copy.append(label, note);
      th.append(comparisonIcon(feature.icon), copy); row.append(th);
      ids.forEach(id => {
        const cell = row.insertCell();
        const value = feature.key === 'coins' ? config.sponsorPackages[id].coins : comparison.packages[id][feature.key];
        if (feature.key === 'coins') {
          const number = document.createElement('strong'); number.className = 'compare-coins'; number.textContent = value ?? '—'; cell.append(number);
          const detail = document.createElement('small'); detail.textContent = value === null ? 'Niet vermeld' : 'munten'; cell.append(detail);
        } else if (value === true) {
          const badge = document.createElement('span'); badge.className = 'compare-check'; badge.append(comparisonIcon('check'));
          const text = document.createElement('span'); text.className = 'sr-only'; text.textContent = 'Inbegrepen'; badge.append(text); cell.append(badge);
        } else if (!value) {
          const dash = document.createElement('span'); dash.className = 'compare-dash'; dash.setAttribute('aria-label', 'Niet vermeld bij dit pakket'); dash.textContent = '—'; cell.append(dash);
        } else {
          const [text, detail] = value.split('|'); cell.append(document.createTextNode(text));
          if (detail) { const small = document.createElement('small'); small.textContent = detail; cell.append(small); }
        }
      });
    });
    const actions = table.createTFoot().insertRow();
    const th = document.createElement('th'); th.scope = 'row'; th.textContent = 'Doe mee'; actions.append(th);
    ids.forEach(id => {
      const cell = actions.insertCell(); const choose = document.createElement('button');
      choose.className = 'compare-choose'; choose.type = 'button'; choose.textContent = 'Kies pakket';
      choose.setAttribute('aria-label', `Vraag het pakket van ${amount(id)} aan`);
      choose.append(comparisonIcon('arrow-up-right'));
      choose.addEventListener('click', () => {
        comparisonChosen = true; compareDialog.close();
        open(id, dialog.open ? opener : comparisonOpener);
      });
      cell.append(choose);
    });
  }
  function changeComparison(changed, other) {
    if (changed.value === other.value) other.value = packageIds.find(id => id !== changed.value);
    renderComparison();
  }
  firstChoice.addEventListener('change', () => changeComparison(firstChoice, secondChoice));
  secondChoice.addEventListener('change', () => changeComparison(secondChoice, firstChoice));
  compactComparison.addEventListener('change', () => { if (compareDialog.open) renderComparison(); });
  if (typeof compareDialog.showModal === 'function') document.querySelectorAll('[data-compare]').forEach(control => {
    control.hidden = false;
    control.addEventListener('click', () => {
      comparisonOpener = control; comparisonChosen = false;
      if (dialog.open) {
        firstChoice.value = select.value;
        if (secondChoice.value === firstChoice.value) secondChoice.value = packageIds.find(id => Number(id) > Number(firstChoice.value)) || '250';
      }
      renderComparison(); compareDialog.showModal();
      document.body.classList.add('sponsor-modal-open'); compareDialog.scrollTop = 0;
      document.getElementById('compare-title').focus();
    });
  });
  compareDialog.querySelector('.compare-close').addEventListener('click', () => compareDialog.close());
  compareDialog.addEventListener('click', event => {
    if (event.target !== compareDialog) return;
    const r = compareDialog.getBoundingClientRect();
    if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) compareDialog.close();
  });
  compareDialog.addEventListener('close', () => {
    if (!dialog.open) document.body.classList.remove('sponsor-modal-open');
    if (!comparisonChosen) comparisonOpener?.focus();
  });
  const initial = new URLSearchParams(location.search).get('pakket');
  if (initial && config.sponsorPackages[initial]) open(initial, null);
})();
