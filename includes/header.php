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

<?php /* THE ICON SET. Every file here is generated: art/favicon.png ->
         tools/trace_logo.py -> tools/make_icons.py. Do not hand-edit one.

         FIVE LINES, AND THE ORDER IS THE POINT. A browser that cannot pick
         by `sizes` takes the LAST rel=icon it understands, so the legacy
         .ico goes FIRST and the SVG last. Written the other way round -- SVG
         first, bitmaps after, which is the intuitive reading order -- a
         modern browser ends up on a 48px bitmap in a tab that would have
         taken vector. That is the whole reason this block is not sorted the
         way it reads.

         WHY NO 16/32/48 PNG LINES. They would be redundant: favicon.ico
         already CONTAINS 16, 32 and 48, each rendered at its own size rather
         than downscaled, and it has to exist anyway for the clients below.
         Three extra files and three extra lines bought nothing.

         /favicon.ico ALSO EARNS ITS PLACE WITHOUT THIS TAG. Crawlers, feed
         readers, link unfurlers and Windows pin-to-taskbar request that bare
         path and read no HTML first. It was a 404 until 2026-09-02.

         THE TAB ICON IS TRANSPARENT, and stays legible by flipping rather
         than by hiding behind a ground. favicon.svg has no <rect>: a tab
         should show the mark, not a pale square. The mountain is a near-black,
         so on a dark tab strip a fixed one would merge and leave a bare orange
         ring -- which is why a ground existed here for three days. Instead the
         file carries its own @media (prefers-color-scheme: dark) rule and
         repaints the mountain paper. Verified in a real browser by drawing the
         SVG to a canvas and reading the pixel back: ink 20,24,26 in light,
         paper 249,246,240 in dark, corner alpha 0 in both.

         THE DARK TWIN IS BELT AND BRACES, not the mechanism. It covers a
         browser that honours media= on this <link> but does not evaluate a
         media query inside an SVG used as an icon. Both paths land on a paper
         mountain; a browser that does neither still gets a transparent icon.

         /favicon.ico IS THE ONE OPAQUE FILE, deliberately. Its real consumer
         is Windows pin-to-taskbar, the taskbar is dark by default, and an
         .ico cannot express a media query. */ ?>
<link rel="icon" href="<?= e(url('favicon.ico')) ?>" sizes="32x32">
<link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(asset('images/favicon-on-dark.svg')) ?>" type="image/svg+xml" media="(prefers-color-scheme: dark)">
<?php /* iOS ignores an SVG icon and will not use one for a home-screen
         bookmark, so the touch icon has to be a raster. */ ?>
<link rel="apple-touch-icon" href="<?= e(asset('images/apple-touch-icon.png')) ?>">
<?php /* The manifest is what makes Android Chrome use the club's icon for a
         home-screen bookmark instead of falling back to the Apple one. It
         declares display:browser on purpose -- this is a website, not an
         app, and half its useful links go to Google Calendar and Slack, so
         stripping the browser chrome would trap somebody there. */ ?>
<link rel="manifest" href="<?= e(url('site.webmanifest')) ?>">

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

<?php /* ONE webfont, ONE request, ONE file. Inter, as a VARIABLE font:
         `wght@400..750` makes Google serve a single woff2 per subset whose
         weight axis is continuous, so the 650 and 720 the headings ask for
         cost nothing extra. Listing static weights instead (400;500;600;700)
         would be four files for fewer weights.

         WHY INTER AND NOTHING ELSE. The club's mark already carries a strong
         condensed bold treatment of its own. A second display face on the
         page would put two typographic identities in front of the same
         visitor, so the website is deliberately quiet and the logo does the
         talking. Do not add a display family here.

         Everything falls back to a good system stack if this fails to load —
         see --font-sans in style.css. */ ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400..750&display=swap">

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

    <?php /* THE NAME IS IN THE LOGO NOW, so it is not also set as text beside
             it. The mark used to be wordless and the masthead supplied
             "Caltech / Alpine Club" in two spans; the artwork carries both
             lines itself as of 2026-08-31, and printing them again would be
             the club's name twice in one bar.

             The dark-background file, because this bar is ink. logo.svg is
             its light-background twin, same trace, one token different.

             The spans are still here as the FALLBACK for an empty site.logo.
             That setting exists so somebody can drop the mark and keep a
             wordmark, and with the words inside the image an empty setting
             would otherwise leave the masthead with no name at all. */ ?>
    <a class="brand" href="<?= e(url('index.php')) ?>">
      <?php if (cfg('site.logo_dark') && alpine_has_image(cfg('site.logo_dark'))): ?>
        <img class="brand__logo" src="<?= e(asset('images/' . cfg('site.logo_dark'))) ?>"
             alt="<?= e(cfg('site.logo_alt')) ?>" width="154" height="44">
      <?php else: ?>
        <span class="brand__text">
          <span class="brand__caltech">Caltech</span>
          <span class="brand__name">Alpine Club</span>
        </span>
      <?php endif; ?>
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
