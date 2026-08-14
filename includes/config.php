<?php
/**
 * ============================================================================
 *  SITE CONFIGURATION
 * ============================================================================
 *
 *  ***  START HERE.  This is the file to edit when a link changes.  ***
 *
 *  Nothing here is secret, and nothing here needs to be secret — the site
 *  runs entirely off the club's PUBLIC Google Calendar, so there is no API
 *  key to leak. See README.md if you ever decide to add one.
 *
 *  Lists that grow and shrink live next door, one file each:
 *      data/officers.php     who runs the club
 *      data/sponsors.php     who supports it
 *      data/activities.php   the [TAG] labels understood on calendar events
 *      data/gear.php         what the club lends
 * ============================================================================
 */

return array(

    /* ---------------------------------------------------------------- site */
    'site' => array(
        'name'        => 'Caltech Alpine Club',
        'short'       => 'Alpine Club',
        'tagline'     => 'Less lab. More mountains.',
        /* Search results and link previews only. Deliberately NOT the same as the
           hero line: this one has to carry the activity words somebody would
           actually type into a search box. */
        'description' => 'Hiking, backpacking, climbing and skiing with the Caltech Alpine '
                       . 'Club: trips, shared gear and people to go with, since 1987. '
                       . 'Anyone can join.',
        'url'         => 'https://alpine.caltech.edu',

        // Drop a logo file in assets/images/ and put its name here. Leave it
        // empty and the header shows a clean wordmark instead — which looks
        // deliberate, so there is no rush.
        'logo'        => '',
        'logo_alt'    => 'Caltech Alpine Club',
    ),

    /* ------------------------------------------------------------ calendar */
    /*  To add an event to the website, add it to this Google Calendar.
        There is no second step. Optionally prefix the title with an activity
        tag — [HIKE], [CLIMB], [SNOW], [RUN], [BIKE], [SOCIAL], [COURSE] —
        and the site turns it into a label. See data/activities.php.          */
    'calendar' => array(
        'calendar_id' => 'e13b4ff623b227d4b2445fe2aadc6cb2cd18080f81f9c2edfcc51f3f9a58f673@group.calendar.google.com',

        'ics_url'     => 'https://calendar.google.com/calendar/ical/'
                       . 'e13b4ff623b227d4b2445fe2aadc6cb2cd18080f81f9c2edfcc51f3f9a58f673%40group.calendar.google.com'
                       . '/public/basic.ics',

        'embed_url'   => 'https://calendar.google.com/calendar/embed?src='
                       . 'e13b4ff623b227d4b2445fe2aadc6cb2cd18080f81f9c2edfcc51f3f9a58f673%40group.calendar.google.com'
                       . '&ctz=America%2FLos_Angeles&mode=MONTH&showTitle=0&showPrint=0&showTabs=1&showCalendars=0',

        // "Subscribe" links members can add to their own calendar app.
        'subscribe_google' => 'https://calendar.google.com/calendar/render?cid='
                       . 'e13b4ff623b227d4b2445fe2aadc6cb2cd18080f81f9c2edfcc51f3f9a58f673%40group.calendar.google.com',
        'subscribe_ical'   => 'webcal://calendar.google.com/calendar/ical/'
                       . 'e13b4ff623b227d4b2445fe2aadc6cb2cd18080f81f9c2edfcc51f3f9a58f673%40group.calendar.google.com'
                       . '/public/basic.ics',

        'timezone'    => 'America/Los_Angeles',

        // How long a fetched copy is reused before we ask Google again.
        // 1800 = 30 minutes. Raise it if the host is slow, lower it if you are
        // impatient while testing.
        'cache_ttl'   => 1800,

        'cache_dir'   => dirname(__DIR__) . '/cache',

        // How many events each section shows.
        'home_limit'  => 4,
        'past_limit'  => 12,

        /* A repeating event (a standing weekly run, say) expands to dozens of
           occurrences. With this on, the site shows only the NEXT one, tagged
           "Weekly on Tuesdays", instead of burying everything else under
           eighty identical cards. Set false to list every occurrence. */
        'collapse_repeats' => true,

        /* --- testing -------------------------------------------------------
           preview.php renders any public Google Calendar using the real site's
           components, so you can test unusual events without putting them on
           the club calendar. Create a second, public calendar and paste its ID
           here. Instructions are at the top of preview.php.                  */
        'test_calendar_id' => '',

        /* Optional. Set this (ideally in includes/config.local.php, which is
           git-ignored) and preview.php returns 404 unless the URL carries
           ?key=<that value>. Leave empty and the page is simply unlisted:
           not linked, noindex, and disallowed in robots.txt.                 */
        'preview_key'      => '',

        // LEAVE EMPTY. Only set this if you have moved to the Google Calendar
        // API, and then set it from outside the repository — see README.md,
        // "If you ever add a Google API key". An empty value means the site
        // uses the public ICS feed, which needs no credentials at all.
        'api_key'     => '',
    ),

    /* --------------------------------------------------------------- links */
    /*  Check these once a year. A dead join link is the single most damaging
        thing that can quietly break on this site.                            */
    'links' => array(
        /* ---------------------------------------------------------------
           THREE DIFFERENT ADDRESSES. They are easy to mix up, and mixing
           them up is expensive, so they are named for what they DO:

             officers   a shared mailbox. Goes to the officers. Use this for
                        every "contact us" link on the site.
             list       the MAILING LIST. Anything sent here goes to all
                        ~200 members. Never wire a contact button to it.
                        Recorded here as a warning to whoever edits this file
                        next; it is deliberately not shown to visitors.
             secretary  membership questions, including join requests from
                        people with no Caltech or JPL affiliation.
           --------------------------------------------------------------- */
        'officers'     => 'alpine@caltech.edu',
        'list'         => 'alpineclub@caltech.edu',
        'secretary'    => 'alpine-secretary@caltech.edu',

        /* Where people subscribe to the list (not the list address itself). */
        'mailing_list' => 'https://lists.caltech.edu/listinfo/alpineclub',
        'mailing_list_note' => 'Needs a campus connection or the Caltech VPN.',

        // TODO: paste the Slack invite link. Until then the Join page tells
        // people to ask on the mailing list, which is honest and still works.
        'slack'        => '',


        /* General equipment: booked through the Caltech Y's own system. */
        'gear_rental'  => 'https://www.caltechy.org/rentals',

        /* Specialist club gear (trad racks, ice tools, packrafts): the reservation
           form IS the booking route. The Google Form itself, not the page that
           links to it. Titled "Alpine club gear reservation"; asks for name,
           phone, affiliation, which gear and the dates. Verified 2026-08-14.

           There was an availability calendar here too. It was dropped on purpose
           (2026-08-14): it gave a second, weaker answer to "can I have this" and
           the form is the one that actually reserves anything. */
        'gear_form'     => 'https://forms.gle/fHShsL6i7F9rQMnx7',
        /* Caltech's giving form, pre-set to the Alpine Club's designated fund
           (dids=1069). Taken from the old site's /donate page, verified 200 on
           2026-08-14. Do not trim the query string: without it the form lands on
           a general Caltech fund and the money never reaches the club. */
        'donate'       => 'https://securelb.imodules.com/s/1709/devassoc/giving/'
                        . 'giving.aspx?sid=1709&gid=3&bledit=1&dids=1069&pgid=498&cid=1220',
        'caltech'      => 'https://www.caltech.edu',
        'accessibility'=> 'https://digitalaccessibility.caltech.edu/',
        'privacy'      => 'https://www.caltech.edu/privacy-notice',
    ),

    /* ------------------------------------------------------------ the club */
    /*  Facts used on the homepage and About page. Update when they change —
        which is rarely, which is the point.                                  */
    'facts' => array(
        'founded'      => 1987,
        'founder'      => 'Fritz Nordby',
        /* Deliberately not a number. An exact count goes stale the moment
           somebody graduates, and nobody recounts it. */
        'members'      => 'Hundreds',
        'banff_since'  => 2001,
        'mission'      => 'To foster the Caltech outdoor community by encouraging '
                        . 'mentorship, responsibility, inclusivity, and radness.',
    ),
);
