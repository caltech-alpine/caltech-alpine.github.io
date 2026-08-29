<?php
/**
 * Gear: what the club lends, and how to book it.
 *
 * Keep this page accurate. A wrong rental price, notice period or eligibility
 * rule is worse than no page at all.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/officers.php';
require_once __DIR__ . '/includes/partials.php';

$PAGE = array(
    'title'       => 'Gear',
    'description' => 'Borrow club tents, crampons, avalanche equipment, touring skis, and '
                   . 'satellite messengers from the Caltech Alpine Club. Equipment is '
                   . 'available to Caltech and JPL affiliates.',
    'nav'         => 'gear.php',
);

$gear = alpine_data('gear');

/* Who currently looks after the club's own equipment.
   -------------------------------------------------
   Asked for by role_id, never by title. This page used to ask for the officer
   whose role was spelled "Gear Officer", which meant the club could not rename
   the job without this page silently losing its contact -- no error, no blank
   space, just a page quietly pointing everybody at the general mailbox instead
   of the person who actually has the rack. tools/check.php now fails if the
   'gear' id ever disappears from data/roles.csv; see alpine_required_roles().

   The title printed below is read from the data too, so renaming the job
   renames it here as well.

   Every use degrades on its own: nobody in the job -> we name the job and link
   to the roster; somebody with no email -> we fall back to the shared officers
   mailbox. Nothing here can produce a dead link or a blank name. */
$gearRole    = alpine_role('gear');
$gearTitle   = $gearRole ? alpine_role_title($gearRole) : 'Gear Officer';
/* $gearEmail was computed here and never read -- alpine_person_link() below
   gets each address from data/people.csv itself. Removed 2026-08-28. A page
   that DOES need one address for a job should use alpine_role_contact(). */
$gearOfficer = alpine_role_holders('gear');

require __DIR__ . '/includes/header.php';
?>

<?php alpine_page_hero(array(
    'title'  => 'Gear',
    'lede'   => 'Caltech and JPL affiliates can rent outdoor gear from the Caltech Y '
              . 'and borrow specialist equipment from the Alpine Club.',
    'photo'  => 'photos/sj-horseflats.jpg',
    'credit' => 'Bouldering at Horse Flats',
)); ?>


<!-- ======================================================= rental ==== -->
<section class="section" id="rental">
  <div class="wrap">
    <div class="split split--wide-left">
      <div>
        <h2 class="h2">How to borrow gear</h2>
        <?php /* THE ONE FACT THAT DECIDES WHETHER THE REST APPLIES TO YOU, so it
                 is a callout rather than a sentence inside a paragraph. It used
                 to be repeated on both booking buttons as well; saying it three
                 times on one screen was what made this page read as anxious.
                 Club MEMBERSHIP is open to anyone, and that distinction is the
                 second sentence. */ ?>
        <div class="gate mt-lg">
          <p class="gate__headline">
            Gear rentals and loans are limited to Caltech and JPL affiliates.
          </p>
          <p class="gate__detail">
            Other members are welcome on club trips and events, but need to bring their
            own gear.
          </p>
        </div>

        <div class="prose mt-lg">
          <h3>General gear from the Caltech Y</h3>
          <p>
            The Caltech Y rents tents, sleeping bags, stoves, snow gear, and other
            equipment. Most items cost about $1 per day, with pickup and return at the Y
            during business hours.
          </p>
        </div>
        <div class="btn-row mt-lg">
          <?php /* THE BADGE IS ON THE BUTTON because the people who most need
                   the rule are the ones who read only the buttons, and this one
                   sends them off the site. The callout above says it in bigger
                   type; this says it at the last moment it can.

                   It is a full phrase, not "Caltech / JPL only". Both spans are
                   inside the link, so a screen reader announces them as one
                   name: "Rent from the Caltech Y, Caltech and JPL affiliates
                   only" is a sentence, where the terse version was a run-on. */ ?>
          <a class="btn btn--primary btn--gated" href="<?= e(alpine_outbound('gear_rental')) ?>" rel="noopener">
            <span class="btn__label">Rent from the Caltech Y <?= icon('external', 'icon icon--xs') ?></span>
            <span class="btn__gate">Caltech and JPL affiliates only</span>
          </a>
        </div>

        <div class="prose mt-lg">
          <h3>Specialist gear from the Alpine Club</h3>
          <p>
            <?php /* Name and address inline: "the Gear Officer" alone does not tell
                     you who will answer, and an address alone does not tell you who
                     they are. Degrades in two steps — no email drops the brackets,
                     no officer at all drops the name and leaves the role linked to
                     the roster. */ ?>
            The club lends climbing, ice, and packrafting gear directly. Submit the
            reservation form at least 48 hours before you need it, and our
            <a href="<?= e(url('about.php#officers')) ?>"><?= e($gearTitle) ?></a><?php
              if ($gearOfficer):
                  /* Their address comes from data/people.csv through
                     alpine_person_link(), the same call the About page makes, so
                     it is not written a second time here. Two people can hold
                     the job and both are named. */
                  $named = array();
                  foreach ($gearOfficer as $person) {
                      $named[] = alpine_person_link($person, 'Specialist gear');
                  }
                  /* Both commas, or the appositive opens and never closes:
                     "our Gear Officer, Forrest McCann will confirm". */
                  echo ', ' . alpine_list_phrase($named) . ',';
              endif;
            ?> will confirm the reservation.
          </p>
        </div>
        <?php if (cfg('links.gear_form')): ?>
          <div class="btn-row mt-lg">
            <a class="btn btn--primary btn--gated" href="<?= e(alpine_outbound('gear_form')) ?>" rel="noopener">
              <span class="btn__label">Request club gear <?= icon('external', 'icon icon--xs') ?></span>
              <span class="btn__gate">Caltech and JPL affiliates only</span>
            </a>
          </div>
        <?php endif; ?>

        <div class="btn-row mt-lg">
          <a class="btn btn--ghost" href="<?= e(url('support.php#donate')) ?>">
            <?= icon('heart', 'icon icon--xs') ?> Donate gear
          </a>
        </div>
      </div>

      <?php /* One rule per note. The 48-hour notice used to live up here,
               unattached to either booking route and phrased as though it
               covered both; it applies to the club's own form, so it is now in
               the sentence that tells you to submit the form. The consumables
               rule and the inReach charges were one paragraph and are two
               different things. */ ?>
      <div class="stack">
        <div class="note">
          <?= icon('clock', 'icon icon--xs') ?>
          <p>Bring your own fuel, batteries, ski wax, and other consumables, and return
             borrowed equipment in working order. Popular items are often booked out
             before long weekends.</p>
        </div>

        <div class="note">
          <?= icon('gear', 'icon icon--xs') ?>
          <p><strong>Garmin inReach:</strong> SOS and preset messages are included.
             Other messaging may be charged. The cost of a backcountry rescue is the
             responsibility of whoever triggers it.</p>
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
        Check the Caltech Y website for current availability.
      </p>
    <?php endif; ?>

  </div>
</section>


<?php require __DIR__ . '/includes/footer.php'; ?>
