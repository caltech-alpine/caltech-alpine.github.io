<?php
/**
 * ============================================================================
 *  ACTIVITY LABELS  —  the [TAG] prefixes understood on calendar event titles.
 * ============================================================================
 *
 *  This list exists for one purpose: turning a Google Calendar event title
 *  like
 *
 *      [HIKE] Mount Baldy day hike
 *
 *  into a card on the website labelled HIKING, with the prefix removed from
 *  the title. That is all. There are no activity pages; the calendar is the
 *  description of what the club does.
 *
 *  To add a new activity tag, copy a block and give it a new key. You can
 *  start using [YOURKEY] in event titles the same day.
 *
 *  Fields
 *    label     what appears on the event card
 *    aliases   other words that may appear in [BRACKETS] and mean this thing
 *    keywords  used ONLY when an event has no [TAG] at all, to guess a label
 *              from the title. Keep these specific: a wrong guess is worse
 *              than no label. An explicit [TAG] always wins.
 *    icon      name of a <symbol> in includes/icons.php
 * ============================================================================
 */

return array(

    'hike' => array(
        'label'    => 'Hiking',
        'aliases'  => array('hiking', 'backpack', 'backpacking', 'peak', 'peakbagging'),
        'keywords' => array('hike', 'hiking', 'backpack', 'summit', 'peak', 'traverse'),
        'icon'     => 'hike',
    ),

    'climb' => array(
        'label'    => 'Climbing',
        'aliases'  => array('climbing', 'rock', 'boulder', 'bouldering', 'ice', 'alpine'),
        'keywords' => array('climb', 'climbing', 'boulder', 'belay', 'crag', 'joshua tree', 'jtree'),
        'icon'     => 'climb',
    ),

    'snow' => array(
        'label'    => 'Snow',
        'aliases'  => array('ski', 'skiing', 'snowboard', 'splitboard', 'mountaineering', 'avalanche'),
        'keywords' => array('ski', 'skiing', 'snowboard', 'splitboard', 'snow', 'mammoth', 'avalanche', 'wax'),
        'icon'     => 'snow',
    ),

    'run' => array(
        'label'    => 'Trail running',
        'aliases'  => array('running', 'trailrun', 'trail run'),
        'keywords' => array('trail run', 'trail running', 'shakeout run'),
        'icon'     => 'run',
    ),

    'bike' => array(
        'label'    => 'Mountain biking',
        'aliases'  => array('biking', 'mtb', 'cycling', 'ride'),
        'keywords' => array('mountain bike', 'mtb', 'bike ride'),
        'icon'     => 'bike',
    ),

    'social' => array(
        'label'    => 'Social',
        'aliases'  => array('party', 'lunch', 'dinner', 'meet', 'meeting', 'hangout'),
        'keywords' => array('kickoff', 'election', 'potluck', 'party', 'social', 'lunch', 'bbq', 'happy hour'),
        'icon'     => 'social',
    ),

    'course' => array(
        'label'    => 'Course',
        'aliases'  => array('clinic', 'training', 'wfa', 'wfr', 'education', 'class'),
        'keywords' => array('wilderness first aid', 'first aid', 'clinic', 'course', 'training', 'workshop'),
        'icon'     => 'course',
    ),

    'talk' => array(
        'label'    => 'Talk',
        'aliases'  => array('speaker', 'lecture', 'presentation'),
        'keywords' => array('talk', 'speaker', 'lecture', 'slideshow'),
        'icon'     => 'talk',
    ),

    'film' => array(
        'label'    => 'Film',
        'aliases'  => array('movie', 'festival', 'banff', 'reelrock'),
        'keywords' => array('banff', 'reel rock', 'reelrock', 'film', 'no man\'s land', 'screening'),
        'icon'     => 'film',
    ),

    'service' => array(
        'label'    => 'Stewardship',
        'aliases'  => array('stewardship', 'trailwork', 'cleanup', 'volunteer'),
        'keywords' => array('trail work', 'trailwork', 'cleanup', 'clean-up', 'stewardship', 'restoration'),
        'icon'     => 'service',
    ),

);
