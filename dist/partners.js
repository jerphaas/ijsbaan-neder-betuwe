(() => {
  const primary = 'https://ijsbaannederbetuwe.nl';
  const setup = () => {
    const wall = document.querySelector('#partner-wall');
    if (!wall) return;
    // Also handle an older public API response during deployment of the static mirror.
    wall.classList.remove('is-collapsed');
    document.querySelector('.partner-expand')?.remove();
    const link = document.querySelector('.partner-strip-title>a');
    if (link) {
      link.classList.add('partner-all-link');
      link.textContent = 'Alle ' + wall.children.length + ' sponsors bekijken ↓';
    }
  };
  if (document.querySelector('[data-partners-rendered]')) { setup(); return; }
  // GitHub Pages uses the same public display from the primary website.
  fetch(primary + '/api/partners.php', {credentials: 'omit', cache: 'no-store'})
    .then(response => { if (!response.ok) throw new Error('unavailable'); return response.json(); })
    .then(data => {
      if (!data.ok) return;
      for (const key of ['strip', 'grid']) {
        const target = document.querySelector('[data-partners="' + key + '"]');
        if (!target || typeof data.fragments[key] !== 'string') continue;
        target.innerHTML = data.fragments[key];
        target.querySelectorAll('img').forEach(img => {
          const src = img.getAttribute('src');
          if (src.startsWith('/')) img.src = primary + src;
        });
      }
      setup();
    }).catch(() => {});
})();
