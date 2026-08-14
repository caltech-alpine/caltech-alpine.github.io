<?php
/**
 * ============================================================================
 *  Stick figures — NOT CURRENTLY USED.
 * ============================================================================
 *
 *  The officers page shows initials for people with no headshot. These
 *  drawings were an alternative to that and are kept in case they are wanted
 *  later. To switch back: require this file from about.php and swap the
 *  .officer__initials block for a <use href="#fig-..."> referencing
 *  alpine_officer_figure() in includes/officers.php. Otherwise delete both.
 *
 *
 *  Ten line drawings of people doing club things. One is picked per person and
 *  stays the same for them, so the page does not shuffle on every reload.
 *
 *  The point of these is that nobody is left off the officers page because
 *  they have not sent a photo, and a missing photo does not look like a bug.
 *  Replace one with a real headshot whenever it turns up.
 *
 *  HOUSE STYLE, if you add more: one simple outlined scene — a tent, a peak,
 *  a slope — with a small figure in it. Not a large stick person filling the
 *  frame. Head radius 6, figures about 36 units tall, everything on the same
 *  ground line. currentColor and no fill, so they inherit the theme.
 *  viewBox is 100x125, the same 4:5 as the headshots.
 * ============================================================================
 */
?>
<svg class="icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">

  <!-- 1. hiking up a hill -->
  <symbol id="fig-hiker" viewBox="0 0 100 125" fill="none" stroke="currentColor"
          stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round">
    <path d="M8 104h84"/>
    <path d="M10 104l26-30 22 30z"/>
    <circle cx="72" cy="58" r="6"/>
    <path d="M72 64v18"/>
    <path d="M72 82l-7 22M72 82l8 22"/>
    <path d="M72 70l10 6"/>
    <path d="M83 64v40"/>
  </symbol>

  <!-- 2. climbing a crag -->
  <symbol id="fig-climber" viewBox="0 0 100 125" fill="none" stroke="currentColor"
          stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round">
    <path d="M8 104h84"/>
    <path d="M72 104V16l-10 12"/>
    <circle cx="50" cy="46" r="6"/>
    <path d="M50 52v14"/>
    <path d="M50 56l13-8M50 60l12 6"/>
    <path d="M50 66l-7 14M50 66l10 12"/>
    <circle cx="66" cy="44" r="2.4"/>
    <circle cx="65" cy="70" r="2.4"/>
  </symbol>

  <!-- 3. skiing a slope -->
  <symbol id="fig-skier" viewBox="0 0 100 125" fill="none" stroke="currentColor"
          stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round">
    <path d="M8 104h84"/>
    <path d="M58 104l20-28 16 28z"/>
    <circle cx="34" cy="52" r="6"/>
    <path d="M34 58l4 16"/>
    <path d="M38 74l-5 18M38 74l7 16"/>
    <path d="M34 62l-10 5M35 63l10 3"/>
    <path d="M24 67l-3 26M45 66l3 26"/>
    <path d="M18 96h34"/>
  </symbol>

  <!-- 4. running a trail -->
  <symbol id="fig-runner" viewBox="0 0 100 125" fill="none" stroke="currentColor"
          stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round">
    <path d="M8 104h84"/>
    <circle cx="54" cy="48" r="6"/>
    <path d="M54 54l-4 16"/>
    <path d="M54 58l-12-5M53 62l11 8"/>
    <path d="M50 70l-9 32M50 70l12 12 2 14"/>
    <path d="M14 58h12M10 70h14M16 82h10"/>
  </symbol>

  <!-- 5. riding a trail -->
  <symbol id="fig-biker" viewBox="0 0 100 125" fill="none" stroke="currentColor"
          stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round">
    <path d="M6 104h88"/>
    <circle cx="28" cy="90" r="12"/>
    <circle cx="72" cy="90" r="12"/>
    <path d="M28 90l15-22h13"/>
    <path d="M43 68l5 22M56 68l16 22"/>
    <path d="M56 68l4-7M53 61h10"/>
    <circle cx="42" cy="42" r="6"/>
    <path d="M44 48l5 14"/>
    <path d="M47 53l11 8"/>
    <path d="M49 62l-2 10 3 16"/>
  </symbol>

  <!-- 6. on a snowy ridge with an ice axe -->
  <symbol id="fig-alpinist" viewBox="0 0 100 125" fill="none" stroke="currentColor"
          stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round">
    <path d="M6 104l34-46 20 24 12-14 22 36z"/>
    <circle cx="40" cy="38" r="6"/>
    <path d="M40 44v14"/>
    <path d="M40 58l-6 10M40 58l7 8"/>
    <path d="M40 48l-9 6M40 50l10-6"/>
    <path d="M50 44l-4-12"/>
    <path d="M46 33l9 3"/>
  </symbol>

  <!-- 7. camping -->
  <symbol id="fig-camper" viewBox="0 0 100 125" fill="none" stroke="currentColor"
          stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round">
    <path d="M18 96l24-42 24 42z"/>
    <path d="M42 54v42"/>
    <path d="M34 96l8-16 8 16"/>
    <circle cx="80" cy="60" r="6"/>
    <path d="M80 66v16"/>
    <path d="M80 82l-6 14M80 82l6 14"/>
    <path d="M80 70l-8 4M80 70l8 4"/>
    <path d="M10 104h80"/>
  </symbol>

  <!-- 8. belaying at the base of a cliff -->
  <symbol id="fig-belayer" viewBox="0 0 100 125" fill="none" stroke="currentColor"
          stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round">
    <path d="M8 104h84"/>
    <path d="M74 104V14l-12 14"/>
    <path d="M52 60C62 44 66 30 66 16"/>
    <circle cx="42" cy="50" r="6"/>
    <path d="M42 56v14"/>
    <path d="M42 70l-7 14M42 70l9 12"/>
    <path d="M42 60l10 2"/>
  </symbol>

  <!-- 9. packrafting -->
  <symbol id="fig-paddler" viewBox="0 0 100 125" fill="none" stroke="currentColor"
          stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round">
    <path d="M18 82c10 10 54 10 64 0"/>
    <path d="M18 82c10-6 54-6 64 0"/>
    <circle cx="50" cy="50" r="6"/>
    <path d="M50 56v12"/>
    <path d="M50 60l-12-6M50 62l12 6"/>
    <path d="M34 48l32 24"/>
    <path d="M31 43l7 9M69 77l-7-9"/>
    <path d="M8 100c11 6 19-6 30 0s19 6 30 0 19-6 24-2"/>
  </symbol>

  <!-- 10. on the summit -->
  <symbol id="fig-summit" viewBox="0 0 100 125" fill="none" stroke="currentColor"
          stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round">
    <path d="M10 104l30-44 14 20 8-11 28 35z"/>
    <circle cx="40" cy="26" r="7"/>
    <path d="M40 33v18"/>
    <path d="M40 38l-13-9M40 38l13-9"/>
    <path d="M40 51l-8 10M40 51l8 10"/>
  </symbol>

</svg>
