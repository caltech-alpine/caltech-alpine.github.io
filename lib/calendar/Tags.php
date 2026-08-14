<?php
/**
 * ============================================================================
 *  Activity tags — the bridge between Google Calendar titles and the site.
 * ============================================================================
 *
 *  The list itself lives in data/activities.php, which is the file officers
 *  edit. These functions just read it. Kept separate from the parser so that
 *  "what counts as a hike" is a content decision, not a code change.
 * ============================================================================
 */

/** The activity table, loaded once. */
function alpine_activities()
{
    static $activities = null;
    if ($activities === null) {
        $file = dirname(dirname(__DIR__)) . '/data/activities.php';
        $activities = is_readable($file) ? require $file : array();
    }
    return $activities;
}

/** One activity's definition, or null. */
function alpine_activity($key)
{
    $all = alpine_activities();
    return isset($all[$key]) ? $all[$key] : null;
}

/** The label shown on an event card, e.g. 'hike' -> "Hiking". */
function alpine_activity_label($key)
{
    $a = alpine_activity($key);
    return $a ? $a['label'] : '';
}

/**
 * Map whatever was typed between [brackets] onto an activity key.
 * "[HIKE]", "[Hiking]" and "[BACKPACKING]" all land on 'hike'.
 *
 * @return string activity key, or '' if it matches nothing we know
 */
function alpine_normalise_tag($raw)
{
    $needle = strtolower(trim($raw));
    if ($needle === '') { return ''; }

    foreach (alpine_activities() as $key => $a) {
        if ($needle === $key) { return $key; }
        if (!empty($a['aliases']) && in_array($needle, $a['aliases'], true)) { return $key; }
        if (strtolower($a['label']) === $needle) { return $key; }
    }
    return '';
}

/**
 * Last resort for events with no [TAG]: guess from words in the title.
 *
 * Only used when there is no explicit prefix, and only on whole-word matches,
 * because a wrong label is worse than none. Returns '' when nothing is clear,
 * and the card then renders without an activity label — which is fine.
 */
function alpine_guess_tag($title)
{
    $haystack = ' ' . strtolower($title) . ' ';

    foreach (alpine_activities() as $key => $a) {
        if (empty($a['keywords'])) { continue; }
        foreach ($a['keywords'] as $word) {
            $pattern = '/\b' . preg_quote(strtolower($word), '/') . '/u';
            if (preg_match($pattern, $haystack)) {
                return $key;
            }
        }
    }
    return '';
}
