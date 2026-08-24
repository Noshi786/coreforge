/* CoreForge admin — reveals, counters, bars, filtering, delete modal. */
(function () {
  'use strict';
  window.__adminReady = true;
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Sidebar (mobile) */
  const sb = $('#sidebar'), scrim = $('#sbScrim');
  $('#burger')?.addEventListener('click', () => { sb.classList.toggle('open'); scrim.classList.toggle('show'); });
  scrim?.addEventListener('click', () => { sb.classList.remove('open'); scrim.classList.remove('show'); });

  /* Reveal on scroll */
  $$('.rv').forEach((el, i) => el.style.setProperty('--d', Math.min(i * 55, 420) + 'ms'));
  if (reduced) {
    $$('.rv').forEach(el => el.classList.add('in'));
  } else {
    const io = new IntersectionObserver(es => es.forEach(e => {
      if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
    }), { threshold: 0.08, rootMargin: '0px 0px -4% 0px' });
    $$('.rv').forEach(el => {
      const r = el.getBoundingClientRect();
      if (r.top < innerHeight) setTimeout(() => el.classList.add('in'), parseInt(el.style.getPropertyValue('--d')) || 0);
      else io.observe(el);
    });
  }

  /* Bars + rails fill when seen */
  const fillIO = new IntersectionObserver(es => es.forEach(e => {
    if (!e.isIntersecting) return;
    e.target.style.width = e.target.dataset.fill + '%';
    fillIO.unobserve(e.target);
  }), { threshold: .25 });
  $$('[data-fill]').forEach(el => reduced ? el.style.width = el.dataset.fill + '%' : fillIO.observe(el));

  /* Donut */
  $$('[data-donut]').forEach(el => {
    const p = parseFloat(el.dataset.donut) || 0;
    if (reduced) { el.style.setProperty('--p', p); return; }
    const dIO = new IntersectionObserver(es => {
      if (!es[0].isIntersecting) return;
      dIO.disconnect();
      const t0 = performance.now(), dur = 1100;
      (function step(now) {
        const k = Math.min((now - t0) / dur, 1);
        el.style.setProperty('--p', (p * (1 - Math.pow(1 - k, 3))).toFixed(2));
        if (k < 1) requestAnimationFrame(step);
      })(t0);
    }, { threshold: .3 });
    dIO.observe(el);
  });

  /* Count-up */
  $$('[data-count]').forEach(el => {
    const target = parseFloat(el.dataset.count);
    if (!isFinite(target)) return;
    const pre = el.dataset.prefix || '', dp = el.dataset.dp | 0;
    const show = v => el.textContent = pre + v.toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });
    if (reduced) { show(target); return; }
    const cIO = new IntersectionObserver(es => {
      if (!es[0].isIntersecting) return;
      cIO.disconnect();
      const t0 = performance.now(), dur = 1000;
      (function step(now) {
        const k = Math.min((now - t0) / dur, 1);
        show(target * (1 - Math.pow(1 - k, 3)));
        if (k < 1) requestAnimationFrame(step);
      })(t0);
    }, { threshold: .35 });
    cIO.observe(el);
  });

  /* Table filter + category select */
  const box = $('#tFilter'), cat = $('#tCat'), out = $('#tCount');
  function applyFilter() {
    if (!box && !cat) return;
    const q = (box?.value || '').trim().toLowerCase();
    const c = cat?.value || '';
    let n = 0;
    $$('table.t2 tbody tr[data-row]').forEach(r => {
      const hitQ = !q || r.dataset.search.includes(q);
      const hitC = !c || r.dataset.cat === c;
      const show = hitQ && hitC;
      r.classList.toggle('gone', !show);
      if (show) n++;
    });
    if (out) out.textContent = n;
    const none = $('#tNone');
    if (none) none.hidden = n > 0;
  }
  box?.addEventListener('input', applyFilter);
  cat?.addEventListener('change', applyFilter);

  /* Delete modal */
  const modal = $('#delModal');
  window.askDelete = (id, name) => {
    $('#delId').value = id;
    $('#delName').textContent = name;
    modal.classList.add('open');
  };
  window.closeDelete = () => modal?.classList.remove('open');
  modal?.addEventListener('click', e => { if (e.target === modal) closeDelete(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDelete(); });

  /* Live preview on the add/edit form */
  const pv = $('#pvImg');
  if (pv) {
    $$('.pick input').forEach(i => i.addEventListener('change', () => {
      pv.style.opacity = 0;
      setTimeout(() => { pv.src = 'assets/img/' + i.value; pv.style.opacity = 1; }, 140);
    }));
    const bind = (id, target, fmt) => {
      const el = $(id), t = $(target);
      el?.addEventListener('input', () => t.textContent = fmt ? fmt(el.value) : (el.value || t.dataset.empty));
    };
    bind('#f-name',  '#pvName');
    bind('#f-brand', '#pvBrand', v => (v || 'Brand').toUpperCase());
    bind('#f-price', '#pvPrice', v => '$' + (parseFloat(v || 0)).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
  }

  /* Toast from a URL flag */
  const t = $('#aToast');
  const msg = new URLSearchParams(location.search).get('msg');
  if (t && msg) { t.textContent = msg; t.classList.add('show'); setTimeout(() => t.classList.remove('show'), 3200); }
})();
