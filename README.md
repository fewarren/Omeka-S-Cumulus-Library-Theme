# Library Theme (Omeka S)

A production-focused Omeka S theme tailored to the Library’s design system, with flexible typography, unified colors/shape, and clean, minimal runtime assets. Cormorant Garamond support is included. This theme builds on the excellent work of Daniel Berthereau who created the Cumulus theme which provided the starting point. The theme intent is to provide extremely customizable elements like type styles, colors, hover ascetics, etc. It also provides enhanced media item thumbnail operation and two alternate styles.  The styles can be saved and restored using the companion module, LibraryThemeStyles.

- Omeka S compatibility: ^4.1.0
- Zero build required: no npm install, no bundling
- Minimal CSS/JS, cache-busted, CDN fonts

## Features

- Style Presets: Traditional, Modern (Library)
- Typography controls: H1, H2, H3, Body (family, size, weight, style, color)
- Colors & Shape: primary, accent; global box border width/radius
- TOC styling: font, size (or rem override), weight/style, colors + hover
- Pagination styling: background, text, hover background/text, size, typography
- Menu & Footer typography
- Breadcrumbs pill style toggle with current page inclusion control
- Resource Page Regions enabled (items, item sets, media)
- Runtime caption background guard for video thumbnails
- Debug gating: console logs only when URL contains ?debug
- **Optional PDF.js viewer integration** with download button hiding capabilities
- Enhanced media handling: audio inline players, PDF thumbnail navigation

## Configuration Overview

- Preset selection: Settings → Style Preset (Traditional or Modern). Saved settings are used at render time with preset fallback for unspecified values.
- Typography:
  - H1/H2/H3/Body families include Georgia, Cormorant Garamond, Helvetica Neue, Arial, etc.
  - Body font-size is applied only to the body element; headings retain their own sizes. This fixes prior HTML block H2 size issues.
- Colors & Shape:
  - Primary and accent colors drive headings/hover, etc.
  - Global “Box Border Width” and “Box Border Radius” control pill/box styling across TOC, breadcrumbs, pagination, nav.
- TOC:
  - Choose size via preset options or override precisely using “TOC Font Size (rem)”.
  - Text/background + hover colors are explicitly configurable.
- Pagination:
  - Background/text color and explicit hover background/text color.
  - Button size (small/medium/large) and full typography controls.
- Header & Branding:
  - Logo, header height, optional Browse/Search buttons (via simple URL fields).
- Tagline:
  - Family, size, weight/style, color, and hover colors.
- Menu & Footer typography:
  - Family, weight/style, and colors (footer uses Footer Typography group).
- Breadcrumbs:
  - Pill style toggle (breadcrumbs_pill_style), enabled by default.
  - Include current page toggle (breadcrumbs_include_current).
  - Typography: font family, size (preset or rem override via breadcrumbs_font_size_rem), weight, style.
  - Colors: text/background and explicit hover text/background colors.
- Page Title:
  - Pill style toggle (page_title_pill_style) to enable/disable rounded pill styling.

## Behavior and Rendering

- Dynamic CSS partial (view/common/theme-setting-css.phtml) injects CSS using saved settings with preset fallback.
- CSS load order: library.css → library-polish.css → library-reoriented-design.css → font-overrides.css (highest specificity).
- Fonts: Cormorant Garamond is loaded via Google Fonts.
- Captions: asset/js/caption-fix.js enforces white backgrounds for video thumbnail tiles and captions as a safety guard.
- Debugging: internal debug logging silenced by default; add `?debug` to the URL to enable.

## Dev Tools

- dev-tools/export-modern-defaults.php (CLI):
  - Export current site’s saved theme settings as JSON for updating defaults.
  - Usage: `php dev-tools/export-modern-defaults.php <site_id>` (run on the host with access to /var/www/omeka-s/config/database.ini)
  - Keys include modern fields (box_border_width/radius, explicit pagination hover colors, accent_color).

## Companion module: LibraryThemeStyles (presets/save & restore)

- Location: /var/www/omeka-s/modules/LibraryThemeStyles
- Preset maps updated to use breadcrumbs_* keys and include both breadcrumbs_pill_style and page_title_pill_style.
- Save defaults: stores all current theme settings (including new keys) as JSON.
- Load defaults: applies built-in presets or stored defaults, including the new settings.
- Note: Active theme is LibraryTheme; WorkingLibraryTheme is a backup and not targeted by DEPLOY.sh.

See MAINTENANCE.md for safe operating procedures, annual maintenance (footer year), and lessons learned.

