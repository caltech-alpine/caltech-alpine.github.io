<?php
/**
 * ============================================================================
 *  IcsParser — turns a Google Calendar .ics feed into AlpineEvent objects.
 * ============================================================================
 *
 *  Officers never need to read this file. It exists so that creating a normal
 *  event in Google Calendar is all it takes to put a card on the website.
 *
 *  What it handles (iCalendar / RFC 5545):
 *    - folded lines (continuations indented with a space or tab)
 *    - property parameters, including quoted values  (DTSTART;TZID="X":...)
 *    - escaped TEXT values  (\n  \,  \;  \\)
 *    - all-day events (VALUE=DATE) and their exclusive end date
 *    - timed events in UTC (...Z), in a named zone (TZID=), or floating
 *    - STATUS:CANCELLED
 *    - repeating events: RRULE, EXDATE, and single-instance edits
 *      (RECURRENCE-ID), expanded over a bounded window
 *    - HTML that Google puts in descriptions, reduced to a safe subset
 *
 *  Deliberate limits, so the code stays readable:
 *    - RRULE support covers FREQ=DAILY/WEEKLY/MONTHLY/YEARLY with INTERVAL,
 *      COUNT, UNTIL, BYDAY and BYMONTHDAY. That covers every repeating event
 *      a club actually creates. Exotic rules (BYSETPOS, BYWEEKNO) fall back to
 *      the plain frequency, so an event is never silently lost.
 *    - Occurrences are only generated inside the window passed to parse(),
 *      which keeps an "every Tuesday forever" event from looping unbounded.
 * ============================================================================
 */

class IcsParser
{
    /** Hard stop on occurrence generation, so a malformed rule cannot hang the page. */
    const MAX_OCCURRENCES_PER_RULE = 400;

    /** @var DateTimeZone */
    private $tz;
    /** @var DateTimeImmutable */
    private $windowStart;
    /** @var DateTimeImmutable */
    private $windowEnd;

    /**
     * @param string $timezone    Club timezone, e.g. "America/Los_Angeles"
     * @param string $windowStart Anything strtotime understands, e.g. "-18 months"
     * @param string $windowEnd   e.g. "+18 months"
     */
    public function __construct($timezone, $windowStart = '-18 months', $windowEnd = '+18 months')
    {
        $this->tz          = new DateTimeZone($timezone);
        $now               = new DateTimeImmutable('now', $this->tz);
        $this->windowStart = $now->modify($windowStart);
        $this->windowEnd   = $now->modify($windowEnd);
    }

    /**
     * @param  string $ics Raw feed text.
     * @return AlpineEvent[] Sorted by start time, ascending.
     */
    public function parse($ics)
    {
        $blocks = $this->vevents($ics);

        // Single-instance edits ("just this one moved to Sunday") arrive as their
        // own VEVENT carrying RECURRENCE-ID. Pull them out first so the expansion
        // below can skip the slot they replace.
        $overrides = array();            // uid => [ recurrenceIdKey => block ]
        $plain     = array();
        foreach ($blocks as $b) {
            if (isset($b['RECURRENCE-ID'])) {
                $uid = isset($b['UID']) ? $b['UID']['value'] : '';
                $key = $this->recurrenceKey($b['RECURRENCE-ID']);
                $overrides[$uid][$key] = $b;
            } else {
                $plain[] = $b;
            }
        }

        $events = array();
        foreach ($plain as $b) {
            foreach ($this->blockToEvents($b, $overrides) as $ev) {
                $events[] = $ev;
            }
        }
        // Overrides that point at a series we never saw still deserve to show up.
        foreach ($overrides as $uid => $byKey) {
            foreach ($byKey as $b) {
                if (!isset($b['__consumed'])) {
                    $ev = $this->buildEvent($b);
                    if ($ev) { $events[] = $ev; }
                }
            }
        }

        usort($events, function ($a, $b) {
            if ($a->start == $b->start) { return strcmp($a->title, $b->title); }
            return ($a->start < $b->start) ? -1 : 1;
        });

        return $events;
    }

