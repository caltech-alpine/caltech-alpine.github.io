<?php
/**
 * ============================================================================
 *  Reusable pieces of markup.
 * ============================================================================
 *  An event card looks the same on the homepage and on the events page
 *  because both call the same function. Change it here, it changes in both.
 * ============================================================================
 */

/**
 * Instructions for writing an email, printed as text.
 *
 *  ***  Use this for every "get in touch" on the site. Never a mailto button. ***
 *
 * A mailto: link is not a reliable way to ask someone to write to you. For
 * anyone reading their mail in a browser tab — which is most people, and every
 * public machine — clicking one either does nothing at all or opens an unused
 * desktop mail client, and in both cases the visitor is left with no address,
 * because the address was hidden inside the link. Worse, everything you asked
 * them to say lived in the mailto's subject and body, so it vanishes with it.
 *
 * So the address, the subject line and the things to include are all rendered
 * as readable, selectable text. The address is still linked, which costs
 * nothing and helps the people it does work for, but nothing that matters is
 * only reachable through the link.
 *
 * @param string $email    address to write to
 * @param string $subject  subject line, shown AND pre-filled
 * @param array  $include  short lines to answer. Keep to three or so — a long
 *                         list reads as a chore and people abandon it.
 * @param array  $opts     'name'  the person behind the address, when it is a
 *                                 person rather than a shared mailbox: it
 *                                 tells a visitor who will read it.
 *                         'label' heading over the address (default 'Write to')
 *                         'body'  pre-filled body, when it should differ from
 *                                 the $include lines
 */
function alpine_write_to($email, $subject = '', array $include = array(), array $opts = array())
{
    $name  = isset($opts['name'])  ? $opts['name']  : '';
    $label = isset($opts['label']) ? $opts['label'] : 'Write to';

    /* The pre-filled body defaults to the same prompts shown on the page, so
       the two never drift apart. Blank lines between them leave somewhere to
       type. */
    if (isset($opts['body'])) {
        $body = $opts['body'];
    } elseif ($include) {
        $body = implode("\n\n\n", $include) . "\n";
    } else {
        $body = '';
    }

    $query = array();
    if ($subject !== '') { $query['subject'] = $subject; }
    if ($body !== '')    { $query['body']    = $body; }
    $href = 'mailto:' . $email . ($query ? '?' . http_build_query($query) : '');
    ?>
    <dl class="contact-list write-to">
      <div>
        <dt><?= e($label) ?></dt>
        <?php /* The note goes INSIDE the <dd>: a <dl>'s <div> may contain only
                 <dt> and <dd>, so a loose <span> beside them is invalid markup. */ ?>
        <dd class="write-to__addr">
          <a href="<?= e($href) ?>"><?= e($email) ?></a>
          <?php if ($name !== ''): ?>
            <span class="contact-list__note">Read by <?= e($name) ?>.</span>
          <?php endif; ?>
        </dd>
      </div>
      <?php if ($subject !== ''): ?>
        <div>
          <dt>Subject</dt>
          <dd class="write-to__plain"><?= e($subject) ?></dd>
        </div>
      <?php endif; ?>
      <?php if ($include): ?>
        <div>
          <dt>Please include</dt>
          <dd class="write-to__plain">
            <ul class="write-to__list">
              <?php foreach ($include as $line): ?>
                <li><?= e($line) ?></li>
              <?php endforeach; ?>
            </ul>
          </dd>
        </div>
      <?php endif; ?>
    </dl>
    <?php
}

/** A DOM id for one event's dialog, stable for a given occurrence. */
function alpine_event_detail_id(AlpineEvent $e)
{
    return 'ev-' . substr(md5($e->uid), 0, 10);
}

/** Has this event already happened? */
function alpine_event_is_past(AlpineEvent $e)
{
    return $e->isPast(new DateTimeImmutable('now', $e->start->getTimezone()));
}

/*
 * Every event opens, whether or not it has a description.
 *
 * An earlier version only made a card clickable when there was more text than
 * the card already showed. That was tidier but unpredictable: some cards
 * responded to a click and others did not, with nothing to tell them apart.
 * Consistency wins — and even a bare event has something to add in the
 * pop-up, since the card shows only "Friday · 2:00 PM" where the dialog gives
 * the full date, the location, whether it repeats, and a proper add-to-calendar
 * button.
 */

