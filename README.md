# Piteå Customisation

Site-specific WordPress plugin for the Piteå municipality website. The site runs on the **Municipio** theme with the **Modularity** page builder, and this plugin is the single place for all customizations that extend or modify their behaviour — from icon sets and search routing to external content imports and accessibility tooling.

## What the plugin does

The plugin is organized as a collection of focused customization classes, each self-contained and responsible for one area of the site. Here is a brief description of each:

| Class               | Description                                                                                                                                                                                                                    |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `Accessibility`     | Integrates ReadSpeaker (customer `9687`) on singular pages by wrapping content in the required `<article id="article">` element. Adds a per-post ACF toggle for the accessibility/print button bar.                            |
| `Archive`           | Keeps empty mounted CPT archives on their post type so PostsList shows the empty-state notice instead of falling back to page or front-page content.                                                                          |
| `Admin`             | Enqueues admin assets, restricts block editor capabilities (removes block directory, limits font sizes, removes paragraph color controls, and adds paragraph block styles).                                                    |
| `Breadcrumbs`       | Hides breadcrumbs on the front page when a breadcrumbs block is in the slider area, replaces separator icons with `horizontal_rule`, and renames the home breadcrumb to "Start".                                               |
| `ColorPicker`       | Replaces the standard WordPress color picker in all ACF fields with a custom `pitea_color_picker` that shows the design-system palette.                                                                                        |
| `Config`            | Miscellaneous global configuration: loads the plugin textdomain, sets default icons on ServiceInfo/ContactBanner/Noticeboard components, and strips `@font-face` rules from Kirki inline styles on the frontend.               |
| `Decorators`        | Decorates `news` post objects so the archive date uses the WordPress `date_format` option rather than a hardcoded format.                                                                                                      |
| `ExternalContent`   | Registers external content integrations: Sokigo Nova publication endpoint for digital noticeboard notices, traffic disruptions from an ArcGIS FeatureServer (into `modularity-service-info` posts), and e-services from the Piteå eNämnd API (into Typesense). |
| `FontAwesome`       | Full FontAwesome Pro replacement for the theme's Material Symbols icon set. Registers a custom ACF icon picker, converts all `icon` ACF fields to it, and adds FA icon pickers to both TinyMCE and the Gutenberg block editor. |
| `Headers`           | Extends the `Content-Security-Policy` header to add `blob:` to `script-src` and `worker-src`, required for ReadSpeaker's web worker.                                                                                           |
| `Navigation`        | Forces tab menu buttons to use the `c-button--md` size class.                                                                                                                                                                  |
| `News`              | Removes thumbnail images from news items in archive and Posts module contexts, and removes the "Tags:" label prefix from the Tags component.                                                                                   |
| `Pagination`        | Custom pagination rendering that always pins the first and last page items and adds ellipses for large page counts.                                                                                                            |
| `Policies`          | Extends the CSP `connect-src` and `img-src` directives with `data:` URIs.                                                                                                                                                      |
| `PostTypes`         | Loads ACF JSON field group definitions from `post-types/` and saves changes back in development mode.                                                                                                                          |
| `Search`            | Routes all searches to `/sok/` via a rewrite rule, redirects legacy `/?s=` queries with a 301, and patches search form action URLs and the hero module's search link.                                                          |
| `ShareButton`       | Adds a "Share page" button (copy link to clipboard) after the post signature. Shown by default on all non-page post types; on pages controlled via an ACF toggle.                                                              |
| `Taxonomies`        | Loads ACF JSON field group definitions from `taxonomies/` and saves changes back in development mode.                                                                                                                          |
| `Templates`         | Registers the plugin's `views/` directory as a Blade template path so plugin-provided templates are resolved by Municipio's view layer.                                                                                        |
| `Typesense`         | Maps Typesense document types to hit templates: `lediga-jobb` → `jobposting`, `pitea-eservice` → custom `hit-eservice` Blade template in `views/search/hits/`.                                                                 |
| `Modules\Container` | Removes the `o-container--remove-spacing` class from full-width `acf/container` blocks.                                                                                                                                        |
| `Modules\InlayList` | Auto-assigns FontAwesome icons per link type: PDF → `fa-file-pdf`, external links → `fa-arrow-up-right-from-square`, internal links → `fa-arrow-right`.                                                                        |
| `Modules\LinkCards` | Populates the link-cards color pickers with the design-system SCSS palette.                                                                                                                                                    |
| `Modules\Posts`     | Sets `fa-solid fa-arrow-right` as the archive link icon in the Posts module.                                                                                                                                                   |
| `Modules\Slider`    | Adds an ACF toggle (`slider_show_stepper`) and applies the corresponding CSS class to the slider component.                                                                                                                    |
| `Modules\Text`      | Adds ACF fields for background color, border color/thickness/radius to the Text module and injects the resulting inline CSS on `wp_head`.                                                                                      |

---

## Directory structure