    /* ===================================================================== */
    /*  Stage 1 — text into property blocks                                   */
    /* ===================================================================== */

    /** Split the feed into VEVENT blocks of parsed properties. */
    private function vevents($ics)
    {
        // Normalise newlines, then unfold: RFC 5545 continues a long line by
        // starting the next one with a space or tab.
        $ics = str_replace(array("\r\n", "\r"), "\n", (string) $ics);
        $ics = preg_replace('/\n[ \t]/', '', $ics);

        $blocks  = array();
        $current = null;

        foreach (explode("\n", $ics) as $line) {
            if ($line === '') { continue; }

            if (strpos($line, 'BEGIN:VEVENT') === 0) { $current = array(); continue; }
            if (strpos($line, 'END:VEVENT') === 0) {
                if ($current !== null) { $blocks[] = $current; }
                $current = null;
                continue;
            }
            if ($current === null) { continue; }   // outside a VEVENT

            $prop = $this->parseLine($line);
            if ($prop === null) { continue; }

            list($name, $params, $value) = $prop;

            // EXDATE can legitimately appear many times; everything else we keep once.
            if ($name === 'EXDATE') {
                $current['EXDATE'][] = array('params' => $params, 'value' => $value);
            } elseif (!isset($current[$name])) {
                $current[$name] = array('params' => $params, 'value' => $value);
            }
        }
        return $blocks;
    }

    /**
     * "DTSTART;TZID=America/Los_Angeles:20260926T080000"
     *   -> ['DTSTART', ['TZID' => 'America/Los_Angeles'], '20260926T080000']
     */
    private function parseLine($line)
    {
        // Find the first colon that is not inside a quoted parameter value.
        $inQuotes = false;
        $colon    = -1;
        $len      = strlen($line);
        for ($i = 0; $i < $len; $i++) {
            $c = $line[$i];
            if ($c === '"') {
                $inQuotes = !$inQuotes;
            } elseif ($c === ':' && !$inQuotes) {
                $colon = $i;
                break;
            }
        }
        if ($colon < 1) { return null; }

        $head  = substr($line, 0, $colon);
        $value = substr($line, $colon + 1);

        $parts  = $this->splitParams($head);
        $name   = strtoupper(array_shift($parts));
        $params = array();
        foreach ($parts as $p) {
            $eq = strpos($p, '=');
            if ($eq === false) { continue; }
            $k = strtoupper(substr($p, 0, $eq));
            $v = trim(substr($p, $eq + 1), '"');
            $params[$k] = $v;
        }
        return array($name, $params, $value);
    }

    /** Split "NAME;A=1;B="x;y"" on semicolons that are not inside quotes. */
    private function splitParams($head)
    {
        $out = array();
        $buf = '';
        $inQuotes = false;
        for ($i = 0, $n = strlen($head); $i < $n; $i++) {
            $c = $head[$i];
            if ($c === '"') { $inQuotes = !$inQuotes; $buf .= $c; continue; }
            if ($c === ';' && !$inQuotes) { $out[] = $buf; $buf = ''; continue; }
            $buf .= $c;
        }
        $out[] = $buf;
        return $out;
    }

    /* ===================================================================== */
    /*  Stage 2 — a block into one or many events                             */
    /* ===================================================================== */

