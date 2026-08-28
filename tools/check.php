<?php
/**
 * ============================================================================
 *  Health check — run this if something looks wrong, and once a year anyway.
 * ============================================================================
 *
 *      php tools/check.php            calendar + configuration + the roster
 *      php tools/check.php --data     ONLY the officer data. No network, instant.
 *                                     Run this after editing a CSV.
 *      php tools/check.php --links    also check every link actually resolves
 *
 *  It prints what the website can currently see. If "Upcoming" is empty here,
 *  it is empty because the Google Calendar has no future events — not because
 *  the website is broken.
 * ============================================================================
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit("This script is for the command line.\n");
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$checkLinks = in_array('--links', $argv, true);
$problems   = 0;

function line($s = '') { echo $s . PHP_EOL; }

/* --------------------------------------------------------------- --data ----
   Just the officer data, and nothing that touches the network. This is the
   mode tools/build_static.py runs before it publishes anything, so a roster
   that does not add up fails the build instead of reaching the club's website.
   It is also the fast one to run after editing a CSV: no calendar, no link
   probing, answers in well under a second. */
if (in_array('--data', $argv, true)) {
    require_once ALPINE_ROOT . '/includes/officers.php';
    require_once ALPINE_ROOT . '/includes/validate.php';

    $found = alpine_data_problems(alpine_required_roles());
    line();
    if (!$found) {
        line('PEOPLE.csv, ROLES.csv and ASSIGNMENTS.csv all agree.');
        line();
        exit(0);
    }
    line(count($found) . ' problem(s) in the officer data:');
    line();
    foreach ($found as $i => $p) { line('  ' . ($i + 1) . '. ' . $p); }
    line();
    exit(1);
}

/**
 * Shorten a title for the columns below.
 *
 * mb_strimwidth() is the right function and it lives in the mbstring
 * extension, which is not always installed -- and when it is missing PHP does
 * not warn, it throws, so the health check died with a fatal error partway
 * through and never reached the content and roles sections. A checker that
 * cannot survive a missing optional extension is worse than useless: it fails
 * loudly in a way that looks like the site is broken. Fall back to substr().
 */
function alpine_short($text, $width)
{
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $width, '...');
    }
    return strlen($text) > $width ? substr($text, 0, $width - 3) . '...' : $text;
}
function ok($s)   { line('  OK    ' . $s); }
function warn($s) { global $problems; $problems++; line('  WARN  ' . $s); }
function bad($s)  { global $problems; $problems++; line('  FAIL  ' . $s); }

line();
line('Caltech Alpine Club — site check');
line(str_repeat('=', 60));

/* ------------------------------------------------------------------ system */
line();
line('Environment');
version_compare(PHP_VERSION, '7.4', '>=')
    ? ok('PHP ' . PHP_VERSION)
    : bad('PHP ' . PHP_VERSION . ' — this site expects 7.4 or newer');

function_exists('curl_init')
    ? ok('curl available')
    : (ini_get('allow_url_fopen')
        ? warn('no curl; falling back to allow_url_fopen (works, but slower)')
        : bad('no curl and allow_url_fopen is off — the site cannot reach Google'));

$cacheDir = cfg('calendar.cache_dir');
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
if (is_dir($cacheDir) && is_writable($cacheDir)) {
    ok('cache directory writable (' . $cacheDir . ')');
} else {
    warn('cache directory not writable — the site still works, but it will '
       . 'ask Google on every single page load. chmod 775 ' . $cacheDir);
}

/* ---------------------------------------------------------------- calendar */
line();
line('Calendar');

$events   = AlpineCalendar::all();
$status   = AlpineCalendar::status();
$upcoming = AlpineCalendar::upcoming();
$past     = AlpineCalendar::past();

switch ($status['state']) {
    case 'live':  ok('fetched fresh from Google just now'); break;
    case 'fresh': ok('served from cache, fetched '
                   . round((time() - $status['fetched_at']) / 60) . ' min ago'); break;
    case 'stale': warn('Google unreachable, showing the last good copy from '
                   . date('Y-m-d H:i', $status['fetched_at'])
                   . ' (' . $status['error'] . ')'); break;
    default:      bad('no calendar data at all: ' . $status['error']); break;
}

