<?php
/**
 * Support Us — sponsorship and donations.
 *
 * Written for two readers at once: a company deciding whether to sponsor, and
 * an alum deciding whether to give. Both want to know exactly where money goes.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/officers.php';
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/includes/partials.php';

$PAGE = array(
    'title'       => 'Support',
    'description' => 'Sponsor or donate to the Caltech Alpine Club. Support funds club trips, '
                   . 'shared gear, and the mountain film festivals the club has hosted since '
                   . '2001.',
    'nav'         => 'support.php',
);

$sponsors = alpine_data('sponsors');
$donate   = cfg('links.donate');

/* Both asks on this page have an owner, and it is not the general mailbox:
   sponsorship belongs to the Partnerships lead, an offer of equipment to
   whoever has to find room for it. Asked for by role_id, so renaming either job
   does not lose the contact. See alpine_role_contact() for how it degrades. */
$partner = alpine_role_contact('partnerships');
$gear    = alpine_role_contact('gear');

require __DIR__ . '/includes/header.php';
?>

<?php alpine_page_hero(array(
    'title'  => 'Support the Alpine Club',
    /* This deck and the first line of the Sponsorship section below said the
       same thing twice -- "sponsorship and donations support club trips..."
       then "the club welcomes sponsorship from companies and other
       organizations". One line at the top names everybody the page is for,
       including the alumni the donations half is written for, and the section
       under it starts with what to give. */
    'lede'   => 'The club welcomes support from companies, organizations, alumni, and '
              . 'friends of the club.',
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
      <?php /* No "For companies" eyebrow: the heading under it says
               Sponsorship, which is the same information in smaller type.

               WHAT THIS SECTION MAY NOT DO is explain the club's internal
               position. It used to open with "the club is looking for sponsors
               and has not settled on what a sponsorship is... there are no
               tiers and no fixed price", which is four clauses of our own
               thinking handed to somebody deciding whether to write. The useful
               part of it is one clause, and it is the last line here. */ ?>
      <h2 class="h2">Sponsorship</h2>
      <div class="prose mt-lg">
        <?php /* "The club welcomes sponsorship from companies and other
                 organizations" opened this section and is now the page deck,
                 broadened to cover the donations half as well. It is not
                 repeated here. */ ?>
        <h3>Ways to support the club</h3>
      </div>

      <?php /* FIVE CATEGORIES, EACH NAMED. This was five bullets of the form
               "Help cover film festival licensing, venue, or ticket costs" --
               a verb phrase per line, so a reader scanning for the one that
               applies to them had to read all five to the end. The kind of
               support is now the label and the sentence is the detail, which
               is what a definition list is. Reusing .contact-list rather than
               adding a component: same two-column shape, already responsive,
               already styled. */ ?>
      <dl class="contact-list mt-lg">
        <div>
          <dt>Gear</dt>
          <dd>New or used outdoor gear that the club can lend to members.</dd>
        </div>
        <div>
          <dt>Discounts and demos</dt>
          <dd>Discounts, demo equipment, or other opportunities for members to try
              outdoor gear.</dd>
        </div>
        <div>
          <dt>Film festivals</dt>
          <dd>Support for screening licenses, venue costs, and other film festival
              expenses.</dd>
        </div>
        <div>
          <dt>Trips</dt>
          <dd>Support for transportation and other costs that make club trips more
              accessible.</dd>
        </div>
        <div>
          <dt>Direct support</dt>
          <dd>Financial contributions can support club equipment, trips, and events.</dd>
        </div>
      </dl>

      <div class="prose mt-lg">
        <?php /* WHAT THE CLUB CAN ACTUALLY DELIVER, and nothing else. The
                 website roster is real (data/sponsors.php renders it on this
                 page and on the homepage), event materials and the festival
                 screenings are the club's own. Do not add a benefit here that
                 no officer has committed to. */ ?>
        <p>
          Depending on the arrangement, the club can name sponsors on this website, in
          event materials, and at the film festival screenings.
        </p>
        <p>
          We don't have fixed sponsorship tiers. If you'd like to support the club,
          email us and we can figure out what makes sense.
        </p>
        <p>
          Email <?php if ($partner['name'] !== ''): ?>the club's
          <?= e($partner['title']) ?>, <?= e($partner['name']) ?>, at <?php endif; ?><a
            href="mailto:<?= e($partner['email']) ?>?subject=Alpine+Club+sponsorship"><?=
            e($partner['email']) ?></a> with what you have in mind.
        </p>
      </div>
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
        <?php /* No "For individuals" eyebrow either. */ ?>
        <h2 class="h2">Donations</h2>
        <div class="prose mt-lg">
          <p>
            Donations help replace shared gear, support club trips, and keep film
            festival tickets affordable for students.
          </p>
        </div>
        <?php if ($donate): ?>
          <div class="btn-row mt-lg">
            <a class="btn btn--primary" href="<?= e(alpine_outbound('donate')) ?>" rel="noopener">
              Donate to the Alpine Club <?= icon('external', 'icon icon--xs') ?>
            </a>
          </div>
        <?php else: ?>
          <?php /* No giving page configured yet -- see 'donate' in includes/config.php.
                   There is no way to take money on this site until that value exists,
                   so the page says so plainly rather than dressing an email up as a
                   payment button. Filling in that one link replaces this branch. */ ?>
          <div class="prose">
            <p>
              The club has no online giving page yet. Email
              <a href="mailto:<?= e(cfg('links.officers')) ?>?subject=Donation"><?=
                e(cfg('links.officers')) ?></a>
              and an officer will reply with where to send it.
            </p>
          </div>
        <?php endif; ?>

        <div class="prose mt-lg">
          <h3>Donating equipment</h3>
          <?php /* WHAT IS SOLICITED HERE IS DELIBERATELY NOT SAFETY-CRITICAL.
                   The list used to invite ropes and climbing hardware, and to
                   say that "worn but still serviceable" equipment was worth
                   offering. The club has no written inspection or acceptance
                   procedure -- not in this repository and not on the old site --
                   so the page does not publicly ask for used gear whose failure
                   mode is a fall. An offer of climbing hardware still reaches
                   the Gear Officer, who can judge it; the difference is that
                   the club is not asking for it in advance. */ ?>
          <p>
            The club also takes useful outdoor gear in good working order. Much of what
            it lends today was donated. Tents, packs, skis, and snow equipment are the
            most useful.
          </p>
          <p>
            To offer equipment, email <?php if ($gear['name'] !== ''): ?>the
            <?= e($gear['title']) ?>, <?= e($gear['name']) ?>, at <?php endif; ?><a
              href="mailto:<?= e($gear['email']) ?>?subject=Equipment+donation"><?=
              e($gear['email']) ?></a> with what you have, its rough age and condition,
            and where it is.
          </p>
        </div>
      </div>

    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
