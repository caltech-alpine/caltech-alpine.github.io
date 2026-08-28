<?php
/**
 * ============================================================================
 *  Does the data make sense? One function, run before the site is published.
 * ============================================================================
 *
 *  The three data files point at each other by id, and a typo in an id is the
 *  one mistake that is INVISIBLE on the rendered page. Misspell a role_id in
 *  ASSIGNMENTS.csv and the officer simply does not appear, the job shows as
 *  open, and every page looks entirely normal while advertising a vacancy in a
 *  job somebody is doing. Nothing about the site would tell you, which is
 *  exactly why this file exists.
 *
 *  It is a list of obvious questions, not a framework. Each one returns a
 *  sentence a person can act on -- which file, which row, and what to do --
 *  because the person reading it is next year's secretary and not a programmer.
 *
 *      php tools/check.php          runs these among its other checks
 *      php tools/check.php --data   runs only these, and exits 1 on any
 *
 *  tools/build_static.py runs it too, and refuses to publish if anything is
 *  wrong. A broken roster should fail the build, not reach the club's website.
 * ============================================================================
 */

require_once __DIR__ . '/roles.php';

/**
 * Everything wrong with PEOPLE.csv, ROLES.csv and ASSIGNMENTS.csv, as a
 * list of sentences. An empty list means all good.
 *
 * @param string[] $requiredRoles role_ids the site's own pages look for by
 *                                name, as role_id => "what needs it"
 * @return string[]
 */
