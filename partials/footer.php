</main>

<footer class="site-footer">
  <div class="wrap">
    <div class="footer-grid">
      <div>
        <div class="brand" style="font-size:15px;font-weight:700;letter-spacing:.2em;text-transform:uppercase"><?= BRAND_FULL ?></div>
        <p class="tagline"><?= e(TAGLINE) ?></p>
      </div>
      <div>
        <h4>Shop</h4>
        <ul>
          <?php foreach (array_keys(CATEGORIES) as $c): ?>
            <li><a href="products.php?category=<?= urlencode($c) ?>"><?= e($c) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h4>Company</h4>
        <ul>
          <li><a href="articles.php">Journal</a></li>
          <li><a href="contact.php">Contact</a></li>
          <?php if (isAdmin()): ?>
            <li><a href="manage_products.php">Admin panel</a></li>
            <li><a href="login.php?logout=1">Sign out</a></li>
          <?php else: ?>
            <li><a href="login.php">Staff login</a></li>
          <?php endif; ?>
        </ul>
      </div>
      <div>
        <h4>Newsletter</h4>
        <p style="font-size:.87rem;color:#8b8884">Build guides and restock alerts. No noise.</p>
        <form class="news" onsubmit="event.preventDefault();this.reset();this.querySelector('button').textContent='Done';">
          <input type="email" placeholder="Email address" aria-label="Email address" required>
          <button type="submit">Join</button>
        </form>
        <ul style="margin-top:18px">
          <li><a href="mailto:<?= e(STORE_EMAIL) ?>"><?= e(STORE_EMAIL) ?></a></li>
          <li><?= e(STORE_PHONE) ?></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= BRAND_FULL ?></span>
      <span><?= e(STORE_ADDR) ?></span>
    </div>
  </div>
</footer>

<!-- Bag drawer -->
<div class="scrim" id="scrim" aria-hidden="true"></div>
<aside class="drawer" id="drawer" role="dialog" aria-modal="true" aria-label="Shopping bag">
  <div class="drawer-head">
    <h2>Your bag</h2>
    <button class="drawer-close" data-close-drawer aria-label="Close bag">&times;</button>
  </div>
  <div class="drawer-body" id="drawerBody">
    <div class="drawer-empty"><p>Your bag is empty.</p></div>
  </div>
  <div class="drawer-foot" id="drawerFoot" hidden>
    <div class="row"><span>Subtotal</span><span id="dSub">$0.00</span></div>
    <div class="row"><span>Shipping</span><span id="dShip">Free</span></div>
    <div class="row grand"><span>Total</span><span id="dTotal">$0.00</span></div>
    <p class="drawer-note" id="dNote"></p>
    <a href="checkout.php" class="btn btn-light btn-block">Checkout</a>
  </div>
</aside>

<script src="assets/app.js" defer></script>
<script src="assets/motion.js" defer></script>
<script>
  const t = document.getElementById('navToggle'), n = document.getElementById('nav');
  t?.addEventListener('click', () => {
    const open = n.classList.toggle('open');
    t.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
</script>
</body>
</html>
