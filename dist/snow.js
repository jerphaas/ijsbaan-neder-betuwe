(() => {
  'use strict';
  const hero = document.querySelector('.hero');
  const snow = document.querySelector('.snowfall');
  const toggle = document.querySelector('.snow-toggle');
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  if (!hero || !snow || !toggle) return;

  // A small set of compositor-animated flakes; no canvas or per-frame JS.
  const particles = document.createDocumentFragment();
  for (let i = 0; i < 38; i++) {
    const particle = document.createElement('span');
    particle.className = 'snow-particle';
    particle.style.setProperty('--x', `${(i * 37.7) % 100}%`);
    particle.style.setProperty('--size', `${3 + (i * 7) % 10}px`);
    particle.style.setProperty('--speed', `${13 + (i * 3.3) % 17}s`);
    particle.style.setProperty('--offset', `${-(i * 2.8) % 30}s`);
    particle.style.setProperty('--drift', `${-28 + (i * 19) % 68}px`);
    particle.style.setProperty('--opacity', `${0.24 + (i % 5) * 0.09}`);
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('class', 'icon');
    const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', 'assets/icons.svg#snowflake');
    svg.append(use);
    particle.append(svg);
    particles.append(particle);
  }
  snow.append(particles);

  let userPaused = false;
  let inView = true;
  function update() {
    const active = !userPaused && !reduced.matches && inView && !document.hidden;
    snow.classList.toggle('snow-running', active);
    snow.classList.toggle('snow-paused-by-user', userPaused);
    toggle.hidden = reduced.matches;
    const label = userPaused ? 'Sneeuw aanzetten' : 'Sneeuw pauzeren';
    toggle.setAttribute('aria-label', label);
    toggle.querySelector('span').textContent = label;
    toggle.querySelector('use').setAttribute('href', `assets/icons.svg#${userPaused ? 'play' : 'pause'}`);
  }
  toggle.addEventListener('click', () => { userPaused = !userPaused; update(); });
  reduced.addEventListener('change', update);
  document.addEventListener('visibilitychange', update);
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(entries => {
      inView = entries[0].isIntersecting;
      update();
    }).observe(hero);
  }
  update();
})();
