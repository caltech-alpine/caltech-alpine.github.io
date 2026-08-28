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
    'lede'   => 'Membership is free. Join the mailing list to get started.',
    'photo'  => 'photos/dsc03355-pano-copy.jpg',
    'credit' => 'Moonrise in the Sierra Nevada',
)); ?>


<!-- ======================================================== steps ==== -->
<section class="section">
  <div class="wrap">
    <div class="steps">

      <?php /* STEP 1 IS A FORK, NOT TWO STEPS. The sign-up page is behind the
               Caltech network, so affiliates self-serve and everyone else is added
               by hand — same destination, same membership, different door. They are
               numbered 1a and 1b so nobody works through both, and they sit side by
               side so a non-affiliate never reads the affiliate card and concludes
               the club is closed to them. */ ?>

      <?php /* THE HEADING IS THE AUDIENCE, NOT THE ACTION. The reader's question
               here is "which one is me?", so that has to be the biggest text in
               the card. The shared eyebrow above it — identical on both — is what
               says these are one step with two doors; when the two headings read
               "Join the mailing list" and "Ask to be added" they scanned as two
               unrelated asks, which is the opposite of the point. */ ?>

      <!-- 1a ----------------------------------------------------------- -->
      <div class="step step--primary step--fork">
        <div class="step__num">1a</div>
        <p class="step__same">Join the mailing list</p>
        <h2>If you are Caltech or JPL</h2>
        <p>
          Sign up on the list yourself. It carries trips, film festivals, socials,
          and club elections.
        </p>
        <p class="step__hint">
          <?= e(cfg('links.mailing_list_note')) ?> Off campus, email
          <a href="mailto:<?= e(cfg('links.secretary')) ?>?subject=Mailing+list"><?=
            e(cfg('links.secretary')) ?></a>.
        </p>
        <div class="step__action">
          <a class="btn btn--primary btn--block" href="<?= e(alpine_outbound('mailing_list')) ?>" rel="noopener">
            <?= icon('mail', 'icon icon--xs') ?> Sign up directly
          </a>
        </div>
      </div>

      <!-- 1b ----------------------------------------------------------- -->
      <div class="step step--primary step--fork">
        <div class="step__num">1b</div>
        <p class="step__same">Join the mailing list</p>
        <h2>If you are not</h2>
        <p>
          Email the secretary and they will add you to the same list. Caltech
          affiliation is not required to join.
        </p>
        <?php /* No explanation of WHY this route exists. An earlier draft asserted
                 the sign-up page was network-restricted; it is a gate check on the
                 list, not a network one. Do not describe a mechanism here unless
                 someone has actually confirmed it — the card works without it. */ ?>
        <div class="step__action">
          <?php /* An in-page jump, not an action, so it takes the chevron rather
                   than the mail icon 1a uses for its outbound link. */ ?>
          <a class="btn btn--primary btn--block" href="#outside">
            <?= icon('chevron-down', 'icon icon--xs') ?> See instructions below
          </a>
        </div>
      </div>

      <!-- 2 ------------------------------------------------------------ -->
      <div class="step">
        <div class="step__num">2</div>
        <p class="step__who">Optional</p>
        <h2>Join Slack</h2>
        <p>
          Not required, but it is where most of the week to week happens: finding
          partners, asking questions, and organizing informal outings, with a separate
          channel per activity.
        </p>
        <?php if ($slack): ?>
          <div class="step__action">
            <a class="btn btn--ghost btn--block" href="<?= e(alpine_outbound('slack')) ?>" rel="noopener">
              <?= icon('chat', 'icon icon--xs') ?> Join Slack
            </a>
          </div>
        <?php else: ?>
          <?php /* No public invite link in config yet. The card says where the
                   invite comes from and stops there — no button, because there is
                   nothing here for one to do. Set links.slack and the real Join
                   Slack button above appears. */ ?>
          <p class="step__hint">
            The invite link goes out on the mailing list. If you have missed it, ask
            the secretary at
            <a href="mailto:<?= e(cfg('links.secretary')) ?>?subject=Slack+invite"><?=
              e(cfg('links.secretary')) ?></a>.
          </p>
        <?php endif; ?>
      </div>

      <!-- 3 ------------------------------------------------------------ -->
      <div class="step">
        <div class="step__num">3</div>
        <p class="step__who">Any time</p>
        <h2>Attend an event</h2>
        <p>
          Many club events are open to people without prior experience or club
          involvement, including hikes, talks, film screenings, and socials.
        </p>
        <div class="step__action">
          <a class="btn btn--ghost btn--block" href="<?= e(url('events.php')) ?>">
            <?= icon('calendar', 'icon icon--xs') ?> View upcoming events
          </a>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ================================================ not affiliated ==== -->
<section class="section section--tint" id="outside">
  <div class="wrap wrap--narrow">

    <p class="eyebrow"><?= icon('social', 'icon icon--xs') ?>No Caltech or JPL affiliation</p>
    <h2 class="h2">Joining from outside Caltech</h2>

    <div class="prose mt-lg">
      <p>
        You are welcome in the club. Introduce yourself by email and an officer
        adds you to the mailing list.
      </p>
      <p>
        Write from an email address that identifies you — a work or university
        address is ideal. A couple of sentences on each point below is plenty:
        this is not a screening process, it is how we know a real person is
        asking, and it tells whoever replies which activities to point you at.
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
           . "What I would like to get out of the club:\n\n\n"
           . "How I came across the club:\n\n\n"
           . "Thanks,\n";
    ?>
    <?php alpine_write_to(cfg('links.secretary'), 'Membership request', array(
        'Who you are, and where you work or study',
        'What you would like to get out of the club',
        'How you came across the club',
    ), array('body' => $intro)); ?>

    <div class="note mt-lg">
      <?= icon('gear', 'icon icon--xs') ?>
      <p>
        One limit worth knowing before you write: <strong>club and Caltech Y equipment
        both require Caltech or JPL affiliation</strong>. Everything else — trips,
        events, Slack — is open to you.
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
            'a' => '<p>No. Caltech affiliation is not required to join. Most members are '
                 . 'graduate students, undergraduates, postdocs, faculty, staff, and JPL '
                 . 'employees, but community members are welcome. If that is you, see '
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
                 . 'assume no experience. Climbing and snow trips vary, and trip organizers '
                 . 'state the experience and fitness a given trip requires.</p>',
        ),
        array(
            'q' => 'What if I do not own any gear?',
            'a' => '<p>Specialist club gear is borrowed from the club; general kit is '
                 . 'rented from the Caltech Y for about $1 a day. <strong>Both need '
                 . 'Caltech or JPL affiliation</strong>, so non-affiliates are welcome '
                 . 'on trips but need their own kit. See '
                 . '<a href="' . e(url('gear.php')) . '">what is available</a>.</p>',
        ),
        array(
            'q' => 'How do I find people to go with?',
            'a' => '<p>Most coordination happens on Slack: post what you want to do and when. '
                 . 'The club\'s activity leaders also organize outings that members can join — '
                 . 'see <a href="' . e(url('about.php#officers')) . '">who currently leads '
                 . 'what</a>.</p>',
        ),
        array(
            'q' => 'How does the club handle safety?',
            'a' => '<p>Trip organizers state the experience, fitness, and equipment a trip '
                 . 'requires before people sign up, and deciding a trip is not for you is a '
                 . 'normal outcome. Avalanche beacons, shovels, probes, helmets, and satellite '
                 . 'messengers are all available from the club or the Caltech Y. The club follows and teaches Leave '
                 . 'No Trace practice.</p>',
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
