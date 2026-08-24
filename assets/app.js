/* CoreForge storefront interactions.
   Everything here is progressive enhancement: without JS the forms still POST. */
(function () {
  'use strict';

  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const bagBtn   = $('#bagBtn');
  const bagCount = $('#bagCount');
  const drawer   = $('#drawer');
  const scrim    = $('#scrim');

  /* ---------- Toast ---------- */
  let toastTimer;
  function toast(msg, tone = 'success') {
    if (!msg) return;
    let t = $('#toast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'toast';
      t.className = 'toast';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.dataset.tone = tone;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 2600);
  }

  /* ---------- Fly-to-bag ---------- */
  function flyToBag(imgEl) {
    if (reduced || !imgEl || !bagBtn) return Promise.resolve();

    const from = imgEl.getBoundingClientRect();
    const to   = bagBtn.getBoundingClientRect();
    if (!from.width || !to.width) return Promise.resolve();

    const ghost = document.createElement('img');
    ghost.src = imgEl.currentSrc || imgEl.src;
    ghost.className = 'fly-ghost';
    Object.assign(ghost.style, {
      left:   from.left + 'px',
      top:    from.top + 'px',
      width:  from.width + 'px',
      height: from.height + 'px',
    });
    document.body.appendChild(ghost);

    const dx = (to.left + to.width / 2)  - (from.left + from.width / 2);
    const dy = (to.top  + to.height / 2) - (from.top  + from.height / 2);

    // Arc upward first, then drop into the bag.
    const anim = ghost.animate([
      { transform: 'translate(0,0) scale(1)', opacity: 1, borderRadius: '0px' },
      { transform: `translate(${dx * 0.55}px, ${dy * 0.35 - 70}px) scale(.55)`, opacity: .92, borderRadius: '50%', offset: .55 },
      { transform: `translate(${dx}px, ${dy}px) scale(.08)`, opacity: 0, borderRadius: '50%' },
    ], { duration: 720, easing: 'cubic-bezier(.5,.05,.35,1)' });

    return anim.finished.catch(() => {}).then(() => ghost.remove());
  }

  function bumpBag() {
    if (!bagCount || reduced) return;
    bagCount.classList.remove('bump');
    void bagCount.offsetWidth;          // restart the animation
    bagCount.classList.add('bump');
  }

  /* ---------- Server calls ---------- */
  async function post(action, id) {
    const body = new URLSearchParams({ cart_action: action });
    if (id) body.set('product_id', id);
    const res = await fetch('cart_api.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'fetch' },
      body,
    });
    if (!res.ok) throw new Error('Request failed');
    return res.json();
  }

  function render(data) {
    if (bagCount) bagCount.textContent = data.count;
    if (bagBtn) bagBtn.classList.toggle('has-items', data.count > 0);
    renderDrawer(data);
    // The drawer is the bag once JS is running; the server-rendered
    // section on products.php is the no-JS fallback and stays hidden.
  }

  /* ---------- Drawer ---------- */
  function openDrawer()  { drawer?.classList.add('open'); scrim?.classList.add('show'); document.body.style.overflow = 'hidden'; drawer?.querySelector('.drawer-close')?.focus(); }
  function closeDrawer() { drawer?.classList.remove('open'); scrim?.classList.remove('show'); document.body.style.overflow = ''; }

  function renderDrawer(data) {
    const body  = $('#drawerBody');
    const foot  = $('#drawerFoot');
    if (!body || !foot) return;

    if (!data.items.length) {
      body.innerHTML = '<div class="drawer-empty"><p>Your bag is empty.</p><a href="products.php" class="btn btn-light" style="margin-top:18px">Start shopping</a></div>';
      foot.hidden = true;
      return;
    }

    body.innerHTML = data.items.map(it => `
      <div class="drawer-line" data-id="${it.id}">
        <img src="assets/img/${it.image}" alt="">
        <div class="dl-info">
          <span class="dl-brand">${esc(it.brand)}</span>
          <span class="dl-name">${esc(it.name)}</span>
          <span class="dl-sku">${esc(it.sku)}</span>
        </div>
        <div class="dl-right">
          <span class="dl-line">${it.line}</span>
          <div class="qty dark">
            <button data-act="decrement" data-id="${it.id}" aria-label="Decrease quantity">&minus;</button>
            <span class="v">${it.qty}</span>
            <button data-act="add" data-id="${it.id}" aria-label="Increase quantity" ${it.qty >= it.stock ? 'disabled' : ''}>+</button>
          </div>
          <button class="dl-remove" data-act="remove" data-id="${it.id}">Remove</button>
        </div>
      </div>`).join('');

    foot.hidden = false;
    $('#dSub').textContent   = data.subtotal;
    $('#dShip').textContent  = data.shipping;
    $('#dTotal').textContent = data.total;
    const note = $('#dNote');
    if (note) {
      note.textContent = data.freeFrom ? `Spend ${data.freeFrom} more for free shipping.` : 'Your order ships free.';
    }
  }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }

  /* ---------- Add to bag ---------- */
  async function addToBag(form) {
    const btn = form.querySelector('button');
    const id  = form.querySelector('[name=product_id]')?.value;
    if (!id || btn.disabled || btn.dataset.busy) return;

    const card = form.closest('.p-card');
    const img  = card?.querySelector('.p-media img');

    btn.dataset.busy = '1';
    const original = btn.innerHTML;
    btn.classList.add('is-loading');
    btn.innerHTML = '<span class="spin" aria-hidden="true"></span><span>Adding</span>';

    const flight = flyToBag(img);

    try {
      const data = await post('add', id);
      await flight;

      if (data.ok) {
        btn.classList.remove('is-loading');
        btn.classList.add('is-done');
        btn.innerHTML = '<span class="tick" aria-hidden="true">&#10003;</span><span>Added</span>';
        bumpBag();
        render(data);
        setTimeout(() => {
          btn.classList.remove('is-done');
          btn.innerHTML = data.qty > 1 ? `In bag (${data.qty})` : 'In bag (1)';
          delete btn.dataset.busy;
        }, 1100);
      } else {
        btn.classList.remove('is-loading');
        btn.innerHTML = original;
        delete btn.dataset.busy;
        toast(data.message, data.tone === 'warn' ? 'warn' : 'error');
        render(data);
      }
    } catch (err) {
      btn.classList.remove('is-loading');
      btn.innerHTML = original;
      delete btn.dataset.busy;
      toast('Could not reach the server — please try again.', 'error');
    }
  }

  /* ---------- Wiring ---------- */
  document.addEventListener('submit', e => {
    const form = e.target.closest('form[data-bag]');
    if (!form) return;
    e.preventDefault();
    const action = form.querySelector('[name=cart_action]')?.value;
    if (action === 'add') {
      addToBag(form);
    } else {
      const id = form.querySelector('[name=product_id]')?.value;
      post(action, id).then(d => { render(d); toast(d.message, 'success'); }).catch(() => toast('Something went wrong.', 'error'));
    }
  });

  // Quantity and remove controls inside the drawer.
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-act]');
    if (!b || !b.closest('#drawer')) return;
    e.preventDefault();
    if (b.disabled) return;
    post(b.dataset.act, b.dataset.id)
      .then(d => { render(d); if (b.dataset.act === 'add') bumpBag(); })
      .catch(() => toast('Something went wrong.', 'error'));
  });

  bagBtn?.addEventListener('click', e => {
    e.preventDefault();
    openDrawer();
  });

  $$('[data-close-drawer]').forEach(el => el.addEventListener('click', closeDrawer));
  scrim?.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

  /* Mark JS as live (CSS hides the no-JS bag section) and prime the drawer. */
  if (drawer) {
    document.documentElement.classList.add('js-bag');
    post('peek', null).then(render).catch(() => {});
  }
})();
