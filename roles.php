<?php
/**
 * Get Involved — the jobs that run the club, and how you end up doing one.
 *
 * Almost everything on this page is generated from ROLES.csv and
 * ASSIGNMENTS.csv. The only hand-written prose is the opening section and the
 * "how you get one" block at the bottom, and both are written to stay true for
 * years. Nobody has to edit this file after an election.
 *
 * A page listing only the current officers reads as a closed shop. This is the
 * other half: the same information arranged around what a visitor could do
 * rather than around who is already doing it.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/officers.php';
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/includes/partials.php';

$PAGE = array(
    'title'       => 'Get Involved',
    'description' => 'How to help run the Caltech Alpine Club: what each officer role does, '
                   . 'which are open right now, and who to write to.',
    'nav'         => 'roles.php',
);

$groups = alpine_roles_by_group();

/* Two different questions, and the page answers them in two different places.

     $needed  the club is SHORT of these -- fewer people than it needs. This is
              the list that gets a heading of its own, and the only list the
              homepage is allowed to mention.
     $asking  everything the club would welcome somebody into, which is $needed
              plus the jobs that are running fine and could still use a hand.

   Keeping them apart is what stops "we could use a second hiking coordinator"
   from being presented with the same urgency as "nobody is running the film
   festival". Both are true; only one is a problem. */
$needed = alpine_roles_needed();
$asking = alpine_roles_asking();

require __DIR__ . '/includes/header.php';

alpine_page_hero(array(
    'title'  => 'Get involved',
    /* The second sentence is counted, not typed. "Some of those jobs are open"
       was written here by hand, and it is a claim about the world that stops
       being true the moment somebody fills the last one -- with nothing on the
       page or in anyone's calendar to catch it. */
    'lede'   => 'The club runs on people volunteering to organize things. '
              . ($needed
                  ? 'Some of those jobs are looking for somebody.'
                  : 'There is always more of it to go round.'),
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
<?php /* Renders NOTHING when every job has the people it needs. There is no "no
         vacancies at this time" placeholder, because a section that exists only
         to say it has nothing to say is a section that will one day say it
         wrongly.

         Two lists, and they are separated on purpose. The club being SHORT of a
         film festival coordinator and the club having room for a second hiking
         coordinator are both true and are not the same news; running them
         together in one list makes the first sound routine and the second sound
         like an emergency. Which list a job lands in is decided by min_people
         and max_people in ROLES.csv, not by anybody rewording anything. */ ?>
<?php if ($asking): ?>
<section class="section section--tight section--tint" id="open">
  <div class="wrap">

    <?php if ($needed): ?>
      <h2 class="h2">What the club needs right now</h2>
      <div class="prose mt-lg">
        <p>
          Nobody has to be appointed to any of these, and none of them are hard to
          take on. Whoever did one last is usually happy to explain what it involved.
        </p>
      </div>

      <ul class="wanted mt-lg">
        <?php foreach ($needed as $r): ?>
          <li class="wanted__item">
            <a class="wanted__role" href="#<?= e($r['role_id']) ?>"><?= e(alpine_role_title($r)) ?></a>
            <span class="wanted__note"><?= e(alpine_role_status_line($r)) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php
    /* The quieter half: jobs that are running, and would still welcome
       somebody. Under its own heading when there is a "needs" list above it,
       and promoted to the section's heading when there is not -- so a year in
       which nothing is actually short does not open with an empty urgency. */
    $spare = array();
    foreach ($asking as $r) {
        if ($r['state'] !== ALPINE_ROLE_NEEDED) { $spare[] = $r; }
    }
    ?>
    <?php if ($spare): ?>
      <?php if ($needed): ?>
        <h3 class="h3 mt-lg">Room for more</h3>
        <div class="prose">
          <p>These are being done. They would go better with another pair of hands.</p>
        </div>
      <?php else: ?>
        <h2 class="h2">Room for more</h2>
        <div class="prose mt-lg">
          <p>
            Every job the club needs doing is being done. These would still welcome
            somebody, and it is the easiest time to start &mdash; you would be
            learning it from whoever is already doing it.
          </p>
        </div>
      <?php endif; ?>

      <ul class="wanted mt-lg">
        <?php foreach ($spare as $r): ?>
          <li class="wanted__item">
            <a class="wanted__role" href="#<?= e($r['role_id']) ?>"><?= e(alpine_role_title($r)) ?></a>
            <span class="wanted__note"><?= e(alpine_role_status_line($r)) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

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
        <p>Roles are listed in <code>ROLES.csv</code>.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($groups as $groupName => $roles): ?>
      <div class="officer-group">
        <h3 class="officer-group__title"><?= e($groupName) ?></h3>

        <div class="roles">
          <?php foreach ($roles as $r): ?>
            <?php
              $status = alpine_role_status_line($r);

              /* THE ANCHOR IS THE role_id, NOT THE TITLE. A link built from the
                 title breaks the day somebody renames the job -- silently, from
                 four other pages, and only for the people who followed the old
                 link. The id never changes, so neither does the link. */
              $anchor = $r['role_id'];

              /* Who to write to about this job. The people doing it if anybody
                 is; the club's shared mailbox if nobody is, because an empty
                 contact line on the page that exists to recruit is the one
                 place a dead end costs something. */
              $holderLinks = array();
              foreach ($r['holders'] as $person) {
                  $holderLinks[] = alpine_person_link($person, alpine_role_title($r));
              }
            ?>
            <article class="role<?= $r['state'] === ALPINE_ROLE_NEEDED ? ' role--wanted' : '' ?>"
                     id="<?= e($anchor) ?>">

              <h4 class="role__name"><?= e(alpine_role_title($r)) ?></h4>

              <?php if ($r['description'] !== ''): ?>
                <p class="role__what"><?= e($r['description']) ?></p>
              <?php endif; ?>

              <?php /* WHO DOES IT, AND HOW TO WRITE TO THEM -- both, in the same
                       place. This page used to name the person and stop there,
                       which sent anybody who wanted to ask them about it back to
                       the About page to look up an address the site already knew.
                       The address is not repeated in this file: it comes from
                       PEOPLE.csv through alpine_person_link(), the same way
                       the About page gets it, so there is one copy of it. */ ?>
              <p class="role__who">
                <?php if ($holderLinks): ?>
                  <span class="role__holders">
                    Currently <?= alpine_list_phrase($holderLinks) ?>.
                  </span>
                <?php else: ?>
                  <span class="role__holders">
                    Nobody at the moment &mdash; write to
                    <a href="mailto:<?= e(cfg('links.officers')) ?>?subject=<?= e(rawurlencode(alpine_role_title($r))) ?>"><?= e(cfg('links.officers')) ?></a>
                    if you are interested.
                  </span>
                <?php endif; ?>

                <?php if ($status !== ''): ?>
                  <span class="role__status"><?= e($status) ?></span>
                <?php endif; ?>
              </p>

            </article>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <p class="mt-lg">
      <a class="arrow-link" href="<?= e(url('about.php#officers')) ?>">
        See the officers with their photographs <?= icon('arrow-right', 'icon icon--xs') ?>
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
