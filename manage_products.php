<?php
require_once __DIR__ . '/partials/auth.php';
requireAdmin();

$errors = [];

/** Photos available to assign (product shots only, not hero/guide imagery). */
function productImages(): array {
    $files = glob(__DIR__ . '/assets/img/*.jpg') ?: [];
    $names = array_map('basename', $files);
    $names = array_values(array_filter($names, fn($n) => !str_starts_with($n, 'hero-') && !str_starts_with($n, 'guide-')));
    sort($names);
    return $names;
}
$images = productImages();

$blank = ['id'=>'','name'=>'','category'=>array_key_first(CATEGORIES),'sku'=>'','brand'=>'',
          'price'=>'','stock'=>'','description'=>'','image'=>CATEGORIES[array_key_first(CATEGORIES)]];
$form  = $blank;

/* ---------------- Save (insert or update) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $chosen = $_POST['image'] ?? '';
    $form = [
        'id'          => $_POST['id'] ?? '',
        'name'        => trim($_POST['name'] ?? ''),
        'category'    => $_POST['category'] ?? '',
        'sku'         => strtoupper(trim($_POST['sku'] ?? '')),
        'brand'       => trim($_POST['brand'] ?? ''),
        'price'       => trim($_POST['price'] ?? ''),
        'stock'       => trim($_POST['stock'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        // Only ever accept a filename we actually hold on disk.
        'image'       => in_array($chosen, $images, true)
                         ? $chosen
                         : (CATEGORIES[$_POST['category'] ?? ''] ?? CATEGORIES[array_key_first(CATEGORIES)]),
    ];

    if ($form['name'] === '')                  $errors['name']     = 'Product name is required.';
    if (!isset(CATEGORIES[$form['category']])) $errors['category'] = 'Choose a valid category.';
    if ($form['sku'] === '')                   $errors['sku']      = 'SKU is required.';
    if ($form['brand'] === '')                 $errors['brand']    = 'Brand is required.';
    $price = filter_var($form['price'], FILTER_VALIDATE_FLOAT);
    if ($price === false || $price < 0)        $errors['price']    = 'Price must be a number of 0 or more.';
    $stock = filter_var($form['stock'], FILTER_VALIDATE_INT);
    if ($stock === false || $stock < 0)        $errors['stock']    = 'Stock must be a whole number of 0 or more.';

    if (!$errors) {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ? AND id <> ?");
        $dup->execute([$form['sku'], $form['id'] ?: 0]);
        if ($dup->fetchColumn() > 0) $errors['sku'] = "SKU {$form['sku']} is already used by another product.";
    }

    if (!$errors) {
        try {
            if ($form['id']) {
                $pdo->prepare("UPDATE products SET name=?, category=?, sku=?, brand=?, price=?, stock=?, description=?, image=? WHERE id=?")
                    ->execute([$form['name'],$form['category'],$form['sku'],$form['brand'],$price,$stock,$form['description'],$form['image'],$form['id']]);
                $note = 'Updated ' . $form['name'];
            } else {
                $pdo->prepare("INSERT INTO products (name,category,sku,brand,price,stock,description,image) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$form['name'],$form['category'],$form['sku'],$form['brand'],$price,$stock,$form['description'],$form['image']]);
                $note = 'Added ' . $form['name'];
            }
            header('Location: manage_products.php?view=products&msg=' . urlencode($note));
            exit;
        } catch (PDOException $ex) {
            $errors['db'] = 'Database error: ' . $ex->getMessage();
        }
    }
}

/* ---------------- Delete (POST only) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $s = $pdo->prepare("SELECT name FROM products WHERE id = ?");
        $s->execute([$id]);
        $name = $s->fetchColumn() ?: 'Product';
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        unset($_SESSION['cart'][$id]);
        header('Location: manage_products.php?view=products&msg=' . urlencode('Deleted ' . $name));
    } catch (PDOException $ex) {
        header('Location: manage_products.php?view=products&msg=' . urlencode('Delete failed'));
    }
    exit;
}

/* ---------------- Which view? ---------------- */
$view = $_GET['view'] ?? 'dashboard';
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $s->execute([(int)$_GET['edit']]);
    if ($row = $s->fetch()) { $form = $row; $view = 'add'; }
}
if ($errors) $view = 'add';
if (!in_array($view, ['dashboard','products','add','restock','orders'], true)) $view = 'dashboard';
$editing = !empty($form['id']);