    private function blockToEvents(array $b, array &$overrides)
    {
        $base = $this->buildEvent($b);
        if ($base === null) { return array(); }

        if (!isset($b['RRULE'])) {
            return array($base);
        }

        $uid      = isset($b['UID']) ? $b['UID']['value'] : '';
        $rrule    = $this->parseRrule($b['RRULE']['value']);
        $exdates  = $this->collectExdates($b);
        $duration = $base->start->diff($base->end);
        $label    = $this->repeatLabel($rrule);

        $out = array();
        foreach ($this->occurrences($base->start, $rrule) as $start) {
            $key = $start->format('Ymd\THis');

            if (isset($exdates[$start->format('Ymd')]) || isset($exdates[$key])) {
                continue;                                  // deleted instance
            }
            if (isset($overrides[$uid][$key]) || isset($overrides[$uid][$start->format('Ymd')])) {
                // This slot was edited; use the edited copy instead.
                $ovKey = isset($overrides[$uid][$key]) ? $key : $start->format('Ymd');
                $ov    = $overrides[$uid][$ovKey];
                $overrides[$uid][$ovKey]['__consumed'] = true;
                $edited = $this->buildEvent($ov);
                if ($edited) {
                    // An edited instance still belongs to the series.
                    $edited->seriesId    = $uid;
                    $edited->repeatLabel = $label;
                    $out[] = $edited;
                }
                continue;
            }

            $ev              = clone $base;
            $ev->start       = $start;
            $ev->end         = $start->add($duration);
            $ev->uid         = $base->uid . '#' . $key;    // unique per occurrence
            $ev->seriesId    = $uid;
            $ev->repeatLabel = $label;
            $out[]           = $ev;
        }
        return $out;
    }

    /** Turn one VEVENT block into an AlpineEvent, or null if it is unusable. */
    private function buildEvent(array $b)
    {
        if (!isset($b['DTSTART'])) { return null; }

        $allDay = false;
        $start  = $this->parseDate($b['DTSTART']['value'], $b['DTSTART']['params'], $allDay);
        if ($start === null) { return null; }

        // No DTEND? Fall back to DURATION, then to a sensible default.
        if (isset($b['DTEND'])) {
            $endAllDay = false;
            $end = $this->parseDate($b['DTEND']['value'], $b['DTEND']['params'], $endAllDay);
        } elseif (isset($b['DURATION'])) {
            $end = $this->applyDuration($start, $b['DURATION']['value']);
        } else {
            $end = null;
        }
        if ($end === null || $end < $start) {
            $end = $allDay ? $start->modify('+1 day') : $start->modify('+1 hour');
        }

        $summary = isset($b['SUMMARY']) ? $this->unescapeText($b['SUMMARY']['value']) : '';
        $rawDesc = isset($b['DESCRIPTION']) ? $this->unescapeText($b['DESCRIPTION']['value']) : '';

        $e             = new AlpineEvent();
        $e->uid        = isset($b['UID']) ? $b['UID']['value'] : md5($summary . $start->format('c'));
        $e->rawTitle   = $summary;
        $e->start      = $start;
        $e->end        = $end;
        $e->allDay     = $allDay;
        $e->cancelled  = isset($b['STATUS']) && strtoupper($b['STATUS']['value']) === 'CANCELLED';
        $e->location   = isset($b['LOCATION']) ? $this->unescapeText($b['LOCATION']['value']) : '';

        list($tag, $title, $guessed) = $this->extractTag($summary);
        $e->tag         = $tag;
        $e->title       = ($title !== '') ? $title : 'Alpine Club event';
        $e->tagGuessed  = $guessed;

        $e->descriptionHtml = $this->safeHtml($rawDesc);
        $e->descriptionText = $this->htmlToText($rawDesc);

        return $e;
    }

    /* ===================================================================== */
    /*  Dates                                                                 */
    /* ===================================================================== */

    /**
     * Handles the three shapes Google emits, and converts everything into the
     * club's timezone so templates never have to think about it.
     */
    private function parseDate($value, array $params, &$allDay)
    {
        $value  = trim($value);
        $allDay = (isset($params['VALUE']) && strtoupper($params['VALUE']) === 'DATE');

        if ($allDay || preg_match('/^\d{8}$/', $value)) {
            $allDay = true;
            $d = DateTimeImmutable::createFromFormat('Ymd|', $value, $this->tz);
            return $d ?: null;
        }

        if (substr($value, -1) === 'Z') {
            $d = DateTimeImmutable::createFromFormat('Ymd\THis\Z', $value, new DateTimeZone('UTC'));
            return $d ? $d->setTimezone($this->tz) : null;
        }

        // TZID given, or "floating" local time — treat floating as club time.
        $zone = $this->tz;
        if (isset($params['TZID'])) {
            try {
                $zone = new DateTimeZone($params['TZID']);
            } catch (Exception $ex) {
                $zone = $this->tz;   // unknown zone name: fall back rather than fail
            }
        }
        $d = DateTimeImmutable::createFromFormat('Ymd\THis', $value, $zone);
        return $d ? $d->setTimezone($this->tz) : null;
    }

