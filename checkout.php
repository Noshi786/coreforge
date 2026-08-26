<?php
require_once __DIR__ . '/partials/config.php';

$bag = cartItems($pdo);

/* Nothing to pay for — send them back to the shop. */
if (!$bag && !isset($_GET['done'])) {
    header('Location: products.php');
    exit;
}

$subtotal = 0;
foreach ($bag as $b) $subtotal += $b['price'] * $b['qty'];
$shipping = ($subtotal > 0 && $subtotal < 150) ? 12.00 : 0.00;
$total    = $subtotal + $shipping;

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

$errors = [];
$f = [
    'customer_name' => '', 'email' => '', 'phone' => '',
    'address' => '', 'city' => '', 'postcode' => '',
    'card_name' => '', 'card_number' => '', 'card_exp' => '', 'card_cvc' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    foreach ($f as $k => $_) $f[$k] = trim($_POST[$k] ?? '');

    /* ---- Contact & delivery ---- */
    if ($f['customer_name'] === '')                              $errors['customer_name'] = 'Enter the name for the order.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL))         $errors['email']    = 'Enter a valid email address.';
    if (strlen(digitsOnly($f['phone'])) < 7)                     $errors['phone']    = 'Enter a reachable phone number.';
    if ($f['address'] === '')                                    $errors['address']  = 'Enter a delivery address.';
    if ($f['city'] === '')                                       $errors['city']     = 'Enter a city.';
    if ($f['postcode'] === '')                                   $errors['postcode'] = 'Enter a postcode.';

    /* ---- Card ---- */
    $number = digitsOnly($f['card_number']);
    $brand  = cardBrand($number);
    if ($f['card_name'] === '')                                  $errors['card_name']   = 'Enter the name printed on the card.';
    if ($number === '') {
        $errors['card_number'] = 'Enter the card number.';
    } elseif ($brand === 'Unknown') {
        $errors['card_number'] = 'We do not recognise that card type. Try a Visa, Mastercard, Amex or Discover number.';
    } elseif (!cardLengthOk($brand, $number)) {
        $lens = cardLengths($brand);
        $need = count($lens) > 1
            ? implode(', ', array_slice($lens, 0, -1)) . ' or ' . end($lens)
            : (string)$lens[0];
        $article = in_array($brand[0], ['A','E','I','O','U'], true) ? 'An' : 'A';
        $errors['card_number'] = "$article $brand number has $need digits — you entered " . strlen($number) . '.';
    } elseif (!luhnValid($number)) {
        // Real card numbers carry a check digit, so an invented number will
        // almost always land here. Say so plainly instead of just "invalid".
        $errors['card_number'] = 'That number fails the checksum every real card carries, so it cannot be a genuine card. For this demo use 4242 4242 4242 4242.';
    }

    $expM = $expY = 0;
    if (!preg_match('#^(\d{2})\s*/\s*(\d{2,4})$#', $f['card_exp'], $m)) {
        $errors['card_exp'] = 'Use MM/YY.';
    } else {
        $expM = (int)$m[1];
        $expY = (int)$m[2];
        if ($expY < 100) $expY += 2000;
        if (!expiryInFuture($expM, $expY)) $errors['card_exp'] = 'That card has expired.';
    }

    $cvc  = digitsOnly($f['card_cvc']);
    $need = cvcLength($brand);
    if ($cvc === '')                    $errors['card_cvc'] = 'Enter the CVC.';
    elseif (strlen($cvc) !== $need)     $errors['card_cvc'] = "The CVC should be $need digits.";

    /* ---- Stock still available? ---- */
    if (!$bag) $errors['bag'] = 'Your bag is empty.';
    foreach ($bag as $b) {
        if ($b['qty'] > $b['stock']) {
            $errors['bag'] = 'We no longer have enough ' . $b['name'] . ' in stock.';
            break;
        }
    }

    /* ---- Place the order ---- */
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $ref = makeOrderRef();

            $pdo->prepare("INSERT INTO orders
                (order_ref, customer_name, email, phone, address, city, postcode,
                 card_name, card_brand, card_last4, card_exp_month, card_exp_year,
                 subtotal, shipping, total)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $ref, $f['customer_name'], $f['email'], $f['phone'],
                    $f['address'], $f['city'], $f['postcode'],
                    $f['card_name'], $brand, substr($number, -4), $expM, $expY,
                    $subtotal, $shipping, $total,
                ]);
            $orderId = (int)$pdo->lastInsertId();

            $item = $pdo->prepare("INSERT INTO order_items
                (order_id, product_id, name, sku, brand, image, unit_price, qty, line_total)
                VALUES (?,?,?,?,?,?,?,?,?)");
            $drop = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");

            foreach ($bag as $b) {
                $item->execute([$orderId, $b['id'], $b['name'], $b['sku'], $b['brand'],
                                $b['image'], $b['price'], $b['qty'], $b['price'] * $b['qty']]);
                $drop->execute([$b['qty'], $b['id'], $b['qty']]);
            }

            $pdo->commit();

            // The card number and CVC were only ever in memory; they end here.
            $_SESSION['cart'] = [];
            $_SESSION['last_order'] = $ref;

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok'       => true,
                    'ref'      => $ref,
                    'total'    => money($total),
                    'brand'    => $brand,
                    'last4'    => substr($number, -4),
                    'redirect' => 'checkout.php?done=' . urlencode($ref),
                ]);
                exit;
            }
            header('Location: checkout.php?done=' . urlencode($ref));
            exit;

        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['bag'] = 'We could not take that payment: ' . $ex->getMessage();
        }
    }

    if ($isAjax && $errors) {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['ok' => false, 'errors' => $errors]);
        exit;
    }
}

