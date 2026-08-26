<?php
/**
 * ============================================================================
 *  Roles: the jobs that exist, and which of them nobody is doing.
 * ============================================================================
 *
 *  Two files, one join. data/roles.csv says which jobs exist; data/officers.csv
 *  says who holds them. A role with no current holder is open.
 *
 *  WHY IT IS DERIVED AND NEVER TYPED
 *  ---------------------------------
 *  The obvious design is one file with a status column: President / Jane Doe /
 *  filled. It does not survive a year. Marking a role vacant takes one edit and
 *  UNMARKING it takes a second one, and the second edit is the one nobody
 *  remembers, so the site ends up advertising a job that was filled in October.
 *  A claim about the world that a human has to keep re-asserting is a claim
 *  that goes stale.
 *
 *  So there is no status column. An officer does the one thing they already do
 *  -- put a year in the 'until' column when somebody steps down -- and the
 *  vacancy appears by itself. Add the new person and it disappears by itself.
 *  Nothing to remember, and the site cannot contradict its own roster because
 *  both readings come from the same rows.
 *
 *  This is the same conclusion the GSC website reached, in its own words:
 *  "the address outlives whoever holds the seat, but a claim that a seat is
 *  vacant goes stale the day it is filled."
 *
 *  The one thing that IS typed is the 'recruiting' column, for the case
 *  derivation cannot see: a role that is filled and still needs somebody,
 *  because the holder is stepping down. That is a human's sentence and it is
 *  shown as one.
 * ============================================================================
 */

require_once __DIR__ . '/officers.php';

/**
 * Every role, in display order, with its current holders attached.
 *
 * Each entry carries everything from data/roles.csv plus:
 *   holders    array of current officer rows (may be empty)
 *   filled     how many people currently hold it
 *   seats      how many the club wants, or null for "as many as turn up"
 *   open       true when nobody holds it, or when it is short of its seats
 *   openSeats  how many are going spare, or null when seats is blank
 *   wanted     true when it is open OR the recruiting column says something
 *
 * @return array[] keyed by nothing in particular; iterate it.
 */
function alpine_roles()
{
    static $cache = null;
    if ($cache !== null) { return $cache; }

    $roles  = alpine_data('roles');
    $roster = alpine_officers();

    /* Current holders, bucketed by office. alpine_role_office() is what makes
       "Co-President" and "President" the same job, so the roles file never has
       to know whether the club currently has one president or two. */
    $holders = array();
    foreach ($roster['current'] as $people) {
        foreach ($people as $o) {
            $holders[alpine_role_office($o['role'])][] = $o;
        }
    }

    $out = array();
    foreach ($roles as $r) {
        if (empty($r['role'])) { continue; }

        $office = alpine_role_office($r['role']);
        $mine   = isset($holders[$office]) ? $holders[$office] : array();
        $filled = count($mine);

        /* Blank seats means "as many as want to do it". Such a role is only
           open when nobody at all is doing it -- a second hiking coordinator is
           always welcome, but that is not news and does not belong on a page
           announcing what the club needs. */
        $seats  = ($r['seats'] === '' || $r['seats'] === null) ? null : (int) $r['seats'];
        $short  = ($seats !== null) ? max(0, $seats - $filled) : ($filled === 0 ? 1 : 0);

        $r['holders']    = alpine_sort_officers($mine);
        $r['filled']     = $filled;
        $r['seats']      = $seats;
        $r['open']       = $short > 0;
        $r['openSeats']  = ($seats !== null) ? $short : null;
        $r['recruiting'] = isset($r['recruiting']) ? trim($r['recruiting']) : '';
        $r['wanted']     = $r['open'] || $r['recruiting'] !== '';
        $r['order']      = isset($r['order']) && $r['order'] !== '' ? (int) $r['order'] : 500;

        $out[] = $r;
    }

    usort($out, function ($a, $b) {
        if ($a['order'] !== $b['order']) { return $a['order'] - $b['order']; }
        return strcasecmp($a['role'], $b['role']);
    });

    return $cache = $out;
}

/**
 * The roles grouped under their headings, in the order the groups first appear
 * in data/roles.csv. Same shape as alpine_officers()['current'].
 */
function alpine_roles_by_group()
{
    $groups = array();
    foreach (alpine_roles() as $r) {
        $g = !empty($r['group']) ? $r['group'] : 'Roles';
        $groups[$g][] = $r;
    }
    return $groups;
}

/**
 * Only the roles the club needs somebody for. Empty array most of the year,
 * which is the point: every caller can test it and render nothing.
 *
 * @return array[]
 */
function alpine_roles_wanted()
{
    $out = array();
    foreach (alpine_roles() as $r) {
        if ($r['wanted']) { $out[] = $r; }
    }
    return $out;
}

/**
 * One role by title, or null. Matching ignores a "Co-" prefix and case.
 */
function alpine_role($title)
{
    $want = alpine_role_office($title);
    foreach (alpine_roles() as $r) {
        if (alpine_role_office($r['role']) === $want) { return $r; }
    }
    return null;
}

/**
 * How one role reads inside "the club is short ___".
 *
 * The distinction matters and it is mechanical, so the template should not have
 * to make it: a role nobody holds needs "a Talks Coordinator", while a role with
 * one of its two seats filled needs "a SECOND President". Saying "a President"
 * there would read as though the club had none, which is both wrong and rude to
 * the person doing the job.
 */
function alpine_role_need_phrase($role)
{
    $name = $role['role'];
    $an   = strpos('aeiouAEIOU', substr($name, 0, 1)) !== false ? 'an ' : 'a ';

    if ($role['recruiting'] !== '' && !$role['open']) {
        return 'a new ' . $name;          /* filled, but the holder is going */
    }
    if ($role['filled'] === 0) {
        return $an . $name;
    }
    if ($role['filled'] === 1) {
        return 'a second ' . $name;
    }
    return 'another ' . $name;
}

/**
 * "Film Festival Coordinator and Talks Coordinator" -- the open roles as one
 * readable phrase, for the homepage line. Oxford comma, per docs/WRITING.md.
 *
 * @param array[] $roles from alpine_roles_wanted()
 */
function alpine_roles_sentence(array $roles)
{
    $names = array();
    foreach ($roles as $r) { $names[] = $r['role']; }
    return alpine_list_phrase($names);
}

/**
 * How a role's availability reads on a page.
 *
 * Returns '' for a role that is fully staffed and not recruiting, so a caller
 * can print it unconditionally.
 */
function alpine_role_status_line($role)
{
    if (!empty($role['recruiting'])) {
        return $role['recruiting'];
    }
    if (!$role['open']) {
        return '';
    }
    if ($role['filled'] === 0) {
        return 'Nobody is doing this at the moment.';
    }
    /* Filled but short: the club wants two and has one. Say the numbers rather
       than the word "vacant", which reads as an unfilled post rather than an
       invitation. */
    return $role['filled'] . ' of ' . $role['seats'] . ' filled.';
}
