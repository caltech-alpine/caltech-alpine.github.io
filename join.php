<?php
/**
 * Join.
 *
 * Actions first, explanation second. Someone who has already decided should be
 * able to join without reading the rest of the page.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/benefits.php';
require_once __DIR__ . '/includes/partials.php';

$PAGE = array(
    'title'       => 'Join',
    'description' => 'How to join the Caltech Alpine Club: join the mailing list, join Slack, '
                   . 'and attend an event. Membership is free and Caltech affiliation is not '
                   . 'required.',
    'nav'         => 'join.php',
);

$slack   = cfg('links.slack');
$mailing = cfg('links.mailing_list');

require __DIR__ . '/includes/header.php';
?>

<?php alpine_page_hero(array(
    'title'  => 'Join the Alpine Club',
    'lede'   => 'Membership is free and open to people at and outside Caltech. The '
              . 'mailing list is the best place to start.',
    'photo'  => 'photos/dsc03355-pano-copy.jpg',
    'credit' => 'Moonrise in the Sierra Nevada',
)); ?>


<!-- ======================================================== steps ==== -->
<?php /* FOUR CARDS, NOT A NUMBERED PROCESS. These were 1a, 1b, 2 and 3, which
         drew a workflow diagram over something that is one email long: joining
         Slack and turning up to a hike are not steps two and three of joining,
         they are other things you can do. The two mailing-list cards still sit
         side by side, because a non-affiliate who reads only the first one
         would conclude the club is closed to them.

         Each heading is the audience or the thing, so the reader can find their
         own row without reading the others. */ ?>
<section class="section">
  <div class="wrap">
    <div class="steps">

      <!-- Caltech and JPL --------------------------------------------- -->
      <div class="step step--primary">
        <h2>Caltech and JPL</h2>
        <p>
          Join the mailing list directly. It carries trip announcements, events,
          film festivals, and club elections.
        </p>
        <p class="step__hint">
          <?= e(cfg('links.mailing_list_note')) ?> Off campus, email
          <a href="mailto:<?= e(cfg('links.secretary')) ?>?subject=Mailing+list"><?=
            e(cfg('links.secretary')) ?></a>.
        </p>
        <div class="step__action">
          <a class="btn btn--primary btn--block" href="<?= e(alpine_outbound('mailing_list')) ?>" rel="noopener">
            <?= icon('mail', 'icon icon--xs') ?> Join the mailing list
          </a>
        </div>
      </div>

      <!-- Outside Caltech --------------------------------------------- -->
      <div class="step step--primary">
        <h2>Outside Caltech</h2>
        <p>
          You can join too. Email the secretary and we will add you to the same
          mailing list.
        </p>
        <div class="step__action">
          <?php /* An in-page jump, not an action, so it takes the chevron rather
                   than the mail icon the card above uses for its outbound link. */ ?>
          <a class="btn btn--primary btn--block" href="#outside">
            <?= icon('chevron-down', 'icon icon--xs') ?> What to write
          </a>
        </div>
      </div>

      <!-- Slack -------------------------------------------------------- -->
      <div class="step">
        <h2>Slack</h2>
        <?php /* "their own informal outings", not "informal trips". The club's
                 trips are organized by officers and announced on the calendar;
                 what happens on Slack is members arranging their own. The two
                 read as the same thing if this says "trips", and this is the
                 page where somebody works out which is which. */ ?>
        <p>
          Slack is where members find climbing, hiking, and running partners and
          plan their own informal outings, with a separate channel per activity.
          The invite goes out on the mailing list, so join that first.
        </p>
        <?php if ($slack): ?>
          <div class="step__action">
            <a class="btn btn--ghost btn--block" href="<?= e(alpine_outbound('slack')) ?>" rel="noopener">
              <?= icon('chat', 'icon icon--xs') ?> Join Slack
            </a>
          </div>
        <?php else: ?>
          <?php /* No public invite link in config yet. The card says where the
                   invite comes from and stops there -- no button, because there
                   is nothing here for one to do. Set links.slack and the real
                   Join Slack button above appears. */ ?>
          <p class="step__hint">
            If you have missed it, ask the secretary at
            <a href="mailto:<?= e(cfg('links.secretary')) ?>?subject=Slack+invite"><?=
              e(cfg('links.secretary')) ?></a>.
          </p>
        <?php endif; ?>
      </div>

      <!-- Come to an event --------------------------------------------- -->
      <div class="step">
        <h2>Come to an event</h2>
        <p>
          Hikes, talks, film screenings, and socials are open to anyone, member or
          not.
        </p>
        <div class="step__action">
          <a class="btn btn--ghost btn--block" href="<?= e(url('events.php')) ?>">
            <?= icon('calendar', 'icon icon--xs') ?> Upcoming events
          </a>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ================================================ not affiliated ==== -->
