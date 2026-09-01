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
    /* Three words. The two cards below say who joins how, and saying it here
       as well made the deck a summary of the thing directly under it. */
    'lede'   => 'Membership is free.',
    'photo'  => 'photos/dsc03355-pano-copy.jpg',
    'credit' => 'Moonrise in the Sierra Nevada',
)); ?>


<!-- ======================================================== steps ==== -->
<?php
/* ONE CARD PER WAY IN, AND EACH ONE FINISHES THE JOB.

   This page used to answer "how do I join if I am not at Caltech" twice: a
   card headed "Outside Caltech" saying you could join too, whose button jumped
   down to a section headed "Joining from outside Caltech" saying what to
   write. Two headings, two paragraphs and a scroll for a single email. The
   card now carries the instruction and writes the mail, and the second section
   is gone.

   The id stays on the card, because about.php links to join.php#outside and
   the FAQ below links to #outside. Moving the anchor rather than deleting it
   is what keeps those working.

   Each heading is the audience or the thing, so the reader can find their own
   row without reading the others.

   Pre-filled into the mail client, so the note still has to be written by a
   person: that is the whole barrier, easy for a human and useless to anything
   automated. It is deliberately not printed on the page as well -- the card
   says what to write in one sentence, and a "please include" list under it
   would be the same instruction twice. */
$intro = "Hello,\n\n"
       . "I would like to join the Caltech Alpine Club. I am not affiliated with "
       . "Caltech or JPL.\n\n"
       . "Who I am, and where I work or study:\n\n\n"
       . "What I am interested in doing:\n\n\n"
       . "Thanks,\n";
$secretaryMail = 'mailto:' . cfg('links.secretary') . '?'
               . http_build_query(array('subject' => 'Membership request', 'body' => $intro));
?>
<section class="section">
  <div class="wrap">
    <div class="steps">

      <!-- Caltech or JPL ---------------------------------------------- -->
      <div class="step step--primary">
        <h2>Caltech or JPL</h2>
        <p>
          Join the mailing list directly.
        </p>
        <?php /* The VPN caveat stays: it is the reason the button below will
                 fail for somebody sitting at home, and it names the way round
                 that. A fact, not an explanation. */ ?>
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
      <div class="step step--primary" id="outside">
        <h2>Outside Caltech</h2>
        <p>
          Email the secretary with your name, where you're based, and what you'd like
          to do with the club.
        </p>
        <?php /* THE ADDRESS AS TEXT, not only inside the button. A mailto does
                 nothing for anybody reading their mail in a browser tab, and
                 when the address lives only in the href those visitors are left
                 with no way to write at all. This costs one line and removes
                 that failure. */ ?>
        <p class="step__hint">
          <a href="mailto:<?= e(cfg('links.secretary')) ?>"><?= e(cfg('links.secretary')) ?></a>
        </p>
        <div class="step__action">
          <a class="btn btn--primary btn--block" href="<?= e($secretaryMail) ?>">
            <?= icon('mail', 'icon icon--xs') ?> Email the secretary
          </a>
        </div>
      </div>

      <!-- Slack -------------------------------------------------------- -->
      <div class="step">
        <h2>Slack</h2>
        <?php /* "informal outings", not "trips". The club's trips are organized
                 by officers and announced on the calendar; what happens on
                 Slack is members arranging their own. The two read as the same
                 thing if this says "trips", and this is the page where somebody
                 works out which is which. */ ?>
        <p>
          Members use Slack to find partners and plan informal outings. The invite goes
          through the mailing list.
        </p>
        <?php if ($slack): ?>
          <div class="step__action">
            <a class="btn btn--ghost btn--block" href="<?= e(alpine_outbound('slack')) ?>" rel="noopener">
              <?= icon('chat', 'icon icon--xs') ?> Join Slack
            </a>
          </div>
        <?php endif; ?>
        <?php /* No public invite link in config yet, so no button -- there is
                 nothing here for one to do, and the sentence above already says
                 where the invite comes from. A second line telling somebody who
                 missed it to write to the secretary was the third mention of
                 that address on this page. Set links.slack and the real Join
                 Slack button appears. */ ?>
      </div>

      <!-- Come to an event --------------------------------------------- -->
      <div class="step">
        <h2>Come to an event</h2>
        <p>
          Hikes, talks, film screenings, and socials are open to anyone.
        </p>
        <div class="step__action">
          <a class="btn btn--ghost btn--block" href="<?= e(url('events.php')) ?>">
            <?= icon('calendar', 'icon icon--xs') ?> See upcoming events
          </a>
        </div>
      </div>

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
            'a' => '<p>No. Membership is open beyond Caltech and JPL. If you\'re outside '
                 . 'Caltech, <a href="#outside">email the secretary</a> to join the '
                 . 'mailing list.</p>',
        ),
        array(
            'q' => 'Does membership cost anything?',
            'a' => '<p>No. Membership is free.</p>',
        ),
        array(
            'q' => 'Do I need prior experience?',
            'a' => '<p>No. Experience requirements depend on the trip. Check the event '
                 . 'description or ask the trip leader if you\'re unsure.</p>',
        ),
        array(
            'q' => "What if I don't own gear?",
            'a' => '<p>Caltech and JPL affiliates can borrow or rent gear through the club '
                 . 'and the <a href="' . e(url('gear.php')) . '">Caltech Y</a>. Members '
                 . 'outside Caltech and JPL need to bring their own.</p>',
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
