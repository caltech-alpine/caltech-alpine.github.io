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
      <?php /* THE MARK SAYS THE NAME, SO NOTHING HERE SAYS IT TWICE.

               Three earlier versions of this hero got it wrong in three
               different ways: a slogan ("Less lab. More mountains."), which
               the club president disliked and which named nobody; the identity
               line alone, which read well but left the club's name to the
               masthead and the tab title; and then the name set at --step-5 as
               live text, which named the club but stacked FOUR typographic
               levels -- eyebrow, name, sentence, buttons -- in front of a
               visitor who wanted one of the buttons.

               The horizontal logo is now the first thing the eye meets and it
               already reads "CALTECH ALPINE CLUB", so the level-one heading is
               .sr-only: present for the document outline, for search results
               and for heading navigation, printed nowhere. The <img> is
               DECORATIVE -- alt="" -- because the hidden h1 is already its
               accessible name, and giving it alt text as well would announce
               the club twice to a screen reader.

               "Pasadena, California · Founded 1987" used to sit above all of
               this. It is gone from the hero: the founding year is a
               historical fact, it is already in the About band further down
               this same page, and it was costing a whole typographic level
               before the visitor reached the club's name.

               ONE description, not two. There is no second introductory
               sentence stacked under this: the deck below is the only one, and
               the paragraph that used to sit under the buttons is gone. */ ?>
      <h1 class="display hero__title">Caltech Alpine Club</h1>

      <p class="hero__text">
        Outdoor adventures for Caltech, JPL, and the extended Caltech community.
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
<?php /* OFFICER RECRUITMENT. FIVE THINGS, IN FIVE LINES, IN THIS ORDER.

         A Caltech student who has never seen this site has to get all of it at
         a glance: these are officer positions · they are open now · the club
         wants Caltech students for them · a role name will explain itself ·
         there are more roles than these. Each line does exactly one of those,
         and none of them is a paragraph. The role descriptions belong on Get
         Involved; this block advertises the vacancies and nothing else.

         WHY THE HEADING IS NOT "Help run the club" (2026-08-31, second
         attempt). That heading, and "Get involved" before it, both name a
         category far wider than what is underneath: helping run the club could
         mean driving people to a trailhead, working a film-festival door, or
         donating a tent. A reader then meets two bare role names with no frame
         to hang them on. "Join the officer team" names the thing, so the names
         under it read as posts on that team.

         THE ONE DISTINCTION THIS BLOCK EXISTS TO KEEP: the CLUB is open to
         Caltech, JPL and the wider Caltech community -- which the hero says
         four inches above -- while the OFFICER POSITIONS are held by Caltech
         students. It is carried by PLACEMENT: "Caltech students" appears in
         this block and nowhere else on the page, so nothing here can be read
         as narrowing who may join the club. DO NOT add a paragraph explaining
         the difference, and do not put "Caltech students" into any general
         membership sentence elsewhere.

         THE COUNT IS DERIVED, NEVER TYPED. count($short) is the number of
         roles listed directly below it, so the sentence and the list cannot
         disagree -- which is the failure a hard-coded "two" produces the day
         somebody fills one. Same rule as every other claim on this site: a
         number a human has to re-assert is a number that goes stale.

         $short is alpine_roles_needed(), not alpine_roles_asking(): a job with
         one of its two presidents is not an OPEN position, so it is offered on
         Get Involved and never counted here. The link target is the role_id,
         so renaming a job does not break it.

         When every seat is filled this renders nothing at all -- including the
         eligibility line, which is correct: there is no claim about who may be
         an officer on a page that is not asking for one. That is the property
         that matters when nobody has touched the site for a year: the failure
         mode is silence, not a stale banner. */ ?>
