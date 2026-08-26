<?php
/**
 * ============================================================================
 *  Officers: current, past, and the order they appear in.
 * ============================================================================
 *
 *  The roster is data/officers.csv. Everything here is presentation:
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
 * Everything data/roles.csv knows about one job, or null.
 *
 * Matching ignores case and a "Co-" prefix, so "Co-President" finds the row
 * that says President.
 */
function alpine_role_meta($role)
{
    static $index = null;

    if ($index === null) {
        $index = array();
        foreach (alpine_data('roles') as $r) {
            if (empty($r['role'])) { continue; }
            $index[alpine_role_office($r['role'])] = $r;
        }
    }

    $key = alpine_role_office($role);
    return isset($index[$key]) ? $index[$key] : null;
}

/**
 * How many people the club wants in this job, or null for "as many as turn up".
 */
function alpine_role_seats($role)
{
    $meta = alpine_role_meta($role);
    if (!$meta || !isset($meta['seats']) || $meta['seats'] === '') { return null; }
    return (int) $meta['seats'];
}

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

    /* A job the club wants TWO people in keeps its "Co-" even while only one
       person is doing it. Dropping it there was actively misleading: a lone
       co-president rendered as "President", which is exactly how a page hides
       the fact that the other seat is empty. The prefix is the visible half of
       the vacancy, so it stays until data/roles.csv says one seat is enough. */
    $seats = alpine_role_seats($role);
    if ($seats !== null && $seats > 1) { return $role; }

    if (!preg_match('/^co[-\s]*(.+)$/i', $role, $m)) { return $role; }

    $bare = trim($m[1]);
    return $bare === '' ? $role : ucfirst($bare);
}

/**
 * Role seniority. Lower sorts first, and it comes from the 'order' column in
 * data/roles.csv.
 *
 * This used to be a hardcoded table right here, which meant role knowledge
 * lived in two places -- and they had already drifted: the table ranked a
 * 'webmaster' that appears in no roster. A role the CSV does not mention sorts
 * after the ones it does, alphabetically, which is what you want for activity
 * leaders where no job outranks another.
 */
function alpine_role_rank($role)
{
    $meta = alpine_role_meta($role);
    if (!$meta || !isset($meta['order']) || $meta['order'] === '') { return 500; }
    return (int) $meta['order'];
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
 * Matching is on the role title exactly as written in data/officers.csv, case
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
