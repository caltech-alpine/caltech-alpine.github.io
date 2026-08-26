<?php
/**
 * ============================================================================
 *  Member benefits: the discounts that come with membership.
 * ============================================================================
 *
 *  The structure exists so that adding a deal later is ONE LINE in
 *  data/benefits.csv rather than another redesign. Today the file has no rows
 *  and the Join page shows nothing, which is correct: the club has deals but
 *  nobody has confirmed which of them are current or which may be advertised.
 *
 *  TWO THINGS THIS HAS TO GET RIGHT, and they are the reasons it is code
 *  rather than a paragraph somebody types:
 *
 *  1. SOME DEALS MAY NOT BE ADVERTISED. A sponsor can require that a member
 *     rate is not published. So a row is either 'public' or 'members', and a
 *     'members' row contributes NOTHING to the page except to the count -- no
 *     name, no terms, not even a hint of who it is with. The page says that
 *     member-only deals exist and who to ask, which is true and gives nothing
 *     away. Anything less blunt than this ends with somebody publishing a rate
 *     they agreed not to.
 *
 *  2. A DEAL WITH A DATE ON IT WILL OUTLIVE ITS DATE. The old site's deals
 *     page is the proof: an October sign-up window, written in the present
 *     tense, with no year, still sitting there years later. So a row can carry
 *     an expiry and the site stops showing it on its own. Nobody has to
 *     remember, which is the only kind of maintenance that survives an officer
 *     handover.
 * ============================================================================
 */

require_once __DIR__ . '/officers.php';

/**
 * The benefits, split by whether they can be shown.
 *
 * @return array{public: array[], restricted: int}
 *         public     rows to print, in file order
 *         restricted how many exist that cannot be named
 */
function alpine_benefits()
{
    static $cache = null;
    if ($cache !== null) { return $cache; }

    $today      = date('Y-m-d');
    $public     = array();
    $restricted = 0;

    foreach (alpine_data('benefits') as $b) {
        if (empty($b['benefit'])) { continue; }

        /* Expired rows are gone from BOTH counts. A deal that has run out is
           not a deal we are keeping quiet about, it is a deal that ended. */
        if (!empty($b['expires']) && trim($b['expires']) < $today) { continue; }

        /* Anything that is not explicitly public is treated as restricted.
           The failure that matters here is publishing something we agreed not
           to, so an empty or misspelled visibility column has to fail closed. */
        if (strtolower(trim(isset($b['visibility']) ? $b['visibility'] : '')) === 'public') {
            $public[] = $b;
        } else {
            $restricted++;
        }
    }

    return $cache = array('public' => $public, 'restricted' => $restricted);
}

/** True when there is anything at all worth rendering a section for. */
function alpine_has_benefits()
{
    $b = alpine_benefits();
    return $b['public'] || $b['restricted'] > 0;
}

/**
 * Who to ask about deals. The person currently holding the role if the roster
 * names one, otherwise the shared officers mailbox — so this keeps working
 * through a handover, and through the role being renamed back to "Deals Shark"
 * if the club ever wants it.
 */
function alpine_benefits_contact()
{
    $officer = alpine_officer_for('Partnerships & Deals Lead');
    if ($officer && !empty($officer['email'])) {
        return array('email' => $officer['email'], 'name' => $officer['name']);
    }
    return array('email' => cfg('links.officers'), 'name' => '');
}