/**
 * The pop-up itself. Shared by cards and rows so both show the same thing.
 *
 * Native <dialog>, so focus trapping, Escape and the backdrop come from the
 * browser. With JavaScript off it stays closed and nothing else is affected.
 */
function alpine_event_dialog(AlpineEvent $e)
{
    $activity = $e->tag ? alpine_activity($e->tag) : null;
    $id       = alpine_event_detail_id($e);
    ?>
    <dialog class="event-dialog" id="<?= e($id) ?>" aria-labelledby="<?= e($id) ?>-title">
      <form method="dialog" class="event-dialog__close-form">
        <button class="event-dialog__close" aria-label="Close"><?= icon('close', 'icon') ?></button>
      </form>

      <div class="event-dialog__body">
        <?php if ($e->cancelled): ?>
          <p class="event-card__cancelled">Cancelled</p>
        <?php endif; ?>

        <?php if ($activity): ?>
          <p class="event-card__tag">
            <?= icon($activity['icon'], 'icon icon--xs') ?><?= e($activity['label']) ?>
          </p>
        <?php endif; ?>

        <?php /* NOT a heading element. The same dialog is opened from a card
                 (whose title is an <h3>) and from a past-events row (an <h4>),
                 so any fixed level is wrong in one of the two places — it was an
                 <h2>, which put an h2 between two h4s and broke the outline of
                 the events page. A closed dialog should not contribute to the
                 page outline at all; aria-labelledby on the <dialog> is what
                 actually names it, and that still points here. */ ?>
        <p class="event-dialog__title" id="<?= e($id) ?>-title"><?= e($e->title) ?></p>

        <p class="event-dialog__meta">
          <?= icon('clock', 'icon icon--xs') ?>
          <time datetime="<?= e($e->isoStart()) ?>"><?= e($e->whenLine()) ?></time>
          <?php if ($e->repeatLabel !== ''): ?>
            <span class="event-card__repeat"><?= e($e->repeatLabel) ?></span>
          <?php endif; ?>
        </p>

        <?php if ($e->location !== ''): ?>
          <p class="event-dialog__meta">
            <?= icon('pin', 'icon icon--xs') ?><span><?= e($e->location) ?></span>
          </p>
        <?php endif; ?>

        <div class="event-dialog__text prose"><?= $e->descriptionHtml ?></div>

        <?php if (!$e->cancelled && !alpine_event_is_past($e)): ?>
          <div class="btn-row">
            <a class="btn btn--primary btn--sm" href="<?= e($e->addToCalendarUrl()) ?>" rel="noopener">
              <?= icon('calendar', 'icon icon--xs') ?> Add to calendar
            </a>
          </div>
        <?php endif; ?>
      </div>
    </dialog>
    <?php
}

/**
 * One event card.
 *
 * @param AlpineEvent $e
 * @param bool $showExcerpt  Cards in tight grids read better without one.
 */
function alpine_event_card(AlpineEvent $e, $showExcerpt = true)
{
    $activity = $e->tag ? alpine_activity($e->tag) : null;
    $excerpt  = $showExcerpt ? $e->excerpt() : '';

    $dialogId = alpine_event_detail_id($e);
    ?>
    <article class="event-card event-card--openable<?= $e->cancelled ? ' event-card--cancelled' : '' ?>"
             data-dialog="<?= e($dialogId) ?>">

      <div class="event-card__date" aria-hidden="true">
        <span class="event-card__month"><?= e($e->chipMonth()) ?></span>
        <span class="event-card__day"><?= e($e->chipDay()) ?></span>
        <?php if ($e->chipYear()): ?>
          <span class="event-card__year"><?= e($e->chipYear()) ?></span>
        <?php endif; ?>
      </div>

      <div class="event-card__body">

        <?php if ($e->cancelled): ?>
          <p class="event-card__cancelled">Cancelled</p>
        <?php endif; ?>

        <?php if ($activity): ?>
          <p class="event-card__tag">
            <?= icon($activity['icon'], 'icon icon--xs') ?><?= e($activity['label']) ?>
          </p>
        <?php endif; ?>

        <h3 class="event-card__title"><?= e($e->title) ?></h3>

        <p class="event-card__meta">
          <?= icon('clock', 'icon icon--xs') ?>
          <time datetime="<?= e($e->isoStart()) ?>"><?= e($e->shortWhen()) ?></time>
        </p>

        <?php /* Only the next occurrence of a series is listed, so say plainly
                 that it repeats — otherwise it reads as a one-off. */ ?>
        <?php if ($e->repeatLabel !== ''): ?>
          <p class="event-card__repeat"><?= e($e->repeatLabel) ?></p>
        <?php endif; ?>

        <?php if ($e->location !== ''): ?>
          <p class="event-card__meta">
            <?= icon('pin', 'icon icon--xs') ?><span><?= e($e->location) ?></span>
          </p>
        <?php endif; ?>

        <?php if ($excerpt !== ''): ?>
          <p class="event-card__excerpt"><?= e($excerpt) ?></p>
        <?php endif; ?>

        <div class="event-card__foot">
          <button class="event-card__details" type="button"
                  aria-haspopup="dialog" data-open="<?= e($dialogId) ?>">
            Details <?= icon('arrow-right', 'icon icon--xs') ?>
          </button>
          <?php /* No point offering to add something that has already happened. */ ?>
          <?php if (!$e->cancelled && !alpine_event_is_past($e)): ?>
            <a class="arrow-link event-card__cal" href="<?= e($e->addToCalendarUrl()) ?>" rel="noopener">
              Add to calendar
            </a>
          <?php endif; ?>
        </div>

      </div>

      <?php alpine_event_dialog($e); ?>
    </article>
    <?php
}