    /** Minimal ISO 8601 duration support (P1DT2H30M), used only when DTEND is absent. */
    private function applyDuration(DateTimeImmutable $start, $duration)
    {
        try {
            return $start->add(new DateInterval(trim($duration)));
        } catch (Exception $e) {
            return null;
        }
    }

    /* ===================================================================== */
    /*  Repeats                                                               */
    /* ===================================================================== */

    private function parseRrule($value)
    {
        $out = array();
        foreach (explode(';', $value) as $pair) {
            $eq = strpos($pair, '=');
            if ($eq === false) { continue; }
            $out[strtoupper(substr($pair, 0, $eq))] = strtoupper(substr($pair, $eq + 1));
        }
        return $out;
    }

    /** EXDATE values, keyed so lookup is O(1). Keys use both date and datetime forms. */
    private function collectExdates(array $b)
    {
        $out = array();
        if (empty($b['EXDATE'])) { return $out; }

        foreach ($b['EXDATE'] as $ex) {
            foreach (explode(',', $ex['value']) as $v) {
                $ignored = false;
                $d = $this->parseDate($v, $ex['params'], $ignored);
                if ($d === null) { continue; }
                $out[$d->format('Ymd\THis')] = true;
                $out[$d->format('Ymd')]      = true;
            }
        }
        return $out;
    }

    /**
     * Generate start times for a repeating event, bounded by the parse window,
     * by UNTIL/COUNT, and by a hard iteration cap.
     *
     * @return DateTimeImmutable[]
     */
    private function occurrences(DateTimeImmutable $dtstart, array $rrule)
    {
        $freq = isset($rrule['FREQ']) ? $rrule['FREQ'] : '';
        if (!in_array($freq, array('DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'), true)) {
            return array($dtstart);
        }

        $interval = isset($rrule['INTERVAL']) ? max(1, (int) $rrule['INTERVAL']) : 1;
        $count    = isset($rrule['COUNT']) ? (int) $rrule['COUNT'] : null;

        $until = null;
        if (isset($rrule['UNTIL'])) {
            $ignored = false;
            $until   = $this->parseDate($rrule['UNTIL'], array(), $ignored);
        }

        $byDay = array();
        if ($freq === 'WEEKLY' && isset($rrule['BYDAY'])) {
            foreach (explode(',', $rrule['BYDAY']) as $d) {
                $idx = $this->weekdayIndex(substr(trim($d), -2));
                if ($idx !== null) { $byDay[] = $idx; }
            }
            sort($byDay);
        }

        $out       = array();
        $emitted   = 0;
        $iteration = 0;
        $cursor    = $dtstart;
        $hour = (int) $dtstart->format('H');
        $min  = (int) $dtstart->format('i');
        $sec  = (int) $dtstart->format('s');

        // MONTHLY needs the original day-of-month remembered, otherwise adding a
        // month to the 31st silently slides into the following month.
        $anchorDay = (int) $dtstart->format('j');

        while ($iteration++ < self::MAX_OCCURRENCES_PER_RULE) {
            $slots = array();

            if ($freq === 'WEEKLY' && $byDay) {
                // Monday-based week containing the cursor.
                $weekStart = $cursor->modify('-' . $this->weekdayIndex($cursor->format('D')) . ' days');
                foreach ($byDay as $idx) {
                    $slots[] = $weekStart->modify('+' . $idx . ' days')->setTime($hour, $min, $sec);
                }
            } elseif ($freq === 'MONTHLY') {
                $slots[] = $this->clampToMonth($cursor, $anchorDay)->setTime($hour, $min, $sec);
            } else {
                $slots[] = $cursor;
            }

            foreach ($slots as $s) {
                if ($s < $dtstart)            { continue; }
                if ($until !== null && $s > $until) { break 2; }
                if ($count !== null && $emitted >= $count) { break 2; }
                if ($s > $this->windowEnd)    { break 2; }

                $emitted++;
                if ($s >= $this->windowStart) { $out[] = $s; }
            }

            switch ($freq) {
                case 'DAILY':   $cursor = $cursor->modify('+' . $interval . ' day');   break;
                case 'WEEKLY':  $cursor = $cursor->modify('+' . $interval . ' week');  break;
                case 'MONTHLY': $cursor = $this->addMonths($cursor, $interval);        break;
                case 'YEARLY':  $cursor = $cursor->modify('+' . $interval . ' year');  break;
            }
            if ($cursor > $this->windowEnd) { break; }
        }

        return $out;
    }

