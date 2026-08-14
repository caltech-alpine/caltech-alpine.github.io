<?php
/**
 * ============================================================================
 *  EXAMPLE local configuration.
 * ============================================================================
 *
 *  Copy this file to  includes/config.local.php  on the server and edit it.
 *  That filename is git-ignored, so anything in it stays off GitHub.
 *
 *      cp includes/config.local.example.php includes/config.local.php
 *
 *  Values here override includes/config.php. You only need the keys you are
 *  actually changing; everything else falls through to the committed config.
 *
 *  This file is only needed if you have something that should not be public.
 *  The site works with no local config at all, because the calendar it reads
 *  is public and needs no credentials.
 * ============================================================================
 */

return array(

    'calendar' => array(

        // Locks preview.php behind ?key=... — see the top of preview.php.
        // Make up a long random string; it is not a password for anything else.
        'preview_key' => '',

        // A calendar to test against, so test events never appear on the real
        // club calendar. Safe to put in the public config instead if you like.
        'test_calendar_id' => '',

        // ONLY if the club has moved to the Google Calendar API. Leave empty
        // and the site uses the public .ics feed, which needs no key.
        // Restrict any key you create to the Calendar API and to the
        // alpine.caltech.edu HTTP referrer in the Google Cloud console.
        'api_key' => '',
    ),

);
