(() => {
  const primary = 'https://ijsbaannederbetuwe.nl';
  const setupSlider = wall => {
    const track = document.querySelector('.partner-main-list');
    if (!track || track.closest('.partner-carousel')) return;
    // The static mirror may receive the older, main-sponsors-only API fragment.
    // Always use the full public wall so every visible sponsor gets a turn.
    track.replaceChildren(...Array.from(wall.children, item => {
      const tile = item.cloneNode(true);
      tile.querySelector('.partner-description')?.remove();
      return tile;
    }));
    const items = Array.from(track.children);
    if (!items.length) return;
    document.querySelector('.partner-strip').setAttribute('aria-label', 'Onze sponsors');
    document.querySelector('.partner-strip-title .eyebrow').textContent = 'ONZE SPONSORS';
    track.id = 'partner-slider';
    track.setAttribute('aria-label', 'Alle sponsors in de slider');
    const carousel = document.createElement('div');
    carousel.className = 'partner-carousel';
    carousel.setAttribute('role', 'region');
    carousel.setAttribute('aria-roledescription', 'carrousel');
    carousel.setAttribute('aria-label', 'Sponsors van deze editie');
    track.before(carousel);
    carousel.append(track);
    const controls = document.createElement('div');
    controls.className = 'partner-slider-controls';
    const icon = name => '<svg viewBox="0 0 24 24" aria-hidden="true"><use href="assets/icons.svg#' + name + '"></use></svg>';
    controls.innerHTML = '<span class="partner-slider-status" aria-live="off"></span>' +
      '<button type="button" class="partner-slider-toggle" aria-controls="partner-slider">' + icon('pause') + '<span>Pauzeren</span></button>' +
      '<button type="button" class="partner-slider-prev" aria-label="Vorige sponsors" aria-controls="partner-slider">' + icon('arrow-up') + '</button>' +
      '<button type="button" class="partner-slider-next" aria-label="Volgende sponsors" aria-controls="partner-slider">' + icon('arrow-up') + '</button>';
    carousel.append(controls);
    const status = controls.querySelector('.partner-slider-status');
    const toggle = controls.querySelector('.partner-slider-toggle');
    const previous = controls.querySelector('.partner-slider-prev');
    const next = controls.querySelector('.partner-slider-next');
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    let paused = reduced.matches, inView = false, hovered = false, focused = false;
    let timer, scrollTimer, targetIndex = 0;
    const perPage = () => Number(getComputedStyle(track).getPropertyValue('--partner-visible')) || 4;
    const step = () => items.length > 1 ? items[1].offsetLeft - items[0].offsetLeft : track.clientWidth;
    const firstVisible = () => Math.max(0, Math.min(items.length - 1, Math.round(track.scrollLeft / step())));
    const updateStatus = () => {
      const first = firstVisible();
      status.textContent = (first + 1) + '–' + Math.min(first + perPage(), items.length) + ' van ' + items.length;
    };
    const finishScroll = () => { targetIndex = firstVisible(); updateStatus(); };
    const schedule = () => {
      clearTimeout(timer);
      if (!paused && !reduced.matches && inView && !hovered && !focused && !document.hidden && items.length > perPage()) {
        timer = setTimeout(() => advance(1, false), 4500);
      }
    };
    const updatePause = () => {
      toggle.innerHTML = icon(paused ? 'play' : 'pause') + '<span>' + (paused ? 'Afspelen' : 'Pauzeren') + '</span>';
      toggle.setAttribute('aria-label', paused ? 'Sponsorcarrousel afspelen' : 'Sponsorcarrousel pauzeren');
      status.setAttribute('aria-live', paused || reduced.matches ? 'polite' : 'off');
      // Manual navigation remains available when the visitor requests less motion.
      toggle.hidden = reduced.matches || items.length <= perPage();
      schedule();
    };
    const advance = (direction, manual) => {
      const last = Math.max(0, items.length - perPage());
      const wrapping = direction > 0 ? targetIndex >= last : targetIndex <= 0;
      targetIndex = direction > 0 ? (targetIndex >= last ? 0 : Math.min(last, targetIndex + perPage()))
        : (targetIndex <= 0 ? last : Math.max(0, targetIndex - perPage()));
      if (manual) { paused = true; updatePause(); }
      track.scrollTo({left: targetIndex * step(), behavior: reduced.matches || wrapping ? 'instant' : 'smooth'});
      schedule();
    };
    previous.addEventListener('click', () => advance(-1, true));
    next.addEventListener('click', () => advance(1, true));
    toggle.addEventListener('click', () => { paused = !paused; updatePause(); });
    track.addEventListener('scroll', () => {
      clearTimeout(scrollTimer);
      scrollTimer = setTimeout(finishScroll, 120);
    }, {passive: true});
    track.addEventListener('scrollend', finishScroll);
    track.addEventListener('pointerdown', () => { paused = true; updatePause(); });
    track.addEventListener('wheel', () => { paused = true; updatePause(); }, {passive: true});
    track.addEventListener('mouseenter', () => { hovered = true; schedule(); });
    track.addEventListener('mouseleave', () => { hovered = false; schedule(); });
    track.addEventListener('focusin', () => { focused = true; schedule(); });
    track.addEventListener('focusout', event => { focused = track.contains(event.relatedTarget); schedule(); });
    document.addEventListener('visibilitychange', schedule);
    reduced.addEventListener('change', () => { paused = reduced.matches || paused; updatePause(); });
    new IntersectionObserver(entries => { inView = entries[0].isIntersecting; schedule(); }, {threshold: .25}).observe(carousel);
    new ResizeObserver(() => {
      const last = Math.max(0, items.length - perPage());
      targetIndex = Math.min(targetIndex, last);
      track.scrollTo({left: targetIndex * step(), behavior: 'instant'});
      previous.disabled = next.disabled = last === 0;
      updateStatus(); updatePause();
    }).observe(track);
    updateStatus(); updatePause();
  };
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
    setupSlider(wall);
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
