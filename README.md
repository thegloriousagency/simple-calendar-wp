# Church Events Calendar

Lightweight, developer-friendly events calendar plugin tailored for churches. Provides a clean Events CPT, RRULE-based recurrence, and modern shortcodes/REST endpoints without heavyweight interfaces.

## Features

- **Events CPT** with start/end, location, all-day flag, and RRULE-backed recurrence (Daily, Weekly, Monthly with COUNT/UNTIL plus EXDATE/RDATE).
- **Admin recurrence UI**: weekly controls by default, optional advanced frequencies/end conditions toggled via settings.
- **Settings page (`cec_settings`)** for week start, default list limit, and advanced recurrence toggle.
- **Reusable Locations** CPT plus per-event controls for default/saved/custom locations.
- **Month calendar shortcode** `[church_event_calendar]` with AJAX Prev/Next/Today navigation and category filter (honors week-start preference).
- **Upcoming events shortcode** `[church_event_list]` with `limit`, `category`, and `tag` attributes.
- **REST API**: `/church-events/v1/month-view` (HTML fragment) and `/church-events/v1/events` (expanded JSON occurrences).
- **Caching layer** using transients for month HTML and JSON responses; invalidates automatically when events/taxonomies change.
- **Admin Tools page** under Events → Tools with an event inspector, cache info, and log viewer for debugging.
- **No Gutenberg blocks** (shortcodes + templates keep things lightweight; blocks can be added later if desired).

## Requirements

- WordPress 6.2 or newer.
- PHP 8.1+.
- Composer (for PHP dependencies) and Node 18+ with npm (for asset build).

## Installation

1. Clone or download this repo into `wp-content/plugins/church-events-calendar`.
2. Install PHP dependencies:
   ```bash
   composer install
   composer dump-autoload
   ```
3. Install/build frontend assets:
   ```bash
   npm install
   npm run build
   ```
4. Activate “Church Events Calendar” from the WordPress Plugins screen.

## Usage

1. **Create events** under **Events → Add New**:
   - Set start/end date/time (12-hour picker) or mark as all-day.
   - Optional location text.
   - Enable “Recurring Event” for weekly (or, if settings allow, daily/monthly) recurrences with COUNT/UNTIL.
2. **Insert calendar view** in pages/posts with:
   ```
   [church_event_calendar category="youth" default_year="2025" default_month="1"]
   ```
   - Visitors can browse months via AJAX controls and filter by taxonomy.
3. **Insert upcoming list** with:
   ```
   [church_event_list limit="5" category="outreach"]
   ```
   - If `limit` is omitted, the setting `list_default_limit` is used.
4. **Configure defaults** under **Events → Settings**:
   - Week start: Sunday/Monday (affects calendar grid and templates).
   - Default list limit.
   - Advanced recurrence toggle (exposes Daily/Monthly + end options in the meta box).
   - Default location (none, text-based, or saved Location CPT entry). Events can also pick saved/custom locations individually.

## REST API

- `GET /wp-json/church-events/v1/events?start=2025-01-01&end=2025-01-31&category=youth&limit=50`
  - Returns expanded occurrences with title, permalink, start/end ISO strings, all-day flag, location, categories, and tags.
- `GET /wp-json/church-events/v1/month-view?year=2025&month=1&category=youth`
  - Returns rendered HTML for the given month (used by AJAX navigation).

## Caching

- Month HTML cached per `(year, month, category, locale)` key.
- JSON events cached per `(start, end, category, tag, limit, locale)`.
- Automatically invalidated when events are saved/trashed/deleted or when related taxonomies change.
- Filters:
  - `cec_month_cache_ttl` (default `6 * HOUR_IN_SECONDS`)
  - `cec_events_cache_ttl` (default `10 * MINUTE_IN_SECONDS`)
  - `cec_enable_cache` (return `false` to bypass caching).

## Styling & Theming

- Base HTML structures use `.cec-*` classes:
  - `.cec-calendar`, `.cec-calendar__nav`, `.cec-calendar__day`, `.cec-calendar__event`, etc.
  - `.cec-events-list`, `.cec-events-list__item`, `.cec-events-list__meta`, etc.
- The bundled CSS (`assets/css/frontend.css`) only provides a neutral skeleton (spacing, layout, light borders). No strong colors or fonts are applied so themes can layer their own identity.
- To override styles, enqueue a stylesheet after the plugin’s CSS or add custom rules targeting the namespaced classes.

## Development Notes

- Shortcodes render PHP templates that can be overridden via `wp-content/themes/{theme}/church-events/`.
- No Gutenberg blocks are shipped; extend via classic shortcodes or custom themes.
- Automated tests live under `tests/` (currently focused on recurrence engine behavior).
- Admin Tools (Events → Tools) provide an Event Debug Inspector, cache stats/clear button, and log viewer. Logs live in `wp-content/uploads/church-events/logs.log`.

