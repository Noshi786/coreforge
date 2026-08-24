<?php
require_once __DIR__ . '/partials/config.php';

$flash = null;

/* ---------------- Bag actions (POST → redirect, so refresh never resubmits) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_action'])) {
    $id  = (int)($_POST['product_id'] ?? 0);
    $act = $_POST['cart_action'];
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

    if ($act === 'add' && $id) {
        $stmt = $pdo->prepare("SELECT name, stock FROM products WHERE id = ?");
        $stmt->execute([$id]);
        if ($row = $stmt->fetch()) {
            $have = $_SESSION['cart'][$id] ?? 0;
            if ($row['stock'] <= 0) {
                $_SESSION['flash'] = ['warning', e($row['name']) . ' is sold out.'];
            } elseif ($have >= (int)$row['stock']) {
                $_SESSION['flash'] = ['warning', 'That is all the stock we have of ' . e($row['name']) . '.'];
            } else {
                $_SESSION['cart'][$id] = $have + 1;
                $_SESSION['flash'] = ['success', e($row['name']) . ' added to your bag.'];
            }
        }
    } elseif ($act === 'decrement' && $id) {
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]--;
            if ($_SESSION['cart'][$id] < 1) unset($_SESSION['cart'][$id]);
        }
    } elseif ($act === 'remove' && $id) {
        unset($_SESSION['cart'][$id]);
        $_SESSION['flash'] = ['success', 'Removed from your bag.'];
    } elseif ($act === 'clear') {
        $_SESSION['cart'] = [];
        $_SESSION['flash'] = ['success', 'Bag emptied.'];
    }

    $qs = $_GET ? '?' . http_build_query($_GET) : '';
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . $qs . '#bag');
    exit;
}
if (isset($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

/* ---------------- Filters ---------------- */
$category = $_GET['category'] ?? '';
$search   = trim($_GET['q'] ?? '');
$sort     = $_GET['sort'] ?? 'name';
$sortMap  = ['name'=>'name ASC','price_low'=>'price ASC','price_high'=>'price DESC','newest'=>'id DESC'];
$orderBy  = $sortMap[$sort] ?? $sortMap['name'];

$where = []; $args = [];
if ($category !== '' && isset(CATEGORIES[$category])) { $where[] = 'category = ?'; $args[] = $category; }
if ($search !== '') {
    $where[] = '(name LIKE ? OR sku LIKE ? OR brand LIKE ?)';
    $like = '%' . $search . '%';
    array_push($args, $like, $like, $like);
}
$sql  = "SELECT * FROM products" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY $orderBy";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$products = $stmt->fetchAll();

$counts = [];
foreach ($pdo->query("SELECT category, COUNT(*) n FROM products GROUP BY category") as $r) {
    $counts[$r['category']] = (int)$r['n'];
}
$total = array_sum($counts);

$bag = cartItems($pdo);
$subtotal = 0;
foreach ($bag as $b) $subtotal += $b['price'] * $b['qty'];
$shipping = ($subtotal > 0 && $subtotal < 150) ? 12.00 : 0.00;

$page_title = ($category ?: 'Shop') . ' — ' . BRAND_FULL;
$page_desc  = 'Browse processors, graphics cards, memory, storage and motherboards.';
require __DIR__ . '/partials/header.php';

function qs(array $overrides = []): string {
    $q = array_filter(array_merge($_GET, $overrides), fn($v) => $v !== '' && $v !== null);
    return $q ? '?' . http_build_query($q) : '';
}
?>

<section class="section-tight band-dark" style="padding-block:clamp(30px,4vw,54px)">
  <div class="wrap">
    <span class="micro">
      <a href="index.php">Home</a> / <?= $category ? e($category) : 'All components' ?>
    </span>
    <h1 class="h-lg" style="margin-top:12px"><?= $category ? e($category) : 'All components' ?></h1>
    <p class="lede" style="margin-top:8px">
      <?= count($products) ?> of <?= $total ?> products<?= $search !== '' ? ' matching &ldquo;' . e($search) . '&rdquo;' : '' ?>
    </p>
  </div>
</section>

