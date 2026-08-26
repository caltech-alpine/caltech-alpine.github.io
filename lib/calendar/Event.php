<?php
/**
 * ============================================================================
 *  AlpineEvent — one calendar event, normalised for the templates.
 * ============================================================================
 *
 *  You should not need to edit this file to run the club.
 *  Events come from Google Calendar; see README.md -> "How to update the site".
 *
 *  Everything the templates need is either a public property or a small
 *  formatting method, so page code stays free of date arithmetic.
 * ============================================================================
 */

class AlpineEvent
{
    /** Stable id from the calendar (UID, plus the occurrence date for repeats). */
    public $uid = '';

    /** Title exactly as typed in Google Calendar, e.g. "[RUN] Weekly trail run". */
    public $rawTitle = '';

    /** What visitors see. A leading [bracket], if any, is dropped. */
    public $title = '';

    /**
     * For a repeating event, the id shared by every occurrence in the series.
     * Empty for one-off events. Used to collapse "every Tuesday forever" down
     * to its next occurrence instead of printing eighty identical cards.
     */
    public $seriesId = '';

    /** Human label for how often it repeats, e.g. "Weekly on Tuesdays". */
    public $repeatLabel = '';

    /** @var DateTimeImmutable Start, already converted to the club timezone. */
    public $start;

    /**
     * @var DateTimeImmutable End, in the club timezone.
     * For all-day events this is the EXCLUSIVE end that iCalendar uses
     * (a one-day event ends at midnight the following day). Use
     * displayEnd() for anything shown to a human.
     */
    public $end;

    /** True for date-only events ("all day" in Google Calendar). */
    public $allDay = false;

    /** True when the organizer cancelled it but it is still in the feed. */
    public $cancelled = false;

    /** May be an empty string — templates must cope. */
    public $location = '';

    /** Sanitised HTML (safe subset) — Google descriptions may contain links. */
    public $descriptionHtml = '';

    /** Same content as plain text, used for excerpts and meta tags. */
    public $descriptionText = '';

    /* --------------------------------------------------------------------- */
    /*  Shape                                                                 */
    /* --------------------------------------------------------------------- */

    /** The last day a human would say the event runs on. */
    public function displayEnd()
    {
        if ($this->allDay) {
            // iCalendar all-day DTEND is exclusive; step back into the last real day.
            $e = $this->end->modify('-1 day');
            return ($e < $this->start) ? $this->start : $e;
        }
        return $this->end;
    }

    /** True when the event spans more than one calendar day. */
    public function isMultiDay()
    {
        return $this->start->format('Y-m-d') !== $this->displayEnd()->format('Y-m-d');
    }

    /** Has the event finished? Compared against the end, so today's events stay "upcoming". */
    public function isPast(DateTimeImmutable $now)
    {
        return $this->end <= $now;
    }

    /* --------------------------------------------------------------------- */
    /*  Human-readable date and time                                          */
    /* --------------------------------------------------------------------- */

    /**
     * "Saturday, September 26"           single day
     * "September 26–28"                  multi-day, same month
     * "September 30 – October 2"         multi-day, spanning months
     * "December 30, 2026 – January 2, 2027"  spanning years
     */
    public function dateLine()
    {
        $start = $this->start;
        $end   = $this->displayEnd();

        if (!$this->isMultiDay()) {
            $fmt = $this->sameYearAsNow($start) ? 'l, F j' : 'l, F j, Y';
            return $start->format($fmt);
        }

        $sameYear  = $start->format('Y') === $end->format('Y');
        $sameMonth = $sameYear && $start->format('m') === $end->format('m');

        if ($sameMonth) {
            // September 26–28
            return $start->format('F j') . '–' . $end->format('j');
        }
        if ($sameYear) {
            // September 30 – October 2
            return $start->format('F j') . ' – ' . $end->format('F j');
        }
        // December 30, 2026 – January 2, 2027
        return $start->format('F j, Y') . ' – ' . $end->format('F j, Y');
    }

