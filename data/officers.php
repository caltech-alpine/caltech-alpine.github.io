<?php
/**
 * ============================================================================
 *  OFFICERS  —  update this after every election. It is the only file to edit.
 * ============================================================================
 *
 *  WHEN SOMEONE STEPS DOWN, DO NOT DELETE THEM.
 *  Add an 'until' year and they move to the past-officers list on the About
 *  page automatically. That way the club keeps a record of who ran it without
 *  anybody having to maintain a second list.
 *
 *      'until' => 2026,      <- served through 2026, now a past officer
 *      (no 'until')          <- currently serving
 *
 *  Fields
 *    name     required
 *    role     required — their title. People sharing a title appear together.
 *    handles  what to contact them about. Leave empty to hide the line.
 *    group    the heading they appear under while serving
 *    email    optional. Club addresses are fine to publish; think twice
 *             before publishing a personal one.
 *    photo    optional filename in assets/images/officers/
 *             No photo? The page draws a stick figure instead, which looks
 *             deliberate — so nobody is held up waiting for a headshot.
 *    until    optional year they stopped serving. See above.
 *
 *  ORDER ON THE PAGE is worked out for you, by seniority of role and then
 *  alphabetically. Do not bother arranging this file by hand.
 *  The seniority table is alpine_role_rank() in includes/officers.php.
 *
 *  ADDING A HEADSHOT: crop it to roughly 4:5, about 500px wide, save it as
 *  assets/images/officers/firstname-lastname.jpg, and name it below.
 * ============================================================================
 */

return array(

    /* ---- Steering Committee ---------------------------------------------- */

    array(
        'name'    => 'Zach Auvil',
        'role'    => 'Co-President',
        'handles' => 'Anything about the club in general',
        'email'   => 'zauvil@caltech.edu',
        'group'   => 'Steering Committee',
        'photo'   => 'zach-auvil.jpg',
    ),

    array(
        'name'    => 'Haakon Ludvig Langeland Ervik',
        'role'    => 'Treasurer',
        'handles' => 'Reimbursements and club funds',
        'email'   => 'haakon@caltech.edu',
        'group'   => 'Steering Committee',
        'photo'   => 'haakon-ludvig-langeland-ervik.jpg',
    ),

    array(
        'name'    => 'Kyle Hunady',
        'role'    => 'Secretary',
        'handles' => 'Mailing list and membership',
        'email'   => 'khunady@caltech.edu',
        'group'   => 'Steering Committee',
        'photo'   => 'kyle-hunady.jpg',
    ),

    array(
        'name'    => 'Forrest McCann',
        'role'    => 'Gear Officer',
        'handles' => 'Borrowing club equipment, including specialist gear',
        'email'   => 'fmccann@caltech.edu',
        'group'   => 'Steering Committee',
        'photo'   => '',
    ),

    array(
        'name'    => 'Holly Krynicki',
        'role'    => 'Partnerships & Deals Lead',
        'handles' => 'Sponsorships, member discounts, and pro deals',
        'email'   => 'hkrynick@caltech.edu',
        'group'   => 'Steering Committee',
        'photo'   => 'holly-krynicki.jpg',
    ),

    /* ---- Activity Leaders ------------------------------------------------ */

    array(
        'name'    => 'Elise Sledge',
        'role'    => 'Climbing Commodore',
        'handles' => 'Climbing trips and the bouldering wall',
        'email'   => '',          // TODO: still needed
        'group'   => 'Activity Leaders',
        'photo'   => '',
    ),

    array(
        'name'    => 'Jarek Kwiecinski',
        'role'    => 'Hiking and Backpacking Trip Coordinator',
        'handles' => 'Hiking and backpacking trips',
        'email'   => 'jkwiecin@caltech.edu',
        'group'   => 'Activity Leaders',
        'photo'   => 'jarek-kwiecinski.jpg',
    ),

    array(
        'name'    => 'Max Freeman',
        'role'    => 'Hiking and Backpacking Trip Coordinator',
        'handles' => 'Hiking and backpacking trips',
        'email'   => 'mpfreema@caltech.edu',
        'group'   => 'Activity Leaders',
        'photo'   => '',
    ),

    array(
        'name'    => 'Julian F. Schmitt',
        'role'    => 'Trail Run Tyrant',
        'handles' => 'The weekly trail run',
        'email'   => 'jschmitt@caltech.edu',
        'group'   => 'Activity Leaders',
        'photo'   => '',
    ),

    /* ---- Past officers ---------------------------------------------------
       Kept here, not deleted. The 'until' year is what moves them.          */

    array(
        'name'    => 'Daniel Van Beveren',
        'role'    => 'Co-President',
        'group'   => 'Steering Committee',
        'photo'   => 'daniel-van-beveren.jpg',
        'until'   => 2026,
    ),

    array(
        'name'    => 'Tina Seeger',
        'role'    => 'Film Festival Coordinator',
        'group'   => 'Steering Committee',
        'photo'   => 'tina-seeger.jpg',
        'until'   => 2026,
    ),

    array(
        'name'    => 'Abby Keebler',
        'role'    => 'Film Festival Coordinator',
        'group'   => 'Steering Committee',
        'photo'   => 'abby-keebler.jpg',
        'until'   => 2026,
    ),

    array(
        'name'    => 'Noel Csomay-Shanklin',
        'role'    => 'Talks Coordinator',
        'group'   => 'Activity Leaders',
        'photo'   => 'noel-csomay-shanklin.jpg',
        'until'   => 2026,
    ),

    array(
        'name'    => 'Aubrey Schonhoff',
        'role'    => 'Tyrant of Trail Running',
        'group'   => 'Activity Leaders',
        'photo'   => 'aubrey-schonhoff.jpg',
        'until'   => 2026,
    ),

);