<section class="section section--tint" id="outside">
  <div class="wrap wrap--narrow">

    <h2 class="h2">Joining from outside Caltech</h2>

    <?php /* NOT AN APPLICATION, and it must not read like one. An earlier draft
             insisted "this is not a screening process, it is how we know a real
             person is asking", which announces a filter while denying there is
             one. Two things are actually needed: an address a person can reply
             to, and enough to point whoever answers at the right activity.
             "How you came across the club" was a third, and nothing was ever
             done with the answer. */ ?>
    <div class="prose mt-lg">
      <p>
        Email the secretary with your name, where you are based, and what you would
        like to do with the club. A work or university address helps.
      </p>
    </div>

    <?php
    /* Shown on the page AND pre-filled into the mail client, so the note still
       has to be written by a person. That is the whole barrier: easy for a
       human, useless to anything automated. */
    $intro = "Hello,\n\n"
           . "I would like to join the Caltech Alpine Club. I am not affiliated with "
           . "Caltech or JPL.\n\n"
           . "Who I am, and where I work or study:\n\n\n"
           . "What I am interested in doing:\n\n\n"
           . "Thanks,\n";
    ?>
    <?php /* No "Please include" list: the sentence above it is the
             instruction, and the same two prompts are already pre-filled into
             the mail client by $intro. This block is the address and the
             subject line. */ ?>
    <?php alpine_write_to(cfg('links.secretary'), 'Membership request', array(),
        array('body' => $intro)); ?>

    <div class="note mt-lg">
      <?= icon('gear', 'icon icon--xs') ?>
      <p>
        <strong>Gear loans and rentals are limited to Caltech and JPL
        affiliates.</strong> Other members can still join trips, events, and Slack, but
        need to bring their own gear.
      </p>
    </div>

  </div>
</section>


<!-- ===================================================== benefits ==== -->
<?php /* Renders only when data/benefits.csv has something in it, which today
         it does not. Same switch as the sponsor row on the homepage: the
         structure is here so adding a deal is one line in a CSV rather than a
         new page, and an empty file costs the visitor no height at all.

         Read includes/benefits.php before adding anything. Some deals may not
         be advertised publicly, and the 'members' setting is what keeps one of
         those off the page entirely. */ ?>
<?php if (alpine_has_benefits()): ?>
  <?php $benefits = alpine_benefits(); $ask = alpine_benefits_contact(); ?>