## PDF Viewer Integration (Optional)

The Library Theme includes optional integration with PDF.js for enhanced PDF viewing capabilities. This feature allows administrators to control how PDFs are displayed and whether download options are available to users.

### PDF Viewer Behavior

**Without PDF.js installed:**
- PDFs open in the browser's native PDF viewer
- Download/save buttons are controlled by the browser
- Standard Omeka S media display behavior

**With PDF.js installed:**
- PDFs open in a custom PDF.js viewer
- Download/save buttons can be hidden via theme settings
- Enhanced viewing experience with consistent interface
- Better control over user interactions with PDF content

### Theme Settings

Two settings control PDF viewer behavior:

1. **Hide Download Links** (Media Controls section)
   - `Show Download Links (Default)`: Standard behavior, download buttons visible
   - `Hide Download Links`: Attempts to hide download/save buttons from PDF viewer

2. **Use Custom PDF Viewer (pdf.js)** (Media Controls section)
   - `Disabled (Default)`: Use browser's native PDF viewer
   - `Enabled`: Use custom PDF.js viewer when hiding downloads

**Important**: The custom PDF viewer is only used when BOTH settings are enabled:
- "Hide Download Links" = "Hide Download Links"
- "Use Custom PDF Viewer" = "Enabled"

### PDF.js Installation

To enable the custom PDF viewer functionality:

1. **Download PDF.js**
   ```bash
   # Download the latest stable release from Mozilla
   wget https://github.com/mozilla/pdf.js/releases/download/v4.0.379/pdfjs-4.0.379-dist.zip
   ```

2. **Extract to theme assets**
   ```bash
   # Extract to the theme's asset directory
   cd /path/to/library-theme/asset/
   unzip pdfjs-4.0.379-dist.zip
   mv pdfjs-4.0.379-dist pdfjs
   ```

3. **Verify installation**
   The following file structure should exist:
   ```
   asset/
   ├── pdfjs/
   │   ├── build/
   │   │   ├── pdf.mjs
   │   │   ├── pdf.worker.mjs
   │   │   └── ...
   │   └── web/
   │       ├── viewer.html
   │       ├── viewer.mjs
   │       └── ...
   ```

4. **Test installation**
   - Navigate to: `http://your-site.com/themes/LibraryTheme/asset/pdfjs/web/viewer.html`
   - You should see the PDF.js viewer interface

### Custom Viewer Implementation

The theme includes a custom PDF viewer wrapper (`asset/pdf-custom-viewer.html`) that:

- Loads the standard PDF.js viewer in an iframe
- Applies CSS overlay to hide download/print buttons when configured
- Provides fallback behavior if PDF.js is not installed
- Includes comprehensive debug logging for troubleshooting

### Troubleshooting

**PDF viewer not working:**
1. Verify PDF.js files are properly installed under `asset/pdfjs/`
2. Check theme settings are configured correctly
3. Clear browser cache and try again
4. Check browser console for error messages

**Download buttons still visible:**
1. Ensure both "Hide Download Links" and "Use Custom PDF Viewer" are enabled
2. Verify PDF.js installation is complete
3. Test with different browsers (Firefox, Chrome, Safari)

**Debug mode:**
Add `?debug` to any URL to enable detailed console logging for PDF viewer troubleshooting.

## Installation

1) Copy this theme directory to Omeka S themes folder and activate in Admin → Sites → Theme.
2) Configure the theme in Admin → Sites → Theme settings.

No build steps are required.

## Project Structure (selected)

- config/theme.ini: Theme settings and element groups (admin UI grouping)
- view/layout/layout.phtml: Main layout; includes CSS/JS and preset fallback handling
- view/common/theme-setting-css.phtml: Dynamic CSS from settings
- view/common/foundation-breadcrumbs.phtml: Breadcrumb template with current page control
- view/omeka/site/item/show.phtml: Item detail page with custom breadcrumbs
- view/omeka/site/media/show.phtml: Media display with PDF viewer routing
- asset/css/*.css: Base and override styles
- asset/js/caption-fix.js: Runtime guard for caption/tile white backgrounds
- asset/pdf-custom-viewer.html: Custom PDF.js viewer wrapper (optional)
- asset/pdfjs/: PDF.js distribution files (optional, user-installed)
- dev-tools/export-modern-defaults.php: Exporter for capturing current settings

## License

Distributed under the site’s standard terms for themes. See project LICENSE if included; Omeka S is GPLv3.
