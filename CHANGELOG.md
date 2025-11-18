# Changelog

## 1.0.0 · 2025-11-18

Initial public release of Church Events Calendar, featuring:

- Custom `church_event` post type with location, all-day flag, and RRULE-based recurrence metadata (Daily/Weekly/Monthly, COUNT/UNTIL, EXDATE/RDATE).
- Recurrence meta box UI with optional advanced controls governed by plugin settings.
- Settings page (`cec_settings`) to configure week start, default list limit, and advanced recurrence toggle.
- `[church_event_calendar]` shortcode with month grid, AJAX navigation (Prev/Today/Next), and category filtering. Template overrides supported via theme files.
- `[church_event_list]` shortcode for upcoming events with limit/category/tag filtering.
- Locations CPT with optional address/meta, default-location settings, and per-event location modes (default/saved/custom).
- REST endpoints:
  - `/church-events/v1/month-view` returning HTML fragments for AJAX navigation.
  - `/church-events/v1/events` returning expanded occurrence JSON payloads.
- Transient-based caching for month HTML and JSON responses, with automatic invalidation on event/taxonomy changes and filterable TTLs.
- Frontend/admin asset pipeline (Vite) plus PHPUnit coverage for recurrence expansion.

