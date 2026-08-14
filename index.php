<?php
/**
 * Homepage.
 *
 * Answers, in order: what the club is, what is coming up, what it organises,
 * what members can borrow, who runs it, and how to join.
 */

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$PAGE = array(
    'title'       => '',                       // home uses the site name alone
    'description' => cfg('site.description'),
    'nav'         => 'index.php',
);

$upcoming = AlpineCalendar::upcoming(cfg('calendar.home_limit'));
$sponsors = alpine_data('sponsors');

require __DIR__ . '/includes/header.php';
?>

<!-- ========================================================== hero ==== -->
<section class="hero">
  <?php if (alpine_has_image('hero.jpg')): ?>
    <div class="hero__media" style="background-image:url(<?= e(asset('images/hero.jpg')) ?>)"></div>
  <?php else: ?>
    <?php /* No photograph yet: the contour map carries the hero. Drop a wide
             club photo at assets/images/hero.jpg and it takes over. */ ?>
    <div class="topo hero__topo"></div>
  <?php endif; ?>

  <div class="wrap">
    <div class="hero__inner">
      <p class="hero__eyebrow">Pasadena, California · Founded <?= e(cfg('facts.founded')) ?></p>

      <h1 class="display hero__title">
        Less lab.<em>More mountains.</em>
      </h1>

      <p class="hero__text">
        Hiking, backpacking, climbing, and more, to get Caltech and JPL outdoors.
      </p>

      <div class="btn-row hero__actions">
        <a class="btn btn--primary btn--lg" href="<?= e(url('join.php')) ?>">Join the club</a>
        <a class="btn btn--light btn--lg" href="<?= e(url('events.php')) ?>">Upcoming events</a>
        <a class="btn btn--light btn--lg" href="<?= e(url('gear.php#rental')) ?>">Borrow gear</a>
      </div>

      <p class="hero__note">
        <?= icon('heart', 'icon icon--xs') ?>
        Many events are open to beginners. Caltech affiliation is not required to join.
      </p>
    </div>
  </div>
</section>


<!-- ==================================================== coming up ==== -->
<section class="section" id="coming-up">
  <div class="wrap">

    <div class="section-head">
      <div class="section-head__text">
        <p class="eyebrow"><?= icon('calendar', 'icon icon--xs') ?>Events</p>
        <h2 class="h2">Coming up</h2>
      </div>
      <a class="arrow-link" href="<?= e(url('events.php')) ?>">
        All events <?= icon('arrow-right', 'icon icon--xs') ?>
      </a>
    </div>

    <?php if (AlpineCalendar::isUnavailable()): ?>
      <?php alpine_calendar_unavailable(); ?>
    <?php elseif (!$upcoming): ?>
      <?php alpine_events_empty(); ?>
    <?php else: ?>
      <div class="events-grid">
        <?php foreach ($upcoming as $event) { alpine_event_card($event); } ?>
      </div>
    <?php endif; ?>

  </div>
</section>


<!-- ========================================================= gear ==== -->
<section class="section section--tint" id="gear">
  <div class="wrap">
    <div class="split split--wide-left">
      <div>
        <p class="eyebrow"><?= icon('gear', 'icon icon--xs') ?>Equipment</p>
        <h2 class="h2">Gear</h2>
        <div class="prose mt-lg">
          <p>
            Tents, sleeping bags, stoves, crampons, ice axes, avalanche equipment,
            touring skis, splitboards and satellite messengers, for about $1 per day
            through the Caltech&nbsp;Y. Specialist climbing and packrafting equipment
            is held by the club directly.
          </p>
        </div>
        <div class="btn-row mt-lg">
          <a class="btn btn--primary" href="<?= e(url('gear.php#rental')) ?>">Borrow gear</a>
          <a class="btn btn--ghost" href="<?= e(url('support.php#donate')) ?>">Donate gear</a>
        </div>
      </div>

      <?php /* One caveat, the one that decides whether you can use any of this.
               Booking mechanics belong on the gear page, not here. */ ?>
      <div class="stack">
        <div class="note">
          <?= icon('gear', 'icon icon--xs') ?>
          <p>Club and Caltech Y equipment alike is for <strong>Caltech and JPL
             affiliates</strong>. Anyone can join the club and come on trips, but
             non-affiliates need their own kit.</p>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ==================================================== about ======== -->
