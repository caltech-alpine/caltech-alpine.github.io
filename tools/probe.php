<?php
/**
 * probe.php - the first file to put on a new server.
 *
 * Upload this one file to the document root as _probe.php, open it in a
 * browser, and it answers every question worth asking before uploading the
 * rest of the site: does PHP run, which version, can it reach Google for the
 * calendar, can it write a cache, and is .htaccess being read.
 *
 *   scp tools/probe.php USER@portal.caltech.edu:/path/to/docroot/_probe.php
 *   open https://staging.alpine.caltech.edu/_probe.php
 *
 * DELETE IT AFTERWARDS. It reports server details no visitor needs:
 *
 *   ssh USER@portal.caltech.edu 'rm /path/to/docroot/_probe.php'
 *
 * It touches nothing except a file called _probe_write_test.tmp, which it
 * removes again.
 */

header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: text/html; charset=utf-8');

$rows = array();

function row(&$rows, $label, $ok, $detail)
{
    $rows[] = array($label, $ok, $detail);
}

// ---------------------------------------------------------------- PHP ------

$version_ok = version_compare(PHP_VERSION, '7.4', '>=');
row($rows, 'PHP version', $version_ok,
    PHP_VERSION . ($version_ok ? '' : ' - the site needs 7.4 or newer'));

row($rows, 'How PHP runs', true, PHP_SAPI);

// -------------------------------------------------- outbound network -------
// The calendar is fetched from Google by the SERVER, so the server has to be
// allowed out. A firewall that blocks this is invisible until the events
// section is empty and nobody knows why.

$has_curl = function_exists('curl_init');
$has_fopen = (bool) ini_get('allow_url_fopen');
row($rows, 'cURL extension', $has_curl, $has_curl ? 'available' : 'missing');
row($rows, 'allow_url_fopen', $has_fopen, $has_fopen ? 'on' : 'off');
row($rows, 'One of the two', $has_curl || $has_fopen,
    ($has_curl || $has_fopen)
        ? 'the calendar can be fetched'
        : 'NEITHER - the calendar cannot be fetched at all. Ask IMSS.');

$reach = 'not attempted';
$reach_ok = false;
if ($has_curl) {
    $ch = curl_init('https://calendar.google.com/');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
    ));
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    $reach_ok = $code > 0;
    $reach = $reach_ok
        ? 'HTTP ' . $code . ' from calendar.google.com'
        : 'failed: ' . ($err !== '' ? $err : 'no response');
} elseif ($has_fopen) {
    $body = @file_get_contents('https://calendar.google.com/',
        false, stream_context_create(array('http' => array('timeout' => 10))));
    $reach_ok = ($body !== false);
    $reach = $reach_ok ? 'reachable over streams' : 'failed';
}
row($rows, 'Can reach Google', $reach_ok, $reach);

// -------------------------------------------------------------- writing ----

$dir = __DIR__;
$test = $dir . DIRECTORY_SEPARATOR . '_probe_write_test.tmp';
$wrote = @file_put_contents($test, 'test');
if ($wrote !== false) {
    @unlink($test);
}
row($rows, 'Can write to the document root', $wrote !== false,
    $wrote !== false
        ? 'yes - cache/ will work once it exists'
        : 'no - cache/ will need chmod 2775 and the right group');

row($rows, 'Runs as user', true,
    function_exists('posix_getpwuid') && function_exists('posix_geteuid')
        ? posix_getpwuid(posix_geteuid())['name']
        : (getenv('USER') ? getenv('USER') : 'unknown'));

// -------------------------------------------------------------- server -----

$server = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'unknown';
row($rows, 'Server software', stripos($server, 'apache') !== false, $server
    . (stripos($server, 'apache') !== false ? '' : ' - not Apache, so .htaccess is ignored'));

row($rows, 'Document root', true,
    isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'unknown');

if (function_exists('apache_get_modules')) {
    $mods = apache_get_modules();
    foreach (array('mod_rewrite', 'mod_headers', 'mod_expires', 'mod_deflate') as $m) {
        row($rows, $m, in_array($m, $mods, true),
            in_array($m, $mods, true) ? 'loaded' : 'not loaded - the matching .htaccess block is skipped');
    }
} else {
    row($rows, 'Apache modules', true,
        'not visible from this SAPI - verify .htaccess with tools/verify_deploy.py instead');
}

// TLS is terminated in front of this server, so PHP sees plain HTTP. This is
// expected here; it is recorded because it is what makes a naive force-HTTPS
// rewrite loop forever. See docs/SERVERS.md.
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$fwd = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : '(none)';
row($rows, 'PHP thinks the request is', true,
    ($https ? 'HTTPS' : 'HTTP') . ', X-Forwarded-Proto: ' . $fwd);

$pass = 0;
foreach ($rows as $r) {
    if ($r[1]) {
        $pass++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex, nofollow">
<title>Server probe</title>
<style>
  body { font: 15px/1.5 system-ui, sans-serif; margin: 2rem auto; max-width: 46rem; padding: 0 1rem; }
  table { border-collapse: collapse; width: 100%; }
  td { padding: .4rem .6rem; border-bottom: 1px solid #ddd; vertical-align: top; }
  td:first-child { width: 14rem; font-weight: 600; }
  .ok { color: #15803d; } .no { color: #b91c1c; }
  code { background: #f4f4f5; padding: .1rem .3rem; }
  p.note { color: #52525b; }
</style>
</head>
<body>
<h1>Server probe</h1>
<p><?= $pass ?> of <?= count($rows) ?> checks passed on
   <?= htmlspecialchars(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') ?>,
   <?= date('Y-m-d H:i T') ?>.</p>
<table>
<?php foreach ($rows as $r): ?>
  <tr>
    <td><?= htmlspecialchars($r[0]) ?></td>
    <td class="<?= $r[1] ? 'ok' : 'no' ?>"><?= $r[1] ? 'ok' : 'no' ?></td>
    <td><?= htmlspecialchars($r[2]) ?></td>
  </tr>
<?php endforeach; ?>
</table>
<p class="note">Copy this into <code>docs/DEPLOY-LOG.md</code>, then delete this
file from the server.</p>
</body>
</html>
