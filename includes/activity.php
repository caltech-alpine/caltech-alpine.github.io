<?php
/**
 * ============================================================================
 *  ACTIVITY LOG — how much the site is used, without tracking anybody
 * ============================================================================
 *
 *  No cookies. No JavaScript. No third party. Nothing leaves this server, so
 *  there is no consent banner to add and nothing to disclose beyond Caltech's
 *  own privacy notice.
 *
 *  HOW IT COUNTS PEOPLE WITHOUT IDENTIFYING THEM
 *  ---------------------------------------------
 *  Each line carries a visitor token: the first 12 hex characters of
 *
 *      sha256( IP + user agent + a secret salt + today's date )
 *
 *  Because the date is inside the hash, the SAME person gets a different token
 *  tomorrow. That is deliberate and it is the whole privacy design: you can ask
 *  "roughly how many different people came today" without ever storing an
 *  address, and the ability to link a token back to anyone expires at midnight.
 *  The salt lives in logs/.salt, is generated once, and is never committed.
 *
 *  Raw daily files are deleted after activity.retain_days. Nothing here is
 *  meant to be kept forever, and nothing here would help you if it were.
 *
 *  WHERE IT DOES NOT RUN
 *  ---------------------
 *  tools/build_static.py sets ALPINE_STATIC=1 while it renders the site for
 *  GitHub Pages. That switches this off, so the build does not log its own
 *  crawl and outbound links come out as plain URLs — because go.php needs PHP
 *  and a static host has none.
 * ============================================================================
 */

/** Is logging on for this request? */
function alpine_activity_on()
{
    static $on = null;
    if ($on !== null) { return $on; }

    // The static builder is not a visitor.
    if (getenv('ALPINE_STATIC')) { return $on = false; }
    if (!cfg('activity.enabled')) { return $on = false; }
    if (PHP_SAPI === 'cli') { return $on = false; }

    $dir = cfg('activity.dir');
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    return $on = (is_dir($dir) && is_writable($dir));
}

/** The per-install secret behind the visitor token. Created once, never shared. */
function alpine_activity_salt()
{
    static $salt = null;
    if ($salt !== null) { return $salt; }

    $file = cfg('activity.dir') . '/.salt';
    if (is_readable($file)) {
        $salt = trim(file_get_contents($file));
        if ($salt !== '') { return $salt; }
    }
    $salt = bin2hex(function_exists('random_bytes')
        ? random_bytes(16)
        : pack('N4', mt_rand(), mt_rand(), mt_rand(), mt_rand()));
    @file_put_contents($file, $salt, LOCK_EX);
    @chmod($file, 0600);
    return $salt;
}

/** Obvious crawlers. Not exhaustive, and does not need to be. */
function alpine_activity_is_bot($ua)
{
    return $ua === '' || preg_match(
        '~bot|crawl|spider|slurp|curl|wget|python-requests|headless|monitor|preview~i', $ua);
}

/**
 * One line per request. Tab separated, because a club officer opening this in
 * a spreadsheet should not have to think about quoting.
 *
 * @param string $kind  'page' or 'click'
 * @param string $what  the path, or the name of the outbound link
 */
function alpine_activity_record($kind, $what)
{
    if (!alpine_activity_on()) { return; }

    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (alpine_activity_is_bot($ua)) { return; }

    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $today = gmdate('Y-m-d');
    $token = substr(hash('sha256', $ip . $ua . alpine_activity_salt() . $today), 0, 12);

    /* Referrer HOST only. The full URL can carry a search query or a private
       page title, and the host answers the only question worth asking: which
       channel sent them. */
    $refHost = '';
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $h = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        $self = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $refHost = ($h && $h !== $self) ? $h : '';
    }

    $device = preg_match('~Mobi|Android|iPhone|iPad~i', $ua) ? 'mobile' : 'desktop';

    $line = implode("\t", array(
        gmdate('H:i:s'), $kind, str_replace("\t", ' ', $what), $refHost, $device, $token
    )) . "\n";

    @file_put_contents(cfg('activity.dir') . '/' . $today . '.tsv', $line,
                       FILE_APPEND | LOCK_EX);

    alpine_activity_prune();
}

/** Drop raw files past the retention window. Cheap, and only occasionally. */
function alpine_activity_prune()
{
    if (mt_rand(1, 200) !== 1) { return; }        // ~0.5% of requests
    $keep = (int) cfg('activity.retain_days');
    if ($keep < 1) { return; }
    $cutoff = time() - $keep * 86400;
    foreach ((array) glob(cfg('activity.dir') . '/*.tsv') as $f) {
        if (@filemtime($f) < $cutoff) { @unlink($f); }
    }
}

/**
 * The href for an outbound link the club cares about.
 *
 * Returns a go.php redirect when logging is on, and the plain destination when
 * it is not — so the static build, which has no PHP at request time, still
 * produces working links.
 *
 * @param string $name a key under 'links' in config, e.g. 'mailing_list'
 */
function alpine_outbound($name)
{
    $url = cfg('links.' . $name);
    if (!$url || !alpine_activity_on()) { return (string) $url; }
    return 'go.php?to=' . rawurlencode($name);
}

/** Called once per page render from bootstrap. */
function alpine_activity_page()
{
    $path = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '?';
    if ($path === 'go.php' || $path === 'stats.php') { return; }
    alpine_activity_record('page', $path);
}
