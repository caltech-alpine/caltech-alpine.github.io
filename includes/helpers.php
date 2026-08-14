<?php
/**
 * ============================================================================
 *  Small helpers used throughout the templates.
 * ============================================================================
 *  Deliberately few, deliberately short. If you find yourself wanting a
 *  template engine, the site has grown past what it was meant to be.
 * ============================================================================
 */

/**
 * Escape for HTML. Use this around EVERY value that came from the calendar,
 * a data file, or a URL — it is the one habit that keeps the site safe.
 */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Read configuration with a dotted path: cfg('links.slack').
 */
function cfg($path, $default = null)
{
    $node = $GLOBALS['ALPINE_CONFIG'];
    foreach (explode('.', $path) as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) {
            return $default;
        }
        $node = $node[$key];
    }
    return $node;
}

/**
 * Path to a file in assets/.
 *
 * A cache-busting ?v= stamp is appended from the file's modification time, so
 * a CSS change is never hidden behind a stale browser cache — a real problem
 * when someone edits the site the night before an event.
 */
function asset($path)
{
    $path = ltrim($path, '/');
    $file = ALPINE_ROOT . '/assets/' . $path;
    $url  = 'assets/' . $path;
    if (is_readable($file)) {
        $url .= '?v=' . filemtime($file);
    }
    return $url;
}

/**
 * Links are written relative to the site root and every page lives in that
 * root, so this is a passthrough today. It exists so the site keeps working
 * if it is ever deployed into a subdirectory.
 */
function url($path)
{
    return ltrim($path, '/');
}

/** One icon from includes/icons.php. */
function icon($name, $class = 'icon')
{
    if ($name === '') { return ''; }
    return '<svg class="' . e($class) . '" aria-hidden="true" focusable="false">'
         . '<use href="#icon-' . e($name) . '"></use></svg>';
}

/**
 * Load a data file from data/, returning an array.
 *
 * These files are edited by officers, not programmers, so a stray comma is a
 * question of when rather than if. A syntax error inside an included file
 * throws ParseError in PHP 7 and up, which — unlike a syntax error in the page
 * itself — can be caught. So we catch it: a broken data file makes one section
 * say "being updated" instead of taking down every page on the site.
 *
 * Run `php tools/check.php` after editing and it will tell you which file is
 * broken and why, rather than leaving you to guess from a blank section.
 */
function alpine_data($name)
{
    static $cache = array();

    $key = basename($name);
    if (isset($cache[$key])) { return $cache[$key]; }

    /* A .csv wins over a .php of the same name. Lists that officers edit often
       — the roster above all — are far safer as CSV: a spreadsheet cannot
       produce a PHP parse error, and the file opens in Excel, Sheets, Notepad
       or the GitHub web editor without anybody learning array syntax. */
    $csv = ALPINE_ROOT . '/data/' . $key . '.csv';
    if (is_readable($csv)) {
        return $cache[$key] = alpine_read_csv($csv);
    }

    $file = ALPINE_ROOT . '/data/' . $key . '.php';
    if (!is_readable($file)) {
        return $cache[$key] = array();
    }

    try {
        $data = require $file;
    } catch (Throwable $e) {
        // Record it for check.php and the debug banner; do not crash the page.
        $GLOBALS['ALPINE_DATA_ERRORS'][$key] = $e->getMessage();
        if (defined('ALPINE_DEBUG') && ALPINE_DEBUG) {
            trigger_error('data/' . $key . '.php is broken: ' . $e->getMessage(), E_USER_WARNING);
        }
        return $cache[$key] = array();
    }

    return $cache[$key] = (is_array($data) ? $data : array());
}

/**
 * Read one of the officer-editable CSVs into the same shape the .php files
 * returned: a list of associative arrays keyed by the header row.
 *
 * Lines beginning with # are skipped, so the file can carry its own
 * instructions at the top — which is the whole reason the roster is allowed to
 * be a CSV rather than a bare table nobody knows how to fill in.
 *
 * Blank cells come back as '' and blank rows are dropped, so a spreadsheet's
 * habit of leaving trailing empty rows does not create nameless officers.
 */
function alpine_read_csv($path)
{
    $rows   = array();
    $header = null;

    $fh = fopen($path, 'r');
    if (!$fh) { return $rows; }

    while (($cells = fgetcsv($fh)) !== false) {
        if ($cells === array(null) || $cells === false) { continue; }   // blank line
        $first = isset($cells[0]) ? trim((string) $cells[0]) : '';
        if ($first !== '' && $first[0] === '#') { continue; }            // a note

        if ($header === null) {
            $header = array_map('trim', $cells);
            continue;
        }

        if (implode('', array_map('trim', $cells)) === '') { continue; } // empty row

        $row = array();
        foreach ($header as $i => $col) {
            if ($col === '') { continue; }
            $row[$col] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
        }
        // 'until' is compared as a year elsewhere, so hand back a number.
        if (isset($row['until'])) {
            $row['until'] = $row['until'] === '' ? null : (int) $row['until'];
            if ($row['until'] === null) { unset($row['until']); }
        }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

/** Any data files that failed to load this request. Used by tools/check.php. */
function alpine_data_errors()
{
    return isset($GLOBALS['ALPINE_DATA_ERRORS']) ? $GLOBALS['ALPINE_DATA_ERRORS'] : array();
}

/**
 * True when a link should be marked as the current page.
 * Compares file names so query strings and anchors do not matter.
 */
function alpine_is_current($href, $currentPage)
{
    $href = strtok(ltrim($href, '/'), '#?');
    return ($href !== '' && $href === $currentPage);
}

/**
 * A background image rule for a hero or tile, or a class that falls back to
 * the drawn topographic pattern when the photo has not been added yet.
 *
 * Photos are optional by design: the site must look finished before anyone
 * has uploaded anything.
 */
function alpine_bg($imageName)
{
    if ($imageName === '' || $imageName === null) { return ''; }
    $file = ALPINE_ROOT . '/assets/images/' . $imageName;
    if (!is_readable($file)) { return ''; }
    return ' style="background-image:url(' . e(asset('images/' . $imageName)) . ')"';
}

/**
 * Initials for someone with no headshot, e.g. "Elise Sledge" -> "ES".
 * Middle names are ignored, so long names do not produce a wall of letters.
 */
function alpine_initials($name)
{
    $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) { return '?'; }
    $first = function_exists('mb_substr') ? mb_substr($parts[0], 0, 1) : substr($parts[0], 0, 1);
    if (count($parts) === 1) { return strtoupper($first); }
    $last = end($parts);
    $lastInitial = function_exists('mb_substr') ? mb_substr($last, 0, 1) : substr($last, 0, 1);
    return strtoupper($first . $lastInitial);
}

/** Does a real photo exist for this slot? */
function alpine_has_image($imageName)
{
    return $imageName !== '' && $imageName !== null
        && is_readable(ALPINE_ROOT . '/assets/images/' . $imageName);
}
