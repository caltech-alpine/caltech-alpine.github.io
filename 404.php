<?php
/**
 * Not found.
 *
 * Wire this up in .htaccess with:  ErrorDocument 404 /404.php
 */

require_once __DIR__ . '/includes/bootstrap.php';

http_response_code(404);

$PAGE = array(
    'title'       => 'Page not found',
    'description' => 'That page does not exist on the Caltech Alpine Club website.',
    'noindex'     => true,
);

require __DIR__ . '/includes/header.php';
?>

<header class="page-hero">
  <div class="topo"></div>
  <div class="wrap page-hero__inner">
    <p class="eyebrow">404</p>
    <h1 class="h1">Page not found</h1>
    <?php /* No lede. It said "This page does not exist", which is the
             heading again in different words. The links below are the whole
             useful content of a 404. */ ?>
  </div>
</header>

<section class="section">
  <div class="wrap">
    <div class="btn-row">
      <a class="btn btn--primary" href="<?= e(url('index.php')) ?>">Home</a>
      <a class="btn btn--ghost" href="<?= e(url('events.php')) ?>">Upcoming events</a>
      <a class="btn btn--ghost" href="<?= e(url('gear.php')) ?>">Gear</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
