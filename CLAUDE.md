# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Site-specific WordPress plugin for the Piteå municipality website. The site runs the **Municipio** theme with the **Modularity** page builder; this plugin is the single place for all site-specific customizations layered on top of them (icon sets, search routing, external content imports, accessibility tooling, ACF field customizations, etc.). It is not a general-purpose plugin — changes here should stay scoped to Piteå-specific behavior.

This repo is normally a subdirectory (`wp-content/plugins/pitea-customisation`) inside a full WordPress install; it depends on WordPress core, ACF, Municipio, Modularity, ComponentLibrary, `modularity-link-cards`, `modularity-service-info`, and `typesense-search` being present in the parent site (see README.md for details).

### Upstream: Municipio

Everything in this plugin exists to extend or override behavior of [helsingborg-stad/Municipio](https://github.com/helsingborg-stad/Municipio), an open-source WordPress theme built for municipalities, plus its companion plugins from the same GitHub org ([helsingborg-stad](https://github.com/helsingborg-stad)):

- **Modularity** ([helsingborg-stad/Modularity](https://github.com/helsingborg-stad/Modularity)) — the page-builder plugin providing the "module" system (`Slider`, `Text`, `Posts`, `LinkCards`, `ContactBanner`, `Noticeboard`, `ServiceInformation`, etc.). Most `Customisations/Modules/*` classes and the `Module/AccButtons` module hook into this.
- **ComponentLibrary** ([helsingborg-stad/component-library](https://github.com/helsingborg-stad/component-library)) — shared Blade UI components (icons, pagination, buttons, news items) that Municipio/Modularity render through; several customizations (`Pagination`, `Breadcrumbs`, `News`) filter this layer's output.
- **[municipio-developer-guidelines](https://github.com/helsingborg-stad/municipio-developer-guidelines)** — upstream developer documentation for the Municipio ecosystem. Consult this (or the Municipio/Modularity source itself, since neither is vendored here via Composer/npm) when a filter's contract or expected data shape isn't obvious from usage in this repo alone.

When behavior seems to originate outside this plugin (e.g. default markup, base query args, core view data), it's almost always Municipio, Modularity, or ComponentLibrary — check the theme/plugin source (or the guidelines repo) rather than assuming this plugin owns it.

**Filters/actions actually used in this codebase** (grep for these to find the integration points; `grep -rn "'Municipio/\|'Modularity/" source/php views` finds all of them):

- `Municipio/DecoratePostObject` — wrap a `Municipio\PostObject\PostObjectInterface` to alter presentation without touching the underlying post (used by `Decorators.php` / `NewsDateDecorator`).
- `Municipio/Template/viewData`, `Municipio/Template/single/viewData` — inject/modify data passed into Blade view templates.
- `Municipio/viewPaths` — register additional Blade template lookup directories (`Templates.php` adds this plugin's `views/`).
- `Municipio/Breadcrumbs/Items` — modify breadcrumb trail items.
- `Municipio/Hook/innerLoopStart`, `Municipio/Hook/innerLoopEnd` — hooks fired around the main post loop.
- `Municipio/TypesenseSearch/Collection/getSchema`, `Municipio/TypesenseSearch/hitTemplates`, `Municipio/TypesenseSearch/hitTemplateView`, `Municipio/TypesenseSearch/placeholderMappings`, `Municipio/TypesenseSearch/postTypeToTemplate` — wire custom post types/fields into Municipio's Typesense search integration.
- `Modularity/Display/mod-<name>/viewData` — modify a specific module's Blade view data (e.g. `mod-inlaylist`, `mod-text`, `mod-slider`, `mod-quick-links`, `mod-manualinput`).
- `Modularity/Module/<Name>/...` — module-specific config filters, e.g. `Modularity/Module/LinkCards/BackgroundColors`, `Modularity/Module/Posts/ArchiveLink/Icon`, `Modularity/Module/ContactBanner/CtaIcon`, `Modularity/Module/Noticeboard/ArchiveIcon`, `Modularity/ServiceInformation/Module/ArchiveLink/Icon`.
- `Modularity/PiteaHero/SearchUrl` — a Piteå-specific filter on the hero module's search link (not an upstream Municipio/Modularity hook — defined by whatever provides the `PiteaHero` module, patched here in `Search.php`).

## Commands

```bash
composer install          # PHP deps (PSR-4 autoload only, no runtime packages)
npm install                # JS deps

npm run dev                 # Vite dev/watch with HMR
npm run watch                # Vite build --watch (no HMR server)
npm run build                # Full prod build: vite build + gutenberg build
npm run build:vite           # Vite build only (main/admin JS, SCSS, editor-plugin bundles)
npm run build:gutenberg      # wp-scripts build of the Gutenberg block editor extensions
npm run watch:gutenberg      # wp-scripts start (watch) for Gutenberg extensions
npm run make-pot             # Regenerate languages/pitea-customisation.pot from source/php + views
```

There is no test suite, linter config, or CI in this repo — don't invent phpcs/eslint commands.

`build.php` is a deploy-time script (not for local dev): it runs `composer dump-autoload` + `npm install && npm run build`, copies `source/sass/general/variables.scss` to `data/variables.scss` (read at runtime by `ColorPickerField`), and — only when called with `--cleanup` — deletes dev-only files (`node_modules`, `source/js`, `source/sass`, `package.json`, etc.) to slim the plugin for production. Don't run it with `--cleanup` locally.

## Architecture

### Bootstrap and registration

`pitea-customisation.php` is the plugin entry point: defines `PITEA_CUSTOMISATION_PATH`/`_URL`/`_VERSION`, loads the Composer autoloader, and instantiates `App`. It also directly registers one Modularity module (`AccButtons`, under `source/php/Module/`) and adds two `mod-*` entries to `Modularity/externalViewPath` — these bypass the `App`/`Customisations` pattern because Modularity module registration happens on `init` before `App` hooks would normally fire.

`source/php/App.php` is the actual bootstrap:
1. `registerInstances()` instantiates every class in its `$classes` array — this is the single manifest of active customizations. **Any new customization class must be added to this array or it will never run.**
2. Each class registers its own WordPress/Municipio/Modularity hooks inside its own constructor — there is no central hook registry.
3. `enqueueAssets()` enqueues the main frontend JS/CSS via `Helpers\CacheBust`.

To add a new customization: create a class under `source/php/Customisations/` (or `Customisations/Modules/` for Modularity-module-specific tweaks), give it a constructor that calls `add_filter`/`add_action`, then add `Customisations\YourClass::class` to the array in `App::registerInstances()`. New files in an already-autoloaded namespace directory are picked up automatically (PSR-4, `PiteaCustomisation\` → `source/php/`); run `composer dump-autoload` only if you add a new top-level sub-namespace directory.

### Directory map (`source/php/`)

- `Customisations/` — one class per customization area, each self-contained and hooking WordPress/Municipio/Modularity filters in its constructor. `Customisations/Modules/` holds customizations scoped to a single Modularity module (Container, InlayList, LinkCards, Posts, QuickLinks, ManualInput, Slider, Text). `Customisations/Admin/` and `Customisations/Permissions/` group admin-UI and page/role-permission concerns respectively.
- `Admin/` — the plugin's own settings UI: `Settings.php` renders a **Settings → Piteå kommun** page with tabs implementing `SettingsTabInterface`, defined under `Admin/Tabs/`. Settings save via AJAX with nonce validation. Adding a settings tab means creating a class in `Admin/Tabs/` implementing that interface and wiring it into `Settings.php`.
- `AcfFields/` — programmatic ACF field group / custom field type registrations (e.g. the FontAwesome icon picker, the custom color picker, share-button and accessibility toggles).
- `ExternalContent/` — integrations that pull external data into WordPress content, grouped by target: `Noticeboard/` (Sokigo Nova → `noticeboard_notice` posts via REST endpoint), `ServiceInfo/` (ArcGIS FeatureServer traffic/Piteva disruption importers → `modularity-service-info` posts), `Search/EServices/` (Piteå eNämnd API → Typesense index).
- `Decorators/` — decorator classes that wrap post objects to adjust presentation (e.g. `NewsDateDecorator` formats the news archive date using the WP `date_format` option instead of a hardcoded format) without changing the underlying model.
- `Helpers/` — `CacheBust` (Vite manifest → cache-busted asset URL resolution, see below), `ScssColorParser`/`DesignSystemColors` (parse the SCSS palette for use in ACF color pickers), `Utils`.
- `Module/` — the one Modularity module actually shipped by this plugin (`AccButtons`), registered directly in `pitea-customisation.php` rather than via `App`, because Modularity's own module-registration API expects a module directory + `init`-time call rather than a filter-hooked class.

### ACF-driven post types / taxonomies

Custom post types and taxonomies are defined as ACF JSON, not PHP registration code. `Customisations/PostTypes.php` and `Customisations/Taxonomies.php` each add the plugin's `post-types/` / `taxonomies/` directory to `acf/settings/load_json`, and — only when `wp_get_environment_type() === 'development'` — add the same directory to `acf/settings/save_json`, so editing the post type/taxonomy via the ACF UI on a dev environment writes back into the versioned JSON files. In production, ACF only reads these files; editing them there gets overwritten on the next ACF sync.

### Frontend asset pipeline

Vite (`vite.config.js`) builds multiple independent entry points — not a single bundle — because different bundles are needed in different WP contexts (frontend `main`, `admin`, several ACF/TinyMCE/Quicktags editor-plugin bundles that must load standalone inside the block/classic editor). Output goes to `dist/` with a Vite manifest (`dist/.vite/manifest.json`); a custom Vite plugin clears `dist/` on every build *except* the `gutenberg/` subfolder, which is built separately by `wp-scripts` (`npm run build:gutenberg`) since Gutenberg block registration needs the `@wordpress/scripts` webpack setup, not Vite.

PHP resolves built asset URLs through `Helpers\CacheBust::getFile($sourcePath)`, which looks up the *source* path (e.g. `source/js/main.js`) as a key in the Vite manifest to get the hashed output filename — always enqueue assets by their manifest-listed source key, not by guessing the `dist/` filename directly.

### Templating

Blade templates in `views/` are registered as a template path via `Customisations/Templates.php` so Municipio's Blade-based view layer can resolve plugin-provided views (search hit templates in `views/search/hits/`, module partials in `views/modularity/`). Follow the existing Blade conventions in `views/` when adding new templates rather than raw PHP includes.

## Notes for future changes

- The `README.md` customization table can drift from `App::registerInstances()` — when in doubt about what's actually active, treat the `$classes` array in `App.php` as ground truth, not the README.
- Hooks that require constants to be defined externally (e.g. `SOKIGO_NOVA_PUBLISH_USERNAME`/`_PASSWORD` for the Nova publish endpoint) are only active when those constants exist in the parent WordPress config — check for `defined()` guards before assuming a feature is live.
