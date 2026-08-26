<?php
/**
 * Get Involved — the jobs that run the club, and how you end up doing one.
 *
 * Almost everything on this page is generated from data/roles.csv and
 * data/officers.csv. The only hand-written prose is the opening section and the
 * "how you get one" block at the bottom, and both are written to stay true for
 * years. Nobody has to edit this file after an election.
 *
 * A page listing only the current officers reads as a closed shop. This is the
 * other half: the same information arranged around what a visitor could do
 * rather than around who is already doing it.
 */

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/roles.php';
require __DIR__ . '/includes/partials.php';

$PAGE = array(
    'title'       => 'Get Involved',
    'description' => 'How to help run the Caltech Alpine Club: what each officer role does, '
                   . 'which are open right now, and who to write to.',
    'nav'         => 'roles.php',
);

$groups = alpine_roles_by_group();
$wanted = alpine_roles_wanted();

require __DIR__ . '/includes/header.php';

alpine_page_hero(array(
    'title'  => 'Get involved',
    'lede'   => 'The club runs on people volunteering to organize things. '
              . 'Some of those jobs are open.',
    'photo'  => 'photos/img-20200822-133229.jpg',
    'credit' => 'Club trip, August 2020',
));
?>

<!-- ================================================== without a role ==== -->
<?php /* FIRST, because it is true for almost everyone who reads this page, and
         because leading with a list of officer posts turns a club into a
         committee. */ ?>
<section class="section">
  <div class="wrap">
    <div class="split split--wide-left">
      <div>
        <h2 class="h2">You do not need a title</h2>
        <div class="prose mt-lg">
          <p>
            Most of what the club does is organized by whoever felt like organizing
            it. If you want to run a hike up Islip next Saturday, put it on the
            calendar and go &mdash; ask an officer and they will add it. Nobody has
            to appoint you.
          </p>
          <p>
            The same goes for the parts of a big event that need hands rather than a
            plan: taking tickets at the film festival, setting problems in the
            bouldering cave, driving people to the trailhead, carrying a rope.
          </p>
          <p>
            Officer roles exist for the work that has to happen whether or not
            anybody feels like it &mdash; the money, the mailing list, the gear that
            has to come back.
          </p>
        </div>
      </div>

      <div>
        <div class="note">
          <?= icon('mail', 'icon icon--xs') ?>
          <p>
            Easiest first move: write to
            <a href="mailto:<?= e(cfg('links.officers')) ?>"><?= e(cfg('links.officers')) ?></a>
            and say what you would like to do. It reaches the current officers.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- ======================================================== wanted ==== -->
<?php /* Renders NOTHING when the club is fully staffed. There is no "no
         vacancies at this time" placeholder, because a section that exists only
         to say it has nothing to say is a section that will one day say it
         wrongly. */ ?>
<?php if ($wanted): ?>
<section class="section section--tight section--tint" id="open">
  <div class="wrap">
    <h2 class="h2">What the club needs right now</h2>
    <div class="prose mt-lg">
      <?php /* Derived, every time the page loads, from who is listed in
               data/officers.csv. Nobody types the word "vacant" and nobody has
               to remember to take it down. */ ?>
      <p>
        The person who did each of these last is usually happy to explain what it
        involved, and none of them are hard to take on.
      </p>
    </div>

    <ul class="wanted mt-lg">
      <?php foreach ($wanted as $r): ?>
        <li class="wanted__item">
          <a class="wanted__role" href="#<?= e(alpine_slug($r['role'])) ?>"><?= e($r['role']) ?></a>
          <span class="wanted__note"><?= e(alpine_role_status_line($r)) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>


<!-- ========================================================= roles ==== -->
<section class="section" id="roles">
  <div class="wrap">
    <h2 class="h2">The roles</h2>

    <?php if (!$groups): ?>
      <div class="empty-state">
        <h3>The role list is being updated</h3>
        <p>Roles are listed in <code>data/roles.csv</code>.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($groups as $groupName => $roles): ?>
      <div class="officer-group">
        <h3 class="officer-group__title"><?= e($groupName) ?></h3>

        <div class="roles">
          <?php foreach ($roles as $r): ?>
            <?php $status = alpine_role_status_line($r); ?>
            <article class="role<?= $r['wanted'] ? ' role--wanted' : '' ?>" id="<?= e(alpine_slug($r['role'])) ?>">

              <h4 class="role__name"><?= e($r['role']) ?></h4>

              <?php if (!empty($r['description'])): ?>
                <p class="role__what"><?= e($r['description']) ?></p>
              <?php endif; ?>

              <p class="role__who">
                <?php if ($r['holders']): ?>
                  <?php
                    $names = array();
                    foreach ($r['holders'] as $o) { $names[] = $o['name']; }
                  ?>
                  <span class="role__holders">Currently <?= e(alpine_list_phrase($names)) ?>.</span>
                <?php endif; ?>
                <?php if ($status !== ''): ?>
                  <span class="role__status"><?= e($status) ?></span>
                <?php endif; ?>
              </p>

              <?php /* 'chosen' is deliberately blank in data/roles.csv until
                       somebody confirms which roles are actually elected. A
                       blank prints nothing, which beats a confident guess about
                       the club's own rules. */ ?>
              <?php if (!empty($r['chosen'])): ?>
                <p class="role__how"><?= e(ucfirst($r['chosen'])) ?></p>
              <?php endif; ?>

            </article>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <p class="mt-lg">
      <a class="arrow-link" href="<?= e(url('about.php#officers')) ?>">
        Meet the people doing these jobs <?= icon('arrow-right', 'icon icon--xs') ?>
      </a>
    </p>
  </div>
</section>


<!-- ======================================================= how ======= -->
<section class="section section--tint" id="how">
  <div class="wrap wrap--narrow">
    <h2 class="h2">How you end up with one</h2>

    <div class="prose mt-lg">
      <?php /* WHAT THIS SECTION MAY SAY.

               The club has never written its election procedure down. There is
               no constitution, no bylaws and no officer handbook in this
               repository or on the old alpine.caltech.edu -- both searched in
               August 2026. So this paragraph says the part that is known to be
               true, which is: tell an officer, and they will tell you what
               happens next.

               DO NOT fill this out with a nomination process, an eligibility
               rule, a term length or a date. If an officer confirms the real
               procedure, replace this with it and record here who said so. */ ?>
      <p>
        Say you are interested, and an officer will tell you what happens next.
        Sometimes that means waiting for the next round of elections. More often a
        role is simply not being done and you can start doing it.
      </p>
      <p>
        Write to the officers, or to the president directly if you know who that is.
        There is no form to fill in and no deadline to miss.
      </p>
      <p>
        You do not need experience running a club, and for most of these you do not
        need much outdoor experience either. What the jobs mostly need is somebody
        who answers email.
      </p>
    </div>

    <?php
    $body = "Hello,\n\n"
          . "I would like to help run the Alpine Club.\n\n"
          . "Which role, or what sort of thing I would like to do:\n\n\n"
          . "A bit about me:\n\n\n"
          . "Thanks,\n";
    ?>
    <?php alpine_write_to(cfg('links.officers'), 'Getting involved', array(
        'Which role you are interested in, or just what you would like to help with',
        'Whether you are at Caltech or JPL, and roughly how long you expect to be around',
        'Anything you have organized before. It is fine if the answer is nothing.',
    ), array('body' => $body)); ?>

  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
