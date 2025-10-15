# Library Theme Maintenance Notes

Last updated: 2025-09-16

Purpose: Capture the production-safe state of the theme, how presets/settings render, safe maintenance operations, and one-off dev tooling usage.

## Production footprint (kept)
- config/ (theme.ini; other config as applicable)
- view/ (layout + templates)
- asset/ (css + js)
- README.md, MAINTENANCE.md, package.json

## Dev-only artifacts (keep out of prod)
- Temporary debug/diagnostic layouts or scripts
- One-off exporters or DB scripts
- Analysis logs and snapshots

Rationale: These are not used at runtime and may confuse administrators.

## Presets workflow (current)
- The Style Preset selects a baseline set of values (Traditional or Modern).
- Rendering uses saved settings with preset fallback for any empty/unspecified fields.
- There is no in-theme "Apply Preset to Settings" action; that UX is planned for a module. Use the Admin form to save values explicitly.

## Refreshing defaults (future-proof approach)
To adopt a sites current look as new defaults:
1. Identify the target site
2. Export its saved theme settings using the dev tool (see below)
3. Update config/theme.ini defaults accordingly
4. Deploy; new sites (and Load defaults) will inherit the updated defaults

## Dev tool: export current settings
- dev-tools/export-modern-defaults.php (CLI, run on server with DB access)
  - Usage: `php dev-tools/export-modern-defaults.php <site_id>`
  - Reads /var/www/omeka-s/config/database.ini
  - Exports modern keys (box_border_width/radius, explicit pagination hover colors, accent_color)
  - Remove this file after use in production environments

## Known maintenance items
- Footer year: See STALE_DATE_ISSUE.md for the annual update procedure via Admin UI
- Clear Omeka cache after major theme setting changes if values appear stale
- Verify caption backgrounds remain white (caption-fix.js acts as a guard)

## Safety recommendations
- Keep the theme minimal: no ad-hoc scripts under the theme path
- Prefer Admin UI for changes; when changing defaults, edit theme.ini carefully
- Ensure CSS order remains: base css  overrides  font-overrides.css (last)

## Lessons learned
- Typography normalization: Body font-size applies only to the body element so H1/H2/H3 sizes are respected (fixes HTML block H2 = normal size issue)
- Unified box shape: Global box_border_width and box_border_radius replaced TOC-specific border fields
- Explicit hover colors: Pagination and TOC hover states are set by explicit text/background fields
- Specificity: Avoid broad rules that unintentionally affect headings/links; use scoped selectors and minimal !important
- Runtime guardrails: caption-fix.js ensures neutral backgrounds for video thumbnails and captions