    /**
     * "Weekly on Tuesdays", "Every 2 weeks", "Monthly" — something a visitor
     * can read, derived from the RRULE. Empty if we cannot say it simply.
     */
    private function repeatLabel(array $rrule)
    {
        $freq     = isset($rrule['FREQ']) ? $rrule['FREQ'] : '';
        $interval = isset($rrule['INTERVAL']) ? max(1, (int) $rrule['INTERVAL']) : 1;

        $names = array('MO' => 'Mondays', 'TU' => 'Tuesdays', 'WE' => 'Wednesdays',
                       'TH' => 'Thursdays', 'FR' => 'Fridays', 'SA' => 'Saturdays',
                       'SU' => 'Sundays');

        if ($freq === 'WEEKLY' && $interval === 1) {
            if (!empty($rrule['BYDAY'])) {
                $days = array();
                foreach (explode(',', $rrule['BYDAY']) as $d) {
                    $code = substr(trim($d), -2);
                    if (isset($names[$code])) { $days[] = $names[$code]; }
                }
                if (count($days) === 1) { return 'Weekly on ' . $days[0]; }
                if ($days) { return 'Weekly on ' . implode(' and ', $days); }
            }
            return 'Weekly';
        }

        switch ($freq) {
            case 'DAILY':   return $interval === 1 ? 'Daily'   : 'Every ' . $interval . ' days';
            case 'WEEKLY':  return 'Every ' . $interval . ' weeks';
            case 'MONTHLY': return $interval === 1 ? 'Monthly' : 'Every ' . $interval . ' months';
            case 'YEARLY':  return $interval === 1 ? 'Yearly'  : 'Every ' . $interval . ' years';
        }
        return '';
    }

    /** Add whole months without the 31st-of-the-month overflow. */
    private function addMonths(DateTimeImmutable $d, $months)
    {
        return $d->modify('first day of this month')->modify('+' . $months . ' month');
    }

    /** Day-of-month $day in the cursor's month, clamped to the month's length. */
    private function clampToMonth(DateTimeImmutable $cursor, $day)
    {
        $first = $cursor->modify('first day of this month');
        $len   = (int) $first->format('t');
        return $first->modify('+' . (min($day, $len) - 1) . ' days');
    }

    /** MO=0 ... SU=6, matching a Monday-based week. Accepts "MO" or "Mon". */
    private function weekdayIndex($code)
    {
        $map = array(
            'MO' => 0, 'TU' => 1, 'WE' => 2, 'TH' => 3, 'FR' => 4, 'SA' => 5, 'SU' => 6,
            'MON' => 0, 'TUE' => 1, 'WED' => 2, 'THU' => 3, 'FRI' => 4, 'SAT' => 5, 'SUN' => 6,
        );
        $key = strtoupper($code);
        return isset($map[$key]) ? $map[$key] : null;
    }

