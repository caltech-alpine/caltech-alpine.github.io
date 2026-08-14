<?php
/**
 * Sitemap, generated rather than hand-maintained, so it cannot go stale in a
 * way nobody notices.
 *
 * preview.php is deliberately excluded.
 */

require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$base = rtrim(cfg('site.url'), '/') . '/';

$pages = array(
    array('',            '1.0'),
    array('events.php',  '0.9'),
    array('join.php',    '0.9'),
    array('gear.php',    '0.8'),
    array('about.php',   '0.7'),
    array('support.php', '0.6'),
);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($pages as $p): ?>
  <url>
    <loc><?= e($base . $p[0]) ?></loc>
    <priority><?= e($p[1]) ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
