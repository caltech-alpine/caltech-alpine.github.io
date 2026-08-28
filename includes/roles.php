<?php
/**
 * ============================================================================
 *  Roles: the jobs that exist, who holds them, and which need somebody.
 * ============================================================================
 *
 *  THREE FILES, TWO JOINS, AND EVERY JOIN IS ON AN ID.
 *
 *    PEOPLE.csv       who exists                  keyed by person_id
 *    ROLES.csv        what the jobs are           keyed by role_id
 *    ASSIGNMENTS.csv  who is doing which job      person_id -> role_id
 *
 *  NOTHING IN THIS FILE COMPARES A ROLE TITLE.
 *  ------------------------------------------
 *  That is the durability rule the whole design turns on. The version before
 *  this one joined the two files on the title text and normalised it with a
 *  regex that stripped a "Co-" prefix, so that "Co-President" and "President"
 *  would meet. It worked, and it was a trap: it meant the club could not rename
 *  a job without the website quietly disagreeing with itself. Rename President
 *  to Presidents and the join misses, the serving president falls out of the
 *  role, and the site announces a vacancy in a job somebody is doing. Nothing
 *  on the rendered page would tell you.
 *
 *  So the title is now nothing but words on a screen. role_id decides
 *  everything -- who holds what, what is open, what a page links to, which
 *  officer the gear page writes to. A future officer can retitle President to
 *  Co-President, to Presidents, to Chair, in any order, and the only thing that
 *  changes is what the page says.
 *
 *  MIN AND MAX, AND WHY BOTH
 *  -------------------------
 *  min_people is how many the club NEEDS; max_people is how many it can USE.
 *  A job with one of its two presidents is not vacant, and a job the club is
 *  happy to leave empty is not an emergency. Three different sentences, and the
 *  difference between them is two numbers rather than a human remembering to
 *  reword a notice. See alpine_role_state().
 *
 *  NOBODY EVER TYPES "VACANT"
 *  --------------------------
 *  The obvious design is a status column: President / Jane Doe / filled. It
 *  does not survive a year. Marking a role vacant takes one edit and UNMARKING
 *  it takes a second one, and the second edit is the one nobody remembers, so
 *  the site ends up advertising a job that was filled in October. A claim about
 *  the world that a human has to keep re-asserting is a claim that goes stale.
 *  Every "open" on this site is counted, not typed.
 *
 *  The one thing that IS typed is the 'recruiting' column, for the two cases
 *  counting cannot see: a job that is filled and still needs somebody because
 *  the holder is leaving, and a job the club wants to stop advertising for a
 *  while. Both are a human's judgement and neither can be derived.
 * ============================================================================
 */

require_once __DIR__ . '/people.php';

/* The five states a role can be in. Everything the site says about staffing is
   one of these, and they are decided in exactly one place.

     needed    fewer people than the club needs. The only state that reaches
               the homepage. "Open", or "looking for one more".
     spare     enough to run, and room for another. A quieter invitation. Also
               where an optional job (min 0) with nobody in it lands, which is
               the whole reason min and max are separate numbers.
     handover  fully staffed, and the recruiting column says somebody is going.
     staffed   nothing to say. Renders as silence.
     quiet     the club has asked to stop advertising this one for now.        */
define('ALPINE_ROLE_NEEDED',   'needed');
define('ALPINE_ROLE_SPARE',    'spare');
define('ALPINE_ROLE_HANDOVER', 'handover');
define('ALPINE_ROLE_STAFFED',  'staffed');
define('ALPINE_ROLE_QUIET',    'quiet');


/**
 * Current and past assignments, bucketed by role_id.
 *
 * An assignment naming a person or a role that does not exist is skipped
 * rather than rendered as a blank. tools/check.php reports it loudly, which is
 * the right division of labour: the page stays sane, the checker complains.
 *
 * @return array{current: array<string, array[]>, past: array<string, array[]>}
 */
function alpine_assignments()
{
    static $cache = null;
    if ($cache !== null) { return $cache; }

    $current = array();
    $past    = array();

    foreach (alpine_data('assignments') as $a) {
        $personId = isset($a['person_id']) ? trim($a['person_id']) : '';
        $roleId   = isset($a['role_id'])   ? trim($a['role_id'])   : '';
        if ($personId === '' || $roleId === '') { continue; }

        $person = alpine_person($personId);
        if (!$person) { continue; }                 // check.php reports this

        $person['role_id']    = $roleId;
        $person['title_held'] = isset($a['title_held']) ? trim($a['title_held']) : '';
        $person['until']      = !empty($a['until']) ? (int) $a['until'] : null;

        if ($person['until'] !== null) {
            $past[$roleId][] = $person;
        } else {
            $current[$roleId][] = $person;
        }
    }

    return $cache = array('current' => $current, 'past' => $past);
}


/**
 * Which of the five states a role is in.
 *
 * Note what "spare" requires: a max_people that is an actual number. A job with
 * a blank max takes as many volunteers as turn up, so there is no honest way to
 * say "room for one more" -- there is always room, which makes the sentence
 * noise. Once such a job has the people it needs, it says nothing.
 */