/**
 * A denser row, used for the past-events archive where there may be dozens.
 */
function alpine_event_row(AlpineEvent $e)
{
    $activity = $e->tag ? alpine_activity($e->tag) : null;
    $dialogId = alpine_event_detail_id($e);
    ?>
    <article class="event-row event-row--openable" data-dialog="<?= e($dialogId) ?>">
      <div class="event-row__when">
        <time datetime="<?= e($e->isoStart()) ?>">
          <?= e($e->chipMonth()) ?> <?= e($e->chipDay()) ?>
        </time>
      </div>
      <div>
        <?php if ($activity): ?>
          <p class="event-row__tag"><?= e($activity['label']) ?></p>
        <?php endif; ?>
        <?php /* h4, not h3: the month heading above the list is the h3, and an
                 event is inside a month rather than a sibling of it. */ ?>
        <h4 class="event-row__title"><?= e($e->title) ?></h4>
        <?php if ($e->location !== '' || $e->repeatLabel !== ''): ?>
          <p class="event-row__meta">
            <?= e($e->location) ?>
            <?php if ($e->repeatLabel !== ''): ?>
              <span class="event-card__repeat"><?= e($e->repeatLabel) ?></span>
            <?php endif; ?>
          </p>
        <?php endif; ?>
        <button class="event-row__details" type="button"
                aria-haspopup="dialog" data-open="<?= e($dialogId) ?>">
          Details <?= icon('arrow-right', 'icon icon--xs') ?>
        </button>
      </div>

      <?php alpine_event_dialog($e); ?>
    </article>
    <?php
}

/**
 * What "Coming up" shows when the calendar has nothing in the future.
 *
 * This is not a rare edge case. It is the state of the site every summer, and
 * it was the state on the day the site was built, so it gets a real design
 * rather than an empty grid.
 */
function alpine_events_empty($dark = false)
{
    ?>
    <div class="empty-state">
      <?= icon('calendar', 'icon icon--xl empty-state__icon') ?>
      <h3>No upcoming events</h3>
      <p>
        There are no events on the club calendar at the moment. Informal trips are
        often organized on Slack at short notice.
      </p>
      <div class="btn-row">
        <a class="btn <?= $dark ? 'btn--light' : 'btn--primary' ?>" href="<?= e(url('join.php')) ?>">
          Join the club
        </a>
        <a class="btn <?= $dark ? 'btn--light' : 'btn--ghost' ?>" href="<?= e(url('events.php#calendar')) ?>">
          View the calendar
        </a>
      </div>
    </div>
    <?php
}

/**
 * Shown only when we could not reach Google at all. Rare, and deliberately
 * understated: the visitor does not care whose fault it is.
 */
function alpine_calendar_unavailable()
{
    ?>
    <div class="empty-state">
      <?= icon('calendar', 'icon icon--xl empty-state__icon') ?>
      <h3>Calendar unavailable</h3>
      <p>The club calendar could not be loaded. It can be opened directly in Google
         Calendar.</p>
      <div class="btn-row">
        <a class="btn btn--primary" href="<?= e(cfg('calendar.embed_url')) ?>" rel="noopener">
          Open the calendar <?= icon('external', 'icon icon--xs') ?>
        </a>
      </div>
    </div>
    <?php
}

