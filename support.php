<?php
/**
 * Support Us — sponsorship and donations.
 *
 * Written for two readers at once: a company deciding whether to sponsor, and
 * an alum deciding whether to give. Both want to know exactly where money goes.
 */

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

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

<header class="page-hero">
  <div class="topo"></div>
  <div class="wrap page-hero__inner">
    <p class="eyebrow"><?= icon('heart', 'icon icon--xs') ?>Support</p>
    <h1 class="h1">Support the Alpine Club</h1>
    <p class="lede">
      Sponsorship and donations support club trips, shared equipment, and the
      club's film festivals.
    </p>
  </div>
</header>


<!-- ====================================================== sponsor ==== -->
<section class="section" id="sponsor">
  <div class="wrap">
    <div class="split split--wide-left">
      <div>
        <p class="eyebrow">For companies</p>
        <h2 class="h2">Sponsorship</h2>
        <div class="prose mt-lg">
          <?php /* The numbers were restated here in prose, beside a stats block
                   printing the same three. The stats won: a sponsor reads those. */ ?>
          <h3>What sponsorship funds</h3>
          <ul>
            <li>Shared equipment that members borrow for club trips</li>
            <li>Licensing and venue costs for the club's mountain film festivals</li>
            <li>Trip costs, which keeps events affordable for students</li>
          </ul>

          <h3>Sponsor recognition</h3>
          <ul>
            <li>Name and logo on this website and on club event materials</li>
            <li>Acknowledgement at the club's film festival screenings</li>
            <li>Member discounts, as an alternative or addition to logo placement</li>
          </ul>
        </div>

        <?php alpine_write_to(cfg('links.officers'), 'Alpine Club sponsorship', array(
            'What your company does, and who there we would be working with',
            'What you would want in return: logo placement, event recognition, '
                . 'member discounts, or some combination',
            'Roughly what scale of support you have in mind',
        )); ?>
      </div>

      <div class="stack">
        <?php /* Four numbers, four different questions a sponsor actually asks:
                 how many people, for how long, how visible is the flagship
                 event, and how much of it is there. Every one is derived from
                 config rather than typed here, so none of them can go stale
                 quietly. */ ?>
        <div class="stats">
          <div class="stat">
            <div class="stat__num"><?= e(cfg('facts.members')) ?></div>
            <div class="stat__label">
              active members across Caltech, JPL, and the local community, about
              <?= e(cfg('facts.grad_share')) ?> of them graduate students
            </div>
          </div>
          <div class="stat">
            <div class="stat__num"><?= date('Y') - (int) cfg('facts.founded') ?></div>
            <div class="stat__label">years since the club was founded</div>
          </div>
          <div class="stat">
            <div class="stat__num"><?= date('Y') - (int) cfg('facts.banff_since') ?></div>
            <div class="stat__label">
              years hosting the Banff Mountain Film Festival, since
              <?= e(cfg('facts.banff_since')) ?>
            </div>
          </div>
          <div class="stat">
            <div class="stat__num"><?= count(cfg('facts.festivals')) ?></div>
            <div class="stat__label">
              mountain film festivals the club runs:
              <?= e(implode(', ', array_keys(cfg('facts.festivals')))) ?>
            </div>
          </div>
        </div>
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
            <a class="btn btn--primary" href="<?= e($donate) ?>" rel="noopener">
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
