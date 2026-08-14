</main>

<footer class="site-footer">
  <div class="site-footer__topo" aria-hidden="true"></div>

  <div class="wrap site-footer__inner">

    <div class="site-footer__brand">
      <img class="site-footer__mark" src="<?= e(asset('images/ice-axe.svg')) ?>"
           alt="" width="32" height="32" aria-hidden="true">
      <p class="site-footer__blurb">
        Hiking, backpacking, climbing, and more, to get Caltech and JPL outdoors.
        Founded <?= e(cfg('facts.founded')) ?>. Caltech affiliation is not required
        to join.
      </p>

      <dl class="site-footer__contact">
        <dt>General enquiries</dt>
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

    <nav class="site-footer__nav" aria-label="Footer">
      <?php foreach ((require __DIR__ . '/nav.php') as $item): ?>
        <div class="site-footer__col">
          <h2 class="site-footer__heading">
            <a href="<?= e(url($item['href'])) ?>"><?= e($item['label']) ?></a>
          </h2>
          <?php if (!empty($item['children'])): ?>
            <ul>
              <?php foreach ($item['children'] as $child): ?>
                <li><a href="<?= e(url($child['href'])) ?>"><?= e($child['label']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
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