/* ---------------- Data ---------------- */
$products = $pdo->query("SELECT * FROM products ORDER BY category, name")->fetchAll();
$stats = $pdo->query("SELECT COUNT(*) total, COALESCE(SUM(stock),0) units, COALESCE(SUM(price*stock),0) value,
                             COALESCE(AVG(price),0) avgprice, SUM(stock = 0) oos,
                             SUM(stock > 0 AND stock <= 5) low, COALESCE(MAX(price),0) top
                      FROM products")->fetch();

$byCat = [];
foreach ($pdo->query("SELECT category, COUNT(*) n, COALESCE(SUM(stock),0) units, COALESCE(SUM(price*stock),0) val
                      FROM products GROUP BY category ORDER BY val DESC") as $r) {
    $byCat[$r['category']] = ['n'=>(int)$r['n'], 'units'=>(int)$r['units'], 'val'=>(float)$r['val']];
}
$maxVal   = max(1, max(array_column($byCat, 'val') ?: [1]));
$alerts   = $pdo->query("SELECT * FROM products WHERE stock <= 5 ORDER BY stock ASC, name ASC")->fetchAll();
$newest   = $pdo->query("SELECT * FROM products ORDER BY id DESC LIMIT 5")->fetchAll();
$healthy  = max(0, (int)$stats['total'] - count($alerts));
$healthPc = $stats['total'] ? round($healthy / $stats['total'] * 100) : 0;

$alertCount   = count($alerts);
$productTotal = (int)$stats['total'];

// Orders
$orders = $pdo->query("SELECT o.*, (SELECT COALESCE(SUM(qty),0) FROM order_items WHERE order_id = o.id) items
                       FROM orders o ORDER BY o.created_at DESC")->fetchAll();
$orderCount = count($orders);
$revenue    = array_sum(array_column($orders, 'total'));
$aov        = $orderCount ? $revenue / $orderCount : 0;

// One order expanded?
$openOrder = null; $openLines = [];
if (isset($_GET['order'])) {
    $q = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $q->execute([(int)$_GET['order']]);
    if ($openOrder = $q->fetch()) {
        $li = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $li->execute([$openOrder['id']]);
        $openLines = $li->fetchAll();
        $view = 'orders';
    }
}

$headings = ['dashboard'=>'Dashboard','products'=>'All products','add'=>($editing?'Edit product':'Add product'),'restock'=>'Restock list','orders'=>'Orders'];
$heading  = $headings[$view];
$crumb    = '/ ' . $heading;
$topActions = $view === 'products'
    ? '<a class="ab ab-primary" href="manage_products.php?view=add"><i class="fa-solid fa-plus"></i> Add product</a>'
    : ($view === 'dashboard'
        ? '<a class="ab ab-ghost" href="manage_products.php?view=products">View catalog</a><a class="ab ab-primary" href="manage_products.php?view=add"><i class="fa-solid fa-plus"></i> Add product</a>'
        : '<a class="ab ab-ghost" href="manage_products.php?view=products"><i class="fa-solid fa-arrow-left"></i> Back to catalog</a>');

$page_title = $heading . ' — ' . BRAND_FULL . ' Admin';
require __DIR__ . '/partials/admin_header.php';
?>

<?php if ($view === 'dashboard'): ?>
  <!-- ============ DASHBOARD ============ -->
  <div class="kpis">
    <div class="kpi feature rv">
      <div class="kpi-top"><span class="kpi-k">Inventory value</span><span class="kpi-ico"><i class="fa-solid fa-sack-dollar"></i></span></div>
      <div class="kpi-v" data-count="<?= (float)$stats['value'] ?>" data-prefix="$" data-dp="2">$0.00</div>
      <div class="kpi-foot">Retail value of everything on the shelf</div>
      <div class="kpi-rail"><i data-fill="100"></i></div>
    </div>
    <div class="kpi rv">
      <div class="kpi-top"><span class="kpi-k">Active SKUs</span><span class="kpi-ico"><i class="fa-solid fa-barcode"></i></span></div>
      <div class="kpi-v" data-count="<?= $productTotal ?>">0</div>
      <div class="kpi-foot">across <?= count($byCat) ?> categories</div>
      <div class="kpi-rail"><i data-fill="100"></i></div>
    </div>
    <div class="kpi rv">
      <div class="kpi-top"><span class="kpi-k">Units on hand</span><span class="kpi-ico"><i class="fa-solid fa-cubes"></i></span></div>
      <div class="kpi-v" data-count="<?= (int)$stats['units'] ?>">0</div>
      <div class="kpi-foot">avg <?= $productTotal ? round($stats['units'] / $productTotal) : 0 ?> per SKU</div>
      <div class="kpi-rail"><i data-fill="<?= min(100, round($stats['units'] / 6)) ?>"></i></div>
    </div>
    <div class="kpi rv">
      <div class="kpi-top"><span class="kpi-k">Average price</span><span class="kpi-ico"><i class="fa-solid fa-tag"></i></span></div>
      <div class="kpi-v" data-count="<?= (float)$stats['avgprice'] ?>" data-prefix="$" data-dp="2">$0.00</div>
      <div class="kpi-foot">top line <?= money($stats['top']) ?></div>
      <div class="kpi-rail"><i data-fill="<?= $stats['top'] ? round($stats['avgprice'] / $stats['top'] * 100) : 0 ?>"></i></div>
    </div>
  </div>

  <div class="mini rv">
    <div><div class="k">Sold out</div><div class="v" style="color:<?= $stats['oos'] ? 'var(--bad)' : 'var(--ink2)' ?>"><?= (int)$stats['oos'] ?></div></div>
    <div><div class="k">Low stock</div><div class="v" style="color:<?= $stats['low'] ? 'var(--warn)' : 'var(--ink2)' ?>"><?= (int)$stats['low'] ?></div></div>
    <div><div class="k">Healthy lines</div><div class="v"><?= $healthy ?></div></div>
    <div><div class="k">Categories</div><div class="v"><?= count($byCat) ?></div></div>
  </div>

  <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:16px;align-items:start" class="dash-grid">
    <div class="card2 rv">
      <div class="card2-head">
        <div><h2>Inventory value by category</h2><div class="sub">Where the money is sitting</div></div>
      </div>
      <div class="card2-body bars2">
        <?php foreach ($byCat as $cat => $d): ?>
          <div class="bar2 <?= $d['units'] < 20 ? 'warn' : '' ?>">
            <span class="nm"><?= e($cat) ?></span>
            <span class="vl"><?= money($d['val']) ?> &middot; <?= $d['units'] ?> units</span>
            <span class="track"><span class="fill" data-fill="<?= round($d['val'] / $maxVal * 100) ?>"></span></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card2 rv">
      <div class="card2-head"><div><h2>Stock health</h2><div class="sub">Lines not needing attention</div></div></div>
      <div class="card2-body">
        <div class="donut-wrap">
          <div class="donut" data-donut="<?= $healthPc ?>">
            <div class="donut-txt"><span><b><?= $healthPc ?>%</b><span>Healthy</span></span></div>
          </div>
          <div class="legend">
            <div><span class="d" style="background:var(--brand)"></span><?= $healthy ?> healthy</div>
            <div><span class="d" style="background:#eef0f3"></span><?= (int)$stats['low'] ?> low stock</div>
            <div><span class="d" style="background:#eef0f3"></span><?= (int)$stats['oos'] ?> sold out</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px" class="dash-grid">
    <div class="card2 rv">
      <div class="card2-head">
        <div><h2>Needs restocking</h2><div class="sub"><?= $alertCount ?> item<?= $alertCount === 1 ? '' : 's' ?></div></div>
        <a class="ab ab-ghost ab-sm" href="manage_products.php?view=restock">See all</a>
      </div>
      <div class="card2-body">
        <?php if (!$alerts): ?>
          <div class="empty2"><div class="ic"><i class="fa-solid fa-circle-check"></i></div><p>Everything is comfortably in stock.</p></div>
        <?php else: foreach (array_slice($alerts, 0, 4) as $al): ?>
          <div class="alert-row">
            <img src="assets/img/<?= e($al['image']) ?>" alt="">
            <span class="g"><span class="nm"><?= e($al['name']) ?></span><span class="sk"><?= e($al['brand']) ?> &middot; <?= e($al['sku']) ?></span></span>
            <span class="pill <?= $al['stock'] == 0 ? 'out' : 'low' ?>"><span class="dot"></span><?= $al['stock'] == 0 ? 'Sold out' : $al['stock'] . ' left' ?></span>
            <a class="ab ab-ghost ab-sm" href="manage_products.php?edit=<?= (int)$al['id'] ?>">Restock</a>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <div class="card2 rv">
      <div class="card2-head"><div><h2>Recently added</h2><div class="sub">Newest lines in the catalog</div></div></div>
      <div class="card2-body">
        <?php foreach ($newest as $p): [$c, $l] = stockTag((int)$p['stock']); ?>
          <div class="alert-row">
            <img src="assets/img/<?= e($p['image']) ?>" alt="">
            <span class="g"><span class="nm"><?= e($p['name']) ?></span><span class="sk"><?= e($p['category']) ?></span></span>
            <span class="nm"><?= money($p['price']) ?></span>
            <a class="ab ab-ghost ab-sm" href="manage_products.php?edit=<?= (int)$p['id'] ?>">Edit</a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

<?php elseif ($view === 'products' || $view === 'restock'):
  $rows = $view === 'restock' ? $alerts : $products; ?>
  <!-- ============ TABLE ============ -->
  <div class="card2 rv">
    <div class="card2-head">
      <div>
        <h2><?= $view === 'restock' ? 'Items needing attention' : 'Catalog' ?></h2>
        <div class="sub"><span id="tCount"><?= count($rows) ?></span> of <?= $view === 'restock' ? $alertCount : $productTotal ?> shown</div>
      </div>
      <?php if ($view === 'products'): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <select class="f2-input" id="tCat" style="width:auto;padding:8px 12px">
            <option value="">All categories</option>
            <?php foreach (array_keys(CATEGORIES) as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
          </select>
          <input class="f2-input" id="tFilter" type="search" placeholder="Search name, brand or SKU" style="width:230px;padding:8px 12px">
        </div>
      <?php endif; ?>
    </div>
    <div class="t2-wrap">
      <table class="t2">
        <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Value</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6"><div class="empty2"><div class="ic"><i class="fa-solid fa-circle-check"></i></div>
              <p><?= $view === 'restock' ? 'Nothing needs restocking.' : 'No products yet.' ?></p></div></td></tr>
          <?php else: foreach ($rows as $p): [$cls, $lab] = stockTag((int)$p['stock']); ?>
            <tr data-row data-cat="<?= e($p['category']) ?>"
                data-search="<?= e(strtolower($p['name'] . ' ' . $p['brand'] . ' ' . $p['sku'])) ?>">
              <td>
                <div class="t2-prod">
                  <img class="t2-img" src="assets/img/<?= e($p['image']) ?>" alt="" loading="lazy">
                  <div style="min-width:0">
                    <div class="t2-nm"><?= e($p['name']) ?></div>
                    <div class="t2-sk"><?= e($p['brand']) ?> &middot; <?= e($p['sku']) ?></div>
                  </div>
                </div>
              </td>
              <td><span class="pill neutral"><?= e($p['category']) ?></span></td>
              <td style="font-weight:550"><?= money($p['price']) ?></td>
              <td><span class="pill <?= $cls ?>"><span class="dot"></span><?= e($lab) ?></span></td>
              <td class="t2-sk" style="letter-spacing:0;text-transform:none;font-size:.84rem"><?= money($p['price'] * $p['stock']) ?></td>
              <td>
                <div class="t2-act">
                  <a class="ab ab-ghost ab-sm" href="manage_products.php?edit=<?= (int)$p['id'] ?>"><i class="fa-solid fa-pen"></i></a>
                  <button class="ab ab-danger ab-sm" onclick="askDelete(<?= (int)$p['id'] ?>, <?= htmlspecialchars(json_encode($p['name']), ENT_QUOTES) ?>)"><i class="fa-solid fa-trash"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      <div class="empty2" id="tNone" hidden><div class="ic"><i class="fa-solid fa-magnifying-glass"></i></div><p>No products match that search.</p></div>
    </div>
  </div>

<?php elseif ($view === 'orders'): ?>
  <!-- ============ ORDERS ============ -->
  <div class="kpis">
    <div class="kpi feature rv">
      <div class="kpi-top"><span class="kpi-k">Revenue</span><span class="kpi-ico"><i class="fa-solid fa-coins"></i></span></div>
      <div class="kpi-v" data-count="<?= (float)$revenue ?>" data-prefix="$" data-dp="2">$0.00</div>
      <div class="kpi-foot">across <?= $orderCount ?> order<?= $orderCount === 1 ? '' : 's' ?></div>
      <div class="kpi-rail"><i data-fill="100"></i></div>
    </div>
    <div class="kpi rv">
      <div class="kpi-top"><span class="kpi-k">Orders</span><span class="kpi-ico"><i class="fa-solid fa-receipt"></i></span></div>
      <div class="kpi-v" data-count="<?= $orderCount ?>">0</div>
      <div class="kpi-foot">all time</div>
      <div class="kpi-rail"><i data-fill="100"></i></div>
    </div>
    <div class="kpi rv">
      <div class="kpi-top"><span class="kpi-k">Average order</span><span class="kpi-ico"><i class="fa-solid fa-chart-simple"></i></span></div>
      <div class="kpi-v" data-count="<?= (float)$aov ?>" data-prefix="$" data-dp="2">$0.00</div>
      <div class="kpi-foot">per checkout</div>
      <div class="kpi-rail"><i data-fill="<?= $revenue ? min(100, round($aov / max($revenue,1) * 100 * $orderCount)) : 0 ?>"></i></div>
    </div>
    <div class="kpi rv">
      <div class="kpi-top"><span class="kpi-k">Units sold</span><span class="kpi-ico"><i class="fa-solid fa-box-open"></i></span></div>
      <div class="kpi-v" data-count="<?= array_sum(array_column($orders, 'items')) ?>">0</div>
      <div class="kpi-foot">line items shipped</div>
      <div class="kpi-rail"><i data-fill="100"></i></div>
    </div>
  </div>

  <?php if ($openOrder): ?>
    <div class="card2 rv" style="margin-bottom:16px">
      <div class="card2-head">
        <div><h2>Order <?= e($openOrder['order_ref']) ?></h2>
          <div class="sub"><?= date('j M Y, H:i', strtotime($openOrder['created_at'])) ?></div></div>
        <a class="ab ab-ghost ab-sm" href="manage_products.php?view=orders">Close</a>
      </div>
      <div class="card2-body" style="display:grid;grid-template-columns:1fr 1fr;gap:22px" class="dash-grid">
        <div>
          <div class="kpi-k" style="margin-bottom:10px">Customer</div>
          <div style="font-size:.9rem;line-height:1.8">
            <strong><?= e($openOrder['customer_name']) ?></strong><br>
            <?= e($openOrder['email']) ?><br>
            <?= e($openOrder['phone']) ?><br>
            <?= e($openOrder['address']) ?>, <?= e($openOrder['city']) ?> <?= e($openOrder['postcode']) ?>
          </div>
        </div>
        <div>
          <div class="kpi-k" style="margin-bottom:10px">Payment</div>
          <div style="font-size:.9rem;line-height:1.8">
            <strong><?= e($openOrder['card_brand']) ?> &bull;&bull;&bull;&bull; <?= e($openOrder['card_last4']) ?></strong><br>
            <?= e($openOrder['card_name']) ?><br>
            Expires <?= str_pad((string)$openOrder['card_exp_month'],2,'0',STR_PAD_LEFT) ?>/<?= substr((string)$openOrder['card_exp_year'],-2) ?><br>
            <span class="pill ok"><span class="dot"></span><?= e($openOrder['status']) ?></span>
          </div>
        </div>
      </div>
      <div class="card2-body" style="border-top:1px solid var(--line2)">
        <?php foreach ($openLines as $l): ?>
          <div class="alert-row">
            <img src="assets/img/<?= e($l['image']) ?>" alt="">
            <span class="g"><span class="nm"><?= e($l['name']) ?></span><span class="sk"><?= e($l['sku']) ?> &middot; qty <?= (int)$l['qty'] ?> &times; <?= money($l['unit_price']) ?></span></span>
            <span style="font-weight:600"><?= money($l['line_total']) ?></span>
          </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:flex-end;gap:26px;padding-top:14px;font-size:.9rem">
          <span class="kpi-k">Subtotal <strong style="color:var(--ink2)"><?= money($openOrder['subtotal']) ?></strong></span>
          <span class="kpi-k">Shipping <strong style="color:var(--ink2)"><?= $openOrder['shipping'] > 0 ? money($openOrder['shipping']) : 'Free' ?></strong></span>
          <span class="kpi-k">Total <strong style="color:var(--ink2);font-size:1.05rem"><?= money($openOrder['total']) ?></strong></span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="card2 rv">
    <div class="card2-head"><div><h2>All orders</h2><div class="sub"><?= $orderCount ?> total</div></div></div>
    <div class="t2-wrap">
      <table class="t2">
        <thead><tr><th>Reference</th><th>Customer</th><th>Payment</th><th>Items</th><th>Total</th><th>Placed</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
          <?php if (!$orders): ?>
            <tr><td colspan="7"><div class="empty2"><div class="ic"><i class="fa-solid fa-receipt"></i></div>
              <p>No orders yet. Place one through the storefront checkout.</p></div></td></tr>
          <?php else: foreach ($orders as $o): ?>
            <tr>
              <td style="font-family:ui-monospace,monospace;font-size:.82rem"><?= e($o['order_ref']) ?></td>
              <td>
                <div class="t2-nm"><?= e($o['customer_name']) ?></div>
                <div class="t2-sk" style="text-transform:none;letter-spacing:0"><?= e($o['email']) ?></div>
              </td>
              <td>
                <div class="t2-nm"><?= e($o['card_brand']) ?> &bull;&bull;&bull;&bull; <?= e($o['card_last4']) ?></div>
                <div class="t2-sk"><?= str_pad((string)$o['card_exp_month'],2,'0',STR_PAD_LEFT) ?>/<?= substr((string)$o['card_exp_year'],-2) ?></div>
              </td>
              <td><?= (int)$o['items'] ?></td>
              <td style="font-weight:600"><?= money($o['total']) ?></td>
              <td class="t2-sk" style="text-transform:none;letter-spacing:0"><?= date('j M, H:i', strtotime($o['created_at'])) ?></td>
              <td><div class="t2-act"><a class="ab ab-ghost ab-sm" href="manage_products.php?view=orders&order=<?= (int)$o['id'] ?>">View</a></div></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php else: ?>
  <!-- ============ ADD / EDIT ============ -->
  <?php if ($errors): ?>
    <div class="flash err">
      <i class="fa-solid fa-circle-exclamation" style="margin-top:2px"></i>
      <div><strong>Please fix the following:</strong>
        <ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
      </div>
    </div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="id" value="<?= e((string)$form['id']) ?>">
    <div style="display:grid;grid-template-columns:1fr 300px;gap:16px;align-items:start" class="dash-grid">

      <div style="display:grid;gap:16px">
        <div class="card2 rv">
          <div class="card2-head"><div><h2>Product details</h2><div class="sub">What the shopper sees on the card</div></div></div>
          <div class="card2-body f2">
            <div class="f2-field">
              <label for="f-name">Product name</label>
              <input class="f2-input <?= isset($errors['name']) ? 'bad' : '' ?>" id="f-name" name="name" required
                     value="<?= e($form['name']) ?>" placeholder="Ryzen 7 7800X3D">
            </div>
            <div class="f2-row">
              <div class="f2-field">
                <label for="f-brand">Brand</label>
                <input class="f2-input <?= isset($errors['brand']) ? 'bad' : '' ?>" id="f-brand" name="brand" required
                       value="<?= e($form['brand']) ?>" placeholder="AMD">
              </div>
              <div class="f2-field">
                <label for="f-sku">SKU</label>
                <input class="f2-input <?= isset($errors['sku']) ? 'bad' : '' ?>" id="f-sku" name="sku" required
                       value="<?= e($form['sku']) ?>" placeholder="CPU-7800X3D">
                <div class="f2-hint">Must be unique across the catalog.</div>
              </div>
            </div>
            <div class="f2-field">
              <label for="f-desc">Description</label>
              <textarea class="f2-input" id="f-desc" name="description" placeholder="Short spec summary shown on the product card."><?= e($form['description']) ?></textarea>
            </div>
          </div>
        </div>

        <div class="card2 rv">
          <div class="card2-head"><div><h2>Pricing &amp; stock</h2><div class="sub">Drives the storefront badges</div></div></div>
          <div class="card2-body f2">
            <div class="f2-row">
              <div class="f2-field">
                <label for="f-price">Price (USD)</label>
                <div class="f2-prefix"><span>$</span>
                  <input class="f2-input <?= isset($errors['price']) ? 'bad' : '' ?>" id="f-price" name="price"
                         type="number" step="0.01" min="0" required value="<?= e((string)$form['price']) ?>" placeholder="399.00">
                </div>
              </div>
              <div class="f2-field">
                <label for="f-stock">Stock on hand</label>
                <input class="f2-input <?= isset($errors['stock']) ? 'bad' : '' ?>" id="f-stock" name="stock"
                       type="number" min="0" required value="<?= e((string)$form['stock']) ?>" placeholder="12">
                <div class="f2-hint">0 shows as sold out; 5 or fewer shows as low stock.</div>
              </div>
            </div>
            <div class="f2-field">
              <label for="f-cat">Category</label>
              <select class="f2-input" id="f-cat" name="category" required>
                <?php foreach (array_keys(CATEGORIES) as $cat): ?>
                  <option value="<?= e($cat) ?>" <?= $form['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="card2 rv">
          <div class="card2-head"><div><h2>Photo</h2><div class="sub">Pick from the image library</div></div></div>
          <div class="card2-body">
            <div class="pick">
              <?php foreach ($images as $img): ?>
                <label>
                  <input type="radio" name="image" value="<?= e($img) ?>" <?= $form['image'] === $img ? 'checked' : '' ?>>
                  <img src="assets/img/<?= e($img) ?>" alt="<?= e($img) ?>" loading="lazy">
                  <span class="tick2"><i class="fa-solid fa-check"></i></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Live preview -->
      <div class="card2 preview-card rv">
        <div class="card2-head"><div><h2>Live preview</h2><div class="sub">How the card will look</div></div></div>
        <div class="card2-body">
          <div class="preview-media"><img id="pvImg" src="assets/img/<?= e($form['image']) ?>" alt=""></div>
          <div style="margin-top:14px">
            <div class="t2-sk" id="pvBrand" data-empty="BRAND"><?= e($form['brand'] ?: 'Brand') ?></div>
            <div class="t2-nm" id="pvName" data-empty="Product name" style="margin-top:4px"><?= e($form['name'] ?: 'Product name') ?></div>
            <div style="font-size:1.15rem;font-weight:600;letter-spacing:-.03em;margin-top:6px" id="pvPrice"
                 data-empty="$0.00"><?= money($form['price'] ?: 0) ?></div>
          </div>
          <div style="display:grid;gap:8px;margin-top:20px">
            <button class="ab ab-primary ab-block" name="save_product" value="1">
              <i class="fa-solid fa-floppy-disk"></i> <?= $editing ? 'Save changes' : 'Add product' ?>
            </button>
            <a class="ab ab-ghost ab-block" href="manage_products.php?view=products">Cancel</a>
          </div>
        </div>
      </div>
    </div>
  </form>
<?php endif; ?>

<!-- Delete confirmation -->
<div class="m2-back" id="delModal" role="dialog" aria-modal="true">
  <div class="m2">
    <div class="m2-head">
      <div class="m2-icon"><i class="fa-solid fa-trash"></i></div>
      <h2 style="font-size:1.05rem;font-weight:600">Delete product</h2>
    </div>
    <div class="m2-body">
      <p style="font-size:.89rem;color:var(--dim)">Permanently delete <strong id="delName" style="color:var(--ink2)"></strong>? This cannot be undone, and the item is removed from any open bag.</p>
    </div>
    <div class="m2-foot">
      <button type="button" class="ab ab-ghost" onclick="closeDelete()">Cancel</button>
      <form method="post" style="margin:0">
        <input type="hidden" name="id" id="delId">
        <button class="ab ab-danger" name="delete_product" value="1" style="background:var(--bad);color:#fff;border-color:var(--bad)">Delete</button>
      </form>
    </div>
  </div>
</div>

<style>@media (max-width:1000px){.dash-grid{grid-template-columns:1fr !important}.preview-card{position:static}}</style>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>