line('        ' . count($events) . ' events in window, '
   . count($upcoming) . ' upcoming, ' . count($past) . ' past');

if (!$upcoming) {
    warn('nothing upcoming. Add events to the Google Calendar — the website '
       . 'will show them within ' . round(cfg('calendar.cache_ttl') / 60) . ' minutes.');
}

/* Show what the site will actually render, so titles can be eyeballed. */
if ($events) {
    line();
    line('  Next up:');
    foreach (array_slice($upcoming, 0, 5) as $e) {
        printf("    %-42s %s%s\n",
            alpine_short($e->title, 42),
            $e->whenLine(),
            $e->cancelled ? '  [CANCELLED]' : ''
        );
    }
    line();
    line('  Most recent past:');
    foreach (array_slice($past, 0, 5) as $e) {
        printf("    %-42s %s\n",
            alpine_short($e->title, 42),
            $e->whenLine()
        );
    }
}

/* ------------------------------------------------------------------- links */
line();
line('Links');

$links = cfg('links');
$empty = array();
foreach ($links as $key => $value) {
    if ($key === 'mailing_list_note') { continue; }
    if ($value === '' || $value === null) { $empty[] = $key; }
}
if ($empty) {
    warn('not filled in yet: ' . implode(', ', $empty)
       . '  (edit includes/config.php)');
} else {
    ok('every link in config.php has a value');
}

if ($checkLinks) {
    $tested = 0;
    $transportFailures = 0;
    $lastError = '';

    foreach ($links as $key => $value) {
        if (!is_string($value) || strpos($value, 'http') !== 0) { continue; }
        $tested++;

        $error = '';
        $code  = alpine_probe($value, $error);

        if ($code === 0) {
            $transportFailures++;
            $lastError = $error;
            warn(sprintf('%-14s could not connect — %s', $key, $error));
        } elseif ($code >= 400) {
            bad(sprintf('%-14s HTTP %d  %s', $key, $code, $value));
        } else {
            ok(sprintf('%-14s HTTP %d', $key, $code));
        }
    }

    /* If NOTHING could be reached, the problem is almost certainly this
       machine rather than the club's links. Say so, because a checker that
       blames the wrong thing is worse than no checker. */
    if ($tested > 0 && $transportFailures === $tested) {
        line();
        line('        All ' . $tested . ' links failed to connect, which usually means a');
        line('        local problem rather than broken links. The usual cause is PHP');
        line('        having no CA certificate bundle:');
        line('          ' . $lastError);
        line('        Fix by downloading https://curl.se/ca/cacert.pem and setting');
        line('          curl.cainfo = "/path/to/cacert.pem"');
        line('        in php.ini. The website itself is unaffected: it falls back to');
        line('        a stream request when curl fails.');
    }
} else {
    line('        (run with --links to test every URL)');
}

/* ------------------------------------------------------------------- data  */
line();
line('Content');

$sponsors = alpine_data('sponsors');
count($sponsors) ? ok(count($sponsors) . ' sponsors listed') : line('  --    no sponsors yet (data/sponsors.php)');

/* ----------------------------------------------------- people, roles, jobs */
/* THE ONE CLASS OF MISTAKE THAT IS INVISIBLE ON THE PAGE.
   The three data files point at each other by id. Misspell a role_id in
   assignments.csv and the officer simply does not appear, the job shows as
   open, and every page looks entirely normal while advertising a vacancy in a
   job somebody is doing -- which is the one wrong answer this system must not
   give. Nothing about the rendered site would tell you. Hence this. */
require_once ALPINE_ROOT . '/includes/officers.php';
require_once ALPINE_ROOT . '/includes/validate.php';

$people = alpine_people();
$roles  = alpine_roles();

count($people) ? ok(count($people) . ' people listed (PEOPLE.csv)')
               : warn('nobody in PEOPLE.csv');
count($roles)  ? ok(count($roles) . ' roles defined (ROLES.csv)')
               : warn('no roles in ROLES.csv — the Get Involved page will be empty');

$problems = alpine_data_problems(alpine_required_roles());
if ($problems) {
    foreach ($problems as $p) { bad($p); }
} else {
    ok('people, roles and assignments all agree with each other');
}

/* What the site will actually say about staffing, so it can be eyeballed
   against what the club knows to be true. */
