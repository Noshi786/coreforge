<?php
require_once __DIR__ . '/partials/config.php';

/* Editorial content — the journal does not live in the database. */
$guides = [
    ['guide-gpu.jpg','Graphics','How to pick a GPU without overspending',
     'Resolution decides your card, not the badge on the box. A 1080p 144Hz panel is well served by a mid-range card; flagship money only pays off at 4K or with heavy ray tracing.','7 min','12 Aug 2026'],
    ['guide-ram.jpg','Memory','DDR5 speeds and timings, explained plainly',
     'DDR5-6000 CL30 is the sweet spot on AM5 because it keeps the memory controller running in sync. Faster kits often run slower once the controller drops to a divided ratio.','6 min','04 Aug 2026'],
    ['guide-psu.jpg','Power','Sizing a power supply you will not regret',
     'Add your CPU and GPU peak draw, then leave 30% headroom for transient spikes. One 80+ Gold unit from a reputable OEM outlives three cheap ones.','5 min','28 Jul 2026'],
    ['guide-storage.jpg','Storage','NVMe, SATA and spinning rust',
     'NVMe wins on paper and in load times, but SATA SSDs still make excellent bulk game storage. Mechanical drives remain the cheapest place to park archives.','8 min','19 Jul 2026'],
    ['guide-cpu.jpg','Processors','Core counts: when more actually helps',
     'Games rarely scale past eight fast cores, while compiling, rendering and export scale nearly linearly. Match the chip to what you do most, not to the benchmark chart.','6 min','11 Jul 2026'],
    ['guide-compat.jpg','Compatibility','A pre-build compatibility checklist',
     'Socket, chipset, RAM generation, case clearance, PSU connectors and BIOS version — six checks that prevent most parts that arrive and simply will not fit.','9 min','02 Jul 2026'],
];

$featured = array_shift($guides);              // the newest piece leads the page
$topics   = array_values(array_unique(array_column($guides, 1)));

$page_title = 'Journal — ' . BRAND_FULL;
$page_desc  = 'Practical guides to choosing and fitting PC components.';
require __DIR__ . '/partials/header.php';
?>

<!-- Hero -->
<section class="j-hero">
  <div class="wrap">
    <span class="micro line-mask" style="color:#8d8984"><span>The journal &middot; <?= count($guides) + 1 ?> guides</span></span>
    <h1 class="display" style="color:#fff;margin-top:14px;font-size:clamp(2.1rem,5.6vw,4.1rem)">
      <span class="line-mask"><span>Advice from</span></span>
      <span class="line-mask"><span>the bench.</span></span>
    </h1>
    <p class="line-mask" style="color:#9a9691;margin-top:18px;max-width:52ch">
      <span>No affiliate padding and no spec-sheet recitals — just what we tell customers who ask us across the counter.</span>
    </p>
  </div>
</section>

<!-- Featured -->
<section class="section" style="padding-bottom:clamp(30px,4vw,54px)">
  <div class="wrap">
    <div class="section-head" data-reveal>
      <div><span class="micro">Latest</span><h2 class="h-lg">This week's read</h2></div>
    </div>
    <article class="j-feature" data-reveal="scale">
      <div class="j-feature-media">
        <span class="j-badge">New</span>
        <img src="assets/img/<?= $featured[0] ?>" alt="">
      </div>
      <div>
        <div class="j-meta">
          <span><?= e($featured[1]) ?></span><span class="dot"></span>
          <span><?= e($featured[4]) ?> read</span><span class="dot"></span>
          <span><?= e($featured[5]) ?></span>
        </div>
        <h3 class="h-lg" style="margin-top:16px;max-width:18ch"><?= e($featured[2]) ?></h3>
        <p class="lede" style="margin-top:16px;max-width:52ch"><?= e($featured[3]) ?></p>
        <a href="#guides" class="link-u" style="display:inline-block;margin-top:26px">Read the guide</a>
      </div>
    </article>
  </div>
</section>

<!-- Topic filter + grid -->
<section class="section" id="guides" style="padding-top:0">
  <div class="wrap">
    <div class="j-filter" data-reveal>
      <button class="j-chip on" data-topic="all">All (<?= count($guides) ?>)</button>
      <?php foreach ($topics as $t): ?>
        <button class="j-chip" data-topic="<?= e($t) ?>">
          <?= e($t) ?> (<?= count(array_filter($guides, fn($g) => $g[1] === $t)) ?>)
        </button>
      <?php endforeach; ?>
    </div>

    <div class="p-grid p-grid-3" id="jGrid" style="margin-top:clamp(24px,3vw,40px)">
      <?php foreach ($guides as $i => [$img, $tag, $title, $body, $read, $date]): ?>
        <article class="j-card" data-topic="<?= e($tag) ?>" data-reveal>
          <div class="j-media">
            <img src="assets/img/<?= $img ?>" alt="" loading="lazy">
            <span class="j-read">Read <i class="fa-solid fa-arrow-right"></i></span>
          </div>
          <div class="j-body">
            <div class="j-meta">
              <span><?= e($tag) ?></span><span class="dot"></span>
              <span><?= e($read) ?></span><span class="dot"></span>
              <span><?= e($date) ?></span>
            </div>
            <h3><span class="j-title"><?= e($title) ?></span></h3>
            <p class="j-excerpt"><?= e($body) ?></p>
          </div>
        </article>
      <?php endforeach; ?>
      <div class="j-empty" id="jEmpty" hidden>Nothing filed under that topic yet.</div>
    </div>
  </div>
</section>

<!-- Closing split -->
<section class="split">
  <div class="split-media"><img src="assets/img/hero-side.jpg" alt="" loading="lazy"></div>
  <div class="split-body band-dark">
    <span class="micro">Ask us anything</span>
    <h2 class="h-lg" style="margin-top:14px">Still weighing up two parts?</h2>
    <p class="lede" style="margin-top:16px;max-width:46ch">
      Tell us the pair you are stuck between and what you actually run. We will give you a straight answer,
      even when the honest answer is the cheaper one.
    </p>
    <div style="margin-top:28px"><a href="contact.php" class="btn">Ask the team</a></div>
  </div>
</section>

<script>
  /* Topic filter with a re-settle animation on the cards that remain. */
  (function () {
    const chips = document.querySelectorAll('.j-chip');
    const cards = Array.from(document.querySelectorAll('.j-card'));
    const empty = document.getElementById('jEmpty');
    chips.forEach(chip => chip.addEventListener('click', () => {
      chips.forEach(c => c.classList.remove('on'));
      chip.classList.add('on');
      const topic = chip.dataset.topic;
      let shown = 0;
      cards.forEach((card, i) => {
        const match = topic === 'all' || card.dataset.topic === topic;
        card.classList.remove('settle');
        card.classList.toggle('hide', !match);
        if (match) {
          shown++;
          card.style.animationDelay = (shown - 1) * 70 + 'ms';
          void card.offsetWidth;              // restart the animation
          card.classList.add('settle');
        }
      });
      empty.hidden = shown > 0;
    }));
  })();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