function alpine_role_state($filled, $min, $max, $recruiting)
{
    if (strcasecmp($recruiting, 'no') === 0) { return ALPINE_ROLE_QUIET; }
    if ($filled < $min)                      { return ALPINE_ROLE_NEEDED; }
    if ($recruiting !== '')                  { return ALPINE_ROLE_HANDOVER; }
    if ($max !== null && $filled < $max)     { return ALPINE_ROLE_SPARE; }
    return ALPINE_ROLE_STAFFED;
}


/**
 * Every role, in the order ROLES.csv lists them, with its people attached.
 *
 * The file's own order IS the display order -- there is no sort column, because
 * a sort column is a second thing to keep consistent and a spreadsheet already
 * has an obvious way to say "this one goes first": move the row.
 *
 * Each entry carries everything from ROLES.csv plus:
 *   role_id    stable id. The only thing any caller should match on.
 *   holders    current people (person rows), alphabetical. May be empty.
 *   past       people who have finished this job, newest year first
 *   filled     how many people currently hold it
 *   min / max  what the club needs / can use. max is null for "as many as turn up".
 *   state      one of the ALPINE_ROLE_* constants
 *   short      how many below the minimum, 0 if not short
 *   spare      how many more it could take, or null when max is blank
 *   asking     true when the site should be inviting somebody. Test this.
 *
 * @return array[] a plain list; iterate it.
 */
function alpine_roles()
{
    static $cache = null;
    if ($cache !== null) { return $cache; }

    $assignments = alpine_assignments();
    $out = array();

    foreach (alpine_data('roles') as $r) {
        $id = isset($r['role_id']) ? trim($r['role_id']) : '';
        if ($id === '') { continue; }

        $holders = isset($assignments['current'][$id]) ? $assignments['current'][$id] : array();
        $past    = isset($assignments['past'][$id])    ? $assignments['past'][$id]    : array();
        $filled  = count($holders);

        /* A blank minimum means 1: the ordinary case is a job somebody has to
           be doing, and a maintainer who leaves the cell empty means "normal",
           not "optional". Optional is written down, as a 0. */
        $min = (isset($r['min_people']) && $r['min_people'] !== '') ? (int) $r['min_people'] : 1;
        /* A blank maximum means no limit -- as many volunteers as turn up. */
        $max = (isset($r['max_people']) && $r['max_people'] !== '') ? (int) $r['max_people'] : null;

        $recruiting = isset($r['recruiting']) ? trim($r['recruiting']) : '';
        $state      = alpine_role_state($filled, $min, $max, $recruiting);

        usort($past, function ($a, $b) { return $b['until'] - $a['until']; });

        $out[] = array_merge($r, array(
            'role_id'      => $id,
            'title'        => (isset($r['title']) && trim($r['title']) !== '') ? trim($r['title']) : $id,
            'title_shared' => isset($r['title_shared']) ? trim($r['title_shared']) : '',
            'group'        => !empty($r['group']) ? trim($r['group']) : 'Roles',
            'description'  => isset($r['description']) ? trim($r['description']) : '',
            'contact_for'  => isset($r['contact_for']) ? trim($r['contact_for']) : '',
            /* 'no' is an instruction to the site, not a sentence to print. */
            'recruiting'   => (strcasecmp($recruiting, 'no') === 0) ? '' : $recruiting,
            'holders'      => alpine_sort_people($holders),
            'past'         => $past,
            'filled'       => $filled,
            'min'          => $min,
            'max'          => $max,
            'state'        => $state,
            'short'        => max(0, $min - $filled),
            'spare'        => ($max !== null) ? max(0, $max - $filled) : null,
            'asking'       => ($state === ALPINE_ROLE_NEEDED
                            || $state === ALPINE_ROLE_SPARE
                            || $state === ALPINE_ROLE_HANDOVER),
        ));
    }

    return $cache = $out;
}

/** One role by its stable id, or null. */
function alpine_role($roleId)
{
    foreach (alpine_roles() as $r) {
        if ($r['role_id'] === $roleId) { return $r; }
    }
    return null;
}

/**
 * The roles grouped under their headings, in the order the groups first appear
 * in ROLES.csv.
 */
function alpine_roles_by_group()
{
    $groups = array();
    foreach (alpine_roles() as $r) {
        $groups[$r['group']][] = $r;
    }
    return $groups;
}

/**
 * The people currently doing a job, by role_id. Empty array if nobody is.
 *
 * This is what a page uses to find a contact -- gear.php asks for 'gear'. It
 * takes an id and not a title on purpose: a page that asked for "Gear Officer"
 * would find nobody the day the club renamed the job, and would quietly fall
 * back to the general mailbox with no error anywhere.
 */
function alpine_role_holders($roleId)
{
    $r = alpine_role($roleId);
    return $r ? $r['holders'] : array();
}

