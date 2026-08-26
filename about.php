<?php
/**
 * About: what the club is, and who to ask about what.
 *
 * The officer list comes from data/officers.php. After an election, edit that
 * file — nothing on this page needs touching.
 */

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/officers.php';
require __DIR__ . '/includes/roles.php';
require __DIR__ . '/includes/partials.php';

$PAGE = array(
    'title'       => 'About',
    'description' => 'The Caltech Alpine Club: founded in 1987, outdoor '
                   . 'trips and film screenings for the Caltech community. Officers and how '
                   . 'to reach them.',
    'nav'         => 'about.php',
);

/* Current and past officers, split and sorted for us. */
$roster = alpine_officers();

/* The roles nobody is doing, bucketed by the same group headings the people
   are, so an open job appears IN the roster grid rather than in a separate
   "vacancies" box further down the page. Somebody scanning this grid for who to
   ask is exactly the person who should find out the job is going spare, and
   they will not scroll past the fold to find that out. */
$openByGroup = array();
foreach (alpine_roles_wanted() as $r) {
    $openByGroup[!empty($r['group']) ? $r['group'] : 'Officers'][] = $r;
}

require __DIR__ . '/includes/header.php';
?>

<?php alpine_page_hero(array(
    'title'  => 'About the Alpine Club',
    'lede'   => 'The Caltech Alpine Club organizes outdoor trips and film screenings '
              . 'for the Caltech community.',
    'photo'  => 'photos/cac-mammoth-from-sujung.jpg',
    'credit' => 'Club trip to Mammoth',
)); ?>


<!-- ========================================================= what ==== -->
<section class="section" id="what">
  <div class="wrap">
    <div class="split split--wide-left">
      <div>
        <h2 class="h2">Getting people outside</h2>
        <div class="prose mt-lg">
          <p>
            The club organizes hiking, backpacking, trail running, climbing, and
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
                   1987, and that is what the hero and the meta description both say.
                   The 1950 material is older and less certain, so it appears exactly
                   once, here, hedged and subordinate. Do not promote it to a headline
                   number. */ ?>
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

      </div>
    </div>
  </div>
</section>


<!-- ===================================================== officers ==== -->
<section class="section section--tint" id="officers">
  <div class="wrap">
    <div class="section-head">
      <div class="section-head__text">
        <h2 class="h2">Officers</h2>
        <?php /* "elected each year" used to be asserted here. Nobody has been
                 able to point at where that is written down -- there is no
                 constitution or bylaws in this repository or on the old site --
                 so the page no longer claims it. What is certainly true is that
                 they are members who volunteered, and that is the sentence that
                 invites the reader in anyway. */ ?>
        <p class="lede">
          Officers are club members who volunteer to run things. Contact any of
          them with questions.
        </p>
      </div>
      <a class="arrow-link" href="<?= e(url('roles.php')) ?>">
        Ways you can help run the club <?= icon('arrow-right', 'icon icon--xs') ?>
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

            <?php /* Generated from data/roles.csv: a role listed there with
                     nobody currently holding it. Nobody types "vacant" anywhere,
                     and the slot disappears by itself the moment somebody is
                     added to data/officers.csv. */ ?>
            <?php if (!empty($openByGroup[$groupName])): ?>
              <?php foreach ($openByGroup[$groupName] as $r): ?>
                <a class="officer officer--open" href="<?= e(url('roles.php#' . alpine_slug($r['role']))) ?>">
                  <span class="officer__vacant" aria-hidden="true">
                    <?= icon('arrow-right', 'icon icon--lg') ?>
                  </span>
                  <span class="officer__name">Could be you</span>
                  <span class="officer__role"><?= e($r['role']) ?></span>
                  <span class="officer__handles"><?= e(alpine_role_status_line($r)) ?></span>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <?php /* The catch-all, restored 2026-08-14. It was cut as a duplicate of
               the Contact section below, and on a full read of the page it is
               one. But somebody scanning the roster for the right person and
               not finding them stops HERE, at the end of the grid, and never
               scrolls into a section they have no reason to expect. */ ?>
      <div class="note mt-lg">
        <?= icon('mail', 'icon icon--xs') ?>
        <p>
          Not sure who to ask? Write to
          <a href="mailto:<?= e(cfg('links.officers')) ?>"><?= e(cfg('links.officers')) ?></a>
          and it will reach an officer.
        </p>
      </div>

    <?php endif; ?>

    <?php /* Past officers. Nobody maintains this list — an officer gets an
             'until' year in data/officers.csv and appears here by itself. */ ?>
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