```
pitea-customisation/
├── pitea-customisation.php          # Plugin entry point — defines constants, boots App
├── composer.json                    # PSR-4 autoload: PiteaCustomisation\ → source/php/
├── package.json                     # JS deps: Vite, @wordpress/scripts, FontAwesome Kit, Work Sans
├── vite.config.js                   # Asset build config
├── data/
│   └── fontawesome-icons.json       # Icon list used by the FA picker UI
├── post-types/                      # ACF JSON definitions for custom post types
├── taxonomies/                      # ACF JSON definitions for custom taxonomies
├── views/
│   └── search/hits/                 # Blade templates for Typesense search result hits
├── dist/                            # Built CSS/JS output (generated, do not edit)
└── source/
    ├── assets/                      # PDFs, SVGs, dummy images for templates (not Vite entries)
    ├── css/                         # Plain CSS sources; Vite emits hashed files under dist/css/editor-plugins/
    ├── php/
    │   ├── App.php                  # Bootstrap — enqueues assets, instantiates all classes
    │   ├── Admin/                   # Settings page with tab system (General, External Content, Latest Events)
    │   ├── AcfFields/               # Programmatic ACF field group registrations
    │   ├── Customisations/          # All site-specific customization classes (see table above)
    │   │   └── Modules/             # Modularity module-specific customizations
    │   ├── Decorators/              # Post object decorator pattern (e.g. NewsDateDecorator)
    │   ├── ExternalContent/         # NovaPublicationEndpoint, TrafficDisruptionsImporter, EServicesImporter
    │   └── Helpers/                 # CacheBust, ScssColorParser, utility functions
    ├── js/
    │   ├── main.js                  # Frontend JS entry
    │   ├── admin.js                 # Admin JS entry
    │   ├── editor-plugins/          # TinyMCE / Quicktags / ACF color picker (Vite entries → dist/js/editor-plugins/)
    │   ├── gutenberg/               # Gutenberg block editor extensions
    │   ├── modules/                 # Per-module JS
    │   └── acf/                     # ACF field JS built by Vite (icon picker)
    └── sass/
        ├── style.scss               # Frontend styles entry
        ├── admin.scss               # Admin styles entry
        └── components/ modules/     # SCSS partials
```

---

## How it is bootstrapped

`pitea-customisation.php` defines three constants and calls `new App()`:

| Constant                      | Value                             |
| ----------------------------- | --------------------------------- |
| `PITEA_CUSTOMISATION_PATH`    | Absolute path to plugin directory |
| `PITEA_CUSTOMISATION_URL`     | Plugin URL                        |
| `PITEA_CUSTOMISATION_VERSION` | `1.0.0`                           |

`App.php` then:

1. Hooks asset enqueueing (`wp_enqueue_scripts`) with cache-busted filenames read from the Vite manifest.
2. Instantiates every customization class via `registerInstances()`. Each class registers its own WordPress hooks inside its constructor.

---

## Admin settings

The plugin adds a **Settings → Piteå kommun** page in the WordPress admin with three tabs:

| Tab              | Settings                                            |
| ---------------- | --------------------------------------------------- |
| General          | Overview / introduction only                        |
| External Content | Sokigo Nova endpoint info · Traffic disruptions source URL · E-services API URL |
| Latest Events    | Visit Piteå API URL · API token                     |

Settings are saved via AJAX with nonce validation.

## External Content

### Sokigo Nova publication endpoint

The plugin exposes a custom WordPress REST endpoint for Sokigo Nova so building permit notices can be published to the digital noticeboard:

```text
POST /wp-json/nova/v1/publish
```

The endpoint requires HTTP Basic Authentication and is active when these constants are defined:

```php
define('SOKIGO_NOVA_PUBLISH_USERNAME', '...');
define('SOKIGO_NOVA_PUBLISH_PASSWORD', '...');
```

Incoming publications are saved as `noticeboard_notice` posts. Nova `publishDate` becomes the WordPress post date, so future dates are scheduled by WordPress. Nova `publishEndDate` is saved to the noticeboard `archive_date` and `archive_time` fields.

Notice type terms are assigned on `noticeboard_notice_type`:

| Nova type | Terms |
| --------- | ----- |
| `1`       | `Kungörelser`, `Bygglov` |
| `2`       | `Beslut`, `Bygglov`      |
| `3`       | `Bygglov`                |

---

## Requirements

| Requirement                  | Notes                                                |
| ---------------------------- | ---------------------------------------------------- |
| PHP 8.0+                     |                                                      |
| Node.js 18+                  | For building assets                                  |
| Composer                     | PSR-4 autoloading (no additional PHP packages)       |
| Advanced Custom Fields (ACF) | Custom field types, JSON-based post types/taxonomies |
| Municipio theme              | Core view/template filters (`Municipio/*`)           |
| Modularity                   | Module view data filters (`Modularity/*`)            |
| ComponentLibrary             | Icon, Pagination, Button, NewsItem component filters |
| modularity-link-cards        | Color picker integration                             |
| modularity-service-info      | Traffic disruptions content type                     |
| typesense-search             | E-services Typesense indexing                        |
| FontAwesome Pro Kit          | Kit `ae5fa37ad3` (loaded via `@awesome.me`)          |

---

## Installation

1. Navigate to the plugin directory:

   ```bash
   cd wp-content/plugins/pitea-customisation
   ```

2. Install PHP dependencies:

   ```bash
   composer install
   ```

3. Install Node.js dependencies:

   ```bash
   npm install
   ```

4. Build assets:

   ```bash
   npm run build
   ```

5. Activate the plugin in WordPress admin.

---

## Development

```bash
# Watch with HMR
npm run dev

# Production build
npm run build
```

---

## Adding a new customization

1. Create a class in `source/php/Customisations/` (or `Customisations/Modules/` for module-specific work):

   ```php
   <?php

   namespace PiteaCustomisation\Customisations;

   class MyFeature
   {
       public function __construct()
       {
           add_filter('some/filter', [$this, 'myMethod']);
       }

       public function myMethod($value)
       {
           // modify $value
           return $value;
       }
   }
   ```

2. Register it in `source/php/App.php` inside `registerInstances()`:

   ```php
   Customisations\MyFeature::class,
   ```

   Autoloading is PSR-4 — new files in an existing namespace directory are picked up automatically. Run `composer dump-autoload` if you add a new sub-namespace directory.

---

## License

MIT