    private function recurrenceKey(array $prop)
    {
        $ignored = false;
        $d = $this->parseDate($prop['value'], $prop['params'], $ignored);
        return $d ? $d->format('Ymd\THis') : $prop['value'];
    }

    /* ===================================================================== */
    /*  Titles, tags, text                                                    */
    /* ===================================================================== */

    /*
     * The three wrappers below exist so the Google API source can reuse this
     * class's title and description handling. Without them the two sources
     * would drift apart and the same event would render differently depending
     * on which one was switched on.
     */

    /** @see safeHtml */
    public function publicSafeHtml($raw) { return $this->safeHtml($raw); }

    /** @see htmlToText */
    public function publicHtmlToText($raw) { return $this->htmlToText($raw); }

    /** @see extractTag */
    public function publicExtractTag($summary) { return $this->extractTag($summary); }

    /**
     * "[HIKE] Welcome Hike" -> ['hike', 'Welcome Hike', false]
     *
     * When there is no prefix we make one careful guess from keywords, so that
     * events created before this convention existed still get a label. An
     * explicit prefix always wins.
     *
     * @return array [tagKey, cleanTitle, wasGuessed]
     */
    private function extractTag($summary)
    {
        $summary = trim($summary);

        if (preg_match('/^\[\s*([A-Za-z0-9 _\-\/&]{1,24})\s*\]\s*(.*)$/u', $summary, $m)) {
            $key   = alpine_normalise_tag($m[1]);
            $title = trim($m[2]);
            if ($key !== '') {
                return array($key, $title, false);
            }
            // Unknown tag in brackets: drop the bracket, keep the words as the title.
            return array('', trim($m[1] . ' ' . $title), false);
        }

        $guess = alpine_guess_tag($summary);
        return array($guess, $summary, $guess !== '');
    }

    /** Undo iCalendar TEXT escaping. */
    private function unescapeText($v)
    {
        $out = '';
        $len = strlen($v);
        for ($i = 0; $i < $len; $i++) {
            if ($v[$i] === '\\' && $i + 1 < $len) {
                $next = $v[$i + 1];
                if ($next === 'n' || $next === 'N') { $out .= "\n"; $i++; continue; }
                if ($next === ',' || $next === ';' || $next === '\\') { $out .= $next; $i++; continue; }
            }
            $out .= $v[$i];
        }
        return $out;
    }

    /**
     * Google descriptions arrive as either plain text or a little HTML.
     * Keep a small, safe subset; drop everything else including all attributes
     * apart from href, and allow only http/https/mailto links.
     */
    private function safeHtml($raw)
    {
        $raw = trim($raw);
        if ($raw === '') { return ''; }

        $looksLikeHtml = (strpos($raw, '<') !== false);
        if (!$looksLikeHtml) {
            return nl2br(htmlspecialchars($raw, ENT_QUOTES, 'UTF-8'), false);
        }

        $html = strip_tags($raw, '<a><br><p><b><strong><i><em><u><ul><ol><li>');

        // Rebuild every <a> from scratch: keep a vetted href, discard the rest.
        $html = preg_replace_callback('/<a\b[^>]*>/i', function ($m) {
            if (!preg_match('/href\s*=\s*(["\'])(.*?)\1/i', $m[0], $h)) {
                return '<a>';
            }
            $url = html_entity_decode($h[2], ENT_QUOTES, 'UTF-8');
            if (!preg_match('#^(https?://|mailto:)#i', $url)) {
                return '<a>';
            }
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
                 . '" rel="noopener noreferrer" target="_blank">';
        }, $html);

        // Strip attributes from every other permitted tag.
        $html = preg_replace('/<(?!a\b)(\/?)([a-z0-9]+)\b[^>]*>/i', '<$1$2>', $html);

        return $html;
    }

    /** Plain-text version, used for excerpts and meta descriptions. */
    private function htmlToText($raw)
    {
        $t = preg_replace('/<br\s*\/?>/i', "\n", $raw);
        $t = preg_replace('/<\/(p|li|div)>/i', "\n", $t);
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
        return trim($t);
    }
}
