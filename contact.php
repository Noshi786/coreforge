<?php
require_once __DIR__ . '/partials/config.php';

$sent = false; $errors = [];
$subjects = ['Build advice', 'Order status', 'Warranty / RMA', 'Bulk orders', 'Something else'];
$form = ['name'=>'','email'=>'','subject'=>'Build advice','message'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $form = [
        'name'    => trim($_POST['name'] ?? ''),
        'email'   => trim($_POST['email'] ?? ''),
        'subject' => in_array($_POST['subject'] ?? '', $subjects, true) ? $_POST['subject'] : $subjects[0],
        'message' => trim($_POST['message'] ?? ''),
    ];
    if ($form['name'] === '') $errors[] = 'Please tell us your name.';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (mb_strlen($form['message']) < 10) $errors[] = 'Please give us a little more detail (at least 10 characters).';

    if (!$errors) {
      try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
          id INT AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(120) NOT NULL,
          email VARCHAR(160) NOT NULL,
          subject VARCHAR(80) NOT NULL,
          message TEXT NOT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'new',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_contact_created (created_at),
          INDEX idx_contact_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message)
                     VALUES (?, ?, ?, ?)");
        $stmt->execute([$form['name'], $form['email'], $form['subject'], $form['message']]);
        $sent = true;
        $form = ['name'=>'','email'=>'','subject'=>$subjects[0],'message'=>''];
      } catch (PDOException $ex) {
        $errors[] = 'We could not save your message right now. Please try again.';
      }
    }
}

$hours = [
    'Monday'    => '9:00 — 19:00', 'Tuesday'  => '9:00 — 19:00',
    'Wednesday' => '9:00 — 19:00', 'Thursday' => '9:00 — 19:00',
    'Friday'    => '9:00 — 19:00', 'Saturday' => '10:00 — 17:00',
    'Sunday'    => 'Closed',
];
$today = date('l');

$faqs = [
    ['How fast do orders ship?', 'Orders placed before 4pm on a working day are dispatched the same day. Anything after that goes out the next morning. Shipping is free on orders over $150.'],
    ['Can you check parts compatibility before I buy?', 'Yes, and we would rather you asked than guessed. Send us the parts you already own along with what you plan to add, and we will confirm socket, chipset, clearance and power headroom.'],
    ['What does the two-year warranty cover?', 'Manufacturing defects and early failure on every component we sell. We handle the RMA locally rather than sending you to the manufacturer, so you deal with us throughout.'],
    ['Do you build the machine for me?', 'We offer assembly and testing on full parts lists bought through us. Tell us in your message and we will include it in the quote.'],
    ['Can I return something I no longer need?', 'Unopened items can go back within 30 days, no questions asked. Opened components are covered by the warranty rather than the returns policy.'],
];

$page_title = 'Contact — ' . BRAND_FULL;
$page_desc  = 'Talk to the ' . BRAND_FULL . ' team about builds, orders and warranty.';
require __DIR__ . '/partials/header.php';
?>

<!-- Hero -->
<section class="contact-hero">
  <img src="assets/img/hero-side.jpg" alt="">
  <div class="wrap">
    <span class="micro line-mask" style="color:rgba(255,255,255,.75)"><span>Contact</span></span>
    <h1 class="h-lg" style="color:#fff;margin-top:12px;max-width:16ch">
      <span class="line-mask"><span>Talk to a builder,</span></span>
      <span class="line-mask"><span>not a call centre.</span></span>
    </h1>
    <p class="line-mask" style="color:rgba(255,255,255,.78);margin-top:16px;max-width:52ch">
      <span>Every message is answered by someone who assembles machines for a living — usually within one working day.</span>
    </p>
  </div>
</section>

<!-- Overlapping quick-contact tiles -->
<div class="wrap">
  <div class="info-row">
    <div class="info-tile">
      <div class="info-ico"><i class="fa-solid fa-envelope"></i></div>
      <h3>Email us</h3>
      <p>Best for build specs and quotes.</p>
      <p style="margin-top:8px"><a href="mailto:<?= e(STORE_EMAIL) ?>"><?= e(STORE_EMAIL) ?></a></p>
    </div>
    <div class="info-tile">
      <div class="info-ico"><i class="fa-solid fa-phone"></i></div>
      <h3>Call the counter</h3>
      <p>Quickest for stock and order questions.</p>
      <p style="margin-top:8px"><a href="tel:<?= preg_replace('/\s+/', '', STORE_PHONE) ?>"><?= e(STORE_PHONE) ?></a></p>
    </div>
    <div class="info-tile">
      <div class="info-ico"><i class="fa-solid fa-location-dot"></i></div>
      <h3>Visit the workshop</h3>
      <p>Bench testing and collections.</p>
      <p style="margin-top:8px"><?= e(STORE_ADDR) ?></p>
    </div>
  </div>
