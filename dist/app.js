(() => {
  'use strict';
  const config = window.IJSBAAN;
  const edition = config.editions.find(item => item.id === config.activeEdition);
  if (!edition) throw new Error('De actieve editie ontbreekt.');
  function icon(name) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'icon');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', `assets/icons.svg#${name}`);
    svg.append(use);
    return svg;
  }
  const formatDate = (iso, options = { day: 'numeric', month: 'long', year: 'numeric' }) =>
    new Intl.DateTimeFormat('nl-NL', { ...options, timeZone: 'UTC' }).format(new Date(`${iso}T12:00:00Z`));
  const setText = (selector, value) => document.querySelectorAll(selector).forEach(el => { el.textContent = value; });
  setText('[data-place]', edition.place);
  setText('[data-venue]', `${edition.location}, ${edition.place}`);
  setText('[data-location]', edition.location);
  setText('[data-season]', `Winter ${edition.season}`);
  if (edition.start) {
    setText('[data-date]', edition.end ? `${formatDate(edition.start)} – ${formatDate(edition.end)}` : `Vanaf ${formatDate(edition.start)}`);
    setText('[data-date-heading]', `Vanaf ${formatDate(edition.start, { day: 'numeric', month: 'long' })}`);
    setText('[data-faq-date]', `De editie in ${edition.place} ${edition.end ? `loopt van ${formatDate(edition.start)} tot en met ${formatDate(edition.end)}` : `start op ${formatDate(edition.start)}`}. De dagelijkse openingstijden en het programma volgen.${edition.end ? '' : ' De definitieve einddatum wordt nog bevestigd.'}`);
  }
  const map = document.getElementById('map-link');
  map.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${edition.location} ${edition.place}`)}`;
  const list = document.getElementById('edition-list');
  list.replaceChildren(...config.editions.map(item => {
    const row = document.createElement('article');
    row.className = `edition-item${item.id === edition.id ? ' current' : ''}`;
    const years = document.createElement('div'); years.className = 'edition-year';
    const [year, nextYear] = item.season.split(' / ');
    years.append(document.createTextNode(`${year} `));
    if (nextYear) { const span = document.createElement('span'); span.textContent = `/ ${nextYear}`; years.append(span); }
    const name = document.createElement('h3'); name.textContent = item.place;
    const status = document.createElement('span'); status.className = 'edition-status';
    status.textContent = item.id === edition.id ? 'DEZE WINTER' : item.status === 'next' ? 'VOLGENDE EDITIE' : 'BINNENKORT MEER';
    const action = document.createElement(item.id === edition.id ? 'a' : 'span');
    if (item.id === edition.id) { action.href = '#bezoek'; action.setAttribute('aria-label', `Bekijk de editie ${item.place}`); action.append(icon('arrow-up-right')); }
    else { action.className = 'edition-symbol'; action.setAttribute('aria-hidden', 'true'); action.append(icon('snowflake')); }
    row.append(years, name, status, action); return row;
  }));

  const menuButton = document.querySelector('.menu-toggle');
  const mobileNav = document.getElementById('mobile-nav');
  function closeMenu() { mobileNav.hidden = true; menuButton.setAttribute('aria-expanded', 'false'); menuButton.setAttribute('aria-label', 'Menu openen'); }
  menuButton.addEventListener('click', () => { const opening = mobileNav.hidden; mobileNav.hidden = !opening; menuButton.setAttribute('aria-expanded', String(opening)); menuButton.setAttribute('aria-label', opening ? 'Menu sluiten' : 'Menu openen'); });
  mobileNav.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && !mobileNav.hidden) { closeMenu(); menuButton.focus(); } });
  matchMedia('(min-width: 951px)').addEventListener('change', event => { if (event.matches) closeMenu(); });

  const dialog = document.getElementById('sponsor-dialog');
  document.querySelectorAll('[data-package]').forEach(link => link.addEventListener('click', event => {
    if (typeof dialog.showModal !== 'function') return;
    event.preventDefault();
    const amount = new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(Number(link.dataset.package));
    document.getElementById('selected-package').textContent = `Jouw keuze: sponsorpakket van ${amount}`;
    const selected = config.sponsorPackages[link.dataset.package];
    document.getElementById('package-includes').replaceChildren(...selected.benefits.map(text => {
      const item = document.createElement('li');
      const label = document.createElement('span');
      label.textContent = text;
      item.append(icon('check'), label);
      return item;
    }));
    const subject = `Sponsoring IJsbaan ${edition.place} ${edition.season} – ${amount}`;
    const body = `Beste Ton,\n\nGraag dragen wij bij met het sponsorpakket van ${amount}.\n\nBedrijfsnaam:\nContactpersoon:\nTelefoon:\n\nHet ingevulde sponsorformulier voegen wij als bijlage toe.\n\nMet vriendelijke groet,\n`;
    document.getElementById('sponsor-email').href = `mailto:${config.contact.email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    dialog.showModal();
  }));
  dialog.querySelector('.dialog-close').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', event => { if (event.target === dialog) { const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close(); } });

  document.getElementById('calendar-download').addEventListener('click', () => {
    if (!edition.start) return;
    const nextDay = new Date(`${edition.start}T12:00:00Z`); nextDay.setUTCDate(nextDay.getUTCDate() + 1);
    const escape = value => value.replace(/\\/g, '\\\\').replace(/\n/g, '\\n').replace(/,/g, '\\,').replace(/;/g, '\\;');
    const content = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//IJsbaan Neder-Betuwe//Wintereditie//NL', 'CALSCALE:GREGORIAN', 'BEGIN:VEVENT', `UID:${edition.id}-start@ijsbaan-neder-betuwe`, `DTSTAMP:${new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '')}`, `DTSTART;VALUE=DATE:${edition.start.replaceAll('-', '')}`, `DTEND;VALUE=DATE:${nextDay.toISOString().slice(0, 10).replaceAll('-', '')}`, `SUMMARY:${escape(`Start IJsbaan ${edition.place}`)}`, `LOCATION:${escape(`${edition.location}, ${edition.place}`)}`, 'DESCRIPTION:Start van de wintereditie. Openingstijden en programma worden later bekendgemaakt.', 'TRANSP:TRANSPARENT', 'END:VEVENT', 'END:VCALENDAR', ''].join('\r\n');
    const url = URL.createObjectURL(new Blob([content], { type: 'text/calendar;charset=utf-8' }));
    const link = document.createElement('a'); link.href = url; link.download = `ijsbaan-${edition.id}.ics`; document.body.append(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
  });
})();