if ($roles) {
    line();
    line('  What each role reports:');
    foreach ($roles as $r) {
        $cap = ($r['max'] === null) ? 'any' : $r['max'];
        printf("    %-42s %d of min %d / max %-3s  %-9s %s\n",
            alpine_short(alpine_role_title($r), 42),
            $r['filled'], $r['min'], $cap, $r['state'],
            alpine_role_status_line($r));
    }

    line();
    $short = alpine_roles_needed();
    if ($short) {
        line('  --    the homepage says the club is short '
           . alpine_roles_sentence($short));
    } else {
        line('  --    nothing is below its minimum, so the homepage notice is hidden');
    }
}


is_readable(ALPINE_ROOT . '/assets/images/hero.jpg')
    ? ok('homepage hero photograph present')
    : warn('no assets/images/hero.jpg — the homepage is using the drawn contour '
         . 'map. A wide photo of members outdoors is the biggest visual upgrade available.');

$serving   = alpine_serving_officers();
$headshots = glob(ALPINE_ROOT . '/assets/images/officers/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
$withPhoto = 0;
foreach ($serving as $o) {
    if (!empty($o['photo']) && is_readable(ALPINE_ROOT . '/assets/images/officers/' . $o['photo'])) {
        $withPhoto++;
    }
}
line(sprintf('  --    %d of %d serving officers have a headshot (%d file%s on disk); '
           . 'the rest show their initials',
    $withPhoto, count($serving), count($headshots), count($headshots) === 1 ? '' : 's'));

/* Officer email addresses. Serving officers without one can only be reached
   through the general mailbox, which defeats the point of listing who does
   what — so this is worth chasing. */
$missingEmail = array();
foreach ($serving as $o) {
    if ($o['email'] === '') { $missingEmail[] = $o['name']; }
}
if (!$missingEmail && $serving) {
    ok('every serving officer has an email address');
} elseif ($serving) {
    warn(count($missingEmail) . ' of ' . count($serving) . ' serving officers have no '
       . 'email address in PEOPLE.csv (' . implode(', ', $missingEmail) . ') — '
       . 'visitors can only reach them via ' . cfg('links.officers'));
}

$gear = alpine_data('gear');
if ($gear) {
    $count = 0;
    foreach ($gear as $source) {
        foreach ($source['groups'] as $items) { $count += count($items); }
    }
    ok($count . ' gear entries across ' . count($gear) . ' sources (data/gear.php)');
} else {
    warn('no gear inventory in data/gear.php');
}

/* ------------------------------------------------------------------ result */
line();
line(str_repeat('=', 60));
line($problems === 0
    ? 'All good.'
    : $problems . ' thing(s) to look at above.');
line();

exit($problems === 0 ? 0 : 1);


/**
 * Ask a URL whether it is alive.
 *
 * Returns the HTTP status, or 0 if we could not connect at all — in which case
 * $error explains why. The distinction matters: "404" is a broken club link and
 * somebody has to fix it, whereas "could not connect" is usually this computer.
 *
 * Tries HEAD first because it is cheap, falls back to GET for the servers that
 * refuse HEAD, and falls back again to a stream request if curl is unusable.
 */
function alpine_probe($url, &$error)
{
    $error = '';

    if (function_exists('curl_init')) {
        foreach (array(true, false) as $headOnly) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_NOBODY         => $headOnly,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_USERAGENT      => 'CaltechAlpineClub-Website/1.0 (link check)',
            ));
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($code > 0 && $code < 400) { return $code; }
            if ($code >= 400 && !$headOnly) { return $code; }   // genuinely broken
            if ($code === 0) { $error = $err ?: 'curl failed'; }
            // a 4xx/5xx from HEAD falls through and is retried as GET
        }
    } else {
        $error = 'curl extension not installed';
    }

    // Last resort, so a missing CA bundle does not make every link look dead.
    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(array(
            'http' => array('timeout' => 15, 'ignore_errors' => true,
                            'header' => "User-Agent: CaltechAlpineClub-Website/1.0\r\n"),
        ));
        $body = @file_get_contents($url, false, $ctx);
        if ($body !== false && isset($http_response_header[0])
            && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $error = '';
            return (int) $m[1];
        }
    }

    return 0;
}