</div>

<!-- Form + aside -->
<section class="section">
  <div class="wrap contact-grid">

    <div class="contact-card">
      <?php if ($sent): ?>
        <div class="sent-state">
          <div class="sent-ring"><i class="fa-solid fa-check"></i></div>
          <h2 class="h-md">Message received</h2>
          <p class="muted" style="margin-top:10px;max-width:44ch;margin-inline:auto">
            Thanks — it is with the team now. Expect a reply within one working day, sooner if it is a stock question.
          </p>
          <div style="display:flex;gap:10px;justify-content:center;margin-top:26px;flex-wrap:wrap">
            <a href="products.php" class="btn">Keep shopping</a>
            <a href="contact.php" class="btn btn-outline">Send another</a>
          </div>
        </div>
      <?php else: ?>
        <span class="micro" style="color:var(--ink-soft)">Send a message</span>
        <h2 class="h-md" style="margin:10px 0 24px">Tell us what you are building</h2>

        <?php if ($errors): ?>
          <div class="alert alert-danger shake" style="display:block">
            <strong>Please check the form:</strong>
            <ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <form method="post" id="contactForm">
          <div class="field-row">
            <div class="field float">
              <input class="input" id="c-name" name="name" required placeholder=" " value="<?= e($form['name']) ?>">
              <label for="c-name">Your name</label>
            </div>
            <div class="field float">
              <input class="input" id="c-email" name="email" type="email" required placeholder=" " value="<?= e($form['email']) ?>">
              <label for="c-email">Email address</label>
            </div>
          </div>

          <div class="field">
            <label style="margin-bottom:10px">What is it about?</label>
            <div class="chip-row">
              <?php foreach ($subjects as $s): ?>
                <label class="chip-pick">
                  <input type="radio" name="subject" value="<?= e($s) ?>" <?= $form['subject'] === $s ? 'checked' : '' ?>>
                  <span><?= e($s) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="field float">
            <textarea class="textarea" id="c-msg" name="message" required maxlength="1200" placeholder=" "
                      style="padding-top:26px"><?= e($form['message']) ?></textarea>
            <label for="c-msg">Your message</label>
            <div class="hint count-hint">
              <span>Budget, what you plan to run, and any parts you already own.</span>
              <span><span class="n" id="cCount">0</span>/1200</span>
            </div>
          </div>

          <button class="btn" name="send_message" value="1">Send message</button>
        </form>
      <?php endif; ?>
    </div>

    <!-- Aside -->
    <aside class="stack">
      <div class="contact-card">
        <span class="micro" style="color:var(--ink-soft)">Opening hours</span>
        <h3 class="h-md" style="margin-top:8px">When we are in</h3>
        <table class="hours">
          <?php foreach ($hours as $day => $time): ?>
            <tr class="<?= $day === $today ? 'today' : '' ?>">
              <td><?= $day ?><?= $day === $today ? ' &middot; today' : '' ?></td>
              <td><?= $time ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>

      <div class="contact-card band-dark" style="border-color:var(--ink)">
        <span class="micro">Response times</span>
        <h3 class="h-md" style="margin-top:8px;color:#fff">What to expect</h3>
        <ul style="list-style:none;margin-top:16px">
          <?php foreach ([
            ['Stock &amp; orders', 'Same day'],
            ['Build advice', 'Within 1 working day'],
            ['Warranty / RMA', 'Within 2 working days'],
          ] as [$k, $v]): ?>
            <li style="display:flex;justify-content:space-between;gap:14px;padding:11px 0;border-bottom:1px solid #2c2c2c;font-size:.89rem">
              <span style="color:#9a9691"><?= $k ?></span><span style="color:#fff"><?= $v ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </aside>
  </div>
</section>

<!-- FAQ -->
<section class="section bone">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="micro">Before you write</span>
        <h2 class="h-lg">Common questions</h2>
      </div>
      <a href="articles.php" class="link-u">Read the journal</a>
    </div>
    <div style="max-width:900px">
      <?php foreach ($faqs as $i => [$q, $a]): ?>
        <div class="faq-item">
          <button class="faq-q" aria-expanded="false" id="faq-<?= $i ?>">
            <span><?= e($q) ?></span><span class="faq-icon" aria-hidden="true"></span>
          </button>
          <div class="faq-a" role="region" aria-labelledby="faq-<?= $i ?>"><p><?= $a ?></p></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<script>
  // Live character counter on the message field.
  (function () {
    const t = document.getElementById('c-msg'), n = document.getElementById('cCount');
    if (!t || !n) return;
    const sync = () => { n.textContent = t.value.length; };
    t.addEventListener('input', sync); sync();
  })();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
