<?php
/**
 * ============================================================================
 *  People: the humans, read once from data/people.csv.
 * ============================================================================
 *
 *  A person's name, email address and photograph are written in exactly one
 *  place, and every page that shows them reads it from here. Changing an email
 *  address is one edit in one file, and the About page, the Get Involved page,
 *  the gear page and the deals note all follow.
 *
 *  That is not a tidiness preference. The version of this site before it kept
 *  the address next to the ROLE, which meant the same person's address existed
 *  once per job they held and once per page that mentioned them; the failure
 *  mode is not a crash, it is an address that is right in one place and eight
 *  months out of date in another, and nothing on the page tells you which.
 *
 *  Nothing here knows what a role is. That lives in roles.php.
 * ============================================================================
 */

/**
 * Everyone, keyed by person_id.
 *
 * Rows with no person_id or no name are dropped rather than rendered as a
 * nameless officer -- a spreadsheet's habit of leaving a trailing empty row
 * should not put a blank card on the About page. tools/check.php reports them.
 *
 * @return array<string, array{person_id:string,name:string,email:string,photo:string}>
 */
function alpine_people()
{
    static $cache = null;
    if ($cache !== null) { return $cache; }

    $out = array();
    foreach (alpine_data('people') as $p) {
        $id = isset($p['person_id']) ? trim($p['person_id']) : '';
        if ($id === '' || empty($p['name'])) { continue; }

        $out[$id] = array(
            'person_id' => $id,
            'name'      => trim($p['name']),
            'email'     => isset($p['email']) ? trim($p['email']) : '',
            'photo'     => isset($p['photo']) ? trim($p['photo']) : '',
        );
    }

    return $cache = $out;
}

/** One person by id, or null. */
function alpine_person($id)
{
    $people = alpine_people();
    $id = trim((string) $id);
    return isset($people[$id]) ? $people[$id] : null;
}

/**
 * Sort people by name. Used wherever two people share a job, so the pair is in
 * a stable order rather than whatever order the CSV happened to be in.
 */
function alpine_sort_people(array $people)
{
    usort($people, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    return $people;
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
