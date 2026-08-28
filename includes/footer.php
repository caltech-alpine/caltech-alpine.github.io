</main>

<footer class="site-footer">
  <div class="site-footer__topo" aria-hidden="true"></div>

  <div class="wrap site-footer__inner">

    <div class="site-footer__brand">
      <img class="site-footer__mark" src="<?= e(asset('images/ice-axe.svg')) ?>"
           alt="" width="32" height="32" aria-hidden="true">
      <?php /* ONE LINE. This block identifies the site to somebody who has
               scrolled to the bottom; it is not a second About page. "Founded
               1987" was here too, on all eight pages, and it is on the homepage
               hero and on About already. */ ?>
      <p class="site-footer__blurb">
        Trips, shared gear, and people to go with. Membership is open beyond
        Caltech.
      </p>

      <dl class="site-footer__contact">
        <dt>General questions</dt>
        <dd>
          <a href="mailto:<?= e(cfg('links.officers')) ?>"><?= e(cfg('links.officers')) ?></a>
        </dd>

        <?php if (cfg('links.secretary')): ?>
          <dt>Membership</dt>
          <dd>
            <a href="mailto:<?= e(cfg('links.secretary')) ?>"><?= e(cfg('links.secretary')) ?></a>
            <span class="note-small">
              Including membership requests from outside Caltech and JPL.
            </span>
          </dd>
        <?php endif; ?>
      </dl>
    </div>

    <?php /* TOP-LEVEL PAGES ONLY. This used to print every section anchor as
             well: four columns of sub-links, on eight pages, for a site with
             five pages and a Join button. The anchors are still in nav.php and
             still open from the header menu, which is where somebody looking
             for one goes. */ ?>
    <nav class="site-footer__nav" aria-label="Footer">
      <ul class="site-footer__links">
        <?php foreach ((require __DIR__ . '/nav.php') as $item): ?>
          <li><a href="<?= e(url($item['href'])) ?>"><?= e($item['label']) ?></a></li>
        <?php endforeach; ?>
        <li><a href="<?= e(url('join.php')) ?>">Join</a></li>
      </ul>
    </nav>

  </div>

  <div class="wrap site-footer__legal">
    <p>
      &copy; <?= date('Y') ?> Caltech Alpine Club.
      A student club at the
      <a href="<?= e(cfg('links.caltech')) ?>" rel="noopener">California Institute of Technology</a>.
    </p>
    <ul>
      <li><a href="<?= e(cfg('links.accessibility')) ?>" rel="noopener">Accessibility</a></li>
      <li><a href="<?= e(cfg('links.privacy')) ?>" rel="noopener">Privacy notice</a></li>
    </ul>
  </div>
</footer>

<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</body>
</html>
