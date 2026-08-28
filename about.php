<?php
/**
 * About: what the club is, and who to ask about what.
 *
 * The roster comes from data/people.csv, data/roles.csv and data/assignments.csv.
 * After an election, edit those — nothing on this page needs touching.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/officers.php';
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/includes/partials.php';

$PAGE = array(
    'title'       => 'About',
    'description' => 'The Caltech Alpine Club: founded in 1987, outdoor '
                   . 'trips and film screenings for the Caltech community. Officers and how '
                   . 'to reach them.',
    'nav'         => 'about.php',
);

/* Current and past officers, split and sorted for us. */
$roster = alpine_officers();

/* WHY THE OPEN JOBS ARE NOT IN THE GRID BELOW.
   -------------------------------------------
   They used to be: an empty role rendered as a card the same size and shape as
   a person's, with "Could be you" where the name goes. It answered the
   recruiting question in the place somebody was most likely to see it, and that
   was the whole argument for it.

   It was still wrong, for a reason the argument does not reach. This grid
   answers one question -- who runs the club, and how do I write to them -- and
   a card that impersonates an officer without being one makes the reader check
   each card to find out which kind it is. Actual people should look like
   people. An opening is not a person and should not borrow their shape.

   So the grid is people, and the invitation is one deliberate line underneath
   it, which is where somebody who has finished reading the roster arrives
   anyway. What each open job involves belongs on Get Involved, and that page
   now carries it properly. */
$asking = alpine_roles_asking();

require __DIR__ . '/includes/header.php';
?>

<?php alpine_page_hero(array(
    'title'  => 'About the Alpine Club',
    /* Not "for the Caltech community": membership is open to anyone, which
       this site says on four other pages. Gear and the film festivals are two
       of the three things the club actually does, so both are named. */
    'lede'   => 'The Caltech Alpine Club organizes outdoor trips, lends gear, and hosts '
              . 'mountain film screenings.',
    'photo'  => 'photos/cac-mammoth-from-sujung.jpg',
    'credit' => 'Club trip to Mammoth',
)); ?>


<!-- ========================================================= what ==== -->
<section class="section" id="what">
  <div class="wrap">
    <div class="split split--wide-left">
      <div>
        <?php /* The nav calls this anchor "What we do". The heading says the
                 same words, so somebody arriving from the menu lands on the
                 thing they clicked. */ ?>
        <h2 class="h2">What we do</h2>
        <div class="prose mt-lg">
          <p>
            The club organizes hiking, backpacking, trail running, climbing, and other
            trips. Members can also borrow outdoor equipment. Activities vary from year
            to year, so check the
            <a href="<?= e(url('events.php')) ?>">calendar</a> for what is happening now.
          </p>
          <p>
            The club hosts the Banff Mountain Film Festival on campus each year, along
            with other mountain film screenings and talks by members and visiting
            climbers.
          </p>
          <p>
            Members include students, postdocs, faculty, staff, JPL employees, and
            people from outside Caltech. Experience ranges from first-time hikers to
            experienced climbers and mountaineers.
          </p>
          <?php /* ONE founding narrative across the site: the club was founded in
                   1987, and that is what the hero and the meta description both say.
                   The 1950 material is older and less certain, so it appears exactly
                   once, here, hedged and subordinate. Do not promote it to a headline
                   number. */ ?>
          <p>
            The club was founded in <?= e(cfg('facts.founded')) ?> by
            <?= e(cfg('facts.founder')) ?>, though club records suggest Caltech outdoor
            trips go back to around 1950.
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
      <?php /* When the club is asking for people there is a proper callout under
               the grid, and two links to the same page in one section is one
               link too many. This is the fallback for the year everything is
               filled, so the page never becomes a dead end. */ ?>
      <?php if (!$asking): ?>
        <a class="arrow-link" href="<?= e(url('roles.php')) ?>">
          What each of these jobs involves <?= icon('arrow-right', 'icon icon--xs') ?>
        </a>
      <?php endif; ?>
    </div>

    <?php if (!$roster['current']): ?>
      <div class="empty-state">
        <h3>The roster is being updated</h3>
        <p>Officers are listed in <code>data/assignments.csv</code>.</p>
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

                <?php /* Name, email and photo are the PERSON's, from data/people.csv.
                         The title and the "write to them about" line are the JOB's,
                         from data/roles.csv. Neither is written twice anywhere, so
                         two people sharing a job cannot end up describing it
                         differently, and changing an address is one edit. */ ?>
                <div class="officer__name"><?= e($o['name']) ?></div>
                <div class="officer__role"><?= e($o['title']) ?></div>

                <?php if ($o['contact_for'] !== ''): ?>
                  <p class="officer__handles"><?= e($o['contact_for']) ?></p>
                <?php endif; ?>

                <?php if ($o['email'] !== ''): ?>
                  <a class="officer__mail" href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>

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

      <?php /* THE INVITATION, and the only thing on this page about jobs
               nobody is doing. It renders nothing at all when every job has
               the people it needs -- there is no "no vacancies at this time"
               placeholder, because a block that exists only to say it has
               nothing to say is a block that will one day say it wrongly.

               It does not name the jobs. Get Involved describes each one
               properly, and a list here would be a second copy to read past
               on the way to the thing this page is actually for.

               WHAT IT MAY NOT SAY: who is eligible. The club has never written
               that down -- there is no constitution, no bylaws and no officer
               handbook in this repository or on the old site, both searched in
               August 2026 -- so no sentence here invents one. What is known to
               be true is that officers are members who volunteered, and that
               is the sentence that invites somebody in anyway. */ ?>
      <?php if ($asking): ?>
        <aside class="join-callout mt-lg">
          <div class="join-callout__text">
            <h3 class="join-callout__title">Want to help run the Alpine Club?</h3>
            <?php /* ONE SENTENCE, and no count. What the jobs need from a
                     person, and what happens after you say you are interested,
                     are both on Get Involved. The count was derived rather than
                     typed, so it could not go stale, but it was still a number
                     the reader has to hold on the way to a button that shows
                     them the list. */ ?>
            <p>
              Some officer and coordinator roles are currently open.
            </p>
          </div>
          <a class="btn btn--ghost" href="<?= e(url('roles.php#open')) ?>">
            See open roles <?= icon('arrow-right', 'icon icon--xs') ?>
          </a>
        </aside>
      <?php endif; ?>

    <?php endif; ?>

    <?php /* Past officers. Nobody maintains this list — an officer gets an
             'until' year in data/assignments.csv and appears here by itself. */ ?>
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
                  <span class="alumni__role"><?= e($o['title']) ?></span>
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
        <dt>General questions</dt>
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
        <dt>Activity questions</dt>
        <dd>
          <a href="#officers">Find the officer who looks after it</a>
        </dd>
      </div>

    </dl>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
