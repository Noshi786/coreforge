/* CoreForge motion — scroll reveals, parallax, counters.
   Purely decorative: if this file fails to load, the site still works. */
(function () {
  'use strict';

  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  // Tell the head failsafe we arrived, so it leaves the .motion class in place.
  window.__motionReady = true;
  document.documentElement.classList.add('motion');

  /* ---------- Auto-tag things that should reveal ---------- */
  const AUTO = [
    '.section-head', '.p-card', '.cat', '.value', '.ed-card',
    '.split-body > *', '.stats', '.table-wrap', '.rail', '.empty',
    '.contact-card', '.faq-item', '.info-tile',
  ];
  AUTO.forEach(sel => $$(sel).forEach(el => {
    if (!el.hasAttribute('data-reveal') && !el.closest('#drawer')) el.setAttribute('data-reveal', '');
  }));

  /* Stagger siblings inside a grid so they cascade rather than pop together. */
  $$('.p-grid, .cat-grid, .values').forEach(grid => {
    Array.from(grid.children).forEach((child, i) => {
      if (child.hasAttribute('data-reveal')) child.style.setProperty('--d', Math.min(i * 80, 640) + 'ms');
    });
  });

  if (reduced) {
    $$('[data-reveal]').forEach(el => el.classList.add('in'));
    return;
  }

  /* ---------- Reveal on scroll ---------- */
  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('in');
        io.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

  $$('[data-reveal]').forEach(el => {
    // Anything already on screen at load reveals immediately, staggered.
    const r = el.getBoundingClientRect();
    if (r.top < window.innerHeight * 0.92 && r.bottom > 0) {
      setTimeout(() => el.classList.add('in'), parseInt(el.style.getPropertyValue('--d')) || 0);
    } else {
      io.observe(el);
    }
  });

  /* ---------- Masked heading lines ---------- */
  $$('.line-mask').forEach((el, i) => {
    el.style.setProperty('--d', i * 110 + 'ms');
    setTimeout(() => el.classList.add('in'), 120 + i * 110);
  });

  /* ---------- Parallax + scroll progress ---------- */
  const splits = $$('.split-media');
  const bar = document.getElementById('scrollBar');
  let ticking = false;

  function frame() {
    ticking = false;
    const vh = window.innerHeight;

    splits.forEach(el => {
      const r = el.getBoundingClientRect();
      if (r.bottom < -100 || r.top > vh + 100) return;
      // -1 .. 1 as the panel crosses the viewport
      const p = (r.top + r.height / 2 - vh / 2) / (vh / 2 + r.height / 2);
      const img = el.querySelector('img');
      if (img) img.style.setProperty('--py', (p * 38).toFixed(1) + 'px');
    });

    if (bar) {
      const h = document.documentElement.scrollHeight - vh;
      bar.style.width = (h > 0 ? (window.scrollY / h) * 100 : 0) + '%';
    }
  }

  function onScroll() {
    if (!ticking) { ticking = true; requestAnimationFrame(frame); }
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll, { passive: true });
  frame();

  /* ---------- Count-up for admin stat tiles ---------- */
  $$('[data-count]').forEach(el => {
    const target = parseFloat(el.dataset.count);
    if (!isFinite(target)) return;
    const prefix = el.dataset.prefix || '';
    const dp = (el.dataset.dp | 0);
    const seen = new IntersectionObserver(entries => {
      if (!entries[0].isIntersecting) return;
      seen.disconnect();
      const dur = 1100, t0 = performance.now();
      (function step(now) {
        const k = Math.min((now - t0) / dur, 1);
        const eased = 1 - Math.pow(1 - k, 3);
        const v = target * eased;
        el.textContent = prefix + v.toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });
        if (k < 1) requestAnimationFrame(step);
      })(t0);
    }, { threshold: 0.4 });
    seen.observe(el);
  });

  /* ---------- FAQ accordion ---------- */
  $$('.faq-q').forEach(btn => {
    btn.addEventListener('click', () => {
      const item = btn.closest('.faq-item');
      const open = item.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      const panel = item.querySelector('.faq-a');
      panel.style.maxHeight = open ? panel.scrollHeight + 'px' : '';
    });
  });
})();