/**
 * What to call the person or people currently doing a job.
 *
 * The whole of the site's "is it President or Co-President" logic, and it is
 * two columns and no grammar: title, and title_shared for when more than one
 * person is doing it. If title_shared is blank the title is used however many
 * there are, which is right for most jobs -- two hiking coordinators are two
 * hiking coordinators, not co-anythings.
 *
 * The previous version inferred this: officers wrote "Co-President" and the
 * code stripped the prefix with a regex when only one person held the job. It
 * produced the exact contradiction this page was reported for -- one person
 * shown as "Co-President" while the same page said "President, 1 of 2 filled"
 * -- because an inference cannot be edited, only worked around.
 *
 * @param array|string $role  a row from alpine_roles(), or a role_id
 * @param int|null     $count how many people to name it for. Defaults to how
 *                            many actually hold it now.
 */
function alpine_role_title($role, $count = null)
{
    if (is_string($role)) { $role = alpine_role($role); }
    if (!$role) { return ''; }

    $count = ($count === null) ? $role['filled'] : $count;

    return ($count > 1 && $role['title_shared'] !== '')
        ? $role['title_shared']
        : $role['title'];
}

/**
 * The roles the club is inviting somebody into: needed, spare or handover.
 * Empty most of the year, which is the point -- every caller can test it and
 * render nothing at all rather than a "no vacancies at this time" placeholder.
 */
function alpine_roles_asking()
{
    $out = array();
    foreach (alpine_roles() as $r) {
        if ($r['asking']) { $out[] = $r; }
    }
    return $out;
}

/**
 * Only the roles below their minimum -- the ones the club is actually short of.
 * This is the set the homepage may mention. "Room for one more" is a real thing
 * to say on the Get Involved page and noise on a homepage.
 */
function alpine_roles_needed()
{
    $out = array();
    foreach (alpine_roles() as $r) {
        if ($r['state'] === ALPINE_ROLE_NEEDED) { $out[] = $r; }
    }
    return $out;
}

/**
 * How a role's staffing reads on a page, in plain words.
 *
 * Returns '' for anything with nothing to say, so a caller can print it
 * unconditionally. Deliberately never says "vacant", never says "nobody is
 * doing this", and never prints the minimum and maximum at a visitor: those two
 * numbers are how the site decides what to say, not something a reader needs.
 */
function alpine_role_status_line($role)
{
    switch ($role['state']) {

        case ALPINE_ROLE_NEEDED:
            if ($role['filled'] === 0) { return 'Open'; }
            /* Somebody IS doing it and the club wants more. "Open" here would
               read as though nobody were, which is both wrong and rude to the
               person doing the job. */
            return $role['short'] === 1
                ? 'Looking for one more person'
                : 'Looking for ' . alpine_number_word($role['short']) . ' more people';

        case ALPINE_ROLE_SPARE:
            /* An optional job nobody is doing. The club is not short of it --
               it would just be nice. That difference in tone is the entire
               reason min_people and max_people are separate numbers. */
            if ($role['filled'] === 0) { return 'Open, if somebody wants it'; }
            return $role['spare'] === 1
                ? 'Room for one more'
                : 'Room for ' . alpine_number_word($role['spare']) . ' more';

        case ALPINE_ROLE_HANDOVER:
            return $role['recruiting'];             /* a human's own sentence */

        default:
            return '';
    }
}

/**
 * How one role reads inside "the club is short ___" on the homepage.
 *
 * The distinction is mechanical, so no template should have to make it: a job
 * nobody holds needs "a Talks Coordinator", while a job with one of its two
 * presidents needs "a SECOND President".
 *
 * The title is used here as WORDS -- to pick "a" or "an" -- and never to decide
 * anything. That is the line this file does not cross.
 */
function alpine_role_need_phrase($role)
{
    $title = alpine_role_title($role, $role['filled'] + 1);
    $an    = (strpos('aeiouAEIOU', substr($title, 0, 1)) !== false) ? 'an ' : 'a ';

    if ($role['filled'] === 0) { return $an . $title; }
    if ($role['filled'] === 1) { return 'a second ' . $title; }
    return 'another ' . $title;
}

/**
 * "Film Festival Coordinator and Talks Coordinator" -- role titles as one
 * readable phrase. Oxford comma, per docs/WRITING.md.
 *
 * @param array[] $roles rows from alpine_roles()
 */
function alpine_roles_sentence(array $roles)
{
    $names = array();
    foreach ($roles as $r) { $names[] = alpine_role_title($r); }
    return alpine_list_phrase($names);
}

/**
 * Small numbers as words, because "Looking for 2 more people" reads like a
 * form. Anything above six stays a digit: spelling out "eleven" in a club this
 * size would be a stranger sight than the number.
 */
function alpine_number_word($n)
{
    static $words = array(1 => 'one', 2 => 'two', 3 => 'three',
                          4 => 'four', 5 => 'five', 6 => 'six');
    return isset($words[$n]) ? $words[$n] : (string) $n;
}
