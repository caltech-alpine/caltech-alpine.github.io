<?php
/**
 * About: what the club is, and who to ask about what.
 *
 * The officer list comes from data/officers.php. After an election, edit that
 * file — nothing on this page needs touching.
 */

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/officers.php';
require __DIR__ . '/includes/partials.php';

$PAGE = array(
    'title'       => 'About',
    'description' => 'The Caltech Alpine Club: founded in 1987, 200+ active members, outdoor '
                   . 'trips and film screenings for the Caltech community. Officers and how '
                   . 'to reach them.',
    'nav'         => 'about.php',
);

/* Current and past officers, split and sorted for us. */
$roster = alpine_officers();

require __DIR__ . '/includes/header.php';
?>

<header class="page-hero">
  <div class="topo"></div>
  <div class="wrap page-hero__inner">
    <p class="eyebrow"><?= icon('mountain', 'icon icon--xs') ?>About</p>
    <h1 class="h1">About the Alpine Club</h1>
    <p class="lede">
      The Caltech Alpine Club organises outdoor trips and film screenings for the
      Caltech community.
    </p>
  </div>
</header>


<!-- ========================================================= what ==== -->
<section class="section" id="what">
  <div class="wrap">
    <div class="split split--wide-left">
      <div>
        <p class="eyebrow">What we do</p>
        <h2 class="h2">Getting people outside</h2>
        <div class="prose mt-lg">
          <p>
            The club organises hiking, backpacking, trail running, climbing, and
            other outdoor trips, and keeps a stock of equipment members can borrow.
            What runs in a given year depends on who is leading it, so the
            <a href="<?= e(url('events.php')) ?>">calendar</a> is the best guide to
            what is happening now.
          </p>
          <p>
            The club also hosts the Banff Mountain Film Festival on campus each year,
            along with other mountain film screenings and talks by members and visiting
            climbers.
          </p>
          <p>
            Members are graduate students, undergraduates, postdocs, faculty, staff, and
            JPL employees, and range from experienced mountaineers to people on their
            first outdoor trip.
          </p>
          <?php /* ONE founding narrative across the site: the club was founded in
                   1987, and that is what the hero, the stats and the meta description
                   all say. The 1950 material is older and less certain, so it appears
                   exactly once, here, hedged and subordinate. Do not promote it to a
                   headline number. */ ?>
          <p>
            The club was founded in <?= e(cfg('facts.founded')) ?>, but club records
            suggest Caltech outdoor trips go back to around 1950.
          </p>
        </div>
      </div>

      <div class="stack-lg">
        <blockquote class="pull">
          &ldquo;<?= e(rtrim(cfg('facts.mission'), '.')) ?>.&rdquo;
        </blockquote>

        <div class="stats stats--light">
          <div class="stat">
            <div class="stat__num"><?= e(cfg('facts.members')) ?></div>
            <div class="stat__label">members across Caltech, JPL, and the local community</div>
          </div>
          <div class="stat">
            <div class="stat__num"><?= e(cfg('facts.founded')) ?></div>
            <div class="stat__label">founded by <?= e(cfg('facts.founder')) ?></div>
          </div>
          <div class="stat">
            <div class="stat__num"><?= date('Y') - (int) cfg('facts.banff_since') ?></div>
            <div class="stat__label">
              years hosting the Banff Mountain Film Festival, since
              <?= e(cfg('facts.banff_since')) ?>
            </div>
          </div>
          <?php /* The count comes from facts.festivals so this cannot drift out
                   of step with the same figure on the Support page. */ ?>
          <div class="stat">
            <div class="stat__num"><?= count(cfg('facts.festivals')) ?></div>
            <div class="stat__label">
              mountain film festivals the club runs:
              <?= e(implode(', ', array_keys(cfg('facts.festivals')))) ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ===================================================== officers ==== -->
