<?php
/**
 * Get Involved — what each officer role does, which need somebody, and how to
 * end up in one.
 *
 * Almost everything here is generated from data/roles.csv and
 * data/assignments.csv. The only hand-written prose is the hero sentence and
 * the "how to become an officer" block at the bottom, and both are written to
 * stay true for years. Nobody has to edit this file after an election.
 *
 * WHAT THIS PAGE MUST NOT SAY: that any member can organize a club trip or
 * event. It used to open with exactly that -- "any member can organize a trip
 * or an event... ask an officer and we will help put it on the calendar" --
 * which contradicts the rest of the site and the role descriptions on this very
 * page, where climbing days belong to the Climbing Commodore, hikes to the
 * Hiking Coordinator, the weekly run to the Trail Run Tyrant. The club's
 * calendar is what its officers organize. Members finding each other on Slack
 * and going out together is a real and good thing, and the homepage is where it
 * is described, in one sentence, as the informal half.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/officers.php';
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/includes/partials.php';

$PAGE = array(
    'title'       => 'Get Involved',
    'description' => 'How to help run the Caltech Alpine Club: what each officer role does, '
                   . 'which ones need somebody, and how to become one.',
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
    /* TWO SENTENCES, and they say what the page is. No claim about what is
       open today: that is a statement about the world, and the section below
       already lists exactly which jobs from data/roles.csv, or renders nothing.
       No motivational opening either -- the reader came here to find out what
       the jobs are. */
    'lede'   => 'The Alpine Club is run by Caltech student officers. They organize club '
              . 'trips and events and handle the gear, finances, and membership.',
    'photo'  => 'photos/img-20200822-133229.jpg',
    'credit' => 'Club trip, August 2020',
));
?>

<!-- ======================================================== wanted ==== -->
<?php /* ONE LIST, not two. This was split into "what the club needs right now"
         and "could use more help", because a job nobody is doing and a job that
         could take a second person are genuinely different news. They still
         are, and the difference is still decided by min_people and max_people
         in data/roles.csv rather than by anybody rewording anything. It is now
         carried by the status beside each row ("Open" against "Room for one
         more") instead of by two headings, because two headings made the
         second list read as the leftovers.

         What min/max still decide: which jobs the HOMEPAGE may mention, which
         is only the ones the club is actually short of.

         Renders NOTHING when every job has the people it needs. There is no
         "no vacancies at this time" placeholder, because a section that exists
         only to say it has nothing to say is a section that will one day say
         it wrongly. */ ?>
