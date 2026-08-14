<?php
/**
 * Gear: what the club lends, and how to book it.
 *
 * Keep this page accurate. A wrong rental price, notice period or eligibility
 * rule is worse than no page at all.
 */

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/officers.php';
require __DIR__ . '/includes/partials.php';

$PAGE = array(
    'title'       => 'Gear',
    'description' => 'Borrow club tents, crampons, avalanche equipment, touring skis, and '
                   . 'satellite messengers from the Caltech Alpine Club. Equipment is '
                   . 'available to Caltech and JPL affiliates.',
    'nav'         => 'gear.php',
);

$gear = alpine_data('gear');

/* Who currently holds the role, if anyone. Every use below degrades on its
   own: no officer listed -> we just say "the Gear Officer" and link to the
   roster; officer listed but no email -> we fall back to the shared officers
   mailbox. Nothing here can produce a dead link or a blank name. */
$gearOfficer = alpine_officer_for('Gear Officer');
$gearEmail   = ($gearOfficer && !empty($gearOfficer['email']))
             ? $gearOfficer['email']
             : cfg('links.officers');

require __DIR__ . '/includes/header.php';
?>

<header class="page-hero">
  <div class="topo"></div>
  <div class="wrap page-hero__inner">
    <p class="eyebrow"><?= icon('gear', 'icon icon--xs') ?>Equipment</p>
    <h1 class="h1">Gear</h1>
    <p class="lede">
      Specialist club equipment members can borrow, plus general kit rented
      through the Caltech Y.
    </p>
  </div>
</header>


<!-- ======================================================= rental ==== -->
<section class="section" id="rental">
  <div class="wrap">
    <div class="split split--wide-left">
      <div>
        <h2 class="h2">Renting and borrowing</h2>
        <?php /* THE ELIGIBILITY RULE COVERS BOTH POOLS, and it is the one fact on
                 this page that decides whether any of the rest applies to you. It
                 was a sentence inside a paragraph, which is where a scanning
                 reader misses it, so it is a callout — and it is repeated on both
                 booking buttons below, because the people who need it most are
                 the ones who read only the buttons. Club MEMBERSHIP is open to
                 anyone; that is the distinction worth keeping straight. */ ?>
        <div class="gate mt-lg">
          <p class="gate__headline">
            Equipment is for Caltech and JPL affiliates only.
          </p>
          <p class="gate__detail">
            That covers both the Caltech Y's general kit and the club's own specialist
            gear. Club membership is open to anyone — non-affiliates are welcome in the
            club, on trips, and at events, but need to bring their own equipment.
          </p>
        </div>

        <div class="prose mt-lg">
          <h3>General kit — rented from the Caltech Y</h3>
          <p>
            Tents, sleeping bags, stoves, snowshoes, and the rest are booked through the
            <strong>Caltech Y</strong>, which handles pickup and return during business
            hours. Most items cost about $1 per day.
          </p>
        </div>
        <div class="btn-row mt-lg">
          <a class="btn btn--primary btn--gated" href="<?= e(cfg('links.gear_rental')) ?>" rel="noopener">
            <span class="btn__label">Rent from the Caltech Y <?= icon('external', 'icon icon--xs') ?></span>
            <span class="btn__gate">Caltech / JPL only</span>
          </a>
        </div>

        <div class="prose mt-lg">
          <h3>Specialist gear — borrowed from the club</h3>
          <p>
            <?php /* Name and address inline: "the Gear Officer" alone does not tell
                     you who will answer, and an address alone does not tell you who
                     they are. Degrades in two steps — no email drops the brackets,
                     no officer at all drops the name and leaves the role linked to
                     the roster. */ ?>
            Rock, ice, and packrafting equipment belongs to the club rather than the Y,
            and members borrow it. Submit the reservation form and our
            <a href="<?= e(url('about.php#officers')) ?>">Gear Officer</a><?php
              if ($gearOfficer):
                echo ', ' . e($gearOfficer['name']);
                if (!empty($gearOfficer['email'])):
                  ?> (<a href="mailto:<?= e($gearOfficer['email']) ?>?subject=Specialist+gear"><?= e($gearOfficer['email']) ?></a>)<?php
                endif;
              endif;
            ?> will confirm it.
          </p>
        </div>
        <?php if (cfg('links.gear_form')): ?>
          <div class="btn-row mt-lg">
            <a class="btn btn--primary btn--gated" href="<?= e(cfg('links.gear_form')) ?>" rel="noopener">
              <span class="btn__label">Borrow from the club <?= icon('external', 'icon icon--xs') ?></span>
              <span class="btn__gate">Caltech / JPL only</span>
            </a>
          </div>
        <?php endif; ?>

        <div class="btn-row mt-lg">
          <a class="btn btn--ghost" href="<?= e(url('support.php#donate')) ?>">
            <?= icon('heart', 'icon icon--xs') ?> Donate gear
          </a>
        </div>
      </div>

      <div class="stack">
        <div class="note">
          <?= icon('clock', 'icon icon--xs') ?>
          <p><strong>Requests require at least 48 hours notice.</strong> Equipment is not
             kept on site for immediate collection, and popular items are often booked
             out before long weekends.</p>
        </div>

        <div class="note">
          <?= icon('heart', 'icon icon--xs') ?>
          <p>You provide your own consumables, including batteries, fuel, and ski
             wax, and are responsible for returning equipment in working order. inReach
             devices charge for features beyond basic SOS and preset messages, and any
             backcountry rescue is charged to whoever triggers it.</p>
        </div>
      </div>
    </div>

    <?php /* The inventory itself lives in data/gear.php so it can be corrected
             without touching this page. */ ?>
    <?php if ($gear): ?>
      <div class="inventory mt-lg">
        <?php foreach ($gear as $source): ?>
          <div class="inventory__source">
            <h3 class="inventory__title"><?= e($source['title']) ?></h3>
            <p class="inventory__blurb"><?= e($source['blurb']) ?></p>

            <?php foreach ($source['groups'] as $groupName => $items): ?>
              <h4 class="inventory__group"><?= e($groupName) ?></h4>
              <ul class="inventory__list">
                <?php foreach ($items as $item): ?>
                  <li><?= e($item) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <p class="inventory__foot">
        The Caltech Y's own listing is the authoritative record of what is in stock
        today.
      </p>
    <?php endif; ?>

  </div>
</section>


<?php require __DIR__ . '/includes/footer.php'; ?>