/* ---------------- Confirmation ---------------- */
if (isset($_GET['done'])) {
    $ref = $_GET['done'];
    $o = $pdo->prepare("SELECT * FROM orders WHERE order_ref = ?");
    $o->execute([$ref]);
    $order = $o->fetch();

    // Only the person who just placed it may view it.
    if (!$order || ($_SESSION['last_order'] ?? null) !== $ref) {
        header('Location: products.php'); exit;
    }
    $li = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $li->execute([$order['id']]);
    $lines = $li->fetchAll();

    $page_title = 'Order ' . $order['order_ref'] . ' — ' . BRAND_FULL;
    require __DIR__ . '/partials/header.php';
    ?>
    <section class="section">
      <div class="wrap" style="max-width:820px">
        <div style="text-align:center;position:relative">
          <div class="ok-ring"><i class="fa-solid fa-check"></i></div>
          <span class="micro" style="color:var(--ink-soft)">Payment approved</span>
          <h1 class="h-lg" style="margin-top:12px">Thanks, <?= e(explode(' ', $order['customer_name'])[0]) ?>.</h1>
          <p class="lede" style="margin-top:12px">
            Your order is confirmed and will be dispatched from Quetta on the next working day.
          </p>
          <p style="margin-top:20px"><span class="ref-chip"><?= e($order['order_ref']) ?></span></p>
        </div>

        <div class="rcpt" style="margin-top:38px">
          <div class="rcpt-head">
            <div>
              <span class="micro" style="color:var(--ink-soft)">Delivering to</span>
              <div style="margin-top:8px;font-weight:550"><?= e($order['customer_name']) ?></div>
              <div class="muted" style="font-size:.87rem">
                <?= e($order['address']) ?><br>
                <?= e($order['city']) ?>, <?= e($order['postcode']) ?><br>
                <?= e($order['phone']) ?>
              </div>
            </div>
            <div style="text-align:right">
              <span class="micro" style="color:var(--ink-soft)">Paid with</span>
              <div style="margin-top:8px;font-weight:550"><?= e($order['card_brand']) ?> &bull;&bull;&bull;&bull; <?= e($order['card_last4']) ?></div>
              <div class="muted" style="font-size:.87rem">
                <?= e($order['card_name']) ?><br>
                Expires <?= str_pad((string)$order['card_exp_month'], 2, '0', STR_PAD_LEFT) ?>/<?= substr((string)$order['card_exp_year'], -2) ?><br>
                <?= date('j M Y, H:i', strtotime($order['created_at'])) ?>
              </div>
            </div>
          </div>

          <div class="rcpt-body">
            <?php foreach ($lines as $l): ?>
              <div class="co-line">
                <img src="assets/img/<?= e($l['image']) ?>" alt="">
                <span class="g">
                  <span class="mt"><?= e($l['brand']) ?></span>
                  <span class="nm"><?= e($l['name']) ?></span>
                  <span class="mt"><?= e($l['sku']) ?> &middot; qty <?= (int)$l['qty'] ?></span>
                </span>
                <span style="font-weight:550"><?= money($l['line_total']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="rcpt-foot">
            <dl class="kv">
              <dt>Subtotal</dt><dd><?= money($order['subtotal']) ?></dd>
              <dt>Shipping</dt><dd><?= $order['shipping'] > 0 ? money($order['shipping']) : 'Free' ?></dd>
              <dt style="font-weight:650;color:var(--ink)">Total paid</dt>
              <dd style="font-weight:700;font-size:1.1rem"><?= money($order['total']) ?></dd>
            </dl>
          </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:center;margin-top:30px;flex-wrap:wrap">
          <a href="products.php" class="btn">Continue shopping</a>
          <a href="index.php" class="btn btn-outline">Back to home</a>
        </div>
      </div>
    </section>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

/* ---------------- Checkout form ---------------- */
$page_title = 'Checkout — ' . BRAND_FULL;
require __DIR__ . '/partials/header.php';
?>

<section class="section-tight band-dark" style="padding-block:clamp(26px,3.4vw,44px)">
  <div class="wrap">
    <span class="micro"><a href="products.php">Bag</a> / Checkout</span>
    <h1 class="h-lg" style="margin-top:10px">Checkout</h1>
  </div>
</section>

<section class="section" style="padding-top:clamp(24px,3vw,40px)">
  <div class="wrap co-grid">

    <form method="post" id="coForm" novalidate>
      <?php /* Carried as a hidden field: the submit button gets disabled to stop
               double-submits, and a disabled button's own value is not posted. */ ?>
      <input type="hidden" name="place_order" value="1">
      <?php if (isset($errors['bag'])): ?>
        <div class="alert alert-danger shake"><span><?= e($errors['bag']) ?></span></div>
      <?php endif; ?>

      <!-- 1. Contact -->
      <div class="co-panel">
        <div class="co-step">
          <span class="co-num">1</span>
          <div><h2>Contact details</h2><div class="sub">So we can send the receipt and tracking</div></div>
        </div>
        <div class="field-row">
          <div class="co-field">
            <label for="customer_name">Full name</label>
            <input class="co-input <?= isset($errors['customer_name']) ? 'bad' : '' ?>" id="customer_name" name="customer_name"
                   value="<?= e($f['customer_name']) ?>" placeholder="Nosheen Fitras" required>
            <div class="co-err <?= isset($errors['customer_name']) ? 'show' : '' ?>"><?= e($errors['customer_name'] ?? '') ?></div>
          </div>
          <div class="co-field">
            <label for="email">Email</label>
            <input class="co-input <?= isset($errors['email']) ? 'bad' : '' ?>" id="email" name="email" type="email"
                   value="<?= e($f['email']) ?>" placeholder="you@example.com" required>
            <div class="co-err <?= isset($errors['email']) ? 'show' : '' ?>"><?= e($errors['email'] ?? '') ?></div>
          </div>
        </div>
        <div class="co-field">
          <label for="phone">Phone number</label>
          <input class="co-input <?= isset($errors['phone']) ? 'bad' : '' ?>" id="phone" name="phone" inputmode="tel"
                 value="<?= e($f['phone']) ?>" placeholder="+92 300 1234567" required>
          <div class="co-err <?= isset($errors['phone']) ? 'show' : '' ?>"><?= e($errors['phone'] ?? '') ?></div>
        </div>
      </div>

      <!-- 2. Delivery -->
      <div class="co-panel">
        <div class="co-step">
          <span class="co-num">2</span>
          <div><h2>Delivery address</h2><div class="sub">Where the parts should arrive</div></div>
        </div>
        <div class="co-field">
          <label for="address">Street address</label>
          <input class="co-input <?= isset($errors['address']) ? 'bad' : '' ?>" id="address" name="address"
                 value="<?= e($f['address']) ?>" placeholder="12 Jinnah Road" required>
          <div class="co-err <?= isset($errors['address']) ? 'show' : '' ?>"><?= e($errors['address'] ?? '') ?></div>
        </div>
        <div class="field-row">
          <div class="co-field">
            <label for="city">City</label>
            <input class="co-input <?= isset($errors['city']) ? 'bad' : '' ?>" id="city" name="city"
                   value="<?= e($f['city']) ?>" placeholder="Quetta" required>
            <div class="co-err <?= isset($errors['city']) ? 'show' : '' ?>"><?= e($errors['city'] ?? '') ?></div>
          </div>
          <div class="co-field">
            <label for="postcode">Postcode</label>
            <input class="co-input <?= isset($errors['postcode']) ? 'bad' : '' ?>" id="postcode" name="postcode"
                   value="<?= e($f['postcode']) ?>" placeholder="87300" required>
            <div class="co-err <?= isset($errors['postcode']) ? 'show' : '' ?>"><?= e($errors['postcode'] ?? '') ?></div>
          </div>
        </div>
      </div>

      <!-- 3. Payment -->
      <div class="co-panel">
        <div class="co-step">
          <span class="co-num">3</span>
          <div><h2>Payment</h2><div class="sub">Card details are checked, never stored in full</div></div>
        </div>

        <div class="card-stage">
          <div class="card-3d" id="card3d" data-brand="">
            <div class="card-face">
              <div class="card-chip"></div>
              <span class="card-brandmark" id="cardBrandMark"></span>
              <div class="card-number" id="cardNum"></div>
              <div class="card-row">
                <span><span class="card-lab">Card holder</span><span class="card-val" id="cardHolder">YOUR NAME</span></span>
                <span><span class="card-lab">Expires</span><span class="card-val" id="cardExp">MM/YY</span></span>
              </div>
            </div>
            <div class="card-face back">
              <div class="card-stripe"></div>
              <div class="card-cvc-strip" id="cardCvc">•••</div>
              <div class="card-back-note">This card is a demo. No real payment is taken.</div>
            </div>
          </div>
        </div>

        <div class="co-field">
          <label for="card_name">Name on card</label>
          <input class="co-input <?= isset($errors['card_name']) ? 'bad' : '' ?>" id="card_name" name="card_name"
                 value="<?= e($f['card_name']) ?>" placeholder="NOSHEEN FITRAS" autocomplete="cc-name" required>
          <div class="co-err <?= isset($errors['card_name']) ? 'show' : '' ?>"><?= e($errors['card_name'] ?? '') ?></div>
        </div>

        <div class="co-field co-cardnum">
          <label for="card_number">Card number</label>
          <input class="co-input <?= isset($errors['card_number']) ? 'bad' : '' ?>" id="card_number" name="card_number"
                 value="<?= e($f['card_number']) ?>" placeholder="4242 4242 4242 4242"
                 inputmode="numeric" autocomplete="cc-number" maxlength="23" required>
          <span class="brandtag" id="brandTag"></span>
          <div class="co-err <?= isset($errors['card_number']) ? 'show' : '' ?>"><?= e($errors['card_number'] ?? '') ?></div>
        </div>

        <div class="co-row3">
          <div class="co-field">
            <label for="card_exp">Expiry</label>
            <input class="co-input <?= isset($errors['card_exp']) ? 'bad' : '' ?>" id="card_exp" name="card_exp"
                   value="<?= e($f['card_exp']) ?>" placeholder="MM/YY" inputmode="numeric" autocomplete="cc-exp" maxlength="5" required>
            <div class="co-err <?= isset($errors['card_exp']) ? 'show' : '' ?>"><?= e($errors['card_exp'] ?? '') ?></div>
          </div>
          <div class="co-field">
            <label for="card_cvc">CVC</label>
            <?php /* Deliberately never re-populated: the CVC must not be echoed back into the page. */ ?>
            <input class="co-input <?= isset($errors['card_cvc']) ? 'bad' : '' ?>" id="card_cvc" name="card_cvc"
                   placeholder="123" inputmode="numeric" autocomplete="cc-csc" maxlength="4" required>
            <div class="co-err <?= isset($errors['card_cvc']) ? 'show' : '' ?>"><?= e($errors['card_cvc'] ?? '') ?></div>
          </div>
          <div class="co-field" style="display:flex;align-items:flex-end">
            <p class="hint" style="margin:0">
              <strong>Demo card:</strong> 4242&nbsp;4242&nbsp;4242&nbsp;4242<br>
              any future expiry, any CVC. Invented numbers are rejected.
            </p>
          </div>
        </div>
      </div>
    </form>

    <!-- Order summary -->
    <aside class="co-summary">
      <div class="co-sum-head">
        <span class="micro" style="color:var(--ink-soft)">Order summary</span>
        <div style="font-weight:600;margin-top:6px"><?= count($bag) ?> line<?= count($bag) === 1 ? '' : 's' ?> &middot; <?= cartCount() ?> item<?= cartCount() === 1 ? '' : 's' ?></div>
      </div>
      <div class="co-sum-body">
        <?php foreach ($bag as $b): ?>
          <div class="co-line">
            <img src="assets/img/<?= e($b['image']) ?>" alt="">
            <span class="g">
              <span class="mt"><?= e($b['brand']) ?></span>
              <span class="nm"><?= e($b['name']) ?></span>
              <span class="mt">qty <?= (int)$b['qty'] ?></span>
            </span>
            <span style="font-weight:550"><?= money($b['price'] * $b['qty']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="co-sum-foot">
        <div class="r"><span>Subtotal</span><span><?= money($subtotal) ?></span></div>
        <div class="r"><span>Shipping</span><span><?= $shipping > 0 ? money($shipping) : 'Free' ?></span></div>
        <div class="r g"><span>Total</span><span><?= money($total) ?></span></div>
        <div class="pay-btn">
          <button form="coForm" name="place_order" value="1" class="btn btn-block" id="payBtn">
            Pay <?= money($total) ?>
          </button>
        </div>
        <div class="secure-note"><i class="fa-solid fa-lock"></i> Demo checkout — no real payment is taken</div>
      </div>
    </aside>
  </div>
</section>

<!-- Payment processing overlay (JS only; the plain form POST still works without it) -->
<div class="pay-overlay" id="payOverlay" role="dialog" aria-modal="true" aria-live="polite">
  <div class="pay-box" id="payBox">
    <div class="confetti" id="confetti" aria-hidden="true"></div>

    <div class="pay-spin" aria-hidden="true">
      <i></i><i></i><i></i>
      <span class="amt"><?= money($total) ?></span>
    </div>

    <div id="payTickWrap" hidden>
      <div class="pay-tick" aria-hidden="true">
        <svg viewBox="0 0 100 100">
          <circle cx="50" cy="50" r="46"></circle>
          <path d="M30 51 L44 65 L71 37"></path>
        </svg>
      </div>
    </div>

    <h2 class="pay-title" id="payTitle">Processing your payment</h2>
    <p class="pay-sub" id="paySub">Please do not close this window.</p>

    <div class="pay-steps" id="paySteps">
      <div class="pay-step" data-step><span class="pay-dot"></span><span>Checking card details</span></div>
      <div class="pay-step" data-step><span class="pay-dot"></span><span>Contacting issuing bank</span></div>
      <div class="pay-step" data-step><span class="pay-dot"></span><span>Authorising payment</span></div>
      <div class="pay-step" data-step><span class="pay-dot"></span><span>Confirming your order</span></div>
    </div>

    <div id="payDone" hidden>
      <div class="pay-receipt">
        <div class="r"><span>Card</span><span id="payCard">—</span></div>
        <div class="r"><span>Order reference</span><span id="payRef">—</span></div>
        <div class="r big"><span>Paid</span><span id="payAmt"><?= money($total) ?></span></div>
      </div>
      <p class="pay-next">Taking you to your receipt&hellip;</p>
    </div>
  </div>
</div>

<script src="assets/checkout.js" defer></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
