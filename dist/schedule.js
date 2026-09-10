(() => {
  'use strict';
  const edition = window.IJSBAAN.editions.find(item => item.id === window.IJSBAAN.activeEdition);
  const hours = edition?.openingHours;
  const host = document.getElementById('schedule-content');
  if (!host || !hours || !edition.start) return;
  document.querySelector('[data-schedule-status]').textContent = hours.status === 'confirmed' ? 'Rooster bevestigd' : 'Voorlopig rooster';

  const date = iso => new Date(`${iso}T12:00:00Z`);
  const format = (iso, options) => new Intl.DateTimeFormat('nl-NL', { ...options, timeZone: 'UTC' }).format(date(iso));
  const days = [];
  for (const cursor = date(edition.start); cursor <= date(hours.through); cursor.setUTCDate(cursor.getUTCDate() + 1)) {
    const iso = cursor.toISOString().slice(0, 10);
    const weekday = cursor.getUTCDay();
    const reason = hours.closedDates[iso] || '';
    const closed = Boolean(reason) || hours.closedWeekdays.includes(weekday);
    const holiday = iso >= hours.holidayStart && iso <= hours.holidayEnd;
    days.push({ iso, weekday, reason, closed, times: holiday || weekday === 6 ? hours.dayOff : hours.schoolDay });
  }

  // Bundle a short opening weekend with the first full week.
  const weeks = [];
  days.forEach(day => {
    if (!weeks.length || day.weekday === 1) weeks.push([]);
    weeks[weeks.length - 1].push(day);
  });
  if (weeks.length > 1 && weeks[0].length <= 3) weeks.splice(0, 2, [...weeks[0], ...weeks[1]]);
  const range = week => {
    const first = week[0].iso;
    const last = week[week.length - 1].iso;
    return `${format(first, first.slice(0, 7) === last.slice(0, 7) ? { day: 'numeric' } : { day: 'numeric', month: 'short' })} – ${format(last, { day: 'numeric', month: 'short' })}`;
  };

  const picker = document.createElement('div');
  picker.className = 'schedule-picker';
  picker.setAttribute('role', 'group');
  picker.setAttribute('aria-label', 'Kies een periode');
  const table = document.createElement('table');
  table.className = 'schedule-table';
  table.id = 'schedule-table';
  const caption = table.createCaption();
  const header = table.createTHead().insertRow();
  ['Dag', 'Datum', 'Tijden'].forEach(label => {
    const cell = document.createElement('th'); cell.scope = 'col'; cell.textContent = label; header.append(cell);
  });
  const body = table.createTBody();
  const announcement = document.createElement('p');
  announcement.className = 'visually-hidden';
  announcement.setAttribute('role', 'status');
  announcement.setAttribute('aria-live', 'polite');
  const buttons = weeks.map((week, index) => {
    const button = document.createElement('button');
    button.type = 'button'; button.textContent = range(week);
    button.setAttribute('aria-controls', table.id);
    button.addEventListener('click', () => show(index, true));
    picker.append(button); return button;
  });
  function show(index, announce = false) {
    buttons.forEach((button, i) => button.setAttribute('aria-pressed', String(i === index)));
    caption.textContent = `${range(weeks[index])} · ${edition.place}`;
    body.replaceChildren(...weeks[index].map(day => {
      const row = document.createElement('tr');
      row.dataset.date = day.iso;
      if (day.closed) row.className = 'schedule-closed';
      const weekday = document.createElement('th'); weekday.scope = 'row';
      weekday.textContent = format(day.iso, { weekday: 'short' });
      weekday.setAttribute('aria-label', format(day.iso, { weekday: 'long' }));
      const when = document.createElement('td');
      const time = document.createElement('time'); time.dateTime = day.iso;
      time.textContent = format(day.iso, { day: 'numeric', month: 'short' }); when.append(time);
      if (day.reason) { const reason = document.createElement('small'); reason.textContent = day.reason; when.append(reason); }
      const opening = document.createElement('td');
      const label = document.createElement('span');
      label.className = day.closed ? 'closed-label' : 'open-hours';
      label.textContent = day.closed ? 'Gesloten' : day.times.map(t => t.replace(':', '.')).join(' – ');
      opening.append(label); row.append(weekday, when, opening); return row;
    }));
    if (announce) announcement.textContent = `Openingstijden voor ${range(weeks[index])}, ${edition.season}.`;
  }
  const today = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Amsterdam' }).format(new Date());
  const current = weeks.findIndex(week => week.some(day => day.iso === today));
  show(current < 0 ? 0 : current);
  host.replaceChildren(picker, table, announcement);
})();
