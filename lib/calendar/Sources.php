<?php
/**
 * ============================================================================
 *  Where events come from.
 * ============================================================================
 *
 *  WHY THIS IS SERVER-SIDE PHP AND NOT BROWSER JAVASCRIPT
 *  -----------------------------------------------------
 *  Google's public .ics feed does not send an Access-Control-Allow-Origin
 *  header (verified against the club's own feed). A browser therefore refuses
 *  to read it from our pages, so "just fetch the ICS in JavaScript" cannot
 *  work, no matter how it is written. Fetching it from PHP sidesteps CORS
 *  entirely — CORS is a browser rule, not a server one.
 *
 *  Doing it on the server also buys three things worth having:
 *    - no API key, so nothing secret ever lives in this public repository
 *    - events are in the HTML, so they are fast, indexable, and work with
 *      JavaScript switched off
 *    - one cached copy is shared by every visitor instead of every visitor
 *      hitting Google themselves
 *
 *  Two sources are defined below. The ICS one is what runs today. The Google
 *  API one exists so that adding a key later is a config change, not a
 *  redesign — both produce the same AlpineEvent objects.
 * ============================================================================
 */

interface AlpineCalendarSource
{
    /** @return AlpineEvent[] sorted by start time */
    public function events();

    /** @return array{state:string, fetched_at:?int, error:string} */
    public function status();
}


/**
 * ----------------------------------------------------------------------------
 *  Cached HTTP GET.
 * ----------------------------------------------------------------------------
 *  Keeps a copy of the last good response on disk. If Google is slow or down,
 *  we serve the stale copy rather than an empty page — a calendar that is a
 *  few hours out of date is far better than a blank "Coming Up" section.
 */
class AlpineHttpCache
{
    private $dir;
    private $ttl;
    private $timeout;

    public function __construct($cacheDir, $ttl = 1800, $timeout = 8)
    {
        $this->dir     = rtrim($cacheDir, "/\\");
        $this->ttl     = (int) $ttl;
        $this->timeout = (int) $timeout;
    }

    /**
     * @return array{body:?string, state:string, fetched_at:?int, error:string}
     *         state is one of: fresh | live | stale | unavailable
     */
    public function get($url)
    {
        $file = $this->dir . '/' . md5($url) . '.cache';
        $age  = is_readable($file) ? (time() - (int) filemtime($file)) : null;

        // Still inside the TTL: serve it without touching the network.
        if ($age !== null && $age < $this->ttl) {
            return array(
                'body'       => file_get_contents($file),
                'state'      => 'fresh',
                'fetched_at' => filemtime($file),
                'error'      => '',
            );
        }

        $error = '';
        $body  = $this->download($url, $error);

        if ($body !== null && $body !== '') {
            $this->write($file, $body);
            return array('body' => $body, 'state' => 'live', 'fetched_at' => time(), 'error' => '');
        }

        // Network failed. Fall back to whatever we last managed to fetch.
        if ($age !== null) {
            return array(
                'body'       => file_get_contents($file),
                'state'      => 'stale',
                'fetched_at' => filemtime($file),
                'error'      => $error,
            );
        }

        return array('body' => null, 'state' => 'unavailable', 'fetched_at' => null, 'error' => $error);
    }

    /** cURL if the host has it, otherwise streams. Shared hosts vary. */
    private function download($url, &$error)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->timeout,
                CURLOPT_USERAGENT      => 'CaltechAlpineClub-Website/1.0 (+https://alpine.caltech.edu)',
            ));
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($body === false) { $error = 'curl: ' . curl_error($ch); }
            elseif ($code >= 400) { $error = 'HTTP ' . $code; $body = false; }
            curl_close($ch);
            if ($body !== false) { return $body; }
        }

        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(array('http' => array(
                'timeout' => $this->timeout,
                'header'  => "User-Agent: CaltechAlpineClub-Website/1.0\r\n",
            )));
            $body = @file_get_contents($url, false, $ctx);
            if ($body !== false) { return $body; }
            $error = $error ?: 'file_get_contents failed';
            return null;
        }

        $error = $error ?: 'no HTTP transport available (need curl or allow_url_fopen)';
        return null;
    }

    /** Write to a temp file then rename, so a half-written cache is never read. */
    private function write($file, $body)
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
        if (!is_writable($this->dir)) {
            return;   // read-only deploy: run without a cache rather than crash
        }
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $body, LOCK_EX) !== false) {
            @rename($tmp, $file);
        }
    }
}


/**
 * ----------------------------------------------------------------------------
 *  The source in use today: the public .ics feed. No key, no secrets.
 * ----------------------------------------------------------------------------
 */
