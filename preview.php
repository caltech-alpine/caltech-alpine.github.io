<?php
/**
 * ============================================================================
 *  preview.php — test the calendar against a DIFFERENT calendar.
 * ============================================================================
 *
 *  Not linked from anywhere on the site and marked noindex. Deliberately NOT
 *  in robots.txt: a Disallow line would publish the path. Set calendar.
 *  preview_key in config.local.php if you want it behind a key as well.
 *  Use it to check how an unusual event will render before putting
 *  it on the real club calendar.
 *
 *      preview.php                     uses the test calendar from config
 *      preview.php?cal=<calendar-id>   any other public Google calendar
 *      preview.php?fresh=1             ignore the cache and refetch now
 *      preview.php?key=<key>           required if calendar.preview_key is set
 *
 *  SETTING UP A TEST CALENDAR (five minutes, once)
 *    1. Google Calendar, Settings, Add calendar, Create new calendar.
 *       Name it "Alpine Club (test)".
 *    2. Open its settings, Access permissions, tick "Make available to public".
 *       This is what lets the site read it without an API key.
 *    3. Under "Integrate calendar", copy the Calendar ID.
 *    4. Paste it into includes/config.php as calendar.test_calendar_id.
 *
 *  SECURITY NOTE
 *  The ?cal= parameter is a calendar ID, never a URL. The host name is fixed
 *  in the code below and the ID is URL-encoded into the path, so this page
 *  cannot be used to make the server fetch an arbitrary address.
 * ============================================================================
 */

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

/* -------------------------------------------------------------- access --- */

$requiredKey = (string) cfg('calendar.preview_key', '');
$keyParam    = isset($_GET['key']) ? (string) $_GET['key'] : '';

if ($requiredKey !== '' && !hash_equals($requiredKey, $keyParam)) {
    http_response_code(404);
    exit('Not found');
}
$keyQuery = $requiredKey !== '' ? '?key=' . rawurlencode($keyParam) : '';

/* -------------------------------------------------- which calendar, how --- */

$defaultId  = (string) cfg('calendar.test_calendar_id', '');
$requested  = isset($_GET['cal']) ? trim((string) $_GET['cal']) : '';
$fresh      = !empty($_GET['fresh']);
$calendarId = $requested !== '' ? $requested : $defaultId;
$idError    = '';

if ($calendarId !== '' && !preg_match('/^[A-Za-z0-9._%+@-]{3,200}$/', $calendarId)) {
    $idError    = 'That does not look like a Google Calendar ID.';
    $calendarId = '';
}

if ($calendarId !== '') {
    $config                = cfg('calendar');
    $config['calendar_id'] = $calendarId;
    $config['ics_url']     = 'https://calendar.google.com/calendar/ical/'
                           . rawurlencode($calendarId) . '/public/basic.ics';
    $config['api_key']     = '';                 // always exercise the ICS path here
    if ($fresh) { $config['cache_ttl'] = 0; }    // force a refetch this request
    AlpineCalendar::configure($config);
}

$hasCal   = ($calendarId !== '');
$all      = $hasCal ? AlpineCalendar::all()      : array();
$upcoming = $hasCal ? AlpineCalendar::upcoming() : array();
$past     = $hasCal ? AlpineCalendar::past(20)   : array();
$status   = $hasCal ? AlpineCalendar::status()
          : array('state' => 'unavailable', 'fetched_at' => null, 'error' => 'no calendar selected');

$PAGE = array(
    'title'       => 'Calendar preview',
    'description' => 'Internal calendar test page.',
    'noindex'     => true,      // emits the robots tag in <head>, where it counts
);

require __DIR__ . '/includes/header.php';
?>

<style>
/* Scoped to this page. Diagnostics do not need to be pretty, and this keeps
   test-only rules out of the real stylesheet. */
