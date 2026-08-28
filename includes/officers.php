<?php
/**
 * ============================================================================
 *  Officers: the roster as the About page wants to read it.
 * ============================================================================
 *
 *  There is no officer data here and no officer file behind it. An "officer" is
 *  just a person from PEOPLE.csv joined to a role from ROLES.csv by a row in
 *  ASSIGNMENTS.csv, and this file is the one place that does that join in the
 *  shape a roster page needs: grouped by heading, then by year for the alumni
 *  list.
 *
 *  Everything it prints comes from somewhere else, which is the point. The name
 *  and email are the person's, written once in PEOPLE.csv. The title and the
 *  "contact them about" line are the job's, written once in ROLES.csv. Nothing
 *  is duplicated, so nothing can disagree.
 *
 *  ORDER. Officers appear in the order ROLES.csv lists their jobs, and people
 *  sharing a job appear together, alphabetically. Moving a row in ROLES.csv
 *  moves the officers too. There is no separate ordering to keep in step, and
 *  nobody has to sort a spreadsheet.
 * ============================================================================
 */

require_once __DIR__ . '/roles.php';

/**
 * One officer entry as the roster renders it: the person's own facts, plus the
 * job's facts under the names a template expects.
 *
 * 'title' is what to call them, worked out from how many people share the job.
 * 'contact_for' describes the job, so two people sharing one say the same
 * thing -- which is correct, and which used to be two cells that could drift.
 */
function alpine_officer_entry(array $person, array $role, $title)
{
    return array(
        'person_id'   => $person['person_id'],
        'name'        => $person['name'],
        'email'       => $person['email'],
        'photo'       => $person['photo'],
        'role_id'     => $role['role_id'],
        'title'       => $title,
        'contact_for' => $role['contact_for'],
    );
}

/**
 * The roster, split and sorted.
 *
 * @return array{current: array<string, array[]>, past: array<int, array[]>}
 *         current is keyed by group heading, in the order the groups first
 *         appear in ROLES.csv. past is keyed by the year they finished,
 *         newest first.
 */
function alpine_officers()
{
    static $cache = null;
    if ($cache !== null) { return $cache; }

    $current = array();
    $past    = array();

    foreach (alpine_roles() as $role) {
        foreach ($role['holders'] as $person) {
            $current[$role['group']][] =
                alpine_officer_entry($person, $role, alpine_role_title($role));
        }
    }

    /* THE ALUMNI LIST IS BUILT FROM THE ASSIGNMENTS, NOT FROM THE ROLES.
       ------------------------------------------------------------------
       That distinction is the whole of this block. Walking the roles and
       collecting their past holders reads more naturally and is wrong: a job
       the club has stopped doing gets its row deleted from ROLES.csv, and
       everybody who ever held it would then quietly vanish from the Past
       officers list along with it. This list exists precisely to outlive the
       jobs, and README.md promises that retiring a role loses nobody.

       So past officers are read from ASSIGNMENTS.csv directly, and the role is
       looked up only to find out what to call them. */
    foreach (alpine_assignments()['past'] as $roleId => $people) {
        $role = alpine_role($roleId);

        foreach ($people as $person) {
            /* A past officer keeps the title they actually held. If the club
               has renamed the job since, ASSIGNMENTS.csv says so in its
               title_held column and it wins -- an alumni list that silently
               restates history in this year's vocabulary is quietly wrong
               about the one thing it exists to record. */
            if ($person['title_held'] !== '') {
                $title = $person['title_held'];
            } elseif ($role) {
                $title = alpine_role_title($role, 1);
            } else {
                /* The job is gone and nobody wrote down what it was called, so
                   we genuinely do not know. Print the person without a title
                   rather than inventing one out of the role_id. tools/check.php
                   asks for a title_held before it can come to this. */
                $title = '';
            }

            $past[$person['until']][] = $role
                ? alpine_officer_entry($person, $role, $title)
                : array(
                    'person_id'   => $person['person_id'],
                    'name'        => $person['name'],
                    'email'       => $person['email'],
                    'photo'       => $person['photo'],
                    'role_id'     => $roleId,
                    'title'       => $title,
                    'contact_for' => '',
                );
        }
    }

    krsort($past, SORT_NUMERIC);                     // most recent year first
    foreach ($past as $year => $people) {
        $past[$year] = alpine_sort_people($people);
    }

    return $cache = array('current' => $current, 'past' => $past);
}

/**
 * Everyone currently holding any role, in roster order. Used by tools/check.php
 * to ask how many serving officers have an email address and a headshot.
 */
function alpine_serving_officers()
{
    $out = array();
    foreach (alpine_officers()['current'] as $people) {
        foreach ($people as $o) { $out[] = $o; }
    }
    return $out;
}

/**
 * One person and their address as a mailto link, or just their name when the
 * roster has no address for them.
 *
 * Every place a person's email appears on the site comes through here, so the
 * markup is the same everywhere and there is no page that shows a name without
 * making it possible to write to them.
 *
 * @param array  $person  a row from alpine_people(), or an officer entry
 * @param string $subject optional mail subject, already plain text
 */
function alpine_person_link(array $person, $subject = '')
{
    $name = e($person['name']);
    if (empty($person['email'])) { return $name; }

    $href = 'mailto:' . $person['email'];
    if ($subject !== '') { $href .= '?subject=' . rawurlencode($subject); }

    return '<a href="' . e($href) . '">' . $name . '</a>';
}