class AlpineIcsCalendarSource implements AlpineCalendarSource
{
    private $config;
    private $status = array('state' => 'unavailable', 'fetched_at' => null, 'error' => '');

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function events()
    {
        $cache  = new AlpineHttpCache($this->config['cache_dir'], $this->config['cache_ttl']);
        $result = $cache->get($this->config['ics_url']);

        $this->status = array(
            'state'      => $result['state'],
            'fetched_at' => $result['fetched_at'],
            'error'      => $result['error'],
        );

        if ($result['body'] === null) {
            return array();
        }

        $parser = new IcsParser($this->config['timezone']);
        return $parser->parse($result['body']);
    }

    public function status()
    {
        return $this->status;
    }
}


/**
 * ----------------------------------------------------------------------------
 *  Optional future source: the Google Calendar API.
 * ----------------------------------------------------------------------------
 *  Not used unless a key is configured. It is here so that switching is a
 *  one-line config change rather than a rewrite — it returns exactly the same
 *  AlpineEvent objects, so no page code changes.
 *
 *  The API expands repeating events for us (singleEvents=true), which is its
 *  main advantage. It is not otherwise better than the ICS feed for our needs,
 *  and it adds a key to manage — see README.md before turning it on.
 *
 *  NOTE: untested, because the club has no API key yet. Verify against a real
 *  key before relying on it.
 */
class AlpineGoogleApiCalendarSource implements AlpineCalendarSource
{
    private $config;
    private $status = array('state' => 'unavailable', 'fetched_at' => null, 'error' => '');

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function events()
    {
        $tz  = new DateTimeZone($this->config['timezone']);
        $now = new DateTimeImmutable('now', $tz);

        $url = 'https://www.googleapis.com/calendar/v3/calendars/'
             . rawurlencode($this->config['calendar_id']) . '/events?'
             . http_build_query(array(
                 'key'          => $this->config['api_key'],
                 'singleEvents' => 'true',
                 'orderBy'      => 'startTime',
                 'timeMin'      => $now->modify('-18 months')->format('c'),
                 'timeMax'      => $now->modify('+18 months')->format('c'),
                 'maxResults'   => 250,
                 'timeZone'     => $this->config['timezone'],
             ));

        $cache  = new AlpineHttpCache($this->config['cache_dir'], $this->config['cache_ttl']);
        $result = $cache->get($url);

        $this->status = array(
            'state'      => $result['state'],
            'fetched_at' => $result['fetched_at'],
            'error'      => $result['error'],
        );

        if ($result['body'] === null) { return array(); }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['items'])) {
            $this->status['state'] = 'unavailable';
            $this->status['error'] = 'unexpected API response';
            return array();
        }

        $events = array();
        foreach ($data['items'] as $item) {
            $ev = $this->itemToEvent($item, $tz);
            if ($ev) { $events[] = $ev; }
        }

        usort($events, function ($a, $b) {
            if ($a->start == $b->start) { return 0; }
            return ($a->start < $b->start) ? -1 : 1;
        });
        return $events;
    }

    public function status()
    {
        return $this->status;
    }

    private function itemToEvent(array $item, DateTimeZone $tz)
    {
        if (empty($item['start'])) { return null; }

        $allDay = isset($item['start']['date']);
        $rawS   = $allDay ? $item['start']['date'] : $item['start']['dateTime'];
        $rawE   = $allDay
            ? (isset($item['end']['date']) ? $item['end']['date'] : $rawS)
            : (isset($item['end']['dateTime']) ? $item['end']['dateTime'] : $rawS);

        try {
            $start = (new DateTimeImmutable($rawS))->setTimezone($tz);
            $end   = (new DateTimeImmutable($rawE))->setTimezone($tz);
        } catch (Exception $e) {
            return null;
        }

        $summary = isset($item['summary']) ? $item['summary'] : '';
        $desc    = isset($item['description']) ? $item['description'] : '';

        $e            = new AlpineEvent();
        $e->uid       = isset($item['id']) ? $item['id'] : md5($summary . $rawS);
        $e->rawTitle  = $summary;
        $e->start     = $start;
        $e->end       = $end;
        $e->allDay    = $allDay;
        $e->cancelled = (isset($item['status']) && $item['status'] === 'cancelled');
        $e->location  = isset($item['location']) ? $item['location'] : '';

        // Reuse the ICS parser's title/description handling so both sources agree.
        $helper = new IcsParser($tz->getName());
        $e->descriptionHtml = $helper->publicSafeHtml($desc);
        $e->descriptionText = $helper->publicHtmlToText($desc);
        list($tag, $title, $guessed) = $helper->publicExtractTag($summary);
        $e->tag        = $tag;
        $e->title      = ($title !== '') ? $title : 'Alpine Club event';
        $e->tagGuessed = $guessed;

        return $e;
    }
}
