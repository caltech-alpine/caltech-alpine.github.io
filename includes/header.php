<?php
/**
 * ============================================================================
 *  Shared page head, masthead and navigation.
 * ============================================================================
 *
 *  Every page starts like this:
 *
 *      <?php
 *      require __DIR__ . '/includes/bootstrap.php';
 *      $PAGE = array(
 *          'title'       => 'Events & Trips',
 *          'description' => 'One sentence for Google and for link previews.',
 *          'nav'         => 'events.php',   // which menu item to highlight
 *      );
 *      require __DIR__ . '/includes/header.php';
 *      ?>
 *      ... page content ...
 *      <?php require __DIR__ . '/includes/footer.php'; ?>
 * ============================================================================
 */

if (!defined('ALPINE_BOOTSTRAPPED')) {
    require __DIR__ . '/bootstrap.php';
}

$PAGE = isset($PAGE) ? $PAGE : array();
$pageTitle  = isset($PAGE['title']) ? $PAGE['title'] : '';
$pageDesc   = isset($PAGE['description']) ? $PAGE['description'] : cfg('site.description');
$currentNav = isset($PAGE['nav']) ? $PAGE['nav'] : '';
$bodyClass  = isset($PAGE['class']) ? $PAGE['class'] : '';

$fullTitle = $pageTitle === ''
    ? cfg('site.name') . ' — ' . cfg('site.tagline')
    : $pageTitle . ' — ' . cfg('site.name');

/* Canonical URL. Defaults to this script's own name, which is right for every
   page; set $PAGE['canonical'] to override. Pages that set 'noindex' (the 404
   and the internal preview) get neither a canonical nor a place in search
   results. */
$noindex = !empty($PAGE['noindex']);
$canonicalPath = isset($PAGE['canonical'])
    ? $PAGE['canonical']
    : basename($_SERVER['SCRIPT_NAME']);
if ($canonicalPath === 'index.php') { $canonicalPath = ''; }
$canonicalUrl = rtrim(cfg('site.url'), '/') . '/' . ltrim($canonicalPath, '/');

$navItems = require __DIR__ . '/nav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">
<meta name="theme-color" content="#14181a">

<?php if ($noindex): ?>
  <meta name="robots" content="noindex, nofollow">
<?php else: ?>
  <link rel="canonical" href="<?= e($canonicalUrl) ?>">
<?php endif; ?>

<link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(asset('images/favicon.svg')) ?>">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(cfg('site.name')) ?>">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<?php
/* Link-preview image. A real photo at assets/images/social.jpg wins; the
   generated fallback keeps Slack and iMessage from showing a bare gray box,
   which is how most people will first meet this site. */
$ogImage = alpine_has_image('social.jpg') ? 'images/social.jpg'
         : (alpine_has_image('social-default.png') ? 'images/social-default.png' : '');
?>
<?php if ($ogImage): ?>
<meta property="og:image" content="<?= e(rtrim(cfg('site.url'), '/') . '/' . asset($ogImage)) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="<?= e(cfg('site.name')) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">

<?php /* One webfont, one request. Everything falls back to a good system
         stack if it fails to load — see --font-sans in style.css. */ ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&display=swap">

<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">

<script type="application/ld+json">
<?= json_encode(array(
    '@context' => 'https://schema.org',
    '@type'    => 'SportsClub',
    'name'     => cfg('site.name'),
    'url'      => cfg('site.url'),
    'description' => cfg('site.description'),
    'foundingDate' => (string) cfg('facts.founded'),
    'parentOrganization' => array(
        '@type' => 'CollegeOrUniversity',
        'name'  => 'California Institute of Technology',
        'url'   => 'https://www.caltech.edu',
    ),
), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>
</head>

<body class="<?= e($bodyClass) ?>">
<?php require __DIR__ . '/icons.php'; ?>

<a class="skip-link" href="#main">Skip to content</a>

<header class="masthead">
  <div class="wrap masthead__inner">

    <a class="brand" href="<?= e(url('index.php')) ?>">
      <?php if (cfg('site.logo') && alpine_has_image(cfg('site.logo'))): ?>
        <img class="brand__logo" src="<?= e(asset('images/' . cfg('site.logo'))) ?>"
             alt="<?= e(cfg('site.logo_alt')) ?>" width="40" height="40">
      <?php else: ?>
        <?php /* Placeholder mark until the real logo lands. Set site.logo in
                 includes/config.php and this swaps automatically. */ ?>
        <img class="brand__logo brand__logo--placeholder"
             src="<?= e(asset('images/ice-axe.svg')) ?>" alt="" width="40" height="40" aria-hidden="true">
      <?php endif; ?>
      <span class="brand__text">
        <span class="brand__caltech">Caltech</span>
        <span class="brand__name">Alpine Club</span>
      </span>
    </a>

    <nav class="nav" aria-label="Main">
      <ul class="nav__list">
        <?php /* Four plain links, no dropdowns. Every sub-item was an anchor
                 inside a short page, so a hover-and-aim menu stood in front of
                 something a scroll reaches anyway. The deep links still exist
                 in the footer, where people expect to find them and no
                 hovering is required. */ ?>
        <?php foreach ($navItems as $item): ?>
          <?php $isHere = alpine_is_current($item['href'], $currentNav); ?>
          <li class="nav__item">
            <a class="nav__link<?= $isHere ? ' is-current' : '' ?>"
               href="<?= e(url($item['href'])) ?>"
               <?= $isHere ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <a class="btn btn--primary btn--sm masthead__cta" href="<?= e(url('join.php')) ?>">Join</a>

    <button class="masthead__toggle" type="button"
            aria-expanded="false" aria-controls="mobile-nav">
      <span class="masthead__toggle-open"><?= icon('menu') ?></span>
      <span class="masthead__toggle-close"><?= icon('close') ?></span>
      <span class="sr-only">Menu</span>
    </button>

    <?php /* The mobile panel below ships `hidden` and only site.js can reveal
             it, so with JavaScript blocked a phone had a menu button that did
             nothing and NO other way to reach another page — the desktop nav is
             display:none at that width. This unhides the panel and removes the
             dead button for those visitors. Cheaper than a CSS-only toggle, and
             unlike shipping the panel open it cannot flash before site.js
             runs. */ ?>
    <noscript>
      <style>
        #mobile-nav[hidden] { display: block; }
        .masthead__toggle { display: none; }
      </style>
    </noscript>

  </div>

  <nav class="mobile-nav" id="mobile-nav" aria-label="Main (mobile)" hidden>
    <div class="wrap">
      <ul class="mobile-nav__list">
        <li><a class="mobile-nav__top" href="<?= e(url('index.php')) ?>">Home</a></li>
        <?php foreach ($navItems as $item): ?>
          <li>
            <a class="mobile-nav__top" href="<?= e(url($item['href'])) ?>"><?= e($item['label']) ?></a>
            <?php if (!empty($item['children'])): ?>
              <ul class="mobile-nav__sub">
                <?php foreach ($item['children'] as $child): ?>
                  <li><a href="<?= e(url($child['href'])) ?>"><?= e($child['label']) ?></a></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <a class="btn btn--primary btn--block" href="<?= e(url('join.php')) ?>">Join the club</a>
    </div>
  </nav>
</header>

<main id="main">
