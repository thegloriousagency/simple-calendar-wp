# Testing Guide

This document outlines both automated and manual QA steps for the Church Events Calendar plugin.

## Automated Tests

1. Install dependencies:
   ```bash
   composer install
   composer dump-autoload
   ```
2. Run PHPUnit:
   ```bash
   ./vendor/bin/phpunit
   ```
   Current suite covers the RRULE-powered `Recurrence_Engine`. Add additional tests under `tests/` as the plugin grows.

## Manual QA Scenarios

### Event Creation
- Create a non-recurring event with start/end times and location.
- Verify it appears in `[church_event_calendar]` for that month and in `[church_event_list]`.

### Weekly Recurrence
- Create an event repeating weekly on Sunday with “never ends”.
- Confirm multiple Sundays show the occurrence in both calendar and list views.

### Daily Recurrence with COUNT
- Create a daily event (interval 1) that ends after 5 occurrences.
- Confirm exactly 5 instances render across calendar/list and the JSON endpoint.

### Monthly Recurrence with UNTIL
- Create an event repeating monthly on the 15th with an UNTIL date.
- Navigate several months to verify occurrences stop after the UNTIL boundary.

### End Conditions
- Test “Ends after N occurrences” and “Ends on date” options; ensure the UI saves correctly and displays expected number of events.

### Advanced Recurrence Toggle
- In **Events → Settings**, disable “Advanced recurrence options”.
  - Edit an event and confirm only basic weekly controls display, yet existing RRULE events still persist.
- Re-enable the toggle to restore Daily/Monthly UI.

### AJAX Calendar Navigation
- Use Prev/Next/Today buttons on a page containing `[church_event_calendar]`.
- Confirm the grid updates via AJAX without full reloads.
- Use the category dropdown to filter events.

### JSON REST Endpoint
- Hit `/wp-json/church-events/v1/events?start=YYYY-MM-DD&end=YYYY-MM-DD`.
- Verify the returned occurrences match the events shown in the calendar/list for the same range.

### Caching & Invalidation
- Load a month view twice and ensure the second load is faster (should reuse cache).
- Edit an event within that month, reload the calendar, and confirm the change appears (cache invalidated automatically).

### Settings Page
- Adjust week start and confirm the calendar headers reorder accordingly (Sunday vs Monday).
- Change “Default list limit” and ensure `[church_event_list]` without a `limit` attribute reflects the new default.

### Template Overrides (Optional)
- Copy `templates/calendar-month.php` to `wp-content/themes/{theme}/church-events/`.
- Modify the override and confirm the front-end uses the themed template.

### Admin Tools
- Visit **Events → Tools**.
- Use the Event Debug Inspector to inspect a recent event and confirm raw meta / RRULE / next occurrences display.
- Use the Cache Inspector to review versions/TTLs and click “Clear caches”; reload a calendar page to ensure it reflects changes.
- In the Log Viewer, verify the log path is shown and “Clear log” empties the file without errors.

### Locations & Defaults
- Create a few **Locations** entries (Events → Locations) with addresses/map links.
- In **Events → Settings**, set the default location to “saved Location entry” and choose one of the new locations.
- Edit an event:
  - Choose “Use default location” and save; confirm `_event_location` matches the default location string.
  - Switch to “Use a saved location” and pick another location; ensure the event uses that location.
  - Switch to “Use custom location” and enter ad-hoc text; verify the list/calendar show the custom value.