.pv-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.pv-table th, .pv-table td { padding:.5rem .6rem; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; }
.pv-table th { font-size:.68rem; letter-spacing:.1em; text-transform:uppercase; color:var(--text-mute); }
.pv-table code { font-size:.78rem; word-break:break-all; }
.pv-yes { color:#1d7a4c; font-weight:700; }
.pv-no  { color:var(--line-strong); }
.pv-form { display:flex; flex-wrap:wrap; gap:.6rem; align-items:center; }
.pv-form input[type=text] { flex:1; min-width:18rem; padding:.7rem .8rem; border:1px solid var(--line-strong); border-radius:var(--r-sm); font:inherit; font-size:.9rem; }
.pv-state { display:inline-block; padding:.15rem .55rem; border-radius:100px; font-size:.7rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; }
.pv-state--ok   { background:#e2f0e7; color:#1d7a4c; }
.pv-state--warn { background:#fdf0dd; color:#8a5a12; }
.pv-state--bad  { background:#fbe4e0; color:#a33020; }
</style>

<header class="page-hero">
  <div class="topo"></div>
  <div class="wrap page-hero__inner">
    <p class="eyebrow">Internal page, not linked from the site</p>
    <h1 class="h1">Calendar preview</h1>
    <p class="lede">
      Renders any public Google Calendar using the same components as the live
      site. Use it to test events without putting them on the club calendar.
    </p>
  </div>
</header>


<!-- ================================================== choose calendar ==== -->
<section class="section section--tight">
  <div class="wrap">

    <form class="pv-form" method="get" action="preview.php">
      <?php if ($requiredKey !== ''): ?>
        <input type="hidden" name="key" value="<?= e($keyParam) ?>">
      <?php endif; ?>
      <label class="sr-only" for="cal">Google Calendar ID</label>
      <input type="text" id="cal" name="cal" value="<?= e($calendarId) ?>"
             placeholder="something@group.calendar.google.com">
      <label style="display:flex;gap:.4rem;align-items:center;font-size:.88rem">
        <input type="checkbox" name="fresh" value="1" <?= $fresh ? 'checked' : '' ?>> Skip cache
      </label>
      <button class="btn btn--primary btn--sm" type="submit">Load</button>
      <a class="btn btn--ghost btn--sm" href="preview.php<?= e($keyQuery) ?>">Reset</a>
    </form>

    <?php if ($idError): ?>
      <div class="note mt-lg"><?= icon('close', 'icon icon--xs') ?><p><?= e($idError) ?></p></div>
    <?php endif; ?>

    <?php if (!$hasCal): ?>
      <div class="note mt-lg">
        <?= icon('calendar', 'icon icon--xs') ?>
        <p>
          No test calendar is configured. Create one in Google Calendar, make it public,
          and paste its ID above. To load it by default, set
          <code>calendar.test_calendar_id</code> in <code>includes/config.php</code>.
          Step-by-step instructions are in the comment at the top of this file.
        </p>
      </div>
    <?php else: ?>

      <table class="pv-table mt-lg">
        <tr>
          <th style="width:11rem">Calendar</th>
          <td>
            <code><?= e($calendarId) ?></code>
            <?php if ($calendarId === cfg('calendar.calendar_id')): ?>
              <br><strong>This is the real club calendar, not a test calendar.</strong>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th>Fetch</th>
          <td>
            <?php
              $labels = array(
                  'live'        => array('ok',   'fetched from Google just now'),
                  'fresh'       => array('ok',   'served from cache'),
                  'stale'       => array('warn', 'Google unreachable, showing last good copy'),
                  'unavailable' => array('bad',  'no data'),
              );
              $l = isset($labels[$status['state']]) ? $labels[$status['state']] : array('bad', $status['state']);
            ?>
            <span class="pv-state pv-state--<?= e($l[0]) ?>"><?= e($status['state']) ?></span>
            <?= e($l[1]) ?>
            <?php if ($status['fetched_at']): ?>
              &middot; <?= e(date('Y-m-d H:i:s', $status['fetched_at'])) ?>
              (<?= (int) round((time() - $status['fetched_at']) / 60) ?> min ago)
            <?php endif; ?>
            <?php if ($status['error']): ?>
              <br><code><?= e($status['error']) ?></code>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th>Parsed</th>
          <td>
            <?= count($all) ?> events in window,
            <?= count($upcoming) ?> upcoming, <?= count($past) ?> past
          </td>
        </tr>
      </table>

    <?php endif; ?>

  </div>
</section>


<?php if ($hasCal): ?>

<!-- ================================================== rendered output ==== -->
<section class="section section--tight">
  <div class="wrap">
    <h2 class="h2">Rendered output</h2>
    <p class="lede mt-lg">The same event cards used on the homepage and events page.</p>

    <div class="mt-lg">
      <?php if (!$upcoming): ?>
        <?php alpine_events_empty(); ?>
      <?php else: ?>
        <div class="events-grid">
          <?php foreach ($upcoming as $event) { alpine_event_card($event); } ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>


<!-- ===================================================== diagnostics ==== -->
<section class="section section--tint section--tight">
  <div class="wrap">
    <h2 class="h2">Parser output</h2>
    <p class="lede mt-lg">
      Every event in the window, with the fields most likely to be wrong.
      &ldquo;Shown as&rdquo; is the raw title with any leading <code>[bracket]</code>
      removed.
    </p>

    <div class="mt-lg" style="overflow-x:auto">
      <table class="pv-table">
        <thead>
          <tr>
            <th>Raw title</th><th>Shown as</th>
            <th>Start</th><th>End</th>
            <th>All&nbsp;day</th><th>Multi&nbsp;day</th><th>Cancelled</th>
            <th>Location</th><th>Description</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($all as $ev): ?>
            <tr>
              <td><code><?= e($ev->rawTitle) ?></code></td>
              <td><?= e($ev->title) ?></td>
              <td><?= e($ev->start->format('Y-m-d H:i')) ?></td>
              <td><?= e($ev->end->format('Y-m-d H:i')) ?></td>
              <td><?= $ev->allDay ? '<span class="pv-yes">yes</span>' : '<span class="pv-no">no</span>' ?></td>
              <td><?= $ev->isMultiDay() ? '<span class="pv-yes">yes</span>' : '<span class="pv-no">no</span>' ?></td>
              <td><?= $ev->cancelled ? '<span class="pv-yes">yes</span>' : '<span class="pv-no">no</span>' ?></td>
              <td><?= $ev->location !== '' ? e($ev->location) : '<span class="pv-no">&mdash;</span>' ?></td>
              <td><?= $ev->descriptionText !== ''
                    ? e(mb_strimwidth($ev->descriptionText, 0, 60, '…'))
                    : '<span class="pv-no">&mdash;</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$all): ?>
            <tr><td colspan="9">
              Nothing was parsed. Check that the calendar is set to public.
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>


<!-- ======================================================= checklist ==== -->
<section class="section section--tight">
  <div class="wrap wrap--narrow">
    <h2 class="h2">Cases worth testing</h2>
    <p class="lede mt-lg">
      Create one of each on the test calendar. If they all render correctly here,
      the real calendar should not produce surprises.
    </p>

    <div class="prose mt-lg">
      <ul>
        <li><code>[HIKE] Test hike</code> — a leading bracket is dropped from the title</li>
        <li>A title with no prefix, such as <em>Ski waxing party</em> — shown as typed</li>
        <li>An all-day event — should show "All day" rather than a time</li>
        <li>A multi-day event — should show a date range, and the end date must not be a day late</li>
        <li>An event with no description and no location — should not leave empty rows</li>
        <li>A description containing a link and bold text — HTML should survive and stay safe</li>
        <li>A repeating event, for example every Tuesday for six weeks — one card per occurrence</li>
        <li>A repeating event with one occurrence deleted or moved</li>
        <li>A cancelled event — should show a Cancelled badge rather than disappearing</li>
        <li>An event in the past — should move from Upcoming to Past</li>
      </ul>
    </div>

    <div class="note mt-lg">
      <?= icon('clock', 'icon icon--xs') ?>
      <p>
        Changes made in Google Calendar take up to
        <?= (int) round(cfg('calendar.cache_ttl') / 60) ?> minutes to appear on the live
        site. Tick <strong>Skip cache</strong> above to see them here immediately. Google
        can also take a minute or two to publish a change to the public feed.
      </p>
    </div>
  </div>
</section>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
