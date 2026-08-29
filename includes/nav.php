<?php
/**
 * ============================================================================
 *  NAVIGATION — defined once, rendered everywhere.
 * ============================================================================
 *
 *  Add a page here and it appears in the desktop menu, the mobile menu and
 *  the footer. There is no second list to keep in sync.
 *
 *  A note on the sub-items: several of them are anchors on a parent page
 *  (gear.php#rental) rather than separate files. That is deliberate. It keeps
 *  the site to a handful of excellent pages now, and promoting any anchor to
 *  its own file later is a two-step change: create the file, edit the href
 *  here. Nothing else refers to these URLs.
 * ============================================================================
 */

return array(

    array(
        'label' => 'Events & Trips',
        'href'  => 'events.php',
        'children' => array(
            array('label' => 'Upcoming events', 'href' => 'events.php#upcoming'),
            array('label' => 'Club calendar',   'href' => 'events.php#calendar'),
            array('label' => 'Past events',     'href' => 'events.php#past'),
        ),
    ),

    array(
        'label' => 'Gear',
        'href'  => 'gear.php',
    ),

    /* GET INVOLVED vs the "Join" button in the masthead. They sound alike and
       they are not the same ask: Join gets you on the mailing list and onto
       trips, Get Involved is about helping run the club. Both are kept because
       a site that only offers "Join" leaves somebody who wants to do more with
       nowhere to go, and the officers page alone reads as a closed shop.

       The sub-items skip the "what we need right now" section on purpose: that
       block only renders when a role is actually open, and a footer link to an
       anchor that is not on the page is a dead link for most of the year. */
    array(
        'label' => 'Get Involved',
        'href'  => 'roles.php',
        'children' => array(
            array('label' => 'The roles',    'href' => 'roles.php#roles'),
            array('label' => 'Becoming an officer', 'href' => 'roles.php#how'),
        ),
    ),

    array(
        'label' => 'About',
        'href'  => 'about.php',
        'children' => array(
            array('label' => 'What we do', 'href' => 'about.php#what'),
            array('label' => 'Officers',   'href' => 'about.php#officers'),
            array('label' => 'Contact',    'href' => 'about.php#contact'),
        ),
    ),

    array(
        'label' => 'Support',
        'href'  => 'support.php',
        'children' => array(
            array('label' => 'Sponsorship', 'href' => 'support.php#sponsor'),
            array('label' => 'Donate',      'href' => 'support.php#donate'),
        ),
    ),

);
