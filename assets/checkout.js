/* Checkout — card formatting, brand detection and the live card preview.
   All validation here is convenience only; checkout.php re-checks everything. */
(function () {
  'use strict';
  const $ = s => document.getElementById(s);

  const num = $('card_number'), exp = $('card_exp'), cvc = $('card_cvc'), holder = $('card_name');
  const card = $('card3d'), pvNum = $('cardNum'), pvName = $('cardHolder'),
        pvExp = $('cardExp'), pvCvc = $('cardCvc'), mark = $('cardBrandMark'), tag = $('brandTag');
  if (!num) return;

  const digits = v => v.replace(/\D+/g, '');

  function brandOf(n) {
    if (/^4/.test(n))                 return 'Visa';
    if (/^(5[1-5]|2[2-7])/.test(n))   return 'Mastercard';
    if (/^3[47]/.test(n))             return 'Amex';
    if (/^(6011|65|64[4-9])/.test(n)) return 'Discover';
    if (/^3(0[0-5]|[68])/.test(n))    return 'Diners';
    if (/^35(2[89]|[3-8])/.test(n))   return 'JCB';
    return '';
  }
  const isAmex = n => /^3[47]/.test(n);
  const groupsFor = n => isAmex(n) ? [4, 6, 5] : [4, 4, 4, 4];
  const maxLen = n => isAmex(n) ? 15 : 16;

  function format(n) {
    const out = [];
    let i = 0;
    for (const g of groupsFor(n)) {
      if (i >= n.length) break;
      out.push(n.slice(i, i + g));
      i += g;
    }
    return out.join(' ');
  }

  /* Render the number onto the card, animating only the digit just typed. */
  let prevMasked = '';
  function paintNumber(n) {
    const groups = groupsFor(n);
    const total = maxLen(n);
    let filled = '';
    for (let i = 0; i < total; i++) filled += (i < n.length ? n[i] : '•');
    const parts = [];
    let i = 0;
    for (const g of groups) { parts.push(filled.slice(i, i + g)); i += g; }
    const masked = parts.join(' ');

    pvNum.innerHTML = masked.split('').map((ch, idx) => {
      const changed = prevMasked[idx] !== undefined && prevMasked[idx] !== ch && ch !== '•';
      return `<span class="${changed ? 'pop' : ''}">${ch === ' ' ? '&nbsp;' : ch}</span>`;
    }).join('');
    prevMasked = masked;
  }

  function syncBrand(n) {
    const b = brandOf(n);
    card.dataset.brand = b;
    mark.textContent = b;
    tag.textContent = b;
    tag.classList.toggle('on', !!b);
    cvc.maxLength = isAmex(n) || !b ? 4 : 3;
    return b;
  }

  /* Reformatting on every keystroke rewrites the whole value, which drops the
     caret. Count the digits to the left of the caret, reformat, then put the
     caret back after that many digits -- otherwise typed characters land in
     the wrong place and the number comes out scrambled. */
  function reformat(el, formatter) {
    const before = el.selectionStart ?? el.value.length;
    const digitsBefore = digits(el.value.slice(0, before)).length;
    const raw = digits(el.value).slice(0, maxLen(digits(el.value)));
    el.value = formatter(raw);

    let pos = 0, seen = 0;
    while (pos < el.value.length && seen < digitsBefore) {
      if (/\d/.test(el.value[pos])) seen++;
      pos++;
    }
    // Step over a separator so the caret sits before the next digit.
    while (pos < el.value.length && !/\d/.test(el.value[pos]) && seen >= digitsBefore) break;
    try { el.setSelectionRange(pos, pos); } catch (_) {}
    return raw;
  }

  num.addEventListener('input', () => {
    const n = reformat(num, format);
    syncBrand(n);
    paintNumber(n);
  });

  exp.addEventListener('input', () => {
    let v = digits(exp.value).slice(0, 4);
    // Typing "5" for the month becomes "05/"
    if (v.length === 1 && v > '1') v = '0' + v;
    if (v.length >= 3) v = v.slice(0, 2) + '/' + v.slice(2);
    exp.value = v;
    pvExp.textContent = v || 'MM/YY';
  });

  cvc.addEventListener('input', () => {
    cvc.value = digits(cvc.value).slice(0, cvc.maxLength);
    pvCvc.textContent = cvc.value.replace(/./g, '•') || '•••';
  });
  cvc.addEventListener('focus', () => card.classList.add('flip'));
  cvc.addEventListener('blur',  () => card.classList.remove('flip'));

  holder.addEventListener('input', () => {
    pvName.textContent = holder.value.trim().toUpperCase() || 'YOUR NAME';
  });

  /* Clear a field's error styling as soon as the user edits it. */
  document.querySelectorAll('.co-input').forEach(el => {
    el.addEventListener('input', () => {
      el.classList.remove('bad');
      el.nextElementSibling?.classList?.remove('show');
    });
  });

  /* ============================================================
     Payment submit: overlay with staged progress, then a success
     animation. Falls back to a normal form POST if anything here
     is unavailable.
     ============================================================ */
  const form = $('coForm'), btn = $('payBtn');
  const overlay = $('payOverlay'), box = $('payBox');
  const steps = Array.from(document.querySelectorAll('[data-step]'));
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const wait = ms => new Promise(r => setTimeout(r, reduced ? 0 : ms));

  function showFieldErrors(errs) {
    let first = null;
    for (const [key, msg] of Object.entries(errs)) {
      const el = document.getElementById(key);
      if (!el) continue;
      el.classList.add('bad');
      const box = el.parentElement.querySelector('.co-err');
      if (box) { box.textContent = msg; box.classList.add('show'); }
      if (!first) first = el;
    }
    if (errs.bag) alert(errs.bag);
    first?.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'center' });
    first?.focus({ preventScroll: true });
  }

  function confettiBurst() {
    if (reduced) return;
    const host = $('confetti');
    const colors = ['#1c4c3b', '#d1b06b', '#111111', '#8fbfa8', '#c8a44d'];
    for (let i = 0; i < 34; i++) {
      const bit = document.createElement('i');
      const angle = (Math.PI * 2 * i) / 34 + Math.random() * 0.4;
      const dist = 90 + Math.random() * 130;
      bit.style.background = colors[i % colors.length];
      bit.style.setProperty('--tx', Math.cos(angle) * dist + 'px');
      bit.style.setProperty('--ty', Math.sin(angle) * dist + 'px');
      bit.style.setProperty('--rot', Math.random() * 720 - 360 + 'deg');
      bit.style.animationDelay = (Math.random() * 0.12) + 's';
      host.appendChild(bit);
    }
    setTimeout(() => (host.innerHTML = ''), 1600);
  }

  /** Walk the checklist, holding each step briefly. */
  async function runSteps(upTo) {
    for (let i = 0; i < upTo; i++) {
      steps[i].classList.add('active');
      await wait(430 + Math.random() * 260);
      steps[i].classList.remove('active');
      steps[i].classList.add('done');
    }
  }

  async function succeed(data) {
    // Finish any remaining steps, then flip the box to its success state.
    steps.forEach(s => { s.classList.remove('active'); s.classList.add('done'); });
    await wait(320);

    box.classList.add('success');
    $('payTickWrap').hidden = false;
    $('payTitle').textContent = 'Payment successful';
    $('paySub').textContent = 'Thank you — your order is confirmed.';
    $('payCard').textContent = data.brand + ' •••• ' + data.last4;
    $('payRef').textContent = data.ref;
    $('payAmt').textContent = data.total;
    $('payDone').hidden = false;
    confettiBurst();

    await wait(2600);
    window.location.href = data.redirect;
  }

  function closeOverlay() {
    overlay.classList.remove('on');
    document.body.style.overflow = '';
    steps.forEach(s => s.classList.remove('active', 'done'));
    btn.disabled = false;
    btn.textContent = btn.dataset.label || 'Pay';
  }

  if (form && overlay) {
    btn.dataset.label = btn.textContent.trim();

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (btn.disabled) return;

      btn.disabled = true;
      overlay.classList.add('on');
      document.body.style.overflow = 'hidden';

      // Start the visible steps and the request together, so a fast server
      // still shows the animation and a slow one does not stall it.
      const stepsRun = runSteps(3);
      let res, data;
      try {
        res = await fetch('checkout.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'fetch' },
          body: new FormData(form),
        });
        data = await res.json();
      } catch (err) {
        await stepsRun;
        closeOverlay();
        alert('We could not reach the payment server. Please try again.');
        return;
      }

      await stepsRun;

      if (data && data.ok) {
        await succeed(data);
      } else {
        // Let the failing step register before pulling the overlay away.
        await wait(320);
        closeOverlay();
        showFieldErrors((data && data.errors) || {});
      }
    });
  }

  /* Paint whatever the server rendered back after a failed submit. */
  const start = digits(num.value);
  if (start) { num.value = format(start); syncBrand(start); paintNumber(start); }
  else paintNumber('');
  if (holder.value) pvName.textContent = holder.value.toUpperCase();
  if (exp.value) pvExp.textContent = exp.value;
})();