<?php if ($asking): ?>
<section class="section section--tight section--tint" id="open">
  <div class="wrap">

    <?php /* No line under this heading about who may hold a role: the hero
             sentence two inches up says "Caltech student officers", which is
             the same fact in the place a reader meets first. Anyone can JOIN
             the club, which the homepage, Join and the footer all say; nothing
             here may be read as narrowing that. */ ?>
    <h2 class="h2">What the club needs right now</h2>

    <?php
    /* Jobs nobody is doing first, then the ones that would take another pair
       of hands, each keeping the order of data/roles.csv. This is where
       min_people and max_people went when the two headings became one list. */
    $ordered = array();
    foreach ($asking as $r) { if ($r['state'] === ALPINE_ROLE_NEEDED) { $ordered[] = $r; } }
    foreach ($asking as $r) { if ($r['state'] !== ALPINE_ROLE_NEEDED) { $ordered[] = $r; } }
    ?>
    <?php /* ROLE, WHO IS IN IT, STATUS -- three fields, no sentences, set on
             one line so the column of statuses can be read straight down.

             THE STATUS IS ALWAYS A FRACTION (2026-08-31). It was prose once
             ("Looking for someone"), then a fraction for short roles and prose
             for the rest ("Open", "Room for one more"). Both mixed formats
             failed the same way: this is a COLUMN, a reader compares it
             top-to-bottom, and two rows in different units cannot be compared
             at a glance. One shape -- filled over seats -- and the comparison
             is free. alpine_role_status_line() owns the wording.

             The holders are still named, because "1/2 filled" beside a job
             with a president in it and beside a job with nobody in it are
             different facts, and the name is what separates them without a
             sentence doing the explaining. */ ?>
    <ul class="wanted mt-lg">
      <?php foreach ($ordered as $r): ?>
        <?php
          $holderNames = array();
          foreach ($r['holders'] as $person) { $holderNames[] = e($person['name']); }
        ?>
        <li class="wanted__item">
          <div class="wanted__who">
            <a class="wanted__role" href="#<?= e($r['role_id']) ?>"><?= e(alpine_role_title($r)) ?></a>
            <?php if ($holderNames): ?>
              <span class="wanted__holders"><?= alpine_list_phrase($holderNames) ?></span>
            <?php endif; ?>
          </div>
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
            <?php
              $status = alpine_role_status_line($r);

              /* THE ANCHOR IS THE role_id, NOT THE TITLE. A link built from the
                 title breaks the day somebody renames the job -- silently, from
                 four other pages, and only for the people who followed the old
                 link. The id never changes, so neither does the link. */
              $anchor = $r['role_id'];

              /* NAMES, NOT MAILTO LINKS. alpine_person_link() would make each
                 one open a mail client; the About roster is where somebody
                 decides who to write to, and it shows the face and the job
                 alongside the address. Gear and Support still use the link,
                 because there the reader has already decided. */
              $holderNames = array();
              foreach ($r['holders'] as $person) {
                  $holderNames[] = e($person['name']);
              }
            ?>
            <article class="role<?= $r['state'] === ALPINE_ROLE_NEEDED ? ' role--wanted' : '' ?>"
                     id="<?= e($anchor) ?>">

              <h4 class="role__name"><?= e(alpine_role_title($r)) ?></h4>

              <?php if ($r['description'] !== ''): ?>
                <p class="role__what"><?= e($r['description']) ?></p>
              <?php endif; ?>

              <?php /* ONE STATUS TREATMENT PER ROLE. An empty job used to carry
                       three: "Currently open", then "Talk to one of the
                       officers if you're interested", then "Looking for
                       someone" -- one fact, an invitation, and the same fact
                       again, stacked under a heading that had already said it.
                       What is left is the holders when there are any, and a
                       one- or two-word status when there is something to say.
                       The invitation is the "How to become an officer" section
                       at the foot of this page, once, where somebody who has
                       read the roles arrives.

                       Names, not mailto links: the About roster is where
                       somebody decides who to write to, and it shows the face
                       and the job alongside the address.

                       Both can be empty at once -- a job the club has asked to
                       stop advertising, with nobody in it -- so the whole line
                       is conditional. No empty paragraph, no placeholder: just
                       the name and what the job involves. */ ?>
              <?php if ($holderNames || $status !== ''): ?>
                <p class="role__who">
                  <?php if ($holderNames): ?>
                    <span class="role__holders">
                      Currently <?= alpine_list_phrase($holderNames) ?>.
                    </span>
                  <?php endif; ?>

                  <?php if ($status !== ''): ?>
                    <span class="role__status"><?= e($status) ?></span>
                  <?php endif; ?>
                </p>
              <?php endif; ?>

            </article>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

  </div>
</section>


<!-- ======================================================= how ======= -->
<section class="section section--tint" id="how">
  <div class="wrap wrap--narrow">
    <h2 class="h2">How to become an officer</h2>

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
      <?php /* "or the president directly if you know who that is" went on
               2026-08-31: the roster is one link away and names them all, so
               the clause described a reader the site does not have. */ ?>
      <p>
        Talk to one of the current officers if you're interested. Some positions are
        elected; open coordinator roles can also be filled during the year.
      </p>
      <p>
        Most roles don't require previous club leadership or extensive outdoor
        experience. Being reliable and answering email matters more.
      </p>
    </div>

    <?php /* A LINK TO THE PEOPLE, not a mailto. This was a "write to" block
             with a shared address and a subject line, and before that a
             "please include" list of three things: what you had organized
             before (no role requires it, so asking implied one did) and how
             long you expected to be around (there is no written term or
             eligibility rule anywhere, so nothing could be done with the
             answer). Volunteering is not an application, and it should not
             start by opening a blank email to nobody in particular. */ ?>
    <p class="mt-lg">
      <a class="btn btn--primary" href="<?= e(url('about.php#officers')) ?>">
        Meet the officers <?= icon('arrow-right', 'icon icon--xs') ?>
      </a>
    </p>

  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
