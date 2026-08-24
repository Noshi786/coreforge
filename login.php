<?php
require_once __DIR__ . '/partials/auth.php';

if (isset($_GET['logout'])) {
    logoutAdmin();
    $_SESSION['flash_login'] = ['success', 'You have been signed out.'];
    header('Location: login.php'); exit;
}
if (isAdmin()) { header('Location: manage_products.php'); exit; }

$error = null;
$lock  = loginLockRemaining();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sign_in'])) {
    if ($lock > 0) {
        $error = 'Too many attempts. Try again in ' . ceil($lock / 60) . ' minute(s).';
    } elseif (loginAttempt($pdo, $_POST['username'] ?? '', $_POST['password'] ?? '')) {
        $next = $_SESSION['login_next'] ?? 'manage_products.php';
        unset($_SESSION['login_next']);
        header('Location: ' . $next); exit;
    } else {
        $lock  = loginLockRemaining();
        $error = $lock > 0
            ? 'Too many attempts. Locked for ' . ceil($lock / 60) . ' minute(s).'
            : 'Incorrect username or password.';
    }
}

$flash = $_SESSION['flash_login'] ?? null; unset($_SESSION['flash_login']);
$page_title = 'Sign in — ' . BRAND_FULL;
require __DIR__ . '/partials/header.php';
?>

<section class="auth-wrap">
  <div class="auth-media">
    <img src="assets/img/hero-side.jpg" alt="">
    <div class="auth-media-body">
      <span class="micro">Staff only</span>
      <h2 class="h-lg" style="color:#fff;margin-top:12px">Inventory<br>control room</h2>
      <p style="color:rgba(255,255,255,.75);margin-top:14px;max-width:34ch;font-size:.92rem">
        Manage the catalog, stock levels and pricing.
      </p>
    </div>
  </div>

  <div class="auth-form band-dark">
    <div class="auth-inner reveal" data-reveal>
      <span class="micro">CoreForge admin</span>
      <h1 class="h-lg" style="margin-top:10px;color:#fff">Sign in</h1>

      <?php if ($flash): ?>
        <div class="alert alert-success" style="margin-top:22px"><span><?= e($flash[1]) ?></span></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger shake" style="margin-top:22px"><span><?= e($error) ?></span></div>
      <?php endif; ?>

      <form method="post" style="margin-top:24px">
        <div class="field float">
          <input class="input" id="u" name="username" required autocomplete="username" placeholder=" " value="<?= e($_POST['username'] ?? '') ?>">
          <label for="u">Username</label>
        </div>
        <div class="field float">
          <input class="input" id="p" name="password" type="password" required autocomplete="current-password" placeholder=" ">
          <label for="p">Password</label>
        </div>
        <button class="btn btn-block" name="sign_in" value="1" <?= $lock > 0 ? 'disabled' : '' ?>>Sign in</button>
      </form>

      <p style="margin-top:20px;font-size:11px;letter-spacing:.05em;color:#7a7772">
        Demo credentials — <strong style="color:#bdb9b4">admin / coreforge</strong>
      </p>
      <a href="index.php" class="link-u" style="display:inline-block;margin-top:22px;color:#9a9691">Back to the store</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