<section class="section section--tight" id="benefits">
  <div class="wrap wrap--narrow">
    <h2 class="h2">What membership gets you</h2>

    <?php if ($benefits['public']): ?>
      <dl class="contact-list mt-lg">
        <?php foreach ($benefits['public'] as $b): ?>
          <div>
            <dt>
              <?php if (!empty($b['url'])): ?>
                <a href="<?= e($b['url']) ?>" rel="noopener"><?= e($b['benefit']) ?></a>
              <?php else: ?>
                <?= e($b['benefit']) ?>
              <?php endif; ?>
            </dt>
            <dd><?= e($b['what']) ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
    <?php endif; ?>

    <?php /* Deliberately says nothing about what these are or who they are
             with. A sponsor can require that a member rate is not published,
             and this line is what lets the club honour that and still tell
             members the deals exist. */ ?>
    <?php if ($benefits['restricted'] > 0): ?>
      <div class="note mt-lg">
        <?= icon('heart', 'icon icon--xs') ?>
        <p>
          Some member discounts cannot be listed publicly. Ask
          <a href="mailto:<?= e($ask['email']) ?>"><?= e($ask['email']) ?></a><?php
            if ($ask['name'] !== '') { echo ' (' . e($ask['name']) . ')'; } ?>,
          or watch the mailing list.
        </p>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>


<!-- ==================================================== questions ==== -->
<section class="section">
  <div class="wrap wrap--narrow">

    <div class="section-head">
      <div class="section-head__text">
        <p class="eyebrow">Questions</p>
        <h2 class="h2">Frequently asked questions</h2>
      </div>
    </div>

    <?php
    /* Answers are written into the page so they work without JavaScript and can
       be found with Ctrl+F. site.js collapses them afterwards. */
    $faqs = array(
        array(
            'q' => 'Do I have to be a Caltech student?',
            'a' => '<p>No. Membership is open to people outside Caltech. See '
                 . '<a href="#outside">joining from outside Caltech</a>.</p>',
        ),
        array(
            'q' => 'Does membership cost anything?',
            'a' => '<p>No. Individual activities may have costs, such as film festival tickets '
                 . 'or a share of transport. Caltech Y gear costs about $1 per day.</p>',
        ),
        array(
            'q' => 'Do I need prior experience?',
            'a' => '<p>Not for most club events. Hikes, socials, talks, and film screenings '
                 . 'assume no experience. Climbing and snow trips vary.</p>',
        ),
        array(
            'q' => 'What if I do not own any gear?',
            'a' => '<p>The Caltech Y rents general gear for about $1 a day, and the club '
                 . 'lends specialist climbing, ice, and packrafting gear. <strong>Both '
                 . 'need Caltech or JPL affiliation</strong>, so other members are welcome '
                 . 'on trips but bring their own. See '
                 . '<a href="' . e(url('gear.php')) . '">what is available</a>.</p>',
        ),
        array(
            'q' => 'How does the club handle safety?',
            /* CONCRETE PRACTICES ONLY. The question invites an institutional
               claim, and there is no written safety policy in this repository
               or on the old site to base one on. What went: "deciding a trip is
               not for you is a normal outcome" (reassurance about a worry the
               reader had not raised) and "the club follows and teaches Leave No
               Trace practice" (unverified -- the old site published a Leave No
               Trace guide, which is not the same claim). Put either back only
               when an officer confirms it. */
            'a' => '<p>Each trip organizer states the experience, fitness, and equipment '
                 . 'their trip requires. Avalanche beacons, shovels, probes, helmets, and '
                 . 'satellite messengers are available from the club or the Caltech Y. For a '
                 . 'specific trip, ask the organizer.</p>',
        ),
    );
    ?>

    <div class="faq">
      <?php foreach ($faqs as $i => $faq): ?>
        <div class="faq__item">
          <button class="faq__q" type="button" aria-controls="faq-<?= $i ?>" aria-expanded="true">
            <span><?= e($faq['q']) ?></span>
            <?= icon('chevron-down', 'icon icon--xs') ?>
          </button>
          <div class="faq__a" id="faq-<?= $i ?>"><?= $faq['a'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="note mt-lg">
      <?= icon('mail', 'icon icon--xs') ?>
      <p>
        For anything not covered here, contact the officers at
        <a href="mailto:<?= e(cfg('links.officers')) ?>"><?= e(cfg('links.officers')) ?></a>.
      </p>
    </div>

  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
