<?php
/** Shared page shell — open. Expects $page_title, optionally $page_desc. */
$current = basename($_SERVER['PHP_SELF']);
$nav = [
    'index.php'           => 'Home',
    'products.php'        => 'Shop',
    'articles.php'        => 'Journal',
    'contact.php'         => 'Contact',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title ?? BRAND_FULL) ?></title>
<meta name="description" content="<?= e($page_desc ?? TAGLINE) ?>">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<script>
  /* Enable scroll-reveal styling before first paint so nothing flashes.
     If motion.js fails to load, the failsafe strips the class again so
     content can never be left stranded at opacity 0. */
  document.documentElement.classList.add('motion');
  setTimeout(function () {
    if (!window.__motionReady) document.documentElement.classList.remove('motion');
  }, 2500);
</script>
</head>
<body>

<div class="scroll-bar" id="scrollBar" aria-hidden="true"></div>

<div class="announce">Free shipping on orders over $150 &nbsp;·&nbsp; Two-year warranty on every component</div>

<header class="site-header">
  <div class="wrap header-inner">
    <a href="index.php" class="brand"><?= BRAND_FULL ?></a>
    <nav class="nav" id="nav">
      <?php foreach ($nav as $file => $label): ?>
        <a href="<?= $file ?>"<?= $current === $file ? ' class="active"' : '' ?>><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="header-actions">
      <button type="button" class="bag<?= cartCount() ? ' has-items' : '' ?>" id="bagBtn" aria-label="Open bag">
        <span>Bag</span><span class="n" id="bagCount"><?= cartCount() ?></span>
      </button>
      <button class="nav-toggle" id="navToggle" aria-label="Menu" aria-expanded="false">
        <i class="fa-solid fa-bars"></i>
      </button>
    </div>
  </div>
</header>

<main>
