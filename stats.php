<?php
/**
 * ============================================================================
 *  STATS — what the site is actually being used for
 * ============================================================================
 *
 *      stats.php?key=<calendar.preview_key>          the last 30 days
 *      stats.php?key=...&days=90                     a longer window
 *
 *  UNLIKE preview.php, THIS PAGE REQUIRES A KEY. preview.php with no key set is
 *  merely unlisted, which is fine for a rendering sandbox. This one reports how
 *  many people came and where from, which is nobody else's business — so with
 *  no key configured it returns 404 and stays shut.
 *
 *  Set calendar.preview_key in includes/config.local.php on the server. That
 *  file is git-ignored, so the key never reaches this repository.
 *
 *  Where the data comes from, and why it cannot identify anyone:
 *  includes/activity.php. Read that before adding a column here.
 *
 *  FIVE NUMBERS, NOT A DASHBOARD. Officers turn over every year; a readout that
 *  needs interpreting gets looked at once. If you are tempted to add bounce
 *  rate or time-on-page, work out first which decision it would change.
 * ============================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';

/* ---------------------------------------------------------------- access -- */

$requiredKey = (string) cfg('calendar.preview_key', '');
$keyParam    = isset($_GET['key']) ? (string) $_GET['key'] : '';

if ($requiredKey === '' || !hash_equals($requiredKey, $keyParam)) {
    http_response_code(404);
    exit('Not found');
}
$keyQuery = '?key=' . rawurlencode($keyParam);

/* ------------------------------------------------------------------ read -- */

$days = isset($_GET['days']) ? max(1, min(365, (int) $_GET['days'])) : 30;
$dir  = cfg('activity.dir');

$pages = $clicks = $refs = $devices = array();
$byDay = $visitorsByDay = array();
$funnel = array('index.php' => array(), 'join.php' => array(), 'mailing_list' => array());
$lines = 0;

for ($i = $days - 1; $i >= 0; $i--) {
    $date = gmdate('Y-m-d', time() - $i * 86400);
    $byDay[$date] = 0;
    $visitorsByDay[$date] = array();
    $file = $dir . '/' . $date . '.tsv';
    if (!is_readable($file)) { continue; }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $row) {
        $c = explode("\t", $row);
        if (count($c) < 6) { continue; }
        list($time, $kind, $what, $refHost, $device, $token) = $c;
        $lines++;

        if ($kind === 'page') {
            $pages[$what] = isset($pages[$what]) ? $pages[$what] + 1 : 1;
            $byDay[$date]++;
            $visitorsByDay[$date][$token] = true;
            $devices[$device] = isset($devices[$device]) ? $devices[$device] + 1 : 1;
            if ($refHost !== '') {
                $refs[$refHost] = isset($refs[$refHost]) ? $refs[$refHost] + 1 : 1;
            }
        } else {
            $clicks[$what] = isset($clicks[$what]) ? $clicks[$what] + 1 : 1;
        }

        /* The funnel counts PEOPLE, not hits: a token is added to a stage once,
           so somebody refreshing the join page twenty times still counts once. */
        if (isset($funnel[$what])) { $funnel[$what][$token] = true; }
    }
}

arsort($pages); arsort($clicks); arsort($refs); arsort($devices);
$totalViews    = array_sum($byDay);
$totalVisitors = count(array_reduce($visitorsByDay,
    function ($carry, $day) { return $carry + $day; }, array()));

$PAGE = array(
    'title'       => 'Site usage',
    'description' => 'Internal usage report.',
    'noindex'     => true,
);
require __DIR__ . '/includes/header.php';

$pct = function ($n, $of) {
    return $of > 0 ? round(100 * $n / $of) . '%' : '—';
};
?>

<style>
/* Scoped to this page: an internal readout should not put rules into the real
   stylesheet, and it does not have to be pretty. */
