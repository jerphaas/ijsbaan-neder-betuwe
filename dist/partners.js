(() => {
  const primary = 'https://ijsbaannederbetuwe.nl';
  const setup = () => {
    const wall = document.querySelector('#partner-wall');
    const toggle = document.querySelector('.partner-expand');
    if (!wall || !toggle) return;
    wall.classList.add('is-collapsed'); toggle.hidden = false;
    const allLabel = toggle.textContent;
    toggle.addEventListener('click', () => {
      const expanded = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(expanded));
      wall.classList.toggle('is-collapsed', !expanded);
      toggle.textContent = expanded ? 'Toon minder sponsors ↑' : allLabel;
      if (!expanded) document.querySelector('#onze-sponsors').scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
    });
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