<div class="wrap">
  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash[0]) ?>"><span><?= $flash[1] ?></span></div>
  <?php endif; ?>

  <!-- Filter rail -->
  <div class="rail">
    <a href="<?= qs(['category' => null]) ?>" class="rail-chip <?= $category === '' ? 'on' : '' ?>">All (<?= $total ?>)</a>
    <?php foreach (CATEGORIES as $cat => $img): ?>
      <a href="<?= qs(['category' => $cat]) ?>" class="rail-chip <?= $category === $cat ? 'on' : '' ?>">
        <?= e($cat) ?> (<?= $counts[$cat] ?? 0 ?>)
      </a>
    <?php endforeach; ?>

    <form method="get" class="rail-right">
      <?php if ($category): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
      <input type="search" name="q" class="input rail-search"
             placeholder="Search" value="<?= e($search) ?>" aria-label="Search products">
      <select name="sort" class="select rail-sort" onchange="this.form.submit()" aria-label="Sort">
        <option value="name"       <?= $sort==='name'?'selected':'' ?>>Alphabetical</option>
        <option value="price_low"  <?= $sort==='price_low'?'selected':'' ?>>Price: low to high</option>
        <option value="price_high" <?= $sort==='price_high'?'selected':'' ?>>Price: high to low</option>
        <option value="newest"     <?= $sort==='newest'?'selected':'' ?>>Newest</option>
      </select>
      <button class="btn btn-sm">Go</button>
    </form>
  </div>

  <!-- Grid -->
  <?php if (!$products): ?>
    <div class="empty">
      <p><strong>Nothing matches that search.</strong></p>
      <p style="margin-top:6px">Try a different keyword or clear the filters.</p>
      <a href="products.php" class="btn btn-outline" style="margin-top:22px">Clear filters</a>
    </div>
  <?php else: ?>
    <div class="p-grid">
      <?php foreach ($products as $i => $p):
        [$cls, $label] = stockFlag((int)$p['stock']);
        $inBag = $_SESSION['cart'][$p['id']] ?? 0; ?>
        <article class="p-card fade-up" style="animation-delay:<?= min($i * 45, 520) ?>ms">
          <div class="p-media">
            <?php if ($label): ?><span class="p-flag <?= $cls ?>"><?= e($label) ?></span><?php endif; ?>
            <img src="assets/img/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
            <div class="quick">
              <form method="post" action="<?= qs() ?>" data-bag>
                <input type="hidden" name="cart_action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-sm" <?= $p['stock'] <= 0 ? 'disabled' : '' ?>>
                  <?= $p['stock'] <= 0 ? 'Sold out' : ($inBag ? "In bag ($inBag)" : 'Quick add') ?>
                </button>
              </form>
            </div>
          </div>
          <div class="p-info">
            <span class="p-brand"><?= e($p['brand']) ?></span>
            <span class="p-name"><?= e($p['name']) ?></span>
            <span class="p-price"><?= money($p['price']) ?></span>
            <span class="p-sku"><?= e($p['sku']) ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Bag -->
<section class="section" id="bag">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="micro">Your selection</span>
        <h2 class="h-lg">Shopping bag</h2>
      </div>
      <?php if ($bag): ?>
        <form method="post"><input type="hidden" name="cart_action" value="clear">
          <button class="btn-ghost" style="border:none;background:none;cursor:pointer" >Empty bag</button></form>
      <?php endif; ?>
    </div>

    <?php if (!$bag): ?>
      <div class="empty"><p>Your bag is empty — add a component above to get started.</p></div>
    <?php else: ?>
      <?php foreach ($bag as $b): ?>
        <div class="cart-line">
          <img src="assets/img/<?= e($b['image']) ?>" alt="" loading="lazy">
          <div style="flex:1;min-width:160px">
            <div class="p-brand"><?= e($b['brand']) ?></div>
            <div style="font-weight:500;margin-top:2px"><?= e($b['name']) ?></div>
            <div class="p-sku" style="margin-top:3px"><?= e($b['sku']) ?></div>
          </div>
          <div class="qty">
            <form method="post"><input type="hidden" name="cart_action" value="decrement">
              <input type="hidden" name="product_id" value="<?= (int)$b['id'] ?>">
              <button aria-label="Decrease quantity">&minus;</button></form>
            <span class="v"><?= (int)$b['qty'] ?></span>
            <form method="post"><input type="hidden" name="cart_action" value="add">
              <input type="hidden" name="product_id" value="<?= (int)$b['id'] ?>">
              <button aria-label="Increase quantity">+</button></form>
          </div>
          <div style="min-width:96px;text-align:right;font-weight:500"><?= money($b['price'] * $b['qty']) ?></div>
          <form method="post"><input type="hidden" name="cart_action" value="remove">
            <input type="hidden" name="product_id" value="<?= (int)$b['id'] ?>">
            <button class="btn-ghost" style="border:none;background:none;cursor:pointer" aria-label="Remove">Remove</button></form>
        </div>
      <?php endforeach; ?>

      <div class="totals">
        <div class="row"><span>Subtotal</span><span><?= money($subtotal) ?></span></div>
        <div class="row">
          <span>Shipping<?= $shipping == 0 ? ' — free over $150' : '' ?></span>
          <span><?= $shipping == 0 ? 'Free' : money($shipping) ?></span>
        </div>
        <div class="row grand"><span>Total</span><span><?= money($subtotal + $shipping) ?></span></div>
        <a href="checkout.php" class="btn btn-block" style="margin-top:18px">Proceed to checkout</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
