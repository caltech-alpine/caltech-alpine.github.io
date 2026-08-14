/* ==========================================================================
   CALTECH ALPINE CLUB — site behaviour
   --------------------------------------------------------------------------
   Vanilla JavaScript, no libraries, no build step.

   The site is built so that everything important works with this file
   missing or blocked: events are rendered on the server, every menu item is
   a real link, and the FAQ answers are visible by default until this script
   collapses them. What follows is polish, not plumbing.
   ========================================================================== */

(function () {
  'use strict';

  /* ---------------------------------------------------------------- menu */

  var toggle = document.querySelector('.masthead__toggle');
  var panel  = document.getElementById('mobile-nav');

  if (toggle && panel) {
    var setMenu = function (open) {
      toggle.setAttribute('aria-expanded', String(open));
      panel.hidden = !open;
      document.body.classList.toggle('nav-open', open);
    };

    toggle.addEventListener('click', function () {
      setMenu(toggle.getAttribute('aria-expanded') !== 'true');
    });

    // Escape closes it and returns focus to the button that opened it.
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
        setMenu(false);
        toggle.focus();
      }
    });

    // Following a link inside the panel should leave it closed behind you.
    panel.addEventListener('click', function (event) {
      if (event.target.closest('a')) { setMenu(false); }
    });

    // Resizing up to the desktop layout must not leave the panel stuck open.
    var desktop = window.matchMedia('(min-width: 1060px)');
    var onBreakpoint = function (mq) { if (mq.matches) { setMenu(false); } };
    if (desktop.addEventListener) { desktop.addEventListener('change', onBreakpoint); }
    else if (desktop.addListener) { desktop.addListener(onBreakpoint); }
  }

  /* ------------------------------------------------------------------ FAQ */
  /* Answers ship visible so they are readable (and findable by Ctrl+F, and
     by search engines) without JavaScript. We collapse them here.          */

  var questions = document.querySelectorAll('.faq__q');

  Array.prototype.forEach.call(questions, function (button) {
    var answer = document.getElementById(button.getAttribute('aria-controls'));
    if (!answer) { return; }

    answer.hidden = true;
    button.setAttribute('aria-expanded', 'false');

    button.addEventListener('click', function () {
      var open = button.getAttribute('aria-expanded') === 'true';
      button.setAttribute('aria-expanded', String(!open));
      answer.hidden = open;
    });
  });

  /* ------------------------------------------------------- event details */
  /* Event cards with more to say than fits open a dialog. The markup uses a
     native <dialog>, so focus trapping, Escape and the backdrop are the
     browser's job rather than ours.

     The whole card is clickable for convenience, but the real control is the
     "Details" button inside it — that is what keyboard and screen-reader
     users get, and it is why the card itself is not a link.               */

  var openers = document.querySelectorAll('[data-open]');

  var openDialog = function (id, opener) {
    var dialog = document.getElementById(id);
    if (!dialog || typeof dialog.showModal !== 'function') { return false; }

    dialog.showModal();

    // Clicking the backdrop closes it. The dialog's own box is the only thing
    // inside its rect, so a click landing outside that box is the backdrop.
    var onBackdrop = function (event) {
      var box = dialog.getBoundingClientRect();
      var outside = event.clientX < box.left || event.clientX > box.right
                 || event.clientY < box.top  || event.clientY > box.bottom;
      if (outside) { dialog.close(); }
    };
    dialog.addEventListener('click', onBackdrop);
    dialog.addEventListener('close', function () {
      dialog.removeEventListener('click', onBackdrop);
      if (opener) { opener.focus(); }         // put focus back where it started
    }, { once: true });

    return true;
  };

  Array.prototype.forEach.call(openers, function (button) {
    button.addEventListener('click', function (event) {
      event.stopPropagation();
      openDialog(button.getAttribute('data-open'), button);
    });
  });

  var cards = document.querySelectorAll('[data-dialog]');

  Array.prototype.forEach.call(cards, function (card) {
    card.addEventListener('click', function (event) {
      // Anything that is already interactive keeps its own behaviour.
      if (event.target.closest('a, button')) { return; }
      var opener = card.querySelector('[data-open]');
      openDialog(card.getAttribute('data-dialog'), opener);
    });
  });

  /* ---------------------------------------------------- copyable emails */
  /* Every place the site prints an address as text, it is marked up as a
     mailto link so it works with no JavaScript at all. Here we upgrade those
     into click-to-copy buttons, because most people read mail in a browser
     tab where a mailto link does nothing useful.

     Only links whose visible text IS an address are converted — the action
     buttons ("Contact the officers") keep their mailto behaviour, since
     those carry a pre-filled subject and body worth opening a client for.

     Writing to the clipboard needs no permission prompt; it does need a
     secure context, so on plain http we leave the links alone rather than
     hand over a button that silently fails.                                */

  var canCopy = !!(navigator.clipboard && navigator.clipboard.writeText)
                && window.isSecureContext;

  if (canCopy) {
    var mailLinks = document.querySelectorAll('a[href^="mailto:"]');

    Array.prototype.forEach.call(mailLinks, function (link) {
      var shown = link.textContent.trim();
      if (shown.indexOf('@') === -1) { return; }   // a labelled button, leave it

      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'copy';
      button.textContent = shown;
      button.setAttribute('aria-label', 'Copy ' + shown + ' to the clipboard');
      button.title = 'Copy to clipboard';

      var restore = null;

      button.addEventListener('click', function () {
        navigator.clipboard.writeText(shown).then(function () {
          button.classList.add('is-copied');
          button.textContent = 'Copied';
          window.clearTimeout(restore);
          restore = window.setTimeout(function () {
            button.classList.remove('is-copied');
            button.textContent = shown;
          }, 1600);
        }, function () {
          // Clipboard refused. Fall back to selecting it so it can be copied
          // by hand, which is what would have happened anyway.
          var range = document.createRange();
          range.selectNodeContents(button);
          var sel = window.getSelection();
          sel.removeAllRanges();
          sel.addRange(range);
        });
      });

      link.parentNode.replaceChild(button, link);
    });
  }

  /* ------------------------------------------------------- external links */
  /* Anything pointing off this site opens in a new tab, and says so to a
     screen reader. Doing it here keeps the templates uncluttered.          */

  var host = window.location.hostname;
  var links = document.querySelectorAll('main a[href^="http"], .site-footer a[href^="http"]');

  Array.prototype.forEach.call(links, function (link) {
    if (link.hostname === host || link.hasAttribute('target')) { return; }
    link.setAttribute('target', '_blank');
    link.setAttribute('rel', 'noopener noreferrer');
  });

}());
