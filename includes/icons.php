<?php
/**
 * ============================================================================
 *  Icon sprite — printed once per page, referenced with icon('name').
 * ============================================================================
 *  Inline rather than an icon font or an external library: no extra request,
 *  no dependency, and every icon inherits the surrounding text colour.
 *
 *  To add one, copy a <symbol>, give it a new id, keep the 24x24 viewBox and
 *  the stroke style so it matches the rest.
 * ============================================================================
 */
?>
<svg class="icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
  <defs>
    <g id="s" fill="none" stroke="currentColor" stroke-width="1.6"
       stroke-linecap="round" stroke-linejoin="round"></g>
  </defs>

  <!-- ============================ activities ============================ -->

  <symbol id="icon-hike" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M2 19h20"/>
    <path d="M3 19 10 6l4.2 7.6"/>
    <path d="m11.6 12.1 2.6-2.2 2.1 3"/>
    <path d="M13.2 15.6 16.8 9l4.6 10"/>
  </symbol>

  <symbol id="icon-climb" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M9.5 3.2a5.5 5.5 0 0 1 0 10.6"/>
    <rect x="5" y="3" width="9" height="18" rx="4.5"/>
    <path d="M14 8.5h5.5M19.5 8.5 17 6m2.5 2.5L17 11"/>
  </symbol>

  <symbol id="icon-snow" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 2v20M2.6 7 21.4 17M2.6 17 21.4 7"/>
    <path d="M12 6.2 9.7 4M12 6.2 14.3 4M12 17.8 9.7 20M12 17.8l2.3 2.2"/>
    <path d="m5.1 8.4-.3-3.1M5.1 8.4 2.2 9.5M18.9 15.6l.3 3.1M18.9 15.6l2.9-1.1"/>
    <path d="m5.1 15.6-2.9-1.1M5.1 15.6l-.3 3.1M18.9 8.4l2.9 1.1M18.9 8.4l.3-3.1"/>
  </symbol>

  <symbol id="icon-run" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="15.5" cy="4.5" r="2"/>
    <path d="M13.5 21l1.4-5.2-3-2.6.9-4.7"/>
    <path d="m12.8 8.5 3.5-1.2 2.4 3.1 2.8.9"/>
    <path d="m12.8 8.5-3.4 1.7-1.3 3"/>
    <path d="m15.9 15.8 2.6 2.4.9 2.8"/>
    <path d="M2 9h3.5M2.5 13H6M4 17h3"/>
  </symbol>

  <symbol id="icon-bike" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="5.5" cy="17" r="3.5"/><circle cx="18.5" cy="17" r="3.5"/>
    <path d="M5.5 17 10 8h5"/><path d="m9 8 4.5 9M18.5 17 15 8"/>
    <path d="M14 5.5h2.6"/>
  </symbol>

  <symbol id="icon-social" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="9" cy="8" r="3.2"/>
    <path d="M2.8 20a6.4 6.4 0 0 1 12.4 0"/>
    <path d="M16.2 5.2a3.2 3.2 0 0 1 0 5.9"/>
    <path d="M17.6 14.4A6.4 6.4 0 0 1 21.2 20"/>
  </symbol>

  <symbol id="icon-course" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 6.5C10 4.9 7.4 4.2 3.5 4.4v13c3.9-.2 6.5.5 8.5 2.1 2-1.6 4.6-2.3 8.5-2.1v-13c-3.9-.2-6.5.5-8.5 2.1Z"/>
    <path d="M12 6.5v13.1"/>
  </symbol>

  <symbol id="icon-talk" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M20.5 15.2a2 2 0 0 1-2 2H8.2L4 20.6V5.8a2 2 0 0 1 2-2h12.5a2 2 0 0 1 2 2Z"/>
    <path d="M8.2 8.6h8.1M8.2 12.2h5.4"/>
  </symbol>

  <symbol id="icon-film" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <rect x="2.6" y="4.6" width="18.8" height="14.8" rx="2"/>
    <path d="M7.4 4.6v14.8M16.6 4.6v14.8M2.6 12h18.8"/>
    <path d="M4.9 8.3h.1M4.9 15.7h.1M19.1 8.3h.1M19.1 15.7h.1"/>
  </symbol>

  <symbol id="icon-service" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M4 20c0-8 4.5-13.5 16-14 .5 9.5-4.5 15-11 15a8 8 0 0 1-5-1Z"/>
    <path d="M4.5 19.5C8 15 12 12 17 9.5"/>
  </symbol>

  <!-- =============================== things ============================= -->

  <symbol id="icon-gear" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M5.5 8.5h13a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2Z"/>
    <path d="M8.5 8.5V6a3.5 3.5 0 0 1 7 0v2.5"/>
    <path d="M8.5 13.5h7"/>
  </symbol>

  <symbol id="icon-cave" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M2.5 20.5c0-7.5 4.3-13 9.5-13s9.5 5.5 9.5 13"/>
    <path d="M8.6 20.5c0-3.8 1.5-6.6 3.4-6.6s3.4 2.8 3.4 6.6"/>
    <circle cx="12" cy="11" r=".9" fill="currentColor" stroke="none"/>
  </symbol>

  <symbol id="icon-deal" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12.6 2.6H20a1.4 1.4 0 0 1 1.4 1.4v7.4a1.4 1.4 0 0 1-.4 1l-8.6 8.6a1.4 1.4 0 0 1-2 0l-7.4-7.4a1.4 1.4 0 0 1 0-2l8.6-8.6a1.4 1.4 0 0 1 1-.4Z"/>
    <circle cx="16.8" cy="7.2" r="1.4"/>
  </symbol>

  <symbol id="icon-book" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M5 3.5h12.5a1.5 1.5 0 0 1 1.5 1.5v15H6.5A1.5 1.5 0 0 1 5 18.5Z"/>
    <path d="M5 17.5h14"/><path d="M8.5 7.5h6"/>
  </symbol>

  <symbol id="icon-mountain" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="m2 19 7-13 4.5 8.4"/><path d="m10.6 13.2 3.2-2.6L22 19H2"/>
  </symbol>

  <!-- ============================= interface ============================ -->

  <symbol id="icon-arrow-right" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
    <path d="M4 12h15.5M13.5 6l6 6-6 6"/>
  </symbol>

  <symbol id="icon-calendar" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <rect x="3.2" y="5" width="17.6" height="16" rx="2"/>
    <path d="M3.2 9.8h17.6M8 3v4M16 3v4"/>
  </symbol>

  <symbol id="icon-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 21.5s7-6.1 7-11.3a7 7 0 1 0-14 0c0 5.2 7 11.3 7 11.3Z"/>
    <circle cx="12" cy="10" r="2.6"/>
  </symbol>

  <symbol id="icon-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="12" r="9"/><path d="M12 6.8V12l3.4 2.2"/>
  </symbol>

  <symbol id="icon-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.8" stroke-linecap="round">
    <path d="M3.5 7h17M3.5 12h17M3.5 17h17"/>
  </symbol>

  <symbol id="icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.8" stroke-linecap="round">
    <path d="M5.5 5.5l13 13M18.5 5.5l-13 13"/>
  </symbol>

  <symbol id="icon-chevron-down" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
    <path d="m5.5 9 6.5 6.5L18.5 9"/>
  </symbol>

  <symbol id="icon-external" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M13.5 4.5H20v6.5M20 4.5 11 13.5"/>
    <path d="M18 14.6V19a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 19V8a1.5 1.5 0 0 1 1.5-1.5H10"/>
  </symbol>

  <symbol id="icon-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <rect x="2.8" y="5" width="18.4" height="14" rx="2"/>
    <path d="m3.4 6.6 8.6 6.2 8.6-6.2"/>
  </symbol>

  <symbol id="icon-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 11.5a7.5 8.5 0 0 1-11 7.4L4 20.5l1.8-4.6A8.5 8.5 0 0 1 12 3a8.2 8.2 0 0 1 9 8.5Z"/>
    <path d="M8.6 11.4h.1M12 11.4h.1M15.4 11.4h.1"/>
  </symbol>

  <symbol id="icon-instagram" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <rect x="3.2" y="3.2" width="17.6" height="17.6" rx="5"/>
    <circle cx="12" cy="12" r="4"/><path d="M17.1 6.9h.01"/>
  </symbol>

  <symbol id="icon-heart" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 20.3S3.5 15.2 3.5 9.4A4.9 4.9 0 0 1 12 6.2a4.9 4.9 0 0 1 8.5 3.2c0 5.8-8.5 10.9-8.5 10.9Z"/>
  </symbol>
</svg>
