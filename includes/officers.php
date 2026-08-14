<?php
/**
 * ============================================================================
 *  Officers: current, past, and the order they appear in.
 * ============================================================================
 *
 *  The roster is data/officers.php. Everything here is presentation:
 *
 *    - anyone with an 'until' year is automatically a past officer. You never
 *      move an entry between lists or delete anybody — you add one line when
 *      they step down, and the site sorts it out. That is what keeps the
 *      alumni list honest: it accumulates by itself.
 *
 *    - officers are ordered by the seniority of their role, and people sharing
 *      a role sit together, alphabetically. See $ROLE_RANK below.
 * ============================================================================
 */

/**
 * Role seniority. Lower sorts first. Anything not listed here comes after the
 * listed roles, ordered alphabetically by role name — which is what you want
 * for activity leaders, where no role outranks another.
 *
 * Edit this if the club invents a new officer position.
 */
/**
 * The office behind a title, ignoring any "Co-" prefix.
 * "Co-President" and "President" are both the office `president`.
 */
function alpine_role_office($role)
{
    return strtolower(preg_replace('/^co[-\s]*/i', '', trim($role)));
}

/**
 * Drop a "Co-" prefix when only one person holds that office.
 *
 * Officers write "Co-President" in the data file and the page prints
 * "President" as soon as there is only one of them — nobody has to remember to
 * retitle the survivor when a co-lead steps down. It only ever REMOVES the
 * prefix, never adds one: two people can share a job without being "Co-" (two
 * hiking coordinators are just two coordinators), so inventing the prefix
 * would produce titles nobody uses.
 */
function alpine_display_role($role, $holders)
{
    $role = trim($role);
    if ($holders > 1) { return $role; }
    if (!preg_match('/^co[-\s]*(.+)$/i', $role, $m)) { return $role; }

    $bare = trim($m[1]);
    return $bare === '' ? $role : ucfirst($bare);
}

function alpine_role_rank($role)
{
    static $ranks = array(
        'president'                 => 10,
        'co-president'              => 10,
        'vice president'            => 20,
        'vice-president'            => 20,
        'treasurer'                 => 30,
        'secretary'                 => 40,
        'film festival coordinator' => 50,
        'gear officer'              => 60,
        'partnerships & deals lead' => 70,
        'webmaster'                 => 80,
    );
    $key = strtolower(trim($role));
    return isset($ranks[$key]) ? $ranks[$key] : 500;
}

/**
 * Sort officers: role seniority, then role name, then person's name.
 * Two people with the same title always end up next to each other.
 */
function alpine_sort_officers(array $people)
{
    usort($people, function ($a, $b) {
        $ra = alpine_role_rank(isset($a['role']) ? $a['role'] : '');
        $rb = alpine_role_rank(isset($b['role']) ? $b['role'] : '');
        if ($ra !== $rb) { return $ra - $rb; }

        $roleCmp = strcasecmp(isset($a['role']) ? $a['role'] : '', isset($b['role']) ? $b['role'] : '');
        if ($roleCmp !== 0) { return $roleCmp; }

        return strcasecmp($a['name'], $b['name']);
    });
    return $people;
}

/**
 * The roster, split and sorted.
 *
 * @return array{current: array<string, array[]>, past: array<int, array[]>}
 *         current is keyed by group heading, in the order groups first appear
 *         in the data file. past is keyed by the year they finished, newest
 *         first.
 */
function alpine_officers()
{
    $all = alpine_data('officers');

    $current = array();
    $past    = array();

    foreach ($all as $o) {
        if (empty($o['name'])) { continue; }

        if (!empty($o['until'])) {
            $past[(string) $o['until']][] = $o;
        } else {
            $group = !empty($o['group']) ? $o['group'] : 'Officers';
            $current[$group][] = $o;
        }
    }

    foreach ($current as $group => $people) {
        $current[$group] = alpine_sort_officers($people);
    }

    krsort($past, SORT_NATURAL);            // most recent year first
    foreach ($past as $year => $people) {
        $past[$year] = alpine_sort_officers($people);
    }

    /* Count how many people currently hold each office, then let
       alpine_display_role() drop a redundant "Co-" prefix. Past officers keep
       whatever title they actually held at the time. */
    $holders = array();
    foreach ($current as $people) {
        foreach ($people as $o) {
            $office = alpine_role_office($o['role']);
            $holders[$office] = isset($holders[$office]) ? $holders[$office] + 1 : 1;
        }
    }
    foreach ($current as $group => $people) {
        foreach ($people as $i => $o) {
            $office = alpine_role_office($o['role']);
            $current[$group][$i]['displayRole'] =
                alpine_display_role($o['role'], $holders[$office]);
        }
    }
    foreach ($past as $year => $people) {
        foreach ($people as $i => $o) {
            $past[$year][$i]['displayRole'] = $o['role'];
        }
    }

    return array('current' => $current, 'past' => $past);
}

/**
 * The person currently holding a role, or null if nobody does.
 *
 * Matching is on the role title exactly as written in data/officers.php, case
 * insensitively. Past officers are never returned — an alumnus is not the
 * person to email.
 */
function alpine_officer_for($role)
{
    $roster = alpine_officers();
    foreach ($roster['current'] as $people) {
        foreach ($people as $o) {
            if (isset($o['role']) && strcasecmp(trim($o['role']), trim($role)) === 0) {
                return $o;
            }
        }
    }
    return null;
}

/**
 * Which stick figure stands in for someone with no headshot.
 *
 * Chosen from their name, so it is the same every time the page loads rather
 * than shuffling, and two officers side by side are unlikely to match.
 */
function alpine_officer_figure($name)
{
    static $figures = array(
        'hiker', 'climber', 'skier', 'runner', 'biker',
        'alpinist', 'camper', 'belayer', 'paddler', 'summit',
    );
    return $figures[crc32(strtolower($name)) % count($figures)];
}
