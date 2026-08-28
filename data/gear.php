<?php
/**
 * ============================================================================
 *  GEAR INVENTORY  —  what the club lends, and how each part of it is booked.
 * ============================================================================
 *
 *  Two sources, because they work differently:
 *
 *    caltech_y      everyday equipment, booked through the Caltech Y, about
 *                   $1/day, collected from the Y during business hours.
 *    gear_officer   specialist climbing, ice and packrafting equipment, held
 *                   by the club's Gear Officer and booked directly.
 *
 *  KEEP THIS HONEST rather than complete. Specific models are listed only
 *  where they change what someone can do with the item — ski lengths and
 *  binding ranges belong on the Caltech Y's own listing, which is always more
 *  current than this file. If an item goes missing or is retired, delete the
 *  line; a list that promises equipment the club no longer has is worse than
 *  a short one.
 *
 *  Last checked against the Caltech Y listing: 2026-08-14
 * ============================================================================
 */

return array(

    'caltech_y' => array(
        /* A heading on an inventory should say whose equipment it is, not
           describe the paperwork. How each pool is booked is on gear.php, once
           each; it was here as well, which is where the price and the notice
           period ended up on the page twice. */
        'title'  => 'Caltech Y gear',
        'blurb'  => 'Rented from the Caltech Y.',
        'groups' => array(

            'Camping and backpacking' => array(
                'Tents, sleeping bags, and stoves',
                'Bear canisters',
            ),

            'Snow' => array(
                'Snowshoes',
                'Ice axes',
                'Crampons (strap-on)',
                'Avalanche beacons, shovels, and probes',
                'Snow pickets and a snow saw',
                'Touring skis and a splitboard, with skins, bindings, and poles',
                'Ski wax kits, including iron, edge tuner, scraper, and brush',
            ),

            'Climbing' => array(
                'Helmets',
                'Crash pads for bouldering',
            ),

            'Communication' => array(
                'Garmin inReach satellite messengers',
                'Rocky Talkie two-way radios',
            ),

            'Other' => array(
                'Trunk-mounted bike rack',
            ),
        ),
    ),

    'gear_officer' => array(
        'title'  => 'Alpine Club gear',
        'blurb'  => 'Borrowed from the club through the reservation form.',
        'groups' => array(

            'Trad climbing' => array(
                'Two trad racks — Black Diamond C4 doubles, nuts, nut tool, prusik, and an ATC Guide',
                'Wide gear — Black Diamond C4 #5 and #6',
                'Small gear — Black Diamond C3 #00 to #1',
            ),

            'Ice and alpine' => array(
                'Automatic crampons',
                'Ice tools (Petzl Quark, hammer, and adze)',
                'Ice screws',
                'Crevasse rescue kits — Microtraxion, pulley, Tibloc, and locking carabiners',
                'Four-season tents',
            ),

            'Packrafting' => array(
                'Kokopelli packrafts with paddles, PFDs, a helmet, and a pump',
            ),
        ),
    ),

);