<section class="section section--tint" id="officers">
  <div class="wrap">
    <div class="section-head">
      <div class="section-head__text">
        <p class="eyebrow"><?= icon('social', 'icon icon--xs') ?>Who to ask</p>
        <h2 class="h2">Officers</h2>
        <p class="lede">
          Officers are volunteers, elected each year. Contact any of them with
          questions.
        </p>
      </div>
      <a class="arrow-link" href="#contact">
        Contact details <?= icon('arrow-right', 'icon icon--xs') ?>
      </a>
    </div>

    <?php if (!$roster['current']): ?>
      <div class="empty-state">
        <h3>The roster is being updated</h3>
        <p>Officers are listed in <code>data/officers.php</code>.</p>
      </div>
    <?php else: ?>
      <?php foreach ($roster['current'] as $groupName => $people): ?>
        <div class="officer-group">
          <h3 class="officer-group__title"><?= e($groupName) ?></h3>
          <div class="officers">
            <?php foreach ($people as $o): ?>
              <div class="officer">
                <?php if (!empty($o['photo']) && alpine_has_image('officers/' . $o['photo'])): ?>
                  <img class="officer__photo"
                       src="<?= e(asset('images/officers/' . $o['photo'])) ?>"
                       alt="<?= e($o['name']) ?>" loading="lazy" width="264" height="330">
                <?php else: ?>
                  <?php /* No headshot yet — initials rather than an empty frame. */ ?>
                  <div class="officer__initials" aria-hidden="true"><?= e(alpine_initials($o['name'])) ?></div>
                <?php endif; ?>

                <div class="officer__name"><?= e($o['name']) ?></div>
                <div class="officer__role"><?= e(isset($o['displayRole']) ? $o['displayRole'] : $o['role']) ?></div>

                <?php if (!empty($o['handles'])): ?>
                  <p class="officer__handles"><?= e($o['handles']) ?></p>
                <?php endif; ?>

                <?php if (!empty($o['email'])): ?>
                  <a class="officer__mail" href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

    <?php endif; ?>

    <?php /* Past officers. Nobody maintains this list — an officer gets an
             'until' year in data/officers.php and appears here by itself. */ ?>
    <?php if ($roster['past']): ?>
      <div class="alumni">
        <h3 class="officer-group__title">Past officers</h3>
        <?php foreach ($roster['past'] as $year => $people): ?>
          <div class="alumni__year">
            <h4 class="alumni__heading">Through <?= e($year) ?></h4>
            <ul class="alumni__list">
              <?php foreach ($people as $o): ?>
                <li>
                  <span class="alumni__name"><?= e($o['name']) ?></span>
                  <span class="alumni__role"><?= e(isset($o['displayRole']) ? $o['displayRole'] : $o['role']) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>


<!-- ====================================================== contact ==== -->
<section class="section" id="contact">
  <div class="wrap wrap--narrow">
    <p class="eyebrow"><?= icon('mail', 'icon icon--xs') ?>Contact</p>
    <h2 class="h2">Contact the club</h2>

    <dl class="contact-list mt-lg">
      <div>
        <dt>Anything at all</dt>
        <dd>
          <a href="mailto:<?= e(cfg('links.officers')) ?>"><?= e(cfg('links.officers')) ?></a>
          <span class="contact-list__note">Reaches the current officers.</span>
        </dd>
      </div>

      <?php if (cfg('links.secretary')): ?>
        <div>
          <dt>Membership</dt>
          <dd>
            <a href="mailto:<?= e(cfg('links.secretary')) ?>"><?= e(cfg('links.secretary')) ?></a>
            <span class="contact-list__note">
              Including requests to join from outside Caltech and JPL —
              see <a href="<?= e(url('join.php#outside')) ?>">how to ask</a>.
            </span>
          </dd>
        </div>
      <?php endif; ?>

      <div>
        <dt>A particular activity</dt>
        <dd>
          <a href="#officers">Find the officer who looks after it</a>
        </dd>
      </div>

    </dl>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
