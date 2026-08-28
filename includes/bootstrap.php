<?php
/**
 * ============================================================================
 *  Bootstrap — the first line of every page.
 * ============================================================================
 *
 *      <?php require __DIR__ . '/includes/bootstrap.php';
 *
 *  Loads configuration, helpers and the calendar. Nothing here needs editing
 *  during normal club operation.
 * ============================================================================
 */

if (defined('ALPINE_BOOTSTRAPPED')) { return; }
define('ALPINE_BOOTSTRAPPED', true);

define('ALPINE_ROOT', dirname(__DIR__));

/* --------------------------------------------------------------------------
 *  Errors
 *  On a public server we never want a stack trace in front of a visitor.
 *  Set ALPINE_DEBUG=1 in the environment (or edit the line below while you are
 *  developing) to see problems on screen instead.
 * ------------------------------------------------------------------------ */
$alpineDebug = (getenv('ALPINE_DEBUG') === '1');
/* display_errors FIRST. Whatever the next line does, nothing this file
   evaluates can print to the page before this is settled -- which is exactly
   how a notice used to escape: it was raised while working out the argument
   to error_reporting(), i.e. one statement too early to be suppressed, and
   landed ahead of <!DOCTYPE html>. Anything before the doctype puts the
   browser in quirks mode and breaks any later header() call. */
ini_set('display_errors', $alpineDebug ? '1' : '0');
/* E_STRICT is deliberately not named here. It has done nothing since PHP 8.0
   (its messages were folded into the other levels), the constant itself is
   deprecated in 8.4, and merely mentioning it made 8.4 emit
   "Constant E_STRICT is deprecated" on every page. On PHP 7.4 dropping it
   means E_STRICT-level messages are logged rather than ignored; they are
   still never displayed. */
error_reporting($alpineDebug ? E_ALL : (E_ALL & ~E_NOTICE & ~E_DEPRECATED));
define('ALPINE_DEBUG', $alpineDebug);

/* --------------------------------------------------------------------------
 *  Configuration
 *
 *  includes/config.php     is committed and public.
 *  includes/config.local.php  is OPTIONAL, git-ignored, and overrides it.
 *                          That is where a secret would go if we ever needed
 *                          one — so nothing secret is ever committed.
 * ------------------------------------------------------------------------ */
$ALPINE_CONFIG = require __DIR__ . '/config.php';

$localConfig = __DIR__ . '/config.local.php';
if (is_readable($localConfig)) {
    $overrides = require $localConfig;
    if (is_array($overrides)) {
        $ALPINE_CONFIG = alpine_merge_config($ALPINE_CONFIG, $overrides);
    }
}

// An environment variable beats both, for hosts that provide one.
$envKey = getenv('ALPINE_GCAL_API_KEY');
if ($envKey) {
    $ALPINE_CONFIG['calendar']['api_key'] = $envKey;
}

$GLOBALS['ALPINE_CONFIG'] = $ALPINE_CONFIG;

/* --------------------------------------------------------------------------
 *  Clock
 *  Event times carry their own zone, so they were always right. Anything
 *  printed with plain date() - the "calendar last checked" stamp, the
 *  diagnostics pages - used PHP's default, which on this host is UTC, and
 *  therefore read seven hours ahead of everyone looking at it. One line here
 *  fixes every such call site at once.
 * ------------------------------------------------------------------------ */
if (!empty($ALPINE_CONFIG['calendar']['timezone'])) {
    date_default_timezone_set($ALPINE_CONFIG['calendar']['timezone']);
}

/* -------------------------------------------------------------------------- */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/activity.php';
require_once ALPINE_ROOT . '/lib/calendar/Calendar.php';

AlpineCalendar::configure($ALPINE_CONFIG['calendar']);

/* One line per page view. Silently does nothing when logging is off, when
   the static builder is running, or on the command line. */
alpine_activity_page();

/**
 * Recursive array merge where the override wins for scalars but nested arrays
 * are merged rather than replaced wholesale.
 */
function alpine_merge_config(array $base, array $over)
{
    foreach ($over as $k => $v) {
        if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
            $base[$k] = alpine_merge_config($base[$k], $v);
        } else {
            $base[$k] = $v;
        }
    }
    return $base;
}