<?php if ($short): ?>
<div class="wanted-strip">
  <div class="wrap wanted-strip__inner">
    <div class="wanted-strip__text">
      <p class="wanted-strip__title">Join the officer team</p>
      <p class="wanted-strip__who">
        <?php /* "position" / "positions" is the one word here that is not
                 fixed copy, and it has to follow the count or the sentence is
                 ungrammatical the month only one seat is open. */ ?>
        We have <?= count($short) ?> open officer
        position<?= count($short) === 1 ? '' : 's' ?> for Caltech students.
      </p>
      <ul class="wanted-strip__roles">
        <?php foreach ($short as $r): ?>
          <li class="wanted-strip__role">
            <?php /* THE SAME TITLE GET INVOLVED AND ABOUT USE (Kyle, 2026-09-02),
                     which means the holder count and not min_people. This passed
                     min, so a President with one of two seats taken was
                     "Co-President" here and "President" on the two pages a
                     reader reaches by clicking it -- three pages, two names, one
                     job. Defensible in isolation (the seat on offer IS a
                     co-presidency) and wrong across the site, which is the only
                     scale a name is read at. alpine_role_title()'s default
                     already counts holders; the argument is gone rather than
                     changed, so there is nothing here to keep in step. */ ?>
            <a href="<?= e(url('roles.php#' . $r['role_id'])) ?>"><?=
              e(alpine_role_title($r)) ?></a>
            <?php /* The seat count, from the same function Get Involved uses,
                     so the two pages cannot describe one role differently. */ ?>
            <span class="wanted-strip__count"><?= e(alpine_role_status_line($r)) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <a class="arrow-link" href="<?= e(url('roles.php')) ?>">
      See all officer roles <?= icon('arrow-right', 'icon icon--xs') ?>
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
        <?php /* THE BADGE STAYS ON THIS BUTTON. It came off on 2026-08-31 as a
                 duplicate of the note beside it and went back the same day
                 (Kyle). The rule belongs on the control, not only in the prose
                 near it: somebody who reads the buttons and nothing else is
                 exactly the person the rule is for. Same decision as the two
                 booking buttons on gear.php. */ ?>
        <div class="btn-row mt-lg">
          <a class="btn btn--primary btn--gated" href="<?= e(url('gear.php#rental')) ?>">
            <span class="btn__label">Borrow gear</span>
            <span class="btn__gate">Caltech and JPL affiliates only</span>
          </a>
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

    <h2 class="h2">About the club</h2>
    <div class="prose mt-lg">
      <?php /* Two sentences. The homepage does not need the club's biography --
               the founder, the 1950 records and the full activity list are on
               About, one click away. */ ?>
      <p>
        The Caltech Alpine Club has organized outdoor trips since
        <?= e(cfg('facts.founded')) ?>. Members include students, postdocs,
        faculty, staff, JPL employees, and people from outside Caltech.
      </p>
    </div>

    <?php /* THE MISSION STATEMENT IS ON ABOUT, ONCE. It was set as a pull
             quote in a second column here as well, so the same sentence met
             a visitor twice on a five-page site. It belongs on the page whose
             job is saying what the club is, and this section's job is getting
             somebody to that page. Removing it left this block one column
             wide, which is what it always was: one paragraph and three cards.
             The split wrapper went with the quote rather than being filled
             with something. */ ?>

    <?php /* THREE CARDS, AND THEY ARE ONE LIST: the things the club ORGANIZES.
             There was a fourth, "Finding partners", about members using Slack to
             arrange their own outings. It broke the list twice over -- it is a
             membership resource rather than something the club runs, and saying
             members "organize informal trips" beside a card about officer-led
             trips blurs the one distinction on this page worth keeping. It was
             also the third place on the homepage to mention the mailing list.
             That information now lives where somebody looks for it: the Slack
             step on join.php, and the membership line in the closing section.

             Parallel by construction -- each card is what the club puts on, in
             one sentence, with no history in one and logistics in another. The
             grid is auto-fit, so three lay out as three; nothing here counts to
             four. */ ?>
    <div class="pillars mt-lg">
      <div class="pillar">
        <?= icon('hike', 'icon icon--lg pillar__icon') ?>
        <h3>Trips</h3>
        <p>
          Hikes, trail runs, climbing days, and longer trips organized by club
          officers throughout the year.
        </p>
      </div>
      <div class="pillar">
        <?= icon('film', 'icon icon--lg pillar__icon') ?>
        <h3>Film festivals</h3>
        <p>
          The club has hosted the Banff Mountain Film Festival at Caltech since
          <?= e(cfg('facts.banff_since')) ?>, along with other mountain and
          adventure film screenings.
        </p>
      </div>
      <div class="pillar">
        <?= icon('talk', 'icon icon--lg pillar__icon') ?>
        <h3>Talks</h3>
        <p>
          Talks and presentations by club members, visiting climbers, and others
          from the outdoor community.
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
