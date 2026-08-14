<?php
/**
 * Events & Trips.
 *
 * Three sections, all fed by the same Google Calendar:
 *   #upcoming  cards for everything still to come
 *   #calendar  the month grid, for people who want to browse
 *   #past      an archive that fills itself in as events go by
 *
 * Nobody has to write a trip report for the past list to exist. That is the
 * whole idea: the club runs events, and the website keeps the record.
 */

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$PAGE = array(
    'title'       => 'Events & Trips',
    'description' => 'Upcoming Caltech Alpine Club hikes, trips, talks, and socials, straight '
                   . 'from the club calendar, plus an archive of where the club has been.',
    'nav'         => 'events.php',
);

$upcoming = AlpineCalendar::upcoming();
/* So a visitor can tell an empty calendar from a broken site. */
$calStatus = AlpineCalendar::status();
$past     = AlpineCalendar::past(cfg('calendar.past_limit'));
$byMonth  = AlpineCalendar::groupByMonth($past);

require __DIR__ . '/includes/header.php';
?>

<?php /* Search engines and link previews get real event data, not just a page. */ ?>
<?php if ($upcoming): ?>
<script type="application/ld+json">
<?php
$ld = array();
foreach ($upcoming as $e) {
    $item = array(
        '@context'  => 'https://schema.org',
        '@type'     => 'Event',
        'name'      => $e->title,
        'startDate' => $e->isoStart(),
        'endDate'   => $e->allDay ? $e->end->format('Y-m-d') : $e->end->format('c'),
        'eventStatus' => $e->cancelled
            ? 'https://schema.org/EventCancelled'
            : 'https://schema.org/EventScheduled',
        'organizer' => array('@type' => 'Organization', 'name' => cfg('site.name'), 'url' => cfg('site.url')),
    );
    if ($e->location !== '') {
        $item['location'] = array('@type' => 'Place', 'name' => $e->location);
    }
    if ($e->descriptionText !== '') {
        $item['description'] = $e->excerpt(300);
    }
    $ld[] = $item;
}
echo json_encode(count($ld) === 1 ? $ld[0] : $ld, JSON_UNESCAPED_SLASHES);
?>
</script>
<?php endif; ?>

<header class="page-hero">
  <div class="topo"></div>
  <div class="wrap page-hero__inner">
    <p class="eyebrow"><?= icon('calendar', 'icon icon--xs') ?>Events</p>
    <h1 class="h1">Events and trips</h1>
    <p class="lede">
      Hikes, trips, talks, socials, and film screenings.
    </p>
  </div>
</header>


<!-- ===================================================== upcoming ==== -->
<section class="section" id="upcoming">
  <div class="wrap">

    <div class="section-head">
      <div class="section-head__text">
        <h2 class="h2">Upcoming events</h2>
      </div>
      <?php if ($upcoming): ?>
        <p class="lede" style="margin:0;font-size:var(--step-0)">
          <?= count($upcoming) ?> event<?= count($upcoming) === 1 ? '' : 's' ?> scheduled
        </p>
      <?php endif; ?>
    </div>

    <?php if (AlpineCalendar::isUnavailable()): ?>
      <?php alpine_calendar_unavailable(); ?>
    <?php elseif (!$upcoming): ?>
      <?php alpine_events_empty(); ?>
    <?php else: ?>
      <div class="events-grid">
        <?php foreach ($upcoming as $event) { alpine_event_card($event); } ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($calStatus['fetched_at'])): ?>
      <p class="subscribe-note">
        Calendar last checked
        <time datetime="<?= e(date('c', $calStatus['fetched_at'])) ?>"><?=
          e(date('j M Y, g:ia', $calStatus['fetched_at'])) ?></time>.
        <?php if ($calStatus['state'] === 'stale'): ?>
          Google was unreachable since then, so this may be out of date.
        <?php endif; ?>
      </p>
    <?php endif; ?>

  </div>
</section>


<!-- ===================================================== calendar ==== -->
<section class="section section--tint" id="calendar">
  <div class="wrap">

    <div class="section-head">
      <div class="section-head__text">
        <h2 class="h2">Club calendar</h2>
      </div>
    </div>

    <?php /* Subscribing is the thing we actually want people to do, so it sits
             above the embed rather than below it, where it was easy to miss. */ ?>
    <div class="btn-row">
      <a class="btn btn--primary" href="<?= e(cfg('calendar.subscribe_google')) ?>" rel="noopener">
        <?= icon('calendar', 'icon icon--xs') ?> Add to Google Calendar
      </a>
      <a class="btn btn--ghost" href="<?= e(cfg('calendar.subscribe_ical')) ?>">
        <?= icon('calendar', 'icon icon--xs') ?> Apple / Outlook
      </a>
    </div>


    <div class="calendar-embed">
      <iframe src="<?= e(cfg('calendar.embed_url')) ?>"
              title="Caltech Alpine Club events calendar"
              loading="lazy"></iframe>
    </div>

  </div>
</section>


<!-- ========================================================= past ==== -->
<section class="section" id="past">
  <div class="wrap">

    <div class="section-head">
      <div class="section-head__text">
        <h2 class="h2">Past events</h2>
      </div>
    </div>

    <?php if (!$past): ?>
      <div class="empty-state">
        <?= icon('mountain', 'icon icon--xl empty-state__icon') ?>
        <h3>No past events yet</h3>
        <p>Events appear here once they have taken place.</p>
      </div>
    <?php else: ?>
      <div class="split" style="align-items:start">
        <div>
          <?php
            $months = array_keys($byMonth);
            $half   = (int) ceil(count($months) / 2);
            $cols   = array(array_slice($months, 0, $half), array_slice($months, $half));
          ?>
          <?php foreach ($cols[0] as $month): ?>
            <div class="officer-group">
              <h3 class="officer-group__title"><?= e($month) ?></h3>
              <?php foreach ($byMonth[$month] as $event) { alpine_event_row($event); } ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div>
          <?php foreach ($cols[1] as $month): ?>
            <div class="officer-group">
              <h3 class="officer-group__title"><?= e($month) ?></h3>
              <?php foreach ($byMonth[$month] as $event) { alpine_event_row($event); } ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>
</section>


<!-- ========================================================= join ==== -->
<section class="section section--dark section--tight">
  <div class="topo"></div>
  <div class="wrap center">
    <h2 class="h2">Join the club</h2>
    <p class="lede mt-lg" style="margin-inline:auto">
      Membership is free. Join the mailing list to get started.
    </p>
    <div class="btn-row mt-lg">
      <a class="btn btn--primary btn--lg" href="<?= e(url('join.php')) ?>">Join the club</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
