<?php
/** Admin app shell — open. Expects $page_title, $view, and optionally $alertCount. */
$view = $view ?? 'dashboard';
$nav = [
    'Overview' => [
        ['dashboard', 'Dashboard', 'fa-chart-pie', null],
    ],
    'Sales' => [
        ['orders', 'Orders', 'fa-receipt', $orderCount ?? null],
    ],
    'Catalog' => [
        ['products', 'All products', 'fa-boxes-stacked', $productTotal ?? null],
        ['add',      'Add product',  'fa-circle-plus',   null],
        ['restock',  'Restock',      'fa-triangle-exclamation', $alertCount ?? null],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title ?? 'Admin') ?></title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/admin.css">
<script>
  document.documentElement.classList.add('motion');
  setTimeout(function () { if (!window.__adminReady) document.documentElement.classList.remove('motion'); }, 2500);
</script>
</head>
<body class="admin">

<div class="a-wrap">

  <aside class="sidebar" id="sidebar">
    <div class="sb-brand">
      <span class="sb-mark">CF</span>
      <span>
        <span class="sb-name"><?= BRAND_FULL ?></span>
        <span class="sb-sub">Admin</span>
      </span>
    </div>

    <nav class="sb-nav">
      <?php foreach ($nav as $group => $links): ?>
        <div class="sb-label"><?= $group ?></div>
        <?php foreach ($links as [$key, $label, $icon, $badge]): ?>
          <a class="sb-link <?= $view === $key ? 'on' : '' ?>" href="manage_products.php?view=<?= $key ?>">
            <i class="fa-solid <?= $icon ?>"></i>
            <span><?= $label ?></span>
            <?php if ($badge !== null && $badge > 0): ?>
              <span class="sb-pill <?= $key === 'restock' ? '' : 'quiet' ?>"><?= (int)$badge ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>

      <div class="sb-label">Shop</div>
      <a class="sb-link" href="index.php"><i class="fa-solid fa-arrow-up-right-from-square"></i><span>View storefront</span></a>
    </nav>

    <div class="sb-foot">
      <div class="sb-user">
        <?php $me = adminUser(); ?>
        <span class="sb-avatar"><?= e(strtoupper(substr($me['display_name'] ?? 'A', 0, 2))) ?></span>
        <span>
          <span class="who"><?= e($me['display_name'] ?? 'Admin') ?></span><br>
          <span class="role"><?= e($me['role'] ?? 'Administrator') ?></span>
        </span>
      </div>
      <a class="sb-link" href="login.php?logout=1"><i class="fa-solid fa-right-from-bracket"></i><span>Sign out</span></a>
    </div>
  </aside>
  <div class="sb-scrim" id="sbScrim"></div>

  <div class="a-main">
    <header class="topbar">
      <button class="burger" id="burger" aria-label="Toggle menu"><i class="fa-solid fa-bars"></i></button>
      <div>
        <div class="crumb">Admin <?= $crumb ?? '' ?></div>
        <h1><?= e($heading ?? 'Dashboard') ?></h1>
      </div>
      <div class="top-actions">
        <?= $topActions ?? '' ?>
      </div>
    </header>

    <div class="a-body">
