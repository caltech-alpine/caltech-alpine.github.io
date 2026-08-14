<?php
/**
 * Outbound redirect, so a CLICK is countable and not just a page view.
 *
 * ?to= names a key under 'links' in includes/config.php. It is NOT a URL, and
 * that is the point: an endpoint that redirects to whatever URL it is handed is
 * an open redirect, and an open redirect on a caltech.edu host is a genuinely
 * useful thing for a phishing campaign to borrow. Only names the config already
 * knows about resolve; everything else goes home.
 */

require __DIR__ . '/includes/bootstrap.php';

$name = isset($_GET['to']) ? (string) $_GET['to'] : '';
$url  = ($name !== '' && preg_match('/^[a-z0-9_]+$/', $name)) ? cfg('links.' . $name) : '';

if (!$url || !preg_match('~^(https?|webcal)://~i', $url)) {
    header('Location: ' . url('index.php'), true, 302);
    exit;
}

alpine_activity_record('click', $name);

/* 302, not 301: a permanent redirect would be cached by the browser and the
   second click would never reach this file, which would quietly undercount
   exactly the number this file exists to produce. */
header('Location: ' . $url, true, 302);
header('Referrer-Policy: strict-origin-when-cross-origin');
exit;