function alpine_data_problems(array $requiredRoles = array())
{
    $problems = array();

    $people      = alpine_data('people');
    $roles       = alpine_data('roles');
    $assignments = alpine_data('assignments');

    /* ------------------------------------------------------------- people */
    $personIds = array();
    foreach ($people as $i => $p) {
        $row = $i + 1;
        $id  = isset($p['person_id']) ? trim($p['person_id']) : '';

        if ($id === '') {
            $problems[] = "PEOPLE.csv row $row has no person_id. Every person needs a "
                        . "short permanent id, like jane-doe.";
            continue;
        }
        if (isset($personIds[$id])) {
            $problems[] = "PEOPLE.csv has two people with person_id '$id'. Ids must be "
                        . "unique -- if two people would collide, make it '{$id}-2' or "
                        . "add an initial, and update ASSIGNMENTS.csv to match.";
        }
        $personIds[$id] = true;

        if (empty($p['name'])) {
            $problems[] = "PEOPLE.csv: '$id' has no name.";
        }
        if (!empty($p['email']) && strpos($p['email'], '@') === false) {
            $problems[] = "PEOPLE.csv: '$id' has an email address with no @ in it "
                        . "('{$p['email']}').";
        }
        /* A photo named but not present renders as initials, which is fine and
           deliberate -- but it is usually a typo in the filename, so say so. */
        if (!empty($p['photo'])
            && !is_readable(ALPINE_ROOT . '/assets/images/officers/' . $p['photo'])) {
            $problems[] = "PEOPLE.csv: '$id' names a photo "
                        . "'assets/images/officers/{$p['photo']}' that is not there. "
                        . "The page will show their initials instead. Check the spelling, "
                        . "or clear the cell.";
        }
    }

    /* -------------------------------------------------------------- roles */
    $roleIds = array();
    foreach ($roles as $i => $r) {
        $row = $i + 1;
        $id  = isset($r['role_id']) ? trim($r['role_id']) : '';

        if ($id === '') {
            $problems[] = "ROLES.csv row $row has no role_id. Every job needs a short "
                        . "permanent id, like film_festival.";
            continue;
        }
        if (isset($roleIds[$id])) {
            $problems[] = "ROLES.csv has two jobs with role_id '$id'. Ids must be unique "
                        . "-- rename one of them, and update ASSIGNMENTS.csv to match.";
        }
        $roleIds[$id] = true;

        if (empty($r['title'])) {
            $problems[] = "ROLES.csv: '$id' has no title, so the page has nothing to call it.";
        }
        if (empty($r['group'])) {
            $problems[] = "ROLES.csv: '$id' has no group, so it has no heading to appear under.";
        }

        $minRaw = isset($r['min_people']) ? trim($r['min_people']) : '';
        $maxRaw = isset($r['max_people']) ? trim($r['max_people']) : '';

        foreach (array('min_people' => $minRaw, 'max_people' => $maxRaw) as $col => $raw) {
            if ($raw !== '' && !ctype_digit($raw)) {
                $problems[] = "ROLES.csv: '$id' has $col = '$raw'. It must be a whole "
                            . "number that is zero or more, or empty."
                            . ($col === 'max_people'
                                ? ' Empty means as many volunteers as turn up.'
                                : ' Empty means 1.');
            }
        }

        $min = $minRaw === '' ? 1    : (int) $minRaw;
        $max = $maxRaw === '' ? null : (int) $maxRaw;

        if ($max !== null && $min > $max) {
            $problems[] = "ROLES.csv: '$id' needs at least $min people but allows at most "
                        . "$max. min_people must not be larger than max_people.";
        }
        if ($max !== null && $max === 0) {
            $problems[] = "ROLES.csv: '$id' has max_people = 0, so nobody can ever hold it. "
                        . "If the club has stopped doing this job, delete the row.";
        }
    }

    /* -------------------------------------------------------- assignments */
    $seen  = array();
    $count = array();
    foreach ($assignments as $i => $a) {
        $row      = $i + 1;
        $personId = isset($a['person_id']) ? trim($a['person_id']) : '';
        $roleId   = isset($a['role_id'])   ? trim($a['role_id'])   : '';

        if ($personId === '' || $roleId === '') {
            $problems[] = "ASSIGNMENTS.csv row $row is missing a person_id or a role_id.";
            continue;
        }
        if (!isset($personIds[$personId])) {
            $problems[] = "ASSIGNMENTS.csv row $row gives a job to '$personId', who is not "
                        . "in PEOPLE.csv. Add them there first -- one row with their "
                        . "name, email and photo.";
        }
        $until     = !empty($a['until']) ? (string) $a['until'] : '';
        $titleHeld = isset($a['title_held']) ? trim($a['title_held']) : '';

        /* A missing role_id means two different things, depending on whether
           the person is still serving.

           Still serving: a mistake, always. Somebody is doing a job the site
           does not know exists, so they vanish from the roster entirely.

           Finished: legitimate. Retiring a job means deleting its row from
           ROLES.csv, and the people who held it have to survive that -- the
           Past officers list exists to outlive the jobs. The only thing still
           wanted from them is what they were CALLED, and the deleted row was
           the last record of it. */
        if (!isset($roleIds[$roleId])) {
            if ($until === '') {
                $problems[] = "ASSIGNMENTS.csv row $row puts somebody who is still serving in "
                            . "'$roleId', which is not a role_id in ROLES.csv. They will not "
                            . "appear on the roster at all. Check the spelling, or add the "
                            . "job to ROLES.csv.";
            } elseif ($titleHeld === '') {
                $problems[] = "ASSIGNMENTS.csv row $row: '$personId' held '$roleId', which is "
                            . "no longer in ROLES.csv, and their title_held cell is empty -- "
                            . "so nothing records what that job was called, and the Past "
                            . "officers list has no title to show for them. Put the title "
                            . "they held in the title_held column. (If you have just retired "
                            . "a job, this is the one thing to do before deleting its row.)";
            }
        }

        $key = $personId . '|' . $roleId . '|' . $until;
        if (isset($seen[$key])) {
            $problems[] = "ASSIGNMENTS.csv lists '$personId' in '$roleId' twice. Delete the "
                        . "duplicate row -- otherwise they are counted twice and the job "
                        . "looks fuller than it is.";
        }
        $seen[$key] = true;

        if ($until === '') {
            $count[$roleId] = isset($count[$roleId]) ? $count[$roleId] + 1 : 1;
        } elseif (!ctype_digit($until)) {
            $problems[] = "ASSIGNMENTS.csv row $row has until = '$until'. It should be a "
                        . "year, like 2027, or empty while they are still serving.";
        }
    }

    /* ------------------------------------------------- more than there is room for */
    foreach ($roles as $r) {
        $id = isset($r['role_id']) ? trim($r['role_id']) : '';
        if ($id === '' || empty($r['max_people']) || !ctype_digit(trim($r['max_people']))) {
            continue;
        }
        $max    = (int) $r['max_people'];
        $filled = isset($count[$id]) ? $count[$id] : 0;
        if ($filled > $max) {
            $problems[] = "'$id' has $filled people serving in it but ROLES.csv allows $max. "
                        . "Either raise max_people, or put a year in the 'until' column of "
                        . "whoever has finished, in ASSIGNMENTS.csv.";
        }
    }

    /* ------------------------------------- role_ids the site itself depends on */
    /* A page that needs a particular job -- the gear page writes to whoever holds
       'gear' -- degrades quietly to the general mailbox if that id disappears.
       Quietly is the problem: renaming a role_id is legal, and this is the only
       thing that would notice. */
    foreach ($requiredRoles as $id => $why) {
        if (!isset($roleIds[$id])) {
            $problems[] = "ROLES.csv has no role_id '$id', and $why. Either put the row "
                        . "back with that id, or change the id the page asks for.";
        }
    }

    return $problems;
}

/**
 * The role_ids the site's own pages look up by name, and what would break.
 *
 * ADD TO THIS LIST whenever a page starts asking for a specific job. It is the
 * only register of the ids that are not merely data -- everything else in
 * ROLES.csv can be renamed, moved or deleted freely.
 */
function alpine_required_roles()
{
    return array(
        'gear'         => 'the Gear page shows that officer as the person to book '
                        . 'specialist equipment through (gear.php)',
        'partnerships' => 'the member-deals note names that officer as who to ask '
                        . 'about discounts (includes/benefits.php)',
    );
}
