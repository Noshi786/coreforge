<?php
require_once __DIR__ . '/partials/config.php';

$featured = $pdo->query("SELECT * FROM products WHERE stock > 0 ORDER BY id DESC LIMIT 4")->fetchAll();
$picks    = $pdo->query("SELECT * FROM products WHERE stock > 0 ORDER BY price DESC LIMIT 4")->fetchAll();

$counts = [];
foreach ($pdo->query("SELECT category, COUNT(*) n FROM products GROUP BY category") as $r) {
    $counts[$r['category']] = (int)$r['n'];
}
$total = array_sum($counts);

$page_title = BRAND_FULL . ' — PC Components & Hardware';
$page_desc  = TAGLINE;
require __DIR__ . '/partials/header.php';

/** One product card, used across the site. */
function card(array $p, int $i = 0): void { ?>
  <article class="p-card fade-up" style="animation-delay:<?= min($i * 70, 500) ?>ms">
    <div class="p-media">
      <?php [$cls, $label] = stockFlag((int)$p['stock']); if ($label): ?>
        <span class="p-flag <?= $cls ?>"><?= e($label) ?></span>
      <?php endif; ?>
      <img src="assets/img/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
      <div class="quick">
        <form method="post" action="products.php" data-bag>
          <input type="hidden" name="cart_action" value="add">
          <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-sm" <?= $p['stock'] <= 0 ? 'disabled' : '' ?>>
            <?= $p['stock'] <= 0 ? 'Sold out' : 'Quick add' ?>
          </button>
        </form>
      </div>
    </div>
    <div class="p-info">
      <span class="p-brand"><?= e($p['brand']) ?></span>
      <span class="p-name"><?= e($p['name']) ?></span>
      <span class="p-price"><?= money($p['price']) ?></span>
    </div>
  </article>
<?php }
?>

<!-- Hero -->
<section class="hero">
  <img src="assets/img/hero-main.jpg" alt="A finished desktop build on a workspace">
  <div class="wrap hero-inner">
    <span class="micro line-mask"><span>New season &middot; <?= $total ?> components in stock</span></span>
    <h1 class="display">
      <span class="line-mask"><span>Build it once.</span></span>
      <span class="line-mask"><span>Build it right.</span></span>
    </h1>
    <p class="line-mask"><span>Processors, graphics, memory and storage — every part bench-tested before it ships, and covered for two years.</span></p>
    <div class="hero-cta line-mask"><span style="display:flex;gap:12px;flex-wrap:wrap">
      <a href="products.php" class="btn btn-light">Shop all components</a>
      <a href="articles.php" class="btn btn-outline" style="color:#fff;border-color:rgba(255,255,255,.55)">Read the journal</a>
    </span></div>
  </div>
</section>

<!-- Brand marquee -->
<div class="marquee" aria-hidden="true">
  <div class="marquee-track">
    <?php $brands = ['AMD','NVIDIA','Intel','Corsair','Samsung','G.Skill','ASUS','MSI','Seagate','Kingston','Crucial','Seasonic','Western Digital'];
    for ($k = 0; $k < 2; $k++): ?>
      <span class="marquee-item"><?= implode('</span><span class="marquee-item">', $brands) ?></span>
    <?php endfor; ?>
  </div>
</div>

<!-- Categories -->
<section class="section">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="micro">Departments</span>
        <h2 class="h-lg">Shop by category</h2>
      </div>
      <a href="products.php" class="link-u">View everything</a>
    </div>
    <div class="cat-grid">
      <?php foreach (CATEGORIES as $cat => $img): ?>
        <a href="products.php?category=<?= urlencode($cat) ?>" class="cat">
          <img src="assets/img/<?= $img ?>" alt="<?= e($cat) ?>" loading="lazy">
          <span class="cat-label">
            <span class="n"><?= e($cat) ?></span>
            <span class="c"><?= $counts[$cat] ?? 0 ?> products</span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- New arrivals -->
<section class="section bone">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="micro">Just landed</span>
        <h2 class="h-lg">New arrivals</h2>
      </div>
      <a href="products.php?sort=newest" class="link-u">See all</a>
    </div>
    <?php if (!$featured): ?>
      <div class="empty"><p>Nothing in stock yet — add products from the <a href="manage_products.php" class="link-u">admin panel</a>.</p></div>
    <?php else: ?>
      <div class="p-grid">
        <?php foreach ($featured as $i => $p) card($p, $i); ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Editorial split -->
<section class="split">
  <div class="split-media"><img src="assets/img/hero-side.jpg" alt="A completed build on a desk" loading="lazy"></div>
  <div class="split-body band-dark">
    <span class="micro">Made by builders</span>
    <h2 class="h-lg" style="margin-top:14px">We only sell what we would put in our own machines.</h2>
    <p class="lede" style="margin-top:18px;max-width:48ch">
      Every CPU, GPU and memory kit is POST-tested and stress-checked on the bench before it reaches
      the shelf. If a part has a known quirk, it goes in the description — not in the small print.
    </p>
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:30px">
      <a href="products.php" class="btn">Shop the range</a>
      <a href="contact.php" class="btn btn-outline">Request a build spec</a>
    </div>
  </div>
</section>

<!-- Premium picks -->
<section class="section band-dark">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="micro">The top shelf</span>
        <h2 class="h-lg">Flagship picks</h2>
      </div>
      <a href="products.php?sort=price_high" class="link-u">Shop by price</a>
    </div>
    <div class="p-grid">
      <?php foreach ($picks as $i => $p) card($p, $i); ?>
    </div>
  </div>
</section>

<!-- Values -->
<section class="section bone">
  <div class="wrap">
    <div class="values">
      <?php
      $values = [
        ['01', 'Two-year warranty',  'Every component covered for 24 months, with RMA handled locally rather than shipped overseas.'],
        ['02', 'Bench-tested stock', 'Each part is POST-tested and stress-checked before it is listed as available.'],
        ['03', 'Next-day dispatch',  'Orders placed before 4pm ship the same working day, free over $150.'],
        ['04', 'Build support',      'Stuck on compatibility or a boot loop? We will work through it with you at no charge.'],
      ];
      foreach ($values as [$n, $t, $b]): ?>
        <div class="value">
          <span class="micro" style="color:var(--ink-soft)"><?= $n ?></span>
          <h3><?= $t ?></h3>
          <p><?= $b ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
