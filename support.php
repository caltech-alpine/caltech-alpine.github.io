<?php
/**
 * Support Us — sponsorship and donations.
 *
 * Written for two readers at once: a company deciding whether to sponsor, and
 * an alum deciding whether to give. Both want to know exactly where money goes.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/partials.php';

$PAGE = array(
    'title'       => 'Support Us',
    'description' => 'Sponsor or donate to the Caltech Alpine Club. Support funds club trips, '
                   . 'shared gear, and the mountain film festivals the club has hosted since '
                   . '2001.',
    'nav'         => 'support.php',
);

$sponsors = alpine_data('sponsors');
$donate   = cfg('links.donate');

require __DIR__ . '/includes/header.php';
?>

<?php alpine_page_hero(array(
    'title'  => 'Support the Alpine Club',
    'lede'   => 'Sponsorship and donations support club trips, shared equipment, and the '
              . "club's film festivals.",
    /* dsc02582-hero.jpg is dsc02582.jpg with 221px cropped off the right edge.
       The hero is a wide letterbox, so background-size:cover scales the photo
       by width and a horizontal background-position does nothing at desktop
       sizes -- the only way to move the subject sideways is to reframe the
       file. Uncropped, the chipmunk landed behind the last word of the h1.
       'focus' still does the vertical work: the subject sits two thirds down
       and the top half is haze, so the default middle band gave an empty grey
       rectangle and half an animal on the bottom edge. */
    'photo'  => 'photos/dsc02582-hero.jpg',
    'credit' => 'Summit chipmunk, unimpressed',
    'focus'  => 'center 70%',
)); ?>


<!-- ====================================================== sponsor ==== -->
<section class="section" id="sponsor">
  <div class="wrap">
    <div>
      <p class="eyebrow">For companies</p>
      <h2 class="h2">Sponsorship</h2>
      <div class="prose mt-lg">
        <p>
          The club is looking for sponsors and has not settled on what a
          sponsorship is. There are no tiers and no fixed price. What follows is
          the kind of support that would be useful, not a menu &mdash; the
          arrangement is whatever an officer and a sponsor agree on.
        </p>
        <ul>
          <li>Gear donated to the loanable inventory, new or used</li>
          <li>An equipment demo at a club event</li>
          <li>Money toward film festival licensing and venue costs, or toward trip costs</li>
          <li>Discounts for members</li>
          <li>Name and logo on this site and on club event materials</li>
          <li>Acknowledgement at the film festival screenings</li>
        </ul>
        <p>
          If what you have in mind is not on that list, say it anyway. The club
          would rather hear the idea than turn down something it did not think
          to ask for.
        </p>
      </div>

      <?php alpine_write_to(cfg('links.officers'), 'Alpine Club sponsorship', array(
          'What your company does, and who there we would be working with',
          'What you would want to give, and what you would want in return',
          'Roughly what scale you have in mind, if you have a number yet',
      )); ?>
    </div>

    <?php if ($sponsors): ?>
      <div class="mt-lg">
        <h3 class="officer-group__title">Current sponsors</h3>
        <div class="sponsors">
          <?php foreach ($sponsors as $s): ?>
            <?php
              $tag  = !empty($s['url']) ? 'a' : 'div';
              $href = !empty($s['url']) ? ' href="' . e($s['url']) . '" rel="noopener"' : '';
            ?>
            <<?= $tag ?> class="sponsor"<?= $href ?>>
              <?php if (!empty($s['logo']) && alpine_has_image($s['logo'])): ?>
                <img src="<?= e(asset('images/' . $s['logo'])) ?>" alt="<?= e($s['name']) ?>" loading="lazy">
              <?php else: ?>
                <span class="sponsor__name"><?= e($s['name']) ?></span>
              <?php endif; ?>
              <?php if (!empty($s['tier'])): ?>
                <span class="sponsor__tier"><?= e($s['tier']) ?></span>
              <?php endif; ?>
            </<?= $tag ?>>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>
</section>


<!-- ======================================================= donate ==== -->
<section class="section section--tint" id="donate">
  <div class="wrap">
    <div class="split">
      <div>
        <p class="eyebrow"><?= icon('heart', 'icon icon--xs') ?>For individuals</p>
        <h2 class="h2">Donations</h2>
        <div class="prose mt-lg">
          <p>
            Donations support the same things as sponsorship: shared equipment, and
            keeping event tickets affordable for students.
          </p>
        </div>
        <?php if ($donate): ?>
          <div class="btn-row mt-lg">
            <a class="btn btn--primary" href="<?= e(alpine_outbound('donate')) ?>" rel="noopener">
              Donate to the Alpine Club <?= icon('external', 'icon icon--xs') ?>
            </a>
          </div>
        <?php else: ?>
          <?php /* No giving page configured yet — see 'donate' in includes/config.php.
                   There is no way to take money on this site until that value exists,
                   so the page says so plainly rather than dressing an email up as a
                   payment button. Filling in that one link replaces this branch. */ ?>
          <div class="prose">
            <p>
              The club has no online giving page yet, so a gift starts with an email
              and an officer replies with where to send it.
            </p>
          </div>
          <?php alpine_write_to(cfg('links.officers'), 'Donation', array(
              'That you would like to donate, and roughly how much',
              'Whether you would like it to go to a particular thing, '
                  . 'such as equipment or film festival tickets',
          )); ?>
        <?php endif; ?>

        <div class="prose mt-lg">
          <h3>Donating equipment</h3>
          <p>
            Much of the club's own stock was donated. Tents, packs, ropes, hardware, skis,
            and snow gear are all useful, and equipment that is worn but still serviceable
            is worth offering.
          </p>
        </div>
        <?php /* These three prompts do the job a form would do — they get an officer
                 what they need to answer in one reply — with nothing to host, no spam
                 to filter and no submissions to remember to check. */ ?>
        <?php alpine_write_to(cfg('links.officers'), 'Equipment donation', array(
            'What the equipment is',
            'Rough age and condition',
            'Where you are, and when you could hand it over',
        )); ?>
      </div>

    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