.st-grid { display:grid; gap:1px; grid-template-columns:repeat(auto-fit,minmax(min(100%,11rem),1fr));
           background:var(--line); border:1px solid var(--line); border-radius:var(--r); overflow:hidden; }
.st-cell { background:var(--white); padding:1.2rem 1.1rem; }
.st-num  { font-size:var(--step-3); font-weight:800; line-height:1; letter-spacing:-.02em; }
.st-lab  { margin-top:.35rem; font-size:.78rem; color:var(--text-mute); line-height:1.4; }
.st-table { width:100%; border-collapse:collapse; font-size:.86rem; }
.st-table th, .st-table td { padding:.45rem .6rem; border-bottom:1px solid var(--line); text-align:left; }
.st-table th { font-size:.68rem; letter-spacing:.1em; text-transform:uppercase; color:var(--text-mute); }
.st-table td.n { text-align:right; font-variant-numeric:tabular-nums; width:6rem; }
.st-bar { height:.55rem; background:var(--alpenglow); border-radius:100px; min-width:2px; }
.st-spark { display:flex; align-items:flex-end; gap:2px; height:3.5rem; }
.st-spark i { flex:1; background:var(--alpenglow); border-radius:1px 1px 0 0; min-height:1px; }
</style>

<header class="page-hero">
  <div class="topo"></div>
  <div class="wrap page-hero__inner">
    <p class="eyebrow">Internal page, not linked from the site</p>
    <h1 class="h1">Site usage</h1>
    <p class="lede">
      Last <?= (int) $days ?> days. No cookies, no third party, and nothing
      stored that could identify a visitor — see <code>includes/activity.php</code>.
    </p>
  </div>
</header>

