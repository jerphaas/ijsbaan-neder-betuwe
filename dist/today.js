(() => {
  'use strict';
  const notice = document.querySelector('[data-today-opening]');
  const edition = window.IJSBAAN.editions.find(item => item.id === window.IJSBAAN.activeEdition);
  if (!notice || !window.IJSBAAN_HOURS) return;
  let displayedDate;
  function refresh() {
    const current = window.IJSBAAN_HOURS.today(edition);
    const key = current?.iso || '';
    if (key === displayedDate) return;
    displayedDate = key;
    notice.hidden = !current;
    if (!current) return;
    notice.querySelector('[data-today-text]').textContent = current.text;
    const note = notice.querySelector('[data-today-note]');
    note.textContent = current.reason || current.note;
    note.hidden = !note.textContent;
  }
  refresh();
  // Refresh when an open tab crosses midnight or returns from the background.
  setInterval(refresh, 60_000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