<section class="section" id="about">
  <div class="wrap">

    <div class="split split--wide-left">
      <div>
        <p class="eyebrow"><?= icon('mountain', 'icon icon--xs') ?>About</p>
        <h2 class="h2">About the club</h2>
        <div class="prose mt-lg">
          <p>
            The Caltech Alpine Club was founded in <?= e(cfg('facts.founded')) ?> by
            <?= e(cfg('facts.founder')) ?>.
          </p>
          <p>
            Members are graduate students, undergraduates, postdocs, faculty, staff,
            JPL employees and people from the wider community, and range from
            experienced climbers and mountaineers to people on their first outdoor
            trip.
          </p>
        </div>
      </div>

      <?php /* The stats block that used to sit here repeated "1987" and
               "hundreds" from the prose beside it, and pushed this section past
               a screen. The numbers still appear on the About page, where that
               detail belongs. */ ?>
      <div>
        <blockquote class="pull">
          &ldquo;<?= e(rtrim(cfg('facts.mission'), '.')) ?>.&rdquo;
        </blockquote>
      </div>
    </div>

    <div class="pillars mt-lg">
      <div class="pillar">
        <?= icon('hike', 'icon icon--lg pillar__icon') ?>
        <h3>Trips</h3>
        <p>
          Hikes, trail runs, climbing days and longer trips, organised by officers
          and announced on the
          <a href="<?= e(url('join.php')) ?>">mailing list</a>.
        </p>
      </div>
      <div class="pillar">
        <?= icon('film', 'icon icon--lg pillar__icon') ?>
        <h3>Film festivals</h3>
        <p>
          The club has hosted the Banff Mountain Film Festival on campus since
          <?= e(cfg('facts.banff_since')) ?>, along with other mountain film
          screenings.
        </p>
      </div>
      <div class="pillar">
        <?= icon('talk', 'icon icon--lg pillar__icon') ?>
        <h3>Talks</h3>
        <p>
          Talks by club members and visiting climbers, held on campus during the
          academic year.
        </p>
      </div>
      <div class="pillar">
        <?= icon('social', 'icon icon--lg pillar__icon') ?>
        <h3>Finding partners</h3>
        <p>
          Members use Slack to find partners and organise informal outings between
          the scheduled trips.
        </p>
      </div>
    </div>

  </div>
</section>


<!-- ===================================================== sponsors ==== -->
<?php /* Only shown once there are sponsors to show. The "become a sponsor"
         invitation lives in the closing section below, so an empty roster
         costs no height at all. */ ?>
<?php if ($sponsors): ?>
<section class="section section--tint section--tight" id="sponsors">
  <div class="wrap">
    <div class="section-head">
      <div class="section-head__text">
        <p class="eyebrow"><?= icon('heart', 'icon icon--xs') ?>Support</p>
        <h2 class="h2">Sponsors</h2>
      </div>
      <a class="arrow-link" href="<?= e(url('support.php#sponsor')) ?>">
        Sponsorship information <?= icon('arrow-right', 'icon icon--xs') ?>
      </a>
    </div>

    <div class="sponsors">
      <?php foreach ($sponsors as $sponsor): ?>
        <?php
          $tag  = !empty($sponsor['url']) ? 'a' : 'div';
          $href = !empty($sponsor['url']) ? ' href="' . e($sponsor['url']) . '" rel="noopener"' : '';
        ?>
        <<?= $tag ?> class="sponsor"<?= $href ?>>
          <?php if (!empty($sponsor['logo']) && alpine_has_image($sponsor['logo'])): ?>
            <img src="<?= e(asset('images/' . $sponsor['logo'])) ?>" alt="<?= e($sponsor['name']) ?>" loading="lazy">
          <?php else: ?>
            <span class="sponsor__name"><?= e($sponsor['name']) ?></span>
          <?php endif; ?>
          <?php if (!empty($sponsor['tier'])): ?>
            <span class="sponsor__tier"><?= e($sponsor['tier']) ?></span>
          <?php endif; ?>
        </<?= $tag ?>>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- ============================================== join and support ==== -->
<?php /* One closing section, two asks. These used to be two full-height
         sections stacked on each other, which spent about a screen and a half
         on calls to action nobody had scrolled that far to read twice. */ ?>
<section class="section section--dark" id="join">
  <div class="topo"></div>
  <div class="wrap">
    <div class="split split--wide-left">

      <div>
        <p class="eyebrow"><?= icon('arrow-right', 'icon icon--xs') ?>Membership</p>
        <h2 class="h2">Join the club</h2>
        <p class="lede mt-lg">
          Membership is free. The mailing list carries trips and events; Slack is
          where people find each other week to week.
        </p>
        <div class="btn-row mt-lg">
          <a class="btn btn--primary btn--lg" href="<?= e(url('join.php')) ?>">Join the club</a>
          <a class="btn btn--light btn--lg" href="<?= e(url('events.php')) ?>">Upcoming events</a>
        </div>
      </div>

      <div>
        <p class="eyebrow"><?= icon('heart', 'icon icon--xs') ?>Support</p>
        <h3 class="h3">Support the club</h3>
        <p class="lede mt-lg">
          Sponsorship, donations and donated equipment pay for club trips, shared
          gear and the film festivals.
        </p>
        <div class="btn-row mt-lg">
          <a class="btn btn--light" href="<?= e(url('support.php#sponsor')) ?>">Sponsorship</a>
          <a class="btn btn--light" href="<?= e(url('support.php#donate')) ?>">Donations</a>
        </div>
      </div>

    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