<section class="section">
  <div class="wrap">

    <?php if (!alpine_activity_on()): ?>
      <div class="gate">
        <p class="gate__headline">Logging is off, so this page has nothing to show.</p>
        <p class="gate__detail">
          Either <code>activity.enabled</code> is false in the config, or
          <code><?= e($dir) ?></code> is not writable by the web server. On a
          Caltech Unix host that is usually <code>chmod 775 logs</code>.
        </p>
      </div>
    <?php elseif ($lines === 0): ?>
      <div class="note">
        <?= icon('clock', 'icon icon--xs') ?>
        <p>Logging is on and working, but nothing has been recorded yet in this
           window. Load a page in another browser and refresh this one.</p>
      </div>
    <?php else: ?>

      <div class="st-grid">
        <div class="st-cell">
          <div class="st-num"><?= number_format($totalViews) ?></div>
          <div class="st-lab">page views</div>
        </div>
        <div class="st-cell">
          <div class="st-num"><?= number_format($totalVisitors) ?></div>
          <div class="st-lab">visitors, counted once per person per day</div>
        </div>
        <div class="st-cell">
          <div class="st-num"><?= number_format(array_sum($clicks)) ?></div>
          <div class="st-lab">clicks on a link leaving the site</div>
        </div>
        <div class="st-cell">
          <div class="st-num"><?= $pct(isset($devices['mobile']) ? $devices['mobile'] : 0, $totalViews) ?></div>
          <div class="st-lab">of views on a phone</div>
        </div>
      </div>

      <!-- ------------------------------------------------------ funnel -- -->
      <h2 class="h3 mt-lg">Does anybody actually join?</h2>
      <p class="lede" style="font-size:var(--step-0)">
        The only conversion on this site. Each stage counts people, not visits.
      </p>
      <?php
        $f1 = count($funnel['index.php']);
        $f2 = count($funnel['join.php']);
        $f3 = count($funnel['mailing_list']);
      ?>
      <table class="st-table mt-lg">
        <tr><th>Stage</th><th class="n">People</th><th style="width:45%"></th></tr>
        <tr>
          <td>Saw the homepage</td><td class="n"><?= $f1 ?></td>
          <td><div class="st-bar" style="width:100%"></div></td>
        </tr>
        <tr>
          <td>Reached the join page</td><td class="n"><?= $f2 ?> <small>(<?= $pct($f2, $f1) ?>)</small></td>
          <td><div class="st-bar" style="width:<?= $f1 ? round(100 * $f2 / $f1) : 0 ?>%"></div></td>
        </tr>
        <tr>
          <td>Clicked through to the mailing list</td><td class="n"><?= $f3 ?> <small>(<?= $pct($f3, $f2) ?>)</small></td>
          <td><div class="st-bar" style="width:<?= $f1 ? round(100 * $f3 / $f1) : 0 ?>%"></div></td>
        </tr>
      </table>
      <div class="note mt-lg">
        <?= icon('heart', 'icon icon--xs') ?>
        <p>The number that really matters is not here: it is the subscriber count
           on the Mailman list itself. This shows how many people the site sent
           in that direction, not how many finished.</p>
      </div>

      <!-- ------------------------------------------------------ by day -- -->
      <h2 class="h3 mt-lg">By day</h2>
      <?php $peak = max(1, max($byDay)); ?>
      <div class="st-spark mt-lg" role="img"
           aria-label="Daily page views for the last <?= (int) $days ?> days, peak <?= (int) $peak ?>">
        <?php foreach ($byDay as $d => $n): ?>
          <i style="height:<?= max(1, round(100 * $n / $peak)) ?>%" title="<?= e($d) ?>: <?= (int) $n ?>"></i>
        <?php endforeach; ?>
      </div>

      <!-- ------------------------------------------------------- lists -- -->
      <div class="split mt-lg" style="align-items:start">
        <div>
          <h2 class="h3">Pages</h2>
          <table class="st-table mt-lg">
            <tr><th>Page</th><th class="n">Views</th></tr>
            <?php foreach ($pages as $p => $n): ?>
              <tr><td><?= e($p) ?></td><td class="n"><?= number_format($n) ?></td></tr>
            <?php endforeach; ?>
          </table>

          <h2 class="h3 mt-lg">Clicks off the site</h2>
          <table class="st-table mt-lg">
            <tr><th>Action</th><th class="n">Clicks</th></tr>
            <?php if (!$clicks): ?>
              <tr><td colspan="2">None yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($clicks as $c => $n): ?>
              <tr><td><?= e(str_replace('_', ' ', $c)) ?></td><td class="n"><?= number_format($n) ?></td></tr>
            <?php endforeach; ?>
          </table>
        </div>

        <div>
          <h2 class="h3">Where they came from</h2>
          <table class="st-table mt-lg">
            <tr><th>Referrer</th><th class="n">Views</th></tr>
            <?php if (!$refs): ?>
              <tr><td colspan="2">All direct — typed, bookmarked, or from an app
                  that sends no referrer, which includes most email clients.</td></tr>
            <?php endif; ?>
            <?php foreach (array_slice($refs, 0, 15, true) as $r => $n): ?>
              <tr><td><?= e($r) ?></td><td class="n"><?= number_format($n) ?></td></tr>
            <?php endforeach; ?>
          </table>

          <h2 class="h3 mt-lg">Device</h2>
          <table class="st-table mt-lg">
            <tr><th>Kind</th><th class="n">Views</th></tr>
            <?php foreach ($devices as $d => $n): ?>
              <tr><td><?= e($d) ?></td><td class="n"><?= number_format($n) ?> (<?= $pct($n, $totalViews) ?>)</td></tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>

    <?php endif; ?>

    <p class="lede mt-lg" style="font-size:.86rem;color:var(--text-mute)">
      Window:
      <?php foreach (array(7, 30, 90, 365) as $d): ?>
        <a href="<?= e('stats.php' . $keyQuery . '&days=' . $d) ?>"><?= $d ?> days</a><?= $d === 365 ? '' : ' · ' ?>
      <?php endforeach; ?>
      &nbsp;|&nbsp; raw files are deleted after
      <?= (int) cfg('activity.retain_days') ?> days.
    </p>

  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
