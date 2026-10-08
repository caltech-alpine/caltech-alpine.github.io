<?php
/**
 * Calendar regression checks, no network:  php tools/test_calendar.php
 *
 * Builds a feed shaped like the club's own October 2026 one - a weekly
 * Wednesday run whose next occurrence was renamed in Google Calendar - and
 * runs it through the real parser and the real "show the next one" collapse.
 * Dates are relative to today so the check never ages out.
 */

require __DIR__ . '/../lib/calendar/Calendar.php';

$tz   = new DateTimeZone('America/Los_Angeles');
$next = new DateTimeImmutable('next wednesday 07:00', $tz);   // the special one
$fmt  = function (DateTimeImmutable $d) { return $d->format('Ymd\THis'); };

// The series starts ON the special date: starting it earlier would put a
// still-upcoming ordinary run first whenever this runs on a Wednesday morning.
$series = $next;
$moved  = $next->modify('+14 days');       // a later run moved to Thursday, same title

$ics = implode("\r\n", array(
    'BEGIN:VCALENDAR',
    'BEGIN:VEVENT',
    'DTSTART;TZID=America/Los_Angeles:' . $fmt($series),
    'DTEND;TZID=America/Los_Angeles:' . $fmt($series->modify('+2 hours')),
    'RRULE:FREQ=WEEKLY;BYDAY=WE',
    'UID:run@test',
    'SUMMARY:Weekly Trail Run',
    'END:VEVENT',
    'BEGIN:VEVENT',
    'DTSTART;TZID=America/Los_Angeles:' . $fmt($next),
    'DTEND;TZID=America/Los_Angeles:' . $fmt($next->modify('+2 hours')),
    'UID:run@test',
    'RECURRENCE-ID;TZID=America/Los_Angeles:' . $fmt($next),
    'SUMMARY:Special - Beginner Trail Run',
    'END:VEVENT',
    'BEGIN:VEVENT',
    'DTSTART;TZID=America/Los_Angeles:' . $fmt($moved->modify('+1 day')),
    'DTEND;TZID=America/Los_Angeles:' . $fmt($moved->modify('+1 day +2 hours')),
    'UID:run@test',
    'RECURRENCE-ID;TZID=America/Los_Angeles:' . $fmt($moved),
    'SUMMARY:Weekly Trail Run',
    'END:VEVENT',
    'END:VCALENDAR',
));

$parser = new IcsParser('America/Los_Angeles');
$events = $parser->parse($ics);

// Feed the parsed events to the real collapse without touching the network.
AlpineCalendar::configure(array('timezone' => 'America/Los_Angeles', 'collapse_repeats' => true));
$prop = new ReflectionProperty('AlpineCalendar', 'events');
$prop->setAccessible(true);
$prop->setValue(null, $events);
$up = AlpineCalendar::upcoming();

$fails = 0;
function check($ok, $what) {
    global $fails;
    echo ($ok ? '  OK    ' : '  FAIL  ') . $what . PHP_EOL;
    if (!$ok) { $fails++; }
}

$titles = array();
foreach ($up as $e) { $titles[] = $e->start->format('D M j') . ' ' . $e->title; }

check(count($up) === 2, 'upcoming lists exactly two cards: ' . implode(' | ', $titles));
check(isset($up[0]) && $up[0]->title === 'Special - Beginner Trail Run'
      && $up[0]->start == $next, 'the renamed run comes first, on its own date');
check(isset($up[0]) && $up[0]->repeatLabel === '' && $up[0]->seriesId === '',
      'the renamed run carries no "Weekly on Wednesdays" tag');
check(isset($up[1]) && $up[1]->title === 'Weekly Trail Run'
      && $up[1]->start == $next->modify('+7 days')
      && $up[1]->repeatLabel === 'Weekly on Wednesdays',
      'the series shows its next ORDINARY run, the week after');

$movedEv = null;
foreach ($events as $e) {
    if ($e->start == $moved->modify('+1 day')) { $movedEv = $e; }
}
check($movedEv !== null && $movedEv->seriesId === 'run@test',
      'a run moved to another day, same title, stays in the series');

$uids = array();
foreach ($events as $e) { $uids[] = $e->uid; }
check(count($uids) === count(array_unique($uids)), 'every occurrence has a distinct uid');

echo $fails ? PHP_EOL . $fails . ' failed' . PHP_EOL : PHP_EOL . 'all passed' . PHP_EOL;
exit($fails ? 1 : 0);
