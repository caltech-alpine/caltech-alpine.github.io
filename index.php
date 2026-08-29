<?php
/**
 * Homepage.
 *
 * Answers, in order: what the club is, what is coming up, what it organizes,
 * what members can borrow, who runs it, and how to join.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/includes/partials.php';

$PAGE = array(
    'title'       => '',                       // home uses the site name alone
    'description' => cfg('site.description'),
    'nav'         => 'index.php',
);

$upcoming = AlpineCalendar::upcoming(cfg('calendar.home_limit'));
$sponsors = alpine_data('sponsors');
/* ONLY the jobs the club is actually SHORT of -- below their minimum. A job
   that is running fine and could take another person is a real thing to say on
   the Get Involved page and noise on a homepage, and the difference between the
   two is min_people and max_people in data/roles.csv rather than anyone's
   judgement on the day. */
$short = alpine_roles_needed();

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
        Trips, shared gear, and people to go with.
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


<!-- ======================================================= wanted ==== -->
<?php /* AN INVITATION, NOT A STAFFING NOTICE.

         This said "The club is short a Film Festival Coordinator", which reads
         as an org chart with a hole in it. What it is actually for is telling a
         member that a job they might enjoy is going spare, so the heading says
         that and the link goes to the page that explains what the jobs involve.

         "Caltech student" is in the sentence because these are the club's
         officer roles, and it is one clause rather than a paragraph. It does
         NOT qualify membership: anyone can join, which the hero says two inches
         above this and the footer says on every page.

         When every role is filled this renders nothing at all. That is the
         property that matters when nobody has touched the site for a year: the
         failure mode is silence, not a stale banner. */ ?>
<?php if ($short): ?>
<div class="wanted-strip">
  <div class="wrap wanted-strip__inner">
    <?php
    /* Assembled in PHP and echoed as one string. Built inline in the markup it
       picked up a newline before every comma, so the rendered line read
       "Film Festival Coordinator , and Talks Coordinator ." Anything that has
       to come out as a single sentence should be one string by the time it
       reaches the page.

       The link target is the role_id, so renaming a job does not break it. */
    $needs = array();
    foreach ($short as $r) {
        $needs[] = '<a href="' . e(url('roles.php#' . $r['role_id'])) . '">'
                 . e(alpine_role_help_phrase($r)) . '</a>';
    }
    ?>
    <div class="wanted-strip__text">
      <p class="wanted-strip__title">Help run the Alpine Club</p>
      <p>
        We're looking for
        <?= count($short) === 1 ? 'a Caltech student' : 'Caltech students' ?>
        to take on <?= alpine_list_phrase($needs) ?>.
      </p>
    </div>
    <a class="arrow-link" href="<?= e(url('roles.php')) ?>">
      See ways to get involved <?= icon('arrow-right', 'icon icon--xs') ?>
    </a>
  </div>
</div>
<?php endif; ?>


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
<?php /* --tight, not a full section: this block is two sentences and one
         caveat. Every section on this page used to take the same 102px of
         padding top and bottom regardless of what was in it, which is what
         gives a page that metronomic, generated rhythm. A short section
         should look short. */ ?>
<section class="section section--tint section--tight" id="gear">
  <div class="wrap">
    <div class="split split--wide-left">
      <div>
        <h2 class="h2">Gear</h2>
        <div class="prose mt-lg">
          <?php /* Not the inventory. That is the Gear page's job, and one
                   eleven-item sentence is unreadable on a homepage. Which pool
                   holds what is checked against data/gear.php: the Y has the
                   camping and snow equipment plus helmets and crash pads, the
                   club has the trad racks, the ice tools and the packrafts. */ ?>
          <p>
            Members can rent camping and snow equipment from the Caltech&nbsp;Y for
            about $1 per day. The club lends climbing, ice, and packrafting gear
            directly.
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
          <p><strong>Gear loans and rentals are limited to Caltech and JPL
             affiliates.</strong> Anyone can join the club and come on trips, but other
             members need to bring their own gear.</p>
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
        <h2 class="h2">About the club</h2>
        <div class="prose mt-lg">
          <?php /* Two sentences. The homepage does not need the club's
                   biography -- the founder, the 1950 records and the full
                   activity list are on About, one click away. */ ?>
          <p>
            The Caltech Alpine Club has organized outdoor trips since
            <?= e(cfg('facts.founded')) ?>. Members include students, postdocs,
            faculty, staff, JPL employees, and people from outside Caltech.
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
          Hikes, trail runs, climbing days, and longer trips, organized by officers
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
          Talks on campus by club members and visiting climbers.
        </p>
      </div>
      <div class="pillar">
        <?= icon('social', 'icon icon--lg pillar__icon') ?>
        <h3>Finding partners</h3>
        <p>
          Members use Slack to find partners and organize informal outings between
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
          Sponsorship, donations, and donated equipment pay for club trips, shared
          gear, and the film festivals.
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