    /**
     * "8:00 AM", "8:00 AM – 2:30 PM", or "All day".
     * Returns '' when there is nothing worth showing.
     */
    public function timeLine()
    {
        if ($this->allDay) {
            return $this->isMultiDay() ? '' : 'All day';
        }

        $start = $this->fmtTime($this->start);

        // Only show an end time when it is on the same day and actually different.
        if (!$this->isMultiDay() && $this->end > $this->start) {
            return $start . ' – ' . $this->fmtTime($this->end);
        }
        return $start;
    }

    /** The single line used on cards: "Saturday, September 26 · 8:00 AM". */
    public function whenLine()
    {
        $parts = array_filter(array($this->dateLine(), $this->timeLine()));
        return implode(' · ', $parts);
    }

    /* -- the little date block on a card ---------------------------------- */

    /** "Sep" */
    public function chipMonth() { return $this->start->format('M'); }

    /** "26" */
    public function chipDay() { return $this->start->format('j'); }

    /** "2027", but only when it is not the current year — otherwise ''. */
    public function chipYear()
    {
        return $this->sameYearAsNow($this->start) ? '' : $this->start->format('Y');
    }

    /**
     * The line beside the date block. Avoids repeating what the block already
     * shows: "Saturday · 8:00 AM" rather than "Saturday, September 26 · 8:00 AM".
     * Multi-day events fall back to the full range, which the block cannot show.
     */
    public function shortWhen()
    {
        $time = $this->timeLine();

        if ($this->isMultiDay()) {
            return implode(' · ', array_filter(array($this->dateLine(), $time)));
        }
        return implode(' · ', array_filter(array($this->start->format('l'), $time)));
    }

    /** Machine-readable value for <time datetime="..."> */
    public function isoStart()
    {
        return $this->allDay ? $this->start->format('Y-m-d') : $this->start->format('c');
    }

    /** Month label used to group past events, e.g. "September 2026". */
    public function monthLabel()
    {
        return $this->start->format('F Y');
    }

    /* --------------------------------------------------------------------- */
    /*  Text                                                                  */
    /* --------------------------------------------------------------------- */

    /** Short plain-text summary for a card. Cuts on a word boundary. */
    public function excerpt($maxChars = 165)
    {
        $text = trim(preg_replace('/\s+/u', ' ', $this->descriptionText));
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_strlen') ? mb_strlen($text) <= $maxChars : strlen($text) <= $maxChars) {
            return $text;
        }
        $cut = function_exists('mb_substr') ? mb_substr($text, 0, $maxChars) : substr($text, 0, $maxChars);
        $lastSpace = strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > $maxChars * 0.6) {
            $cut = substr($cut, 0, $lastSpace);
        }
        return rtrim($cut, " \t\n\r\0\x0B.,;:—-") . '…';
    }

    /* --------------------------------------------------------------------- */
    /*  Add-to-calendar                                                       */
    /* --------------------------------------------------------------------- */

    /**
     * A Google Calendar "add this event" link.
     *
     * We deliberately build a TEMPLATE link from the event data rather than
     * deep-linking to the event id. Deep links depend on an undocumented
     * base64 "eid" encoding that breaks quietly; this always works, and it
     * copies the event into the visitor's own calendar, which is what they
     * actually want.
     */
    public function addToCalendarUrl()
    {
        if ($this->allDay) {
            $dates = $this->start->format('Ymd') . '/' . $this->end->format('Ymd');
        } else {
            $utc   = new DateTimeZone('UTC');
            $dates = $this->start->setTimezone($utc)->format('Ymd\THis\Z')
                   . '/' . $this->end->setTimezone($utc)->format('Ymd\THis\Z');
        }

        $params = array(
            'action'  => 'TEMPLATE',
            'text'    => $this->title,
            'dates'   => $dates,
            'details' => $this->descriptionText,
            'location' => $this->location,
            'ctz'     => $this->start->getTimezone()->getName(),
        );
        return 'https://calendar.google.com/calendar/render?' . http_build_query(array_filter($params));
    }

    /* --------------------------------------------------------------------- */

    private function fmtTime(DateTimeImmutable $d)
    {
        // 8:00 AM -> "8:00 AM"; 8:30 PM -> "8:30 PM"; on the hour keeps :00
        // because "8 AM – 2:30 PM" reads unevenly on a card.
        return $d->format('g:i A');
    }

    private function sameYearAsNow(DateTimeImmutable $d)
    {
        return $d->format('Y') === date('Y');
    }
}
