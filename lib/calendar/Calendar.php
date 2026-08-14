<?php
/**
 * ============================================================================
 *  AlpineCalendar — the only calendar thing page templates ever touch.
 * ============================================================================
 *
 *      AlpineCalendar::upcoming(3)   next three events
 *      AlpineCalendar::past(12)      most recent twelve that have finished
 *      AlpineCalendar::status()      where the data came from, for diagnostics
 *
 *  Fetching and parsing happen once per page load no matter how many sections
 *  ask for events.
 * ============================================================================
 */

require_once __DIR__ . '/Event.php';
require_once __DIR__ . '/Tags.php';
require_once __DIR__ . '/IcsParser.php';
require_once __DIR__ . '/Sources.php';

class AlpineCalendar
{
    /** @var array|null */
    private static $config = null;
    /** @var AlpineEvent[]|null */
    private static $events = null;
    /** @var AlpineCalendarSource|null */
    private static $source = null;

    /** Called once from includes/bootstrap.php. */
    public static function configure(array $config)
    {
        self::$config = $config;
        self::$events = null;
        self::$source = null;
    }

    /**
     * Pick a source. An API key in the config switches to the API; with no key
     * we use the public ICS feed, which is the intended default.
     */
    private static function source()
    {
        if (self::$source === null) {
            $c = self::$config;
            self::$source = (!empty($c['api_key']))
                ? new AlpineGoogleApiCalendarSource($c)
                : new AlpineIcsCalendarSource($c);
        }
        return self::$source;
    }

    /** @return AlpineEvent[] everything in the window, ascending. */
    public static function all()
    {
        if (self::$events === null) {
            self::$events = self::source()->events();
        }
        return self::$events;
    }

    /**
     * Events that have not finished yet, soonest first.
     *
     * Cancelled events are kept: someone who was planning to come needs to see
     * that it is off, and the card marks it clearly.
     *
     * @param int|null $limit null for all of them
     * @return AlpineEvent[]
     */
    public static function upcoming($limit = null)
    {
        $now = self::now();
        $out = array();
        foreach (self::all() as $e) {
            if (!$e->isPast($now)) { $out[] = $e; }
        }
        $out = self::collapseSeries($out);
        return ($limit === null) ? $out : array_slice($out, 0, $limit);
    }

    /**
     * Keep only the first occurrence of each repeating series.
     *
     * A standing weekly run expands to something like eighty occurrences over
     * the parse window. Printed in full it buries every other event on the
     * page — so a series shows its next occurrence, carrying a "Weekly on
     * Tuesdays" label so nobody thinks it happens once. One-off events are
     * untouched.
     *
     * @param AlpineEvent[] $events already in the order you want kept
     * @return AlpineEvent[]
     */
    private static function collapseSeries(array $events)
    {
        if (empty(self::$config['collapse_repeats'])) {
            return $events;
        }

        $seen = array();
        $out  = array();
        foreach ($events as $e) {
            if ($e->seriesId !== '') {
                if (isset($seen[$e->seriesId])) { continue; }
                $seen[$e->seriesId] = true;
            }
            $out[] = $e;
        }
        return $out;
    }

    /**
     * Events that have finished, most recent first. This is what makes
     * "Past Adventures" fill itself in without anyone writing a trip report.
     *
     * @return AlpineEvent[]
     */
    public static function past($limit = null)
    {
        $now = self::now();
        $out = array();
        foreach (self::all() as $e) {
            if ($e->isPast($now) && !$e->cancelled) { $out[] = $e; }
        }
        // Most recent first, then collapse — so a series shows the last time
        // it ran rather than filling the archive with identical entries.
        $out = self::collapseSeries(array_reverse($out));
        return ($limit === null) ? $out : array_slice($out, 0, $limit);
    }

    /** Group events by "September 2026" for the archive listing. */
    public static function groupByMonth(array $events)
    {
        $out = array();
        foreach ($events as $e) {
            $out[$e->monthLabel()][] = $e;
        }
        return $out;
    }

    /**
     * Diagnostics: array{state, fetched_at, error}.
     *   fresh|live  everything is fine
     *   stale       Google was unreachable, showing the last good copy
     *   unavailable no data at all — templates fall back to the embed
     */
    public static function status()
    {
        self::all();                      // make sure a fetch has been attempted
        return self::source()->status();
    }

    /** True when we have no usable event data at all. */
    public static function isUnavailable()
    {
        $s = self::status();
        return $s['state'] === 'unavailable';
    }

    private static function now()
    {
        return new DateTimeImmutable('now', new DateTimeZone(self::$config['timezone']));
    }
}
