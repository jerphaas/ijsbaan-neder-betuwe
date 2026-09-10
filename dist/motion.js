(() => {
  'use strict';

  // The page stays fully visible if JavaScript or motion support is unavailable.
  const preference = matchMedia('(prefers-reduced-motion: reduce)');
  if (!('IntersectionObserver' in window)) return;

  const targets = [...document.querySelectorAll([
    '.intro-copy', '.intro-image', '.section-heading', '.visit-item',
    '.program-note', '.together-image', '.together-copy > .eyebrow',
    '.together-copy > h2', '.story-row', '.sponsor-heading', '.sponsor-card',
    '.sponsor-footer', '.edition-item', '.edition-future',
    '.faq-grid > div:first-child', '.faq-items', '.contact > *', '.footer-top > *'
  ].join(','))];
  let observer;

  function reveal(element, immediate = false) {
    if (immediate) element.classList.add('motion-immediate');
    element.classList.add('is-revealed');
    observer?.unobserve(element);
  }

  function revealAll() {
    observer?.disconnect();
    document.documentElement.classList.remove('motion-enabled');
    targets.forEach(element => reveal(element, true));
  }

  function enableMotion() {
    if (preference.matches) { revealAll(); return; }
    try {
      observer?.disconnect();
      observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (entry.isIntersecting) reveal(entry.target);
        });
      }, { threshold: 0.08, rootMargin: '0px 0px -24px 0px' });

      targets.forEach(element => {
        element.classList.add('motion-reveal');
        if (element.matches('.sponsor-card, .visit-item, .story-row, .edition-item')) {
          const siblings = [...element.parentElement.children];
          element.style.setProperty('--reveal-delay', `${(siblings.indexOf(element) % 3) * 95}ms`);
        }
        // Never hide content already in view, above the viewport or keyboard-focused.
        if (element.getBoundingClientRect().top < innerHeight || element.matches(':focus-within')) {
          reveal(element, true);
        } else if (!element.classList.contains('is-revealed')) {
          observer.observe(element);
        }
      });
      document.documentElement.classList.add('motion-enabled');
    } catch {
      revealAll();
    }
  }

  document.addEventListener('focusin', event => {
    const target = event.target.closest('.motion-reveal');
    if (target) reveal(target, true);
  });
  // Hidden reveal states must never leave gaps in printed pages.
  window.addEventListener('beforeprint', revealAll);
  preference.addEventListener('change', enableMotion);
  enableMotion();
})();
